<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_password_reset_link_page(): void
    {
        $target = User::factory()->create([
            'email' => 'reset-target@example.test',
            'active' => true,
        ]);

        $actor = User::factory()->create([
            'email' => 'already-logged@example.test',
            'active' => true,
        ]);

        $token = Password::broker()->createToken($target);

        $response = $this->actingAs($actor)
            ->get(route('password.reset', [
                'token' => $token,
                'email' => $target->email,
            ], absolute: false));

        $response->assertOk();
    }

    public function test_authenticated_user_can_submit_valid_password_reset_token(): void
    {
        $target = User::factory()->create([
            'email' => 'reset-submit-target@example.test',
            'password' => Hash::make('old-password'),
            'active' => true,
        ]);

        $actor = User::factory()->create([
            'email' => 'session-holder@example.test',
            'active' => true,
        ]);

        $token = Password::broker()->createToken($target);

        $response = $this->actingAs($actor)
            ->post(route('password.store', absolute: false), [
                'token' => $token,
                'email' => $target->email,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]);

        $response->assertRedirect(route('login', absolute: false));

        $target->refresh();
        $this->assertTrue(Hash::check('new-password-123', (string) $target->password));
    }
}

