<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Mobile report 2026-10-09: the OpenAPI `search` response listed only the URL slugs for islands, regions and
 * countries, so generated clients had no `id`, `name`, `slug`, `main_image` or `country_name`.
 */
class SearchOpenApiTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function every_search_group_documents_the_fields_the_api_returns(): void
    {
        $docs = json_decode(file_get_contents(storage_path('api-docs/api-docs.json')), true);
        $groups = $docs['paths']['/api/v1/search']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['properties'];
        $fields = fn (string $group) => array_keys($groups[$group]['items']['properties']);

        foreach (['islands', 'regions', 'countries'] as $group) {
            foreach (['id', 'code', 'name', 'slug', 'main_image', 'type', 'continent_slug'] as $field) {
                $this->assertContains($field, $fields($group), "{$group}.{$field}");
            }
        }
        $this->assertContains('country_name', $fields('islands'));
        $this->assertContains('region_name', $fields('islands'));
        $this->assertContains('country_name', $fields('regions'));
        $this->assertContains('continent_name', $fields('countries'));
    }
}
