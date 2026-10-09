<?php

namespace Tests\Feature;

use Tests\TestCase;

/** B-M1 (TASK-005): the mobile app reads versions, store links and feature flags from the server env. */
class AppConfigTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function returns_versions_store_links_and_flags_without_auth(): void
    {
        config([
            'mobile.ios.min_supported_version' => '1.2.0',
            'mobile.ios.latest_version' => '1.4.1',
            'mobile.ios.store_url' => 'https://apps.apple.com/app/id1',
            'mobile.android.store_url' => '',
            'mobile.features.ai_chat' => true,
            'mobile.features.favorites' => false,
        ]);

        $response = $this->getJson('/api/v1/app-config')->assertOk()
            ->assertJsonPath('data.ios.min_supported_version', '1.2.0')
            ->assertJsonPath('data.ios.latest_version', '1.4.1')
            ->assertJsonPath('data.ios.store_url', 'https://apps.apple.com/app/id1')
            ->assertJsonPath('data.android.store_url', null)
            ->assertJsonPath('data.features.ai_chat', true)
            ->assertJsonPath('data.features.favorites', false)
            ->assertJsonPath('data.api_version', 'v1');

        $this->assertStringContainsString('max-age=300', (string) $response->headers->get('Cache-Control'));
    }
}
