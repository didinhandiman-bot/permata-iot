<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SensorLog;

class TelemetryController extends Controller
{
    public function ingest(Request $request)
    {
        $validated = $request->validate([
            'deviceId' => 'required|string|max:32',
            'temperature' => 'required|numeric|between:-10,60',
            'ph' => 'required|numeric|between:0,14',
        ]);

        $log = SensorLog::create([
            'device_id' => $validated['deviceId'],
            'temperature' => (float) $validated['temperature'],
            'ph' => (float) $validated['ph'],
        ]);

        return response()->json([
            'success' => true,
            'id' => $log->id,
            'message' => 'Telemetry persisted',
        ], 201);
    }

    public function recent(Request $request)
    {
        $device = $request->input('device', 'FISH-8A4E');
        $limit = min((int) $request->input('limit', 50), 100);
        $latest = $limit * -1;

        $logs = SensorLog::where('device_id', $device)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $data = [];
        foreach ($logs as $l) {
            $createdAt = $l->created_at;
            // If it's a string from SQLite, convert to Carbon
            if (is_string($createdAt)) {
                $createdAt = \Illuminate\Support\Carbon::parse($createdAt);
            }
            $data[] = [
                'time' => $createdAt->format('H:i:s'),
                'date' => $createdAt->format('Y-m-d'),
                'timestamp' => $createdAt->timestamp,
                'temp' => (float) $l->temperature,
                'ph' => (float) $l->ph,
            ];
        }

        // Return chronological order for chart
        $data = array_reverse($data);

        return response()->json($data);
    }
}
