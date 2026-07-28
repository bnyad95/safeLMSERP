<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebInstallerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set('installer.enabled', true);
        config()->set('installer.token', 'test-installation-code');
    }

    public function test_browser_installer_creates_super_administrator_and_disables_itself(): void
    {
        $this->get(route('installer.show'))
            ->assertOk()
            ->assertSee('Install SafeLMS ERP');

        $this->post(route('installer.install'), [
            'installation_code' => 'test-installation-code',
            'name' => 'Production Owner',
            'email' => 'owner@example.com',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'owner@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasRole('super_administrator'));
        Storage::disk('local')->assertExists('installed');

        $this->get(route('installer.show'))->assertNotFound();
    }

    public function test_browser_installer_rejects_an_invalid_installation_code(): void
    {
        $this->post(route('installer.install'), [
            'installation_code' => 'wrong-code',
            'name' => 'Production Owner',
            'email' => 'owner@example.com',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
        ])
            ->assertSessionHasErrors('installation_code');

        $this->assertDatabaseMissing('users', ['email' => 'owner@example.com']);
        Storage::disk('local')->assertMissing('installed');
    }
}
