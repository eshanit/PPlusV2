<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportsController extends Controller
{
    private const TYPES = [
        'journey' => 'Journey Status',
        'gaps' => 'Gap Overview',
        'evaluator' => 'Evaluator Activity',
        'mentee-scores-csv' => 'Mentee Scores (CSV)',
        'mentee-scores' => 'Mentee Scores (Excel)',
    ];

    public function index(): Response
    {
        $files = collect(Storage::disk('local')->files('exports'))
            ->filter(fn ($path) => in_array(pathinfo($path, PATHINFO_EXTENSION), ['csv', 'xlsx'], true))
            ->sortByDesc(fn ($path) => Storage::disk('local')->lastModified($path))
            ->map(fn ($path) => $this->parseFile($path))
            ->values()
            ->all();

        $downloadUrl = route('reports.exports.download', ['path' => '__PATH__']);

        return Inertia::render('Reports/Exports', [
            'files' => $files,
            'downloadUrlTemplate' => $downloadUrl,
            'types' => self::TYPES,
        ]);
    }

    public function generate(): RedirectResponse
    {
        $exitCode = Artisan::call('export:reports', ['--all' => true]);

        if ($exitCode !== 0) {
            return back()->withErrors([
                'generation' => 'Report generation failed. Check the application logs and export storage permissions.',
            ]);
        }

        return redirect()->route('reports.exports')->with('success', 'Reports generated successfully.');
    }

    public function generateMenteeScoresCsv(): RedirectResponse
    {
        $exitCode = Artisan::call('export:reports', ['type' => 'mentee-scores-csv']);

        if ($exitCode !== 0) {
            return back()->withErrors([
                'generation' => 'Mentee scores CSV generation failed. Check the application logs and export storage permissions.',
            ]);
        }

        return redirect()->route('reports.exports')->with('success', 'Mentee scores CSV generated successfully.');
    }

    public function download(string $path): StreamedResponse
    {
        $decoded = base64_decode($path);
        $safePath = 'exports/'.basename($decoded);

        if (! Storage::disk('local')->exists($safePath)) {
            abort(404);
        }

        $filename = basename($decoded);
        $contentType = match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'text/csv',
        };

        return Storage::disk('local')->download($safePath, $filename, [
            'Content-Type' => $contentType,
        ]);
    }

    private function parseFile(string $path): array
    {
        $basename = pathinfo($path, PATHINFO_FILENAME);
        $parts = explode('_', $basename);
        $type = $parts[0] ?? 'unknown';
        $dateTime = ($parts[1] ?? '').' '.($parts[2] ?? '000000');

        return [
            'path' => base64_encode(pathinfo($path, PATHINFO_BASENAME)),
            'type' => $type,
            'typeLabel' => self::TYPES[$type] ?? ucfirst($type),
            'filename' => pathinfo($path, PATHINFO_BASENAME),
            'size' => $this->formatSize(Storage::disk('local')->size($path)),
            'generatedAt' => $this->formatDateTime($dateTime),
            'generatedAtRelative' => $this->relativeTime(Storage::disk('local')->lastModified($path)),
        ];
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes}B";
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).'KB';
        }

        return round($bytes / (1024 * 1024), 1).'MB';
    }

    private function formatDateTime(string $dateTime): string
    {
        try {
            return Carbon::createFromFormat('Y-m-d His', $dateTime)->format('M j, Y \a\t g:i A');
        } catch (\Exception) {
            return $dateTime;
        }
    }

    private function relativeTime(int $timestamp): string
    {
        return Carbon::createFromTimestamp($timestamp)->diffForHumans();
    }
}
