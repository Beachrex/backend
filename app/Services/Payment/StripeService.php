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
        string $cancelUrl
    ): Session {
        $product = config("ai.stripe.products.{$productType}");

        if (!$product || !$product['price_id']) {
            throw new \Exception("Invalid product type or missing price ID: {$productType}");
        }

        return Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $product['price_id'],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $user->id,
            'metadata' => [
                'product_type' => $productType,
                'user_id' => $user->id,
                'credits' => $product['credits'],
            ],
        ]);
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

