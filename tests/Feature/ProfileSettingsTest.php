<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_shows_password_settings_section(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee(__('profile.password_title'));
        $response->assertSee(__('profile.save_password'));
        $response->assertSee('data-password-toggle', false);
    }

    public function test_user_can_change_password_from_profile_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch(route('profile.password.update'), [
            'current_password' => 'password',
            'password' => 'UpdatedPass123!',
            'password_confirmation' => 'UpdatedPass123!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', __('profile.password_updated'));

        $this->assertTrue(Hash::check('UpdatedPass123!', (string) $user->fresh()?->password));
    }

    public function test_profile_password_update_fails_with_wrong_current_password(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'UpdatedPass123!',
                'password_confirmation' => 'UpdatedPass123!',
            ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHasErrors([
            'current_password' => __('profile.errors.current_password_invalid'),
        ]);

        $this->assertTrue(Hash::check('password', (string) $user->fresh()?->password));
    }
}
