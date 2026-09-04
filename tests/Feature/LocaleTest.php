<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_switch_locale_and_it_reflects_on_the_next_request(): void
    {
        $this->post(route('locale.update'), ['locale' => 'ar'])
            ->assertRedirect();

        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('البريد الإلكتروني');
    }

    public function test_authenticated_user_locale_preference_is_saved_to_their_profile(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $this->actingAs($user)
            ->post(route('locale.update'), ['locale' => 'ckb'])
            ->assertRedirect();

        $this->assertSame('ckb', $user->fresh()->locale);
    }

    public function test_authenticated_user_saved_locale_is_honored_without_a_session_value(): void
    {
        $user = User::factory()->create(['locale' => 'ar']);

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('dir="rtl"', false);
    }

    public function test_invalid_locale_is_rejected(): void
    {
        $this->post(route('locale.update'), ['locale' => 'zz'])
            ->assertSessionHasErrors('locale');
    }

    public function test_default_locale_renders_left_to_right(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('dir="ltr"', false);
    }
}
