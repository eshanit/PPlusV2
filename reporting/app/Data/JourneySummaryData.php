<?php

namespace App\Data;

readonly class JourneySummaryData
{
    public function __construct(
        public ?object $source,
    ) {}

    public function getGroupId(): ?string
    {
        return $this->source?->evaluation_group_id;
    }

    public function getMenteeName(): string
    {
        if (! $this->source) {
            return '';
        }

        return trim("{$this->source->mentee_firstname} {$this->source->mentee_lastname}");
    }

    public function getEvaluatorName(): string
    {
        if (! $this->source) {
            return '';
        }

        return trim("{$this->source->evaluator_firstname} {$this->source->evaluator_lastname}");
    }

    public function getToolLabel(): ?string
    {
        return $this->source?->tool_label;
    }

    public function getDistrict(): ?string
    {
        return $this->source?->district_name;
    }

    public function getFacility(): ?string
    {
        return $this->source?->facility_name;
    }

    public function getStatus(): ?string
    {
        return $this->source?->competency_status;
    }

    public function getTotalSessions(): int
    {
        return $this->source ? (int) $this->source->total_sessions : 0;
    }

    public function getOpenGaps(): int
    {
        return $this->source ? (int) $this->source->open_gaps : 0;
    }

    public function getResolvedGaps(): int
    {
        return $this->source ? (int) $this->source->resolved_gaps : 0;
    }

    public function getBasicCompetentAt(): ?int
    {
        return $this->source?->basic_competent_at;
    }

    public function getSessionsToBasicCompetence(): ?int
    {
        return $this->source?->sessions_to_basic_competence;
    }

    public function toArrayForTrajectory(): array
    {
        return [
            'groupId' => $this->getGroupId(),
            'mentee' => $this->getMenteeName(),
            'tool' => $this->getToolLabel(),
            'status' => $this->getStatus(),
            'totalSessions' => $this->getTotalSessions(),
            'basicCompetentAt' => $this->getBasicCompetentAt(),
            'sessionsToBasic' => $this->getSessionsToBasicCompetence(),
        ];
    }

    public function toArrayForGaps(): array
    {
        return [
            'groupId' => $this->getGroupId(),
            'menteeName' => $this->getMenteeName(),
            'evaluatorName' => $this->getEvaluatorName(),
            'toolLabel' => $this->getToolLabel(),
            'district' => $this->getDistrict(),
            'facility' => $this->getFacility(),
            'status' => $this->getStatus(),
            'totalSessions' => $this->getTotalSessions(),
            'openGaps' => $this->getOpenGaps(),
            'resolvedGaps' => $this->getResolvedGaps(),
        ];
    }
}
