<?php

namespace App\Console\Commands;

use App\Models\JourneySummary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

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
            ? ['journey', 'gaps', 'evaluator', 'mentee-scores-csv', 'mentee-scores']
            : array_map('trim', explode(',', $types));

        $validTypes = ['journey', 'gaps', 'evaluator', 'mentee-scores-csv', 'mentee-scores'];
        $invalid = array_diff($exportTypes, $validTypes);
        if ($invalid) {
            $this->error('Invalid type(s): '.implode(', ', $invalid));

            return self::FAILURE;
        }

        $filename = $this->option('filename');

        $failed = false;

        foreach ($exportTypes as $type) {
            try {
                $failed = ! $this->export($type, $filename) || $failed;
            } catch (Throwable $exception) {
                report($exception);
                $this->error("Failed to export {$type}: {$exception->getMessage()}");
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function export(string $type, ?string $filenameSuffix = null): bool
    {
        $timestamp = now()->format('Y-m-d_His');
        $suffix = $filenameSuffix ? "_{$filenameSuffix}" : '';
        $extension = $type === 'mentee-scores' ? 'xlsx' : 'csv';
        $filename = "exports/{$type}_{$timestamp}{$suffix}.{$extension}";

        if ($type === 'mentee-scores') {
            return $this->exportMenteeScores($filename);
        }
        if ($type === 'mentee-scores-csv') {
            return $this->exportMenteeScoresCsv($filename);
        }

        $data = match ($type) {
            'journey' => $this->exportJourney(),
            'gaps' => $this->exportGaps(),
            'evaluator' => $this->exportEvaluator(),
        };

        $path = Storage::disk('local')->put($filename, $data);

        if ($path) {
            $this->info("Exported {$type} → storage/app/{$filename}");
            return true;
        }

        $this->error("Failed to write {$type} export");

        return false;
    }

    private function exportMenteeScores(string $filename): bool
    {
        $headers = $this->menteeScoreHeaders();
        $disk = Storage::disk('local');
        $disk->makeDirectory('exports');
        $outputPath = $disk->path($filename);
        $worksheetPath = tempnam(sys_get_temp_dir(), 'penplus-scores-');

        if ($worksheetPath === false) {
            throw new RuntimeException('Could not create a temporary file for the mentee scores workbook.');
        }

        $worksheet = null;
        $rowNumber = 2;
        try {
            $worksheet = fopen($worksheetPath, 'wb');

            if ($worksheet === false) {
                throw new RuntimeException('Could not open the temporary mentee scores worksheet.');
            }

            $this->writeWorksheet($worksheet, $headers, $rowNumber);

            $this->menteeScoresQuery()
                ->chunk(500, function ($scores) use ($worksheet, &$rowNumber): void {
                    foreach ($scores as $score) {
                        $this->writeWorksheetRow($worksheet, $this->menteeScoreValues($score), $rowNumber);
                        $rowNumber++;
                    }
                });

            $this->writeWorksheetXml($worksheet, $rowNumber - 1);
            fclose($worksheet);
            $worksheet = null;
            $this->packageXlsx($worksheetPath, $outputPath);
        } finally {
            if (is_resource($worksheet)) {
                fclose($worksheet);
            }

            if (is_file($worksheetPath)) {
                unlink($worksheetPath);
            }
        }

        $this->info("Exported mentee scores → storage/app/{$filename} (".($rowNumber - 2).' rows)');

        return true;
    }

    private function exportMenteeScoresCsv(string $filename): bool
    {
        $headers = $this->menteeScoreHeaders();
        $disk = Storage::disk('local');
        $disk->makeDirectory('exports');
        $outputPath = $disk->path($filename);
        $temporaryPath = tempnam(sys_get_temp_dir(), 'penplus-scores-csv-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Could not create a temporary file for the mentee scores CSV.');
        }

        $stream = null;
        $rowNumber = 1;
        try {
            $stream = fopen($temporaryPath, 'wb');

            if ($stream === false) {
                throw new RuntimeException('Could not open the temporary mentee scores CSV.');
            }

            if (fwrite($stream, "\xEF\xBB\xBF") === false) {
                throw new RuntimeException('Could not write the mentee scores CSV signature.');
            }

            $this->writeCsvRow($stream, $headers);
            $this->menteeScoresQuery()->chunk(1000, function ($scores) use ($stream, &$rowNumber): void {
                foreach ($scores as $score) {
                    $this->writeCsvRow($stream, $this->menteeScoreValues($score));
                    $rowNumber++;
                }
            });

            fclose($stream);
            $stream = null;

            if (! rename($temporaryPath, $outputPath)) {
                throw new RuntimeException("Could not move the generated CSV to [{$outputPath}].");
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }

            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }

        $this->info("Exported mentee scores CSV → storage/app/{$filename} (".($rowNumber - 1).' rows)');

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function menteeScoreHeaders(): array
    {
        return [
            'Mentee ID',
            'Mentee',
            'Tool',
            'Item Code',
            'Competency Item',
            'Category',
            'Mentee Score',
            'Is Advanced',
            'Is Critical',
            'Session Number',
            'Recorded Round of Day',
            'Round Order (Day)',
            'Session Date',
            'Mentorship Phase',
            'Mentor',
            'District',
            'Facility',
            'Evaluation Group ID',
        ];
    }

    private function menteeScoresQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('evaluation_sessions as es')
            ->join('users as mentees', 'mentees.id', '=', 'es.mentee_id')
            ->join('users as mentors', 'mentors.id', '=', 'es.evaluator_id')
            ->join('tools as t', 't.id', '=', 'es.tool_id')
            ->join('session_item_scores as sis', 'sis.session_id', '=', 'es.id')
            ->join('evaluation_items as ei', function ($join): void {
                $join->on('ei.id', '=', 'sis.item_id')
                    ->on('ei.tool_id', '=', 'es.tool_id');
            })
            ->leftJoin('tool_categories as tc', 'tc.id', '=', 'ei.category_id')
            ->leftJoin('districts as d', 'd.id', '=', 'es.district_id')
            ->leftJoin('facilities as f', 'f.id', '=', 'es.facility_id')
            ->leftJoin('v_sessions_numbered as vsn', 'vsn.id', '=', 'es.id')
            ->where('t.slug', '!=', 'counselling')
            ->select([
                'mentees.id as mentee_id',
                'mentees.firstname as mentee_firstname',
                'mentees.lastname as mentee_lastname',
                't.label as tool_label',
                'ei.number as item_number',
                'ei.title as item_title',
                'tc.name as category',
                'sis.mentee_score',
                'ei.is_advanced',
                'ei.is_critical',
                'vsn.session_number',
                'es.day_round as recorded_day_round',
                'vsn.day_round_number',
                'es.eval_date',
                'es.phase',
                'mentors.firstname as mentor_firstname',
                'mentors.lastname as mentor_lastname',
                'd.name as district_name',
                'f.name as facility_name',
                'es.evaluation_group_id',
            ])
            ->orderBy('mentees.lastname')
            ->orderBy('mentees.firstname')
            ->orderBy('t.sort_order')
            ->orderBy('es.eval_date')
            ->orderBy('vsn.session_number')
            ->orderBy('ei.sort_order')
            ->orderBy('es.id');
    }

    /**
     * @return array<int, int|string|null>
     */
    private function menteeScoreValues(object $score): array
    {
        return [
            $score->mentee_id,
            trim("{$score->mentee_firstname} {$score->mentee_lastname}"),
            $score->tool_label,
            $score->item_number,
            $score->item_title,
            $score->category,
            $score->mentee_score === null ? 'N/A' : (int) $score->mentee_score,
            $score->is_advanced ? 'Yes' : 'No',
            $score->is_critical ? 'Yes' : 'No',
            $score->session_number === null ? null : (int) $score->session_number,
            $score->recorded_day_round === null ? null : (int) $score->recorded_day_round,
            $score->day_round_number === null ? null : (int) $score->day_round_number,
            $score->eval_date,
            $score->phase,
            trim("{$score->mentor_firstname} {$score->mentor_lastname}"),
            $score->district_name,
            $score->facility_name,
            $score->evaluation_group_id,
        ];
    }

    /**
     * @param resource $stream
     * @param array<int, mixed> $values
     */
    private function writeCsvRow($stream, array $values): void
    {
        if (fputcsv($stream, $values, ',', '"', '', "\r\n") === false) {
            throw new RuntimeException('Could not write a mentee scores CSV row.');
        }
    }

    /**
     * @param resource $worksheet
     * @param array<int, string> $headers
     */
    private function writeWorksheet($worksheet, array $headers, int $rowNumber): void
    {
        $prefix = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .'<cols><col min="1" max="1" width="38" customWidth="1"/>'
            .'<col min="2" max="6" width="24" customWidth="1"/>'
            .'<col min="7" max="12" width="18" customWidth="1"/>'
            .'<col min="13" max="17" width="22" customWidth="1"/>'
            .'<col min="18" max="18" width="42" customWidth="1"/></cols><sheetData>';

        $this->writeWorksheetXml($worksheet, $prefix);
        $this->writeWorksheetRow($worksheet, $headers, 1, true);
    }

    /**
     * @param resource $worksheet
     */
    private function writeWorksheetXml($worksheet, string|int $xml): void
    {
        $contents = is_int($xml) ? '</sheetData><autoFilter ref="A1:R'.$xml.'"/></worksheet>' : $xml;

        if (fwrite($worksheet, $contents) === false) {
            throw new RuntimeException('Could not write the mentee scores worksheet.');
        }
    }

    /**
     * @param resource $worksheet
     * @param array<int, mixed> $values
     */
    private function writeWorksheetRow($worksheet, array $values, int $rowNumber, bool $header = false): void
    {
        if ($rowNumber > 1_048_576) {
            throw new RuntimeException("The mentee scores export exceeds Excel's 1,048,576-row worksheet limit.");
        }

        $cells = [];
        foreach (array_values($values) as $index => $value) {
            $reference = $this->columnName($index + 1).$rowNumber;
            $style = $header ? ' s="1"' : '';

            if (is_int($value) || is_float($value)) {
                $cells[] = "<c r=\"{$reference}\"{$style}><v>{$value}</v></c>";
                continue;
            }

            if ($value === null || $value === '') {
                $cells[] = "<c r=\"{$reference}\"{$style}/>";
                continue;
            }

            $text = (string) $value;
            $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text) ?? '';
            $escaped = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $cells[] = "<c r=\"{$reference}\" t=\"inlineStr\"{$style}><is><t xml:space=\"preserve\">{$escaped}</t></is></c>";
        }

        if (fwrite($worksheet, '<row r="'.$rowNumber.'">'.implode('', $cells).'</row>') === false) {
            throw new RuntimeException('Could not write a mentee scores row.');
        }
    }

    private function columnName(int $columnNumber): string
    {
        $name = '';

        while ($columnNumber > 0) {
            $remainder = ($columnNumber - 1) % 26;
            $name = chr(65 + $remainder).$name;
            $columnNumber = intdiv($columnNumber - 1, 26);
        }

        return $name;
    }

    private function packageXlsx(string $worksheetPath, string $outputPath): void
    {
        $archive = new \ZipArchive();
        $result = $archive->open($outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        if ($result !== true) {
            throw new RuntimeException("Could not create XLSX archive [{$outputPath}] (ZipArchive code {$result}).");
        }

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                .'</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="Mentee Scores" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                .'</Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
                .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
                .'<fill><patternFill patternType="solid"><fgColor rgb="FFDCEFE5"/><bgColor indexed="64"/></patternFill></fill></fills>'
                .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
                .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
                .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
                .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
        ];

        foreach ($files as $name => $contents) {
            if (! $archive->addFromString($name, $contents)) {
                $archive->close();
                throw new RuntimeException("Could not add [{$name}] to the XLSX archive.");
            }
        }

        if (! $archive->addFile($worksheetPath, 'xl/worksheets/sheet1.xml')) {
            $archive->close();
            throw new RuntimeException('Could not add the worksheet to the XLSX archive.');
        }

        if (! $archive->close()) {
            throw new RuntimeException('Could not finalize the mentee scores XLSX archive.');
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
