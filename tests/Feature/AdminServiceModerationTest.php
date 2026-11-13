<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminServiceModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_separate_user_and_service_management_pages(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['full_name' => 'Campus Test User']);
        $service = Service::create([
            'user_id' => $admin->id,
            'title' => 'Math tutoring',
            'category' => 'Academic',
            'description' => 'Help with calculus',
            'status' => 'Active',
            'visible_in_dashboard' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.index'))
            ->assertOk()
            ->assertSee(route('admin.users.index'))
            ->assertSee(route('admin.services.index'))
            ->assertSee('Reports &amp; overview', false)
            ->assertSee('System activity summary')
            ->assertSee('class="workspace-sidebar"', false)
            ->assertDontSee('Campus Test User')
            ->assertDontSee('Math tutoring');

        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Campus Test User')
            ->assertDontSee('Math tutoring');

        $this->get(route('admin.services.index'))
            ->assertOk()
            ->assertSee('Math tutoring')
            ->assertDontSee('Campus Test User');
    }

    public function test_admin_can_bulk_change_user_roles_and_delete_selected_users(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $promotedUser = User::factory()->create();
        $deletedUser = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.bulk'), [
                'user_ids' => [$promotedUser->id],
                'action' => 'promote',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $promotedUser->id,
            'is_admin' => true,
        ]);

        $this->patch(route('admin.users.bulk'), [
            'user_ids' => [$deletedUser->id],
            'action' => 'delete',
        ])->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $deletedUser->id]);
    }

    public function test_admin_can_toggle_another_users_role_individually(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.role', $user))
            ->assertRedirect()
            ->assertSessionHas('status', 'User role updated.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_admin' => true,
        ]);

        $this->patch(route('admin.users.role', $user))
            ->assertRedirect()
            ->assertSessionHas('status', 'User role updated.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_admin' => false,
        ]);
    }

    public function test_bulk_user_actions_cannot_change_or_delete_the_current_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.users.bulk'), [
                'user_ids' => [$admin->id],
                'action' => 'demote',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'is_admin' => true,
        ]);
    }

    public function test_user_manager_offers_multi_select_without_selecting_current_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('data-select-all-users', false)
            ->assertSee('name="user_ids[]" value="'.$user->id.'"', false)
            ->assertDontSee('name="user_ids[]" value="'.$admin->id.'"', false);
    }

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
                'hide_from_marketplace' => true,
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

        foreach ([$serviceA, $serviceB] as $service) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $admin->id,
                'message' => 'Your service "'.$service->title.'" was deactivated by an administrator. Reason: Policy review required',
                'link' => route('profile.edit', ['tab' => 'notifications']),
                'is_read' => false,
            ]);
        }
    }

    public function test_admin_service_deactivation_notifies_owner_once_with_the_moderation_reason(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $service = Service::create([
            'user_id' => $owner->id,
            'title' => 'Portfolio feedback',
            'category' => 'Creative',
            'description' => 'Portfolio feedback for campus students',
            'status' => 'Active',
            'visible_in_dashboard' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.services.toggle', $service), [
                'status' => 'Inactive',
                'inactive_reason' => 'Please update the listing details.',
                'hide_from_marketplace' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'message' => 'Your service "Portfolio feedback" was deactivated by an administrator. Reason: Please update the listing details.',
            'link' => route('profile.edit', ['tab' => 'notifications']),
            'is_read' => false,
        ]);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'status' => 'Inactive',
            'visible_in_dashboard' => false,
        ]);

        $this->actingAs($owner)
            ->get(route('profile.edit', ['tab' => 'notifications']))
            ->assertOk()
            ->assertSee('Your service "Portfolio feedback" was deactivated by an administrator. Reason: Please update the listing details.');

        $this->actingAs($admin);
        $this->patch(route('admin.services.toggle', $service), [
            'status' => 'Inactive',
            'inactive_reason' => 'Please update the listing details.',
            'hide_from_marketplace' => true,
        ])->assertRedirect();

        $this->assertDatabaseCount('notifications', 1);
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

    public function test_admin_can_delete_another_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
