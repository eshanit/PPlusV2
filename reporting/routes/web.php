<?php

use App\Http\Controllers\ReportingDashboardController;
use App\Http\Controllers\Reports\CohortProgressController;
use App\Http\Controllers\Reports\EvaluatorActivityController;
use App\Http\Controllers\Reports\ExportsController;
use App\Http\Controllers\Reports\GapOverviewController;
use App\Http\Controllers\Reports\JourneyStatusController;
use App\Http\Controllers\Reports\LowScoreWatchlistController;
use App\Http\Controllers\Reports\NeedsAttentionController;
use App\Http\Controllers\Reports\ScoreTrajectoryController;
use App\Http\Controllers\Reports\TimeToCompetenceController;
use Illuminate\Support\Facades\Route;

Route::get('/', ReportingDashboardController::class)->name('dashboard');
Route::get('/dashboard', ReportingDashboardController::class)->name('dashboard.index');

Route::get('/journey-status', JourneyStatusController::class)->name('reports.journey-status');
Route::get('/low-score-watchlist', LowScoreWatchlistController::class)->name('reports.low-score-watchlist');
Route::get('/gap-overview', GapOverviewController::class)->name('reports.gap-overview');

Route::get('/needs-attention', NeedsAttentionController::class)->name('reports.needs-attention');
Route::get('/score-trajectory', ScoreTrajectoryController::class)->name('reports.score-trajectory');
Route::get('/time-to-competence', TimeToCompetenceController::class)->name('reports.time-to-competence');
Route::get('/cohort-progress', CohortProgressController::class)->name('reports.cohort-progress');
Route::get('/evaluator-activity', EvaluatorActivityController::class)->name('reports.evaluator-activity');

Route::get('/exports', [ExportsController::class, 'index'])->name('reports.exports');
Route::get('/exports/{path}', [ExportsController::class, 'download'])
    ->where('path', '.+')
    ->name('reports.exports.download');
