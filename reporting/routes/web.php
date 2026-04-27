<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ReportingDashboardController;
use App\Http\Controllers\Reports\CohortProgressController;
use App\Http\Controllers\Reports\EvaluatorActivityController;
use App\Http\Controllers\Reports\ExportsController;
use App\Http\Controllers\Reports\GapController;
use App\Http\Controllers\Reports\GapOverviewController;
use App\Http\Controllers\Reports\GapReportController;
use App\Http\Controllers\Reports\HighRiskAlertsController;
use App\Http\Controllers\Reports\HotSpotsController;
use App\Http\Controllers\Reports\ItemAnalysisController;
use App\Http\Controllers\Reports\JourneyGapsController;
use App\Http\Controllers\Reports\JourneyHeatmapController;
use App\Http\Controllers\Reports\JourneySessionsController;
use App\Http\Controllers\Reports\JourneyStatusController;
use App\Http\Controllers\Reports\LowScoreWatchlistController;
use App\Http\Controllers\Reports\NeedsAttentionController;
use App\Http\Controllers\Reports\ScoreTrajectoryController;
use App\Http\Controllers\Reports\SessionReportController;
use App\Http\Controllers\Reports\TimeToCompetenceController;
use App\Http\Controllers\Reports\ToolAnalysisController;
use App\Http\Controllers\Reports\ToolScoreDistributionController;
use App\Http\Controllers\ScoreAnalysisExportController;
use Illuminate\Support\Facades\Route;

Route::middleware('reporting.auth')->group(function () {
    Route::get('/', ReportingDashboardController::class)->name('dashboard');
    Route::get('/dashboard', ReportingDashboardController::class)->name('dashboard.index');

    Route::get('/journey-status', JourneyStatusController::class)->name('reports.journey-status');
    Route::get('/journey-sessions', JourneySessionsController::class)->name('reports.journey-sessions');
    Route::get('/journey-gaps', JourneyGapsController::class)->name('reports.journey-gaps');
    Route::get('/gap-report/{id}', GapReportController::class)->name('reports.gap-report');
    Route::get('/low-score-watchlist', LowScoreWatchlistController::class)->name('reports.low-score-watchlist');
    Route::get('/gap-overview', GapOverviewController::class)->name('reports.gap-overview');

    Route::get('/needs-attention', NeedsAttentionController::class)->name('reports.needs-attention');
    Route::get('/score-trajectory', ScoreTrajectoryController::class)->name('reports.score-trajectory');
    Route::get('/time-to-competence', TimeToCompetenceController::class)->name('reports.time-to-competence');
    Route::get('/cohort-progress', CohortProgressController::class)->name('reports.cohort-progress');
    Route::get('/evaluator-activity', EvaluatorActivityController::class)->name('reports.evaluator-activity');

    Route::get('/tool-analysis', ToolAnalysisController::class)->name('reports.tool-analysis');
    Route::get('/tool-score-distribution', ToolScoreDistributionController::class)->name('reports.tool-score-distribution');
    Route::get('/tool-analysis/items/{id}', ItemAnalysisController::class)->name('reports.item-analysis');

    Route::get('/hot-spots', HotSpotsController::class)->name('reports.hot-spots');
    Route::get('/journey-heatmap', JourneyHeatmapController::class)->name('reports.journey-heatmap');
    Route::get('/high-risk-alerts', HighRiskAlertsController::class)->name('reports.high-risk-alerts');

    Route::get('/sessions/{session}', SessionReportController::class)->name('reports.session');

    Route::get('/exports', [ExportsController::class, 'index'])->name('reports.exports');
    Route::get('/exports/{path}', [ExportsController::class, 'download'])
        ->where('path', '.+')
        ->name('reports.exports.download');

    Route::middleware('admin')->group(function () {
        Route::get('/gaps', [GapController::class, 'index'])->name('reports.gaps');
        Route::get('/gaps/search', [GapController::class, 'search'])->name('reports.gaps.search');
        Route::get('/gaps/{id}', [GapController::class, 'show'])->name('reports.gaps.show');
        Route::put('/gaps/{id}', [GapController::class, 'update'])->name('reports.gaps.update');
        Route::delete('/gaps/{id}', [GapController::class, 'destroy'])->name('reports.gaps.destroy');

        // Score Analysis Exports
        Route::get('/score-analysis/export', [ScoreAnalysisExportController::class, 'exportCsv'])->name('score-analysis.export');
        Route::get('/score-analysis/export-items', [ScoreAnalysisExportController::class, 'exportItemsCsv'])->name('score-analysis.export-items');
    });
});

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store']);
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
