<?php

namespace Tests\Feature;

use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(PlatformSeeder::class);
    }

    public function test_manifest_is_installable_and_has_local_icons(): void
    {
        $manifest = json_decode(
            file_get_contents(public_path('manifest.webmanifest')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('ru', $manifest['lang']);
        $this->assertNotEmpty($manifest['shortcuts']);

        $icons = collect($manifest['icons']);
        $this->assertTrue($icons->contains(fn (array $icon) => $icon['sizes'] === '192x192'));
        $this->assertTrue($icons->contains(fn (array $icon) => $icon['sizes'] === '512x512'));
        $this->assertTrue($icons->contains(fn (array $icon) => $icon['purpose'] === 'maskable'));

        foreach ($icons as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }

        $this->assertFileExists(public_path('apple-touch-icon.png'));
    }

    public function test_public_pages_expose_install_and_offline_assets(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('/pwa.js', false)
            ->assertSee('/pwa.css', false)
            ->assertSee('apple-touch-icon', false);

        $this->get('/places')
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('/pwa.js', false);

        $worker = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString('/offline.html', $worker);
        $this->assertStringContainsString('SKIP_WAITING', $worker);
        $this->assertStringContainsString('/platform/data', $worker);
        $this->assertStringContainsString('PRIVATE_PAGE', $worker);
        $this->assertFileExists(public_path('offline.html'));
        $this->assertStringContainsString('Нет подключения', file_get_contents(public_path('offline.html')));
    }
}
