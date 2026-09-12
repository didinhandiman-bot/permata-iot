<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Models\SensorLog;

class ApiProxyController extends Controller
{
    private string $backendUrl;
    private string $localUrl;
    private int $timeout;
    private ?string $apiKey;

    public function __construct()
    {
        $this->backendUrl = (string) config('services.iot.backend_url', 'https://iot.bppmhkp.online');
        $this->localUrl   = (string) config('services.iot.local_url', 'http://localhost:3000');
        $this->timeout    = (int) config('services.iot.timeout', 5);
        $this->apiKey     = config('services.iot.api_key') ?: null;
    }

    /**
     * Returns the primary IoT backend URL.
     * Priority: .env variable > remote endpoint.
     * When deploying to another server, simply change IOT_BACKEND_URL in .env.
     */
    private function getPrimaryUrl(): string
    {
        return $this->backendUrl;
    }

    public function recent(Request $request)
    {
        $device   = $request->input('device', 'FISH-00');
        $limit    = min((int) $request->input('limit', 5), 50);

        // --- Try primary IoT backend from .env first ---
        try {
            $response = Http::timeout($this->timeout)->get("{$this->backendUrl}/api/v1/sensors/history/{$device}", [
                '_limit' => $limit,
            ]);
            
            if ($response->successful()) {
                $json = $response->json();
                
                // Format from IoT Node.js has different structure
                if (!empty($json['data']) && is_array($json['data'])) {
                    $data = [];
                    
                    foreach ($json['data'] as $row) {
                        $createdAt = $row['created_at'] ?? $row['timestamp'] ?? now();
                        
                        // Convert ISO date to Carbon for formatting
                        $carbon = \Illuminate\Support\Carbon::parse($createdAt);
                        
                        $data[] = [
                            'time'      => $carbon->format('H:i:s'),
                            'date'      => $carbon->format('Y-m-d'),
                            'timestamp' => $carbon->timestamp,
                            'temp'      => (float) ($row['temperature'] ?? $row['temp'] ?? 0),
                            'ph'        => (float) ($row['ph'] ?? $row['ph_value'] ?? 0),
                        ];
                    }
                    
                    // Reverse for chronological order
                    return response()->json(array_reverse($data));
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Local IoT fetch failed, trying remote', [
                'error' => $e->getMessage(),
            ]);
        }

        // --- Fallback: local IoT Node.js ---
        try {
            $response = Http::timeout($this->timeout)->get("{$this->localUrl}/api/v1/sensors/history/{$device}", [
                '_limit' => $limit,
            ]);
            
            if ($response->successful()) {
                $json = $response->json();
                if (!empty($json['data']) && is_array($json['data'])) {
                    $data = [];
                    foreach ($json['data'] as $row) {
                        $createdAt = $row['created_at'] ?? $row['timestamp'] ?? now();
                        $carbon = \Illuminate\Support\Carbon::parse($createdAt);
                        $data[] = [
                            'time'      => $carbon->format('H:i:s'),
                            'date'      => $carbon->format('Y-m-d'),
                            'timestamp' => $carbon->timestamp,
                            'temp'      => (float) ($row['temperature'] ?? 0),
                            'ph'        => (float) ($row['ph'] ?? $row['ph_value'] ?? 0),
                        ];
                    }
                    return response()->json(array_reverse($data));
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Local IoT fallback failed', ['error' => $e->getMessage()]);
        }

        // --- Ultimate fallback: local SQLite ---
        $logs = SensorLog::where('device_id', $device)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $data = [];
        foreach ($logs as $l) {
            $createdAt = $l->created_at;
            if (is_string($createdAt)) {
                $createdAt = \Illuminate\Support\Carbon::parse($createdAt);
            }
            $data[] = [
                'time'      => $createdAt->format('H:i:s'),
                'date'      => $createdAt->format('Y-m-d'),
                'timestamp' => $createdAt->timestamp,
                'temp'      => (float) $l->temperature,
                'ph'        => (float) $l->ph,
            ];
        }
        $data = array_reverse($data);
        return response()->json($data);
    }

    public function ingest(Request $request)
    {
        $validated = $request->validate([
            'deviceId'    => 'required|string|max:32',
            'temperature' => 'required|numeric|between:-10,60',
            'ph'          => 'required|numeric|between:0,14',
        ]);

        $log = SensorLog::create([
            'device_id'   => $validated['deviceId'],
            'temperature' => (float) $validated['temperature'],
            'ph'          => (float) $validated['ph'],
        ]);

        // Forward to IoT backends (non-blocking)
        try {
            Http::timeout($this->timeout)->post("{$this->backendUrl}/api/v1/sensors", $validated->all());
        } catch (\Exception $e) {
            \Log::debug('Forward to backend IoT failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'id'      => $log->id,
            'message' => 'Telemetry persisted',
        ], 201);
    }
    
    /**
     * Get all available devices for the dropdown
     */
    public function devices()
    {
        // Combine devices from IoT backend + local SQLite
        $devices = collect();
        
        // From IoT Backend / API
        try {
            $response = Http::timeout($this->timeout)->get("{$this->backendUrl}/api/v1/sensors/all");
            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['data']) && is_array($data['data'])) {
                    foreach ($data['data'] as $item) {
                        $devices->push(['id' => $item['deviceId'] ?? '', 'name' => $item['deviceId'] ?? '']);
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::debug('Devices from local IoT failed', ['error' => $e->getMessage()]);
        }
        
        // From local SQLite
        $sqliteDevices = SensorLog::select('device_id')->distinct()->pluck('device_id');
        foreach ($sqliteDevices as $id) {
            if (!$devices->contains('id', $id)) {
                $devices->push(['id' => $id, 'name' => $id]);
            }
        }
        
        return response()->json(['devices' => $devices]);
    }
}
