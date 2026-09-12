<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$count = \App\Models\SensorLog::count();
echo "Total logs: $count\n";
if ($count > 0) {
    $logs = \App\Models\SensorLog::orderBy('created_at')->get();
    foreach ($logs as $l) {
        echo sprintf("%d | %s | temp:%s | ph:%s | %s\n", 
            $l->id, $l->device_id, $l->temperature, $l->ph, $l->created_at);
    }
} else {
    echo "NO DATA in database.\n";
}
