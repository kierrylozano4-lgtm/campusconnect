<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminServiceModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_bulk_deactivate_selected_services_with_reason_and_hidden_marketplace_flag(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $serviceA = Service::create([
            'user_id' => $admin->id,
            'title' => 'Math tutoring',
            'category' => 'Academic',
            'description' => 'Help with calculus',
            'status' => 'Active',
            'visible_in_dashboard' => true,
        ]);

        $serviceB = Service::create([
            'user_id' => $admin->id,
            'title' => 'Resume review',
            'category' => 'Career',
            'description' => 'Review resumes',
            'status' => 'Active',
            'visible_in_dashboard' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.services.bulk'), [
                'service_ids' => [$serviceA->id, $serviceB->id],
                'status' => 'Inactive',
                'inactive_reason' => 'Policy review required',
                'visible_in_dashboard' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('services', [
            'id' => $serviceA->id,
            'status' => 'Inactive',
            'inactive_reason' => 'Policy review required',
            'visible_in_dashboard' => false,
        ]);

        $this->assertDatabaseHas('services', [
            'id' => $serviceB->id,
            'status' => 'Inactive',
            'inactive_reason' => 'Policy review required',
            'visible_in_dashboard' => false,
        ]);
    }

    public function test_admin_can_delete_a_service(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $service = Service::create([
            'user_id' => $admin->id,
            'title' => 'Photography help',
            'category' => 'Creative',
            'description' => 'Portfolio feedback',
            'status' => 'Active',
            'visible_in_dashboard' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.services.destroy', $service))
            ->assertRedirect();

        $this->assertDatabaseMissing('services', ['id' => $service->id]);
    }
}
