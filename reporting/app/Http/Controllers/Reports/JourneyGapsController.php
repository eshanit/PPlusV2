<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\JourneyGapsRequest;
use App\Services\ReportQueryService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class JourneyGapsController extends Controller
{
    public function __construct(
        private readonly ReportQueryService $queries,
    ) {}

    public function __invoke(JourneyGapsRequest $request): Response
    {
        $groupId = $request->input('group_id');
        $journey = $this->queries->getJourneySummaryData($groupId);

        if (! $journey?->getGroupId()) {
            abort(404);
        }

        $gaps = DB::table('gap_entries')
            ->where('evaluation_group_id', $groupId)
            ->orderBy('identified_at')
            ->get([
                'id',
                'identified_at',
                'description',
                'domains',
                'covered_in_mentorship',
                'covering_later',
                'timeline',
                'supervision_level',
                'resolution_note',
                'resolved_at',
            ])
            ->map(fn (object $g): array => [
                'id' => $g->id,
                'identifiedAt' => $g->identified_at,
                'description' => $g->description,
                'domains' => json_decode($g->domains, true) ?? [],
                'coveredInMentorship' => $g->covered_in_mentorship,
                'coveringLater' => (bool) $g->covering_later,
                'timeline' => $g->timeline,
                'supervisionLevel' => $g->supervision_level,
                'resolutionNote' => $g->resolution_note,
                'resolvedAt' => $g->resolved_at,
                'isResolved' => $g->resolved_at !== null,
            ])
            ->all();

        return Inertia::render('Reports/JourneyGaps', [
            'journey' => $journey->toArrayForGaps(),
            'gaps' => $gaps,
        ]);
    }
}
