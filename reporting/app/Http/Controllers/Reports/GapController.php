<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\GapEntry;
use App\Models\Tool;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GapController extends Controller
{
    private const DOMAINS = [
        'knowledge' => 'Knowledge',
        'critical_reasoning' => 'Critical Reasoning',
        'clinical_skills' => 'Clinical Skills',
        'communication' => 'Communication',
        'attitude' => 'Attitude',
    ];

    private const SUPERVISION_LEVELS = [
        'intensive_mentorship' => 'Intensive Mentorship',
        'ongoing_mentorship' => 'Ongoing Mentorship',
        'independent_practice' => 'Independent Practice',
    ];

    public function index(): Response
    {
        return Inertia::render('Reports/GapManager', [
            'domainOptions' => self::DOMAINS,
            'supervisionOptions' => self::SUPERVISION_LEVELS,
            'tools' => Tool::where('slug', '!=', 'counselling')
                ->orderBy('sort_order')
                ->get(['id', 'label']),
        ]);
    }

    public function search(Request $request)
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $gaps = GapEntry::query()
            ->with(['mentee:id,firstname,lastname', 'tool:id,label'])
            ->where('id', 'like', "{$query}%")
            ->orWhereHas('mentee', fn ($q) => $q->where('firstname', 'like', "%{$query}%")->orWhere('lastname', 'like', "%{$query}%"))
            ->orWhere('description', 'like', "%{$query}%")
            ->limit(20)
            ->get()
            ->map(fn (GapEntry $g): array => [
                'id' => $g->id,
                'mentee' => "{$g->mentee?->firstname} {$g->mentee?->lastname}",
                'tool' => $g->tool?->label,
                'identifiedAt' => $g->identified_at?->toDateString(),
                'resolvedAt' => $g->resolved_at?->toDateString(),
                'isResolved' => $g->resolved_at !== null,
            ]);

        return response()->json($gaps);
    }

    public function show(string $id): Response
    {
        $gap = GapEntry::findOrFail($id);

        return Inertia::render('Reports/GapEdit', [
            'gap' => [
                'id' => $gap->id,
                'evaluationGroupId' => $gap->evaluation_group_id,
                'menteeId' => $gap->mentee_id,
                'evaluatorId' => $gap->evaluator_id,
                'toolId' => $gap->tool_id,
                'identifiedAt' => $gap->identified_at?->toDateString(),
                'description' => $gap->description,
                'domains' => $gap->domains ?? [],
                'coveredInMentorship' => $gap->covered_in_mentorship,
                'coveringLater' => $gap->covering_later,
                'timeline' => $gap->timeline,
                'supervisionLevel' => $gap->supervision_level,
                'resolutionNote' => $gap->resolution_note,
                'resolvedAt' => $gap->resolved_at?->toDateString(),
                'createdAt' => $gap->created_at?->toISOString(),
                'updatedAt' => $gap->updated_at?->toISOString(),
            ],
            'domainOptions' => self::DOMAINS,
            'supervisionOptions' => self::SUPERVISION_LEVELS,
            'tools' => Tool::where('slug', '!=', 'counselling')
                ->orderBy('sort_order')
                ->get(['id', 'label']),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $gap = GapEntry::findOrFail($id);

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:1000'],
            'domains' => ['required', 'array', 'min:1'],
            'domains.*' => ['string', 'in:'.implode(',', array_keys(self::DOMAINS))],
            'covered_in_mentorship' => ['nullable', 'boolean'],
            'covering_later' => ['boolean'],
            'timeline' => ['nullable', 'string', 'max:255'],
            'supervision_level' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::SUPERVISION_LEVELS))],
            'resolution_note' => ['nullable', 'string', 'max:500'],
            'resolved_at' => ['nullable', 'date'],
        ]);

        $gap->description = $validated['description'];
        $gap->domains = $validated['domains'];
        $gap->covered_in_mentorship = $validated['covered_in_mentorship'] ?? false;
        $gap->covering_later = $validated['covering_later'] ?? false;
        $gap->timeline = $validated['timeline'] ?? null;
        $gap->supervision_level = $validated['supervision_level'] ?? null;
        $gap->resolution_note = $validated['resolution_note'] ?? null;
        $gap->resolved_at = $validated['resolved_at'] ? Carbon::parse($validated['resolved_at']) : null;
        $gap->save();

        return redirect()->route('reports.gap-overview')->with('success', 'Gap updated successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $gap = GapEntry::findOrFail($id);
        $gap->delete();

        return redirect()->route('reports.gap-overview')->with('success', 'Gap deleted.');
    }
}
