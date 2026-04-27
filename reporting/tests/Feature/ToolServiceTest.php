<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\User;
use App\Services\ToolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToolServiceTest extends TestCase
{
    use RefreshDatabase;

    private ToolService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ToolService();
    }

    public function test_getAllForDropdownReturnsOnlyNonCounsellingTools(): void
    {
        Tool::factory()->create(['slug' => 'diabetes', 'label' => 'Diabetes', 'sort_order' => 1]);
        Tool::factory()->create(['slug' => 'counselling', 'label' => 'Counselling', 'sort_order' => 2]);
        Tool::factory()->create(['slug' => 'cardiac', 'label' => 'Heart Disease', 'sort_order' => 3]);

        $result = $this->service->getAllForDropdown();

        $this->assertCount(2, $result);
        $slugs = array_column($result, 'slug');
        $this->assertContains('diabetes', $slugs);
        $this->assertContains('cardiac', $slugs);
        $this->assertNotContains('counselling', $slugs);
    }

    public function test_getDistrictsForAdminReturnsAll(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user);

        $result = $this->service->getDistrictsForUser();

        $this->assertIsArray($result);
    }

    public function test_getDistrictsForNonAdminReturnsOnlyTheirDistrict(): void
    {
        $districtId = 'test-district-123';
        $user = User::factory()->create([
            'is_admin' => false,
            'district_id' => $districtId,
        ]);

        $this->actingAs($user);

        $result = $this->service->getDistrictsForUser();

        $this->assertCount(1, $result);
    }

    public function test_getByIdReturnsToolOrNull(): void
    {
        $tool = Tool::factory()->create(['slug' => 'diabetes', 'label' => 'Diabetes']);

        $result = $this->service->getById($tool->id);

        $this->assertNotNull($result);
        $this->assertSame('diabetes', $result->slug);
    }

    public function test_getByIdReturnsNullForNonExistent(): void
    {
        $result = $this->service->getById(99999);

        $this->assertNull($result);
    }
}