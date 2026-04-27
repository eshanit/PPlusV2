<?php

namespace Tests\Feature;

use App\Services\ReportQueryService;
use App\Services\ReportScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReportQueryService(new ReportScopeService());
    }

    public function test_getSessionAveragesReturnsArray(): void
    {
        $result = $this->service->getSessionAverages('non-existent-group');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_getCohortProgressReturnsArray(): void
    {
        $result = $this->service->getCohortProgress();

        $this->assertIsArray($result);
    }

    public function test_getJourneysForToolReturnsEmptyWhenNoData(): void
    {
        $result = $this->service->getJourneysForTool(999);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_getJourneySummaryReturnsNullForNonExistent(): void
    {
        $result = $this->service->getJourneySummary('non-existent-group');

        $this->assertNull($result);
    }

    public function test_getJourneySummaryDataReturnsDataObject(): void
    {
        $result = $this->service->getJourneySummaryData('non-existent-group');

        $this->assertNull($result);
    }

    public function test_getScoreDistributionByToolReturnsArray(): void
    {
        $result = $this->service->getScoreDistributionByTool();

        $this->assertIsArray($result);
    }
}