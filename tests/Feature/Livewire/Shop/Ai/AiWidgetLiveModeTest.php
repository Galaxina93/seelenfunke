<?php

namespace Tests\Feature\Livewire\Shop\Ai;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\System\SystemUser;

class AiWidgetLiveModeTest extends TestCase
{
    /**
     * Test that the live-credentials endpoint is protected by authentication.
     */
    public function test_live_credentials_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/ai/live-credentials');

        $response->assertStatus(401);
    }

    /**
     * Test that the live-credentials endpoint returns the API key when authenticated.
     */
    public function test_live_credentials_endpoint_returns_data_for_authenticated_users(): void
    {
        $user = new SystemUser(['id' => 1]);
        $user->id = 1;

        $response = $this->actingAs($user, 'web')->getJson('/api/ai/live-credentials');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'token',
                     'ws_url',
                     'system_instruction'
                 ]);
    }

    /**
     * Test that the live-credentials endpoint returns data when authenticated as an admin.
     */
    public function test_live_credentials_endpoint_returns_data_for_authenticated_admins(): void
    {
        $admin = new \App\Models\Admin\Admin();
        $admin->id = '019e4aa6-3d82-7120-953e-d62a0843db38';

        $response = $this->actingAs($admin, 'admin')->getJson('/api/ai/live-credentials');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'token',
                     'ws_url',
                     'system_instruction'
                 ]);
    }
}
