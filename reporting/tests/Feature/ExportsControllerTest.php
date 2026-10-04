<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExportsControllerTest extends TestCase
{
    public function test_generate_runs_all_exports_and_redirects_with_success(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('export:reports', ['--all' => true])
            ->andReturn(0);

        $this->actingAs(User::factory()->make(['id' => 'export-test-user']))
            ->post(route('reports.exports.generate'))
            ->assertRedirect(route('reports.exports'))
            ->assertSessionHas('success', 'Reports generated successfully.');
    }

    public function test_generate_reports_failure_in_session_errors(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('export:reports', ['--all' => true])
            ->andReturn(1);

        $this->actingAs(User::factory()->make(['id' => 'export-test-user']))
            ->from(route('reports.exports'))
            ->post(route('reports.exports.generate'))
            ->assertRedirect(route('reports.exports'))
            ->assertSessionHasErrors('generation');
    }

    public function test_generate_scores_csv_runs_only_the_csv_export(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('export:reports', ['type' => 'mentee-scores-csv'])
            ->andReturn(0);

        $this->actingAs(User::factory()->make(['id' => 'export-test-user']))
            ->post(route('reports.exports.generate-mentee-scores-csv'))
            ->assertRedirect(route('reports.exports'))
            ->assertSessionHas('success', 'Mentee scores CSV generated successfully.');
    }

    public function test_exports_page_lists_the_mentee_scores_workbook(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('exports/mentee-scores_2026-10-04_120000.xlsx', 'workbook');

        $this->actingAs(User::factory()->make(['id' => 'export-test-user']))
            ->get(route('reports.exports'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Exports')
                ->where('files.0.typeLabel', 'Mentee Scores (Excel)')
                ->where('files.0.filename', 'mentee-scores_2026-10-04_120000.xlsx'));
    }

    public function test_mentee_scores_download_uses_excel_content_type(): void
    {
        Storage::fake('local');
        $filename = 'mentee-scores_2026-10-04_120000.xlsx';
        Storage::disk('local')->put("exports/{$filename}", 'workbook');

        $this->actingAs(User::factory()->make(['id' => 'export-test-user']))
            ->get(route('reports.exports.download', ['path' => base64_encode($filename)]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_mentee_scores_csv_download_uses_csv_content_type(): void
    {
        Storage::fake('local');
        $filename = 'mentee-scores-csv_2026-10-04_120000.csv';
        Storage::disk('local')->put("exports/{$filename}", 'Mentee,Score');

        $this->actingAs(User::factory()->make(['id' => 'export-test-user']))
            ->get(route('reports.exports.download', ['path' => base64_encode($filename)]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=utf-8');
    }
}
