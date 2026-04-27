<?php

namespace Tests\Unit;

use App\Data\JourneySummaryData;
use PHPUnit\Framework\TestCase;

class JourneySummaryDataTest extends TestCase
{
    private function makeSource(): object
    {
        return (object) [
            'evaluation_group_id' => 'test-group-123',
            'mentee_firstname' => 'John',
            'mentee_lastname' => 'Doe',
            'evaluator_firstname' => 'Jane',
            'evaluator_lastname' => 'Smith',
            'tool_label' => 'Diabetes',
            'district_name' => 'Kampala',
            'facility_name' => 'Kampala Health Center',
            'competency_status' => 'basic_competent',
            'total_sessions' => 5,
            'open_gaps' => 2,
            'resolved_gaps' => 3,
            'basic_competent_at' => 1700000000,
            'sessions_to_basic_competence' => 4,
        ];
    }

    public function test_getGroupIdReturnsCorrectId(): void
    {
        $dto = new JourneySummaryData($this->makeSource());

        $this->assertSame('test-group-123', $dto->getGroupId());
    }

    public function test_getGroupIdReturnsNullWhenSourceIsNull(): void
    {
        $dto = new JourneySummaryData(null);

        $this->assertNull($dto->getGroupId());
    }

    public function test_getMenteeNameReturnsTrimmedFullName(): void
    {
        $dto = new JourneySummaryData($this->makeSource());

        $this->assertSame('John Doe', $dto->getMenteeName());
    }

    public function test_getEvaluatorNameReturnsTrimmedFullName(): void
    {
        $dto = new JourneySummaryData($this->makeSource());

        $this->assertSame('Jane Smith', $dto->getEvaluatorName());
    }

    public function test_getTotalSessionsReturnsInteger(): void
    {
        $dto = new JourneySummaryData($this->makeSource());

        $this->assertSame(5, $dto->getTotalSessions());
    }

    public function test_getTotalSessionsReturnsZeroWhenSourceIsNull(): void
    {
        $dto = new JourneySummaryData(null);

        $this->assertSame(0, $dto->getTotalSessions());
    }

    public function test_toArrayForTrajectoryReturnsCorrectShape(): void
    {
        $dto = new JourneySummaryData($this->makeSource());
        $result = $dto->toArrayForTrajectory();

        $this->assertSame('test-group-123', $result['groupId']);
        $this->assertSame('John Doe', $result['mentee']);
        $this->assertSame('Diabetes', $result['tool']);
        $this->assertSame('basic_competent', $result['status']);
        $this->assertSame(5, $result['totalSessions']);
        $this->assertSame(1700000000, $result['basicCompetentAt']);
        $this->assertSame(4, $result['sessionsToBasic']);
    }

    public function test_toArrayForGapsReturnsCorrectShape(): void
    {
        $dto = new JourneySummaryData($this->makeSource());
        $result = $dto->toArrayForGaps();

        $this->assertSame('test-group-123', $result['groupId']);
        $this->assertSame('John Doe', $result['menteeName']);
        $this->assertSame('Jane Smith', $result['evaluatorName']);
        $this->assertSame('Diabetes', $result['toolLabel']);
        $this->assertSame('Kampala', $result['district']);
        $this->assertSame('Kampala Health Center', $result['facility']);
        $this->assertSame('basic_competent', $result['status']);
        $this->assertSame(5, $result['totalSessions']);
        $this->assertSame(2, $result['openGaps']);
        $this->assertSame(3, $result['resolvedGaps']);
    }
}