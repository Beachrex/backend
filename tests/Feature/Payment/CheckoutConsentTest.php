<?php

namespace Tests\Feature\Payment;

use App\Models\Status;
use App\Models\User;
use App\Services\Payment\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AI-4 (owner 2026-10-09: "to pripravi in dodaj ti"): no checkout without accepting the terms of sale and
 * the immediate-delivery / withdrawal waiver; the consent is stored on the Stripe session and payment.
 * Stripe Tax and Stripe invoices follow the per-environment switches.
 */
class CheckoutConsentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Status::insert([['id' => 1, 'name' => 'active'], ['id' => 2, 'name' => 'inactive']]);
        config([
            'ai.payments_enabled' => true,
            'ai.stripe.enabled' => true,
            'ai.stripe.products.itinerary_7day.price_id' => 'price_7',
            'ai.stripe.terms_version' => '2026-10-09',
        ]);
    }

    public static function missingConsent(): array
    {
        return [
            'nothing' => [[]],
            'terms only' => [['accept_terms' => true]],
            'waiver only' => [['waive_withdrawal' => true]],
            'terms refused' => [['accept_terms' => false, 'waive_withdrawal' => true]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('missingConsent')]
    public function checkout_needs_both_consents(array $consent): void
    {
        $stripe = \Mockery::mock(StripeService::class);
        $stripe->shouldNotReceive('createCheckoutSession');
        $this->app->instance(StripeService::class, $stripe);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/payments/stripe/checkout', ['product_type' => 'itinerary_7day'] + $consent)
            ->assertStatus(422)->assertJsonPath('error', 'consent_required');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_consent_is_stored_on_the_session_and_the_payment(): void
    {
        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $params = app(StripeService::class)->checkoutSessionParams($user, 'itinerary_7day', 's', 'c',
            ['accepted_at' => '2026-10-09T15:00:00+00:00', 'waive_withdrawal' => true, 'locale' => 'si']);

        foreach ([$params['metadata'], $params['payment_intent_data']['metadata']] as $meta) {
            $this->assertSame('2026-10-09', $meta['terms_version']);
            $this->assertSame('2026-10-09T15:00:00+00:00', $meta['terms_accepted_at']);
            $this->assertSame('yes', $meta['withdrawal_waiver']);
        }
        $this->assertSame('buyer@example.com', $params['customer_email']);
        $this->assertArrayNotHasKey('automatic_tax', $params);
        $this->assertArrayNotHasKey('invoice_creation', $params);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function stripe_tax_and_invoices_follow_the_environment_switches(): void
    {
        config(['ai.stripe.tax_enabled' => true, 'ai.stripe.invoices_enabled' => true]);

        $params = app(StripeService::class)->checkoutSessionParams(User::factory()->create(), 'itinerary_7day', 's', 'c');

        $this->assertSame(['enabled' => true], $params['automatic_tax']);
        $this->assertSame('required', $params['billing_address_collection']);
        $this->assertSame(['enabled' => true], $params['tax_id_collection']);
        $this->assertSame(['enabled' => true], $params['invoice_creation']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_terms_of_sale_in_every_language_cover_the_seller_vat_withdrawal_and_complaints(): void
    {
        $files = glob(base_path('database/content/static-pages/*.json'));
        $this->assertCount(19, $files);
        foreach ($files as $file) {
            $terms = json_decode(file_get_contents($file), true)['terms-of-use']['content'];
            $section = substr($terms, strpos($terms, '<h2>5.'), strpos($terms, '<h2>6.') - strpos($terms, '<h2>5.'));
            $lang = basename($file);
            $this->assertStringContainsString('MOJA PLAŽA, d.o.o., Prušnikova ulica 22, 2000 Maribor', $section, $lang);
            $this->assertStringContainsString('2011/83/', $section, $lang); // withdrawal waiver for digital content
            $this->assertStringContainsString('14', $section, $lang);
            $this->assertSame(3, substr_count($section, 'mailto:info@beachrex.com'), $lang);
            $this->assertSame(7, substr_count($section, '<p>') + 1, $lang); // heading + 6 paragraphs
        }
    }
}
