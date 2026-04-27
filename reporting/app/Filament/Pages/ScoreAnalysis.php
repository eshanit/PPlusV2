<?php

namespace App\Filament\Pages;

use App\Models\District;
use App\Models\Facility;
use App\Models\Tool;
use App\Services\ScoreDistributionService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class ScoreAnalysis extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 25;

    protected static string $routePath = 'score-analysis';

    protected static ?string $title = 'Score Analysis';

    protected static ?string $navigationLabel = 'Score Analysis';

    protected static ?string $navigationGroup = 'Analytics';

    protected static string $view = 'filament.pages.score-analysis';

    public ?array $data = [];

    public array $scoreDistribution = [];

    public array $metadata = [];

    public function mount(): void
    {
        $this->form->fill();
        $this->loadScoreDistribution();
    }

    protected function getFormSchema(): array
    {
        $userDistrictId = Auth::user()?->district_id;

        return [
            Section::make('Filters')
                ->schema([
                    CheckboxList::make('districts')
                        ->label('Districts')
                        ->options(function () use ($userDistrictId): array {
                            $query = District::query();

                            if (! Auth::user()?->isAdmin() && $userDistrictId) {
                                $query->where('id', $userDistrictId);
                            }

                            return $query->pluck('name', 'id')->toArray();
                        })
                        ->disabled(function () use ($userDistrictId): bool {
                            return ! Auth::user()?->isAdmin() && $userDistrictId;
                        })
                        ->columnSpanFull()
                        ->live(),

                    CheckboxList::make('facilities')
                        ->label('Facilities')
                        ->options(function (callable $get): array {
                            $districts = $get('districts') ?? [];

                            if (empty($districts)) {
                                return [];
                            }

                            return Facility::whereIn('district_id', $districts)
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->columnSpanFull()
                        ->live(),

                    CheckboxList::make('tools')
                        ->label('Tools/Diseases')
                        ->options(Tool::pluck('label', 'id')->toArray())
                        ->columnSpanFull()
                        ->live(),

                    DatePicker::make('from_date')
                        ->label('From Date')
                        ->native(false)
                        ->columnSpan('col-span-1')
                        ->live(),

                    DatePicker::make('to_date')
                        ->label('To Date')
                        ->native(false)
                        ->columnSpan('col-span-1')
                        ->live(),
                ])->columns(2),
        ];
    }

    protected function loadScoreDistribution(): void
    {
        $service = app(ScoreDistributionService::class);

        $districtIds = $this->data['districts'] ?? [];
        $facilityIds = $this->data['facilities'] ?? [];
        $toolIds = $this->data['tools'] ?? [];

        $result = $service->getAggregateScoreCounts(
            toolIds: ! empty($toolIds) ? $toolIds : null,
            districtIds: ! empty($districtIds) ? $districtIds : null,
            facilityIds: ! empty($facilityIds) ? $facilityIds : null,
            fromDate: $this->data['from_date'] ?? null,
            toDate: $this->data['to_date'] ?? null,
        );

        $this->scoreDistribution = $result['tools'];
        $this->metadata = $result['metadata'];
    }

    public function updatedData(): void
    {
        $this->loadScoreDistribution();
    }

    public function getExportUrl(): string
    {
        return route('score-analysis.export', [
            'tool_ids' => $this->data['tools'] ?? [],
            'district_ids' => $this->data['districts'] ?? [],
            'facility_ids' => $this->data['facilities'] ?? [],
            'from_date' => $this->data['from_date'] ?? null,
            'to_date' => $this->data['to_date'] ?? null,
        ]);
    }
}
