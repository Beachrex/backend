<?php

namespace Tests\Feature\Payment;

use Tests\TestCase;

/** AI-3: public package list with the prices the checkout charges (no Stripe ids). */
class ProductsEndpointTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function lists_the_itinerary_packages_shortest_first_without_auth(): void
    {
        config(['ai.stripe.products.itinerary_7day.price_id' => 'price_secret_7']);

        $response = $this->getJson('/api/v1/payments/products')->assertOk();

        $this->assertSame([3, 7, 14], array_column($response->json('data'), 'days'));
        $this->assertSame('itinerary_7day', $response->json('data.1.product_type'));
        $this->assertSame((float) config('ai.stripe.products.itinerary_7day.amount'), (float) $response->json('data.1.amount'));
        $this->assertStringNotContainsString('price_secret_7', $response->getContent());
    }
}
