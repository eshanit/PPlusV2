<?php

use App\Http\Controllers\ReportingDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', ReportingDashboardController::class)->name('dashboard');
Route::get('/dashboard', ReportingDashboardController::class)->name('dashboard.index');
