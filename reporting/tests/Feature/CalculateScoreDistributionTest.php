<?php

namespace Tests\Feature;

use App\Actions\CalculateScoreDistribution;
use PHPUnit\Framework\TestCase;

class CalculateScoreDistributionTest extends TestCase
{
    private CalculateScoreDistribution $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CalculateScoreDistribution();
    }

    public function test_runCalculatesPercentagesCorrectly(): void
    {
        $rows = [
            (object) [
                'tool_id' => 1,
                'tool_label' => 'Diabetes',
                'tool_slug' => 'diabetes',
                'count_1' => 10,
                'count_2' => 20,
                'count_3' => 30,
                'count_4' => 25,
                'count_5' => 15,
                'total' => 100,
                'avg_score' => 3.25,
            ],
        ];

        $result = $this->action->run($rows);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['toolId']);
        $this->assertSame(10.0, $result[0]['pct1']);
        $this->assertSame(20.0, $result[0]['pct2']);
        $this->assertSame(30.0, $result[0]['pct3']);
        $this->assertSame(25.0, $result[0]['pct4']);
        $this->assertSame(15.0, $result[0]['pct5']);
        $this->assertSame(100, $result[0]['total']);
    }

    public function test_runHandlesZeroTotal(): void
    {
        $rows = [
            (object) [
                'tool_id' => 1,
                'tool_label' => 'Diabetes',
                'tool_slug' => 'diabetes',
                'count_1' => 0,
                'count_2' => 0,
                'count_3' => 0,
                'count_4' => 0,
                'count_5' => 0,
                'total' => 0,
                'avg_score' => null,
            ],
        ];

        $result = $this->action->run($rows);

        $this->assertSame(0.0, $result[0]['pct1']);
        $this->assertSame(0.0, $result[0]['pct5']);
        $this->assertNull($result[0]['avgScore']);
    }

    public function test_calculateTotalsSumsCorrectly(): void
    {
        $tools = [
            ['total' => 100, 'toolId' => 1],
            ['total' => 200, 'toolId' => 2],
            ['total' => 50, 'toolId' => 3],
        ];

        $result = $this->action->calculateTotals($tools);

        $this->assertSame(350, $result['totalScored']);
        $this->assertSame(3, $result['totalItems']);
    }

    public function test_calculatePercentageWithRounding(): void
    {
        $reflection = new \ReflectionClass($this->action);
        $method = $reflection->getMethod('calculatePercentage');
        $method->setAccessible(true);

        $result = $method->invoke($this->action, 1, 3);

        $this->assertEquals(33.3, $result);
    }
}