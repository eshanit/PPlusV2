<?php

namespace App\Console\Commands;

use App\Models\JourneySummary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExportReports extends Command
{
    protected $signature = 'export:reports
                            {type? : The report type (journey, gaps, evaluator). Comma-separated for multiple.}
                            {--all : Export all report types}
                            {--filename= : Custom filename suffix}';

    protected $description = 'Export report data to CSV files';

    public function handle(): int
    {
        $all = $this->option('all');
        $types = $this->argument('type');

        if (! $all && ! $types) {
            $this->error('Provide --all or a type: journey, gaps, evaluator');

            return self::FAILURE;
        }

        $exportTypes = $all
            ? ['journey', 'gaps', 'evaluator']
            : array_map('trim', explode(',', $types));

        $validTypes = ['journey', 'gaps', 'evaluator'];
        $invalid = array_diff($exportTypes, $validTypes);
        if ($invalid) {
            $this->error('Invalid type(s): '.implode(', ', $invalid));

            return self::FAILURE;
        }

        $filename = $this->option('filename');

        foreach ($exportTypes as $type) {
            $this->export($type, $filename);
        }

        return self::SUCCESS;
    }

    private function export(string $type, ?string $filenameSuffix = null): void
    {
        $timestamp = now()->format('Y-m-d_His');
        $suffix = $filenameSuffix ? "_{$filenameSuffix}" : '';
        $filename = "exports/{$type}_{$timestamp}{$suffix}.csv";

        $data = match ($type) {
            'journey' => $this->exportJourney(),
            'gaps' => $this->exportGaps(),
            'evaluator' => $this->exportEvaluator(),
        };

        $path = Storage::disk('local')->put($filename, $data);

        if ($path) {
            $this->info("Exported {$type} → storage/app/{$filename}");
        } else {
            $this->error("Failed to write {$type} export");
        }
    }

    private function exportJourney(): string
    {
        $rows = JourneySummary::query()
            ->select([
                'mentee_firstname',
                'mentee_lastname',
                'tool_label as tool',
                'district_name as district',
                'facility_name as facility',
                'total_sessions',
                'latest_avg_score',
                'competency_status',
                'sessions_to_basic_competence',
                'days_to_basic_competence',
                'latest_session_date',
                'open_gaps',
                'resolved_gaps',
            ])
            ->orderBy('latest_session_date', 'desc')
            ->get();

        return $this->toCsv($rows->first() ? array_keys((array) $rows->first()) : [], $rows->toArray());
    }

    private function exportGaps(): string
    {
        $rows = DB::table('gap_entries as ge')
            ->join('users as mentees', 'mentees.id', '=', 'ge.mentee_id')
            ->join('tools', 'tools.id', '=', 'ge.tool_id')
            ->select([
                'mentees.firstname as mentee_firstname',
                'mentees.lastname as mentee_lastname',
                'tools.label as tool',
                'ge.domains',
                'ge.description',
                'ge.covered_in_mentorship',
                'ge.covering_later',
                'ge.supervision_level',
                'ge.timeline',
                'ge.resolution_note',
                'ge.resolved_at',
                'ge.identified_at',
            ])
            ->orderByDesc('ge.identified_at')
            ->get()
            ->map(fn (object $r): array => [
                'mentee_firstname' => $r->mentee_firstname,
                'mentee_lastname' => $r->mentee_lastname,
                'tool' => $r->tool,
                'domains' => $r->domains,
                'description' => $r->description,
                'covered_in_mentorship' => $r->covered_in_mentorship ? 'Yes' : 'No',
                'covering_later' => $r->covering_later ? 'Yes' : 'No',
                'supervision_level' => $r->supervision_level,
                'timeline' => $r->timeline,
                'resolution_note' => $r->resolution_note,
                'resolved_at' => $r->resolved_at,
                'identified_at' => $r->identified_at,
            ]);

        $headers = [
            'Mentee First Name', 'Mentee Last Name', 'Tool',
            'Domains', 'Description', 'Covered in Mentorship', 'Covering Later',
            'Supervision Level', 'Timeline', 'Resolution Note', 'Resolved At', 'Identified At',
        ];

        $keys = [
            'mentee_firstname', 'mentee_lastname', 'tool',
            'domains', 'description', 'covered_in_mentorship', 'covering_later',
            'supervision_level', 'timeline', 'resolution_note', 'resolved_at', 'identified_at',
        ];

        return $this->toCsv($headers, $rows->toArray(), $keys);
    }

    private function exportEvaluator(): string
    {
        $rows = DB::table('evaluation_sessions as es')
            ->join('users as evaluators', 'evaluators.id', '=', 'es.evaluator_id')
            ->join('tools', 'tools.id', '=', 'es.tool_id')
            ->leftJoin('districts', 'districts.id', '=', 'es.district_id')
            ->leftJoin('v_session_averages as sa', 'sa.session_id', '=', 'es.id')
            ->where('tools.slug', '!=', 'counselling')
            ->selectRaw('CONCAT(evaluators.firstname, " ", evaluators.lastname) as evaluator_name')
            ->selectRaw('DATE_FORMAT(es.eval_date, "%Y-%m") as month')
            ->selectRaw('COUNT(DISTINCT es.id) as session_count')
            ->selectRaw('COUNT(DISTINCT es.evaluation_group_id) as mentee_count')
            ->selectRaw('ROUND(AVG(sa.avg_mentee_score), 2) as avg_score')
            ->groupByRaw('evaluators.id, evaluators.firstname, evaluators.lastname, DATE_FORMAT(es.eval_date, "%Y-%m")')
            ->orderByRaw('month DESC, session_count DESC')
            ->get();

        $headers = ['Evaluator', 'Month', 'Sessions', 'Mentees', 'Avg Score'];

        return $this->toCsv($headers, $rows->map(fn ($r): array => [
            'evaluator_name' => $r->evaluator_name,
            'month' => $r->month,
            'session_count' => (int) $r->session_count,
            'mentee_count' => (int) $r->mentee_count,
            'avg_score' => $r->avg_score,
        ])->toArray());
    }

    private function toCsv(array $headers, array $rows, ?array $keys = null): string
    {
        $lines = [];

        if ($headers) {
            $lines[] = implode(',', $headers);
        } elseif ($rows) {
            $lines[] = implode(',', array_keys((array) $rows[0]));
        }

        foreach ($rows as $row) {
            $values = $keys
                ? array_map(fn ($k) => $this->csvValue($row[$k] ?? ''), $keys)
                : array_map(fn ($v) => $this->csvValue($v), (array) $row);
            $lines[] = implode(',', $values);
        }

        return implode("\n", $lines);
    }

    private function csvValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $str = (string) $value;

        return (str_contains($str, ',') || str_contains($str, '"') || str_contains($str, "\n"))
            ? '"'.str_replace('"', '""', $str).'"'
            : $str;
    }
}
