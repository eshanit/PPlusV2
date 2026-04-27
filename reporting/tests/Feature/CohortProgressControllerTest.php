<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CohortProgressControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_200(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('reports.cohort-progress'))
            ->assertStatus(200);
    }

    public function test_index_accepts_tool_filter(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('reports.cohort-progress', ['tool_id' => 1]))
            ->assertStatus(200);
    }

    public function test_index_accepts_district_filter(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)
            ->get(route('reports.cohort-progress', ['district_id' => 'test-district']))
            ->assertStatus(200);
    }
}