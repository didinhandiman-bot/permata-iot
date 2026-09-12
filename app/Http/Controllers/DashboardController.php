<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SensorLog;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $device = $request->input('device', 'FISH-8A4E');

        $logs = SensorLog::where('device_id', $device)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        $dataSeries = [];
        foreach ($logs as $l) {
            $createdAt = $l->created_at;
            if (is_string($createdAt)) {
                $createdAt = \Illuminate\Support\Carbon::parse($createdAt);
            }
            $dataSeries[] = [
                'time' => $createdAt->format('H:i'),
                'timestamp' => $createdAt->timestamp,
                'temp' => (float) $l->temperature,
                'ph' => (float) $l->ph,
            ];
        }
        $dataSeries = array_reverse($dataSeries);

        // Calculate health status from latest reading
        $healthStatus = $this->calculateHealth($dataSeries);
        $latest = count($dataSeries) > 0 ? end($dataSeries) : null;

        return view('dashboard.index', compact(
            'dataSeries',
            'latest',
            'healthStatus'
        ));
    }

    private function calculateHealth(array $series): array
    {
        if (empty($series)) {
            return ['label' => 'Tidak Ada Data', 'color' => 'gray'];
        }

        $latest = end($series);
        $temp = $latest['temp'];
        $ph = $latest['ph'];

        // IDEAL: Temp 26-30°C AND pH 6.5-8.5
        if ($temp >= 26 && $temp <= 30 && $ph >= 6.5 && $ph <= 8.5) {
            return ['label' => 'OPTIMAL', 'color' => 'emerald'];
        }

        // WARNING: Temp 24-26 or 30-32 OR pH 6.0-6.5 or 8.5-9.0
        $tempWarn = ($temp >= 24 && $temp < 26) || ($temp > 30 && $temp <= 32);
        $phWarn = ($ph >= 6.0 && $ph < 6.5) || ($ph > 8.5 && $ph <= 9.0);
        if ($tempWarn || $phWarn) {
            return ['label' => 'PERLU PERHATIAN', 'color' => 'amber'];
        }

        // DANGER
        return ['label' => 'KRITIS', 'color' => 'rose'];
    }
}
