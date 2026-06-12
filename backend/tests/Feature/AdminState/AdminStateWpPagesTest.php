<?php

namespace Tests\Feature\AdminState;

use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class AdminStateWpPagesTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_admin_state_includes_wp_pages_from_settings(): void
    {
        app(SettingsStore::class)->set('portal_pages', [
            ['id' => 42, 'title' => 'Subscription Page'],
        ]);
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/state?tab=site_settings')
            ->assertOk()
            ->assertJsonPath('wpPages.0.id', 42)
            ->assertJsonPath('wpPages.0.title', 'Subscription Page');
    }

    public function test_reseller_state_has_empty_wp_pages(): void
    {
        app(SettingsStore::class)->set('portal_pages', [
            ['id' => 42, 'title' => 'Subscription Page'],
        ]);
        $this->actingAsReseller();

        $this->getJson('/api/v1/admin/state?tab=dashboard')
            ->assertOk()
            ->assertJsonPath('wpPages', []);
    }
}
