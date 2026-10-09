<?php

namespace App\Services\Payment;

use App\Models\User;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('ai.stripe.secret_key'));
    }

    /**
     * Create a Stripe Checkout session for itinerary purchase.
     *
     * @param User $user
     * @param string $productType (e.g., 'itinerary_7day')
     * @param string $successUrl
     * @param string $cancelUrl
     * @return Session
     * @throws \Exception
     */
    public function createCheckoutSession(
        User $user,
        string $productType,
        string $successUrl,
        string $cancelUrl,
        array $consent = []
    ): Session {
        return Session::create($this->checkoutSessionParams($user, $productType, $successUrl, $cancelUrl, $consent));
    }

    /**
     * Stripe Checkout parameters (AI-4, owner 2026-10-09). The buyer's consent to the terms and to immediate
     * delivery (losing the 14-day withdrawal right once a plan is created) is stored on the session and the
     * payment as evidence; the webhook keeps the whole session on the purchase row. Stripe Tax and Stripe
     * invoices are switched on per environment once the company's Stripe account is set up.
     */
    public function checkoutSessionParams(User $user, string $productType, string $successUrl, string $cancelUrl, array $consent = []): array
    {
        $product = config("ai.stripe.products.{$productType}");

        if (!$product || !$product['price_id']) {
            throw new \Exception("Invalid product type or missing price ID: {$productType}");
        }

        $metadata = [
            'product_type' => $productType,
            'user_id' => $user->id,
            'credits' => $product['credits'],
            'terms_version' => (string) config('ai.stripe.terms_version'),
            'terms_accepted_at' => $consent['accepted_at'] ?? null,
            'withdrawal_waiver' => ! empty($consent['waive_withdrawal']) ? 'yes' : 'no',
            'locale' => $consent['locale'] ?? null,
        ];

        $params = [
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $product['price_id'],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $user->id,
            'customer_email' => $user->email,
            'locale' => 'auto',
            'metadata' => $metadata,
            'payment_intent_data' => ['metadata' => $metadata],
        ];

        if (config('ai.stripe.tax_enabled')) {
            // VAT by the buyer's country (Stripe Tax); companies can enter their VAT number.
            $params['automatic_tax'] = ['enabled' => true];
            $params['billing_address_collection'] = 'required';
            $params['tax_id_collection'] = ['enabled' => true];
        }
        if (config('ai.stripe.invoices_enabled')) {
            // Stripe issues and e-mails an invoice for the payment (company details come from the Stripe account).
            $params['invoice_creation'] = ['enabled' => true];
        }

        return $params;
    }

    /**
     * Verify Stripe webhook signature.
     *
     * @param string $payload
     * @param string $signature
     * @return \Stripe\Event
     * @throws SignatureVerificationException
     * @throws UnexpectedValueException
     */
    public function verifyWebhookSignature(string $payload, string $signature): \Stripe\Event
    {
        $webhookSecret = config('ai.stripe.webhook_secret');

        if (!$webhookSecret) {
            throw new \Exception('Stripe webhook secret not configured');
        }

        return Webhook::constructEvent($payload, $signature, $webhookSecret);
    }

    /**
     * Get product configuration by product type.
     *
     * @param string $productType
     * @return array|null
     */
    public function getProductConfig(string $productType): ?array
    {
        return config("ai.stripe.products.{$productType}");
    }

    /**
     * Get all available products.
     *
     * @return array
     */
    public function getAvailableProducts(): array
    {
        return config('ai.stripe.products', []);
    }

    /**
     * Validate product type.
     *
     * @param string $productType
     * @return bool
     */
    public function isValidProductType(string $productType): bool
    {
        return config("ai.stripe.products.{$productType}") !== null;
    }
}

