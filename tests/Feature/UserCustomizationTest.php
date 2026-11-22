<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserFeedback;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserCustomizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_can_save_profile_images_and_appearance_preferences(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->withoutMiddleware(PreventRequestForgery::class)
            ->actingAs($user)
            ->put(route('profile.update'), [
                'campus_address' => 'North Campus',
                'grade_level' => '3rd year',
                'course_major' => 'Computer Science',
                'bio' => 'Student developer and peer tutor.',
                'profile_photo' => $this->fakePng('profile.png'),
                'cover_photo' => $this->fakePng('cover.png'),
            ])
            ->assertSessionHasNoErrors();

        $profile = $user->fresh()->profile;
        $this->assertNotNull($profile?->profile_photo_path);
        $this->assertNotNull($profile?->cover_photo_path);
        Storage::disk('public')->assertExists($profile->profile_photo_path);
        Storage::disk('public')->assertExists($profile->cover_photo_path);

        $this->actingAs($user)
            ->get(route('profile.show', $user))
            ->assertOk()
            ->assertSee('data-profile-photo-preview', false)
            ->assertSee('Update profile picture')
            ->assertSee('Update cover photo');

        $this->actingAs(User::factory()->create())
            ->get(route('profile.show', $user))
            ->assertOk()
            ->assertSee('data-profile-photo-preview', false)
            ->assertDontSee('Update profile picture')
            ->assertDontSee('Update cover photo');

        $this->actingAs($user)
            ->put(route('profile.preferences'), [
                'design_theme' => 'glass',
                'design_palette' => 'ocean',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'design_theme' => 'glass',
            'design_palette' => 'ocean',
        ]);
    }

    public function test_feedback_reaches_admin_inbox_and_admin_can_resolve_it(): void
    {
        $member = User::factory()->create();
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();

        $this->withoutMiddleware(PreventRequestForgery::class)
            ->actingAs($member)
            ->post(route('profile.feedback.store'), [
                'recipient' => 'admin',
                'subject' => 'Marketplace suggestion',
                'message' => 'Please add a way to filter by availability.',
            ])
            ->assertRedirect(route('profile.edit', ['tab' => 'settings']));

        $feedback = UserFeedback::query()->firstOrFail();
        $this->assertSame($member->id, $feedback->user_id);
        $this->assertSame('New', $feedback->status);

        $this->actingAs($admin)
            ->get(route('admin.feedback.index'))
            ->assertOk()
            ->assertSee('Marketplace suggestion')
            ->assertSee($member->email);

        $this->actingAs($admin)
            ->patch(route('admin.feedback.update', $feedback), ['status' => 'Resolved'])
            ->assertRedirect();

        $this->assertDatabaseHas('user_feedback', [
            'id' => $feedback->id,
            'status' => 'Resolved',
        ]);
        $this->assertNotNull($feedback->fresh()->read_at);

        $this->actingAs($member)
            ->get(route('admin.feedback.index'))
            ->assertForbidden();
    }

    private function fakePng(string $name): UploadedFile
    {
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lTsAAAAASUVORK5CYII=', true);

        $this->assertIsString($image);

        return UploadedFile::fake()->createWithContent($name, $image);
    }
}
