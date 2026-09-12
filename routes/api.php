<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TelemetryController;
use App\Http\Controllers\Api\ApiProxyController;

// Ingestion (save locally + forward to IoT backend)
Route::post('/sensor/data', [ApiProxyController::class, 'ingest']);

// Polling: proxied read from IoT backends with SQLite fallback
Route::get('/v1/sensors/recent', [ApiProxyController::class, 'recent']);
