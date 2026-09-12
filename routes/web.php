<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Api\TelemetryController;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
