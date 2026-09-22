<?php

namespace App\Services;

use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Fine couche au-dessus du SDK stripe-php : centralise la création du client
 * et les deux opérations dont BillingController a besoin (Checkout Session,
 * Billing Portal Session), plus la vérification de signature des webhooks.
 */
class StripeService
{
    private StripeClient $client;

    public function __construct(private string $secretKey, private string $webhookSecret)
    {
        $this->client = new StripeClient($this->secretKey);
    }

    public function isConfigured(): bool
    {
        return $this->secretKey !== '';
    }

    /** Un seul client Stripe par utilisateur : recherché par email, créé sinon. */
    public function findOrCreateCustomer(string $email, int $userId): string
    {
        $results = $this->client->customers->search([
            'query' => 'email:\'' . str_replace('\'', '', $email) . '\'',
            'limit' => 1,
        ]);

        if (count($results->data) > 0) {
            return $results->data[0]->id;
        }

        $customer = $this->client->customers->create([
            'email' => $email,
            'metadata' => ['user_id' => (string) $userId],
        ]);

        return $customer->id;
    }

    public function createCheckoutSession(string $customerId, string $priceId, int $userId, string $successUrl, string $cancelUrl): string
    {
        $session = $this->client->checkout->sessions->create([
            'customer' => $customerId,
            'mode' => 'subscription',
            'line_items' => [['price' => $priceId, 'quantity' => 1]],
            'client_reference_id' => (string) $userId,
            'subscription_data' => ['metadata' => ['user_id' => (string) $userId]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);

        return $session->url;
    }

    public function createPortalSession(string $customerId, string $returnUrl): string
    {
        $session = $this->client->billingPortal->sessions->create([
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);

        return $session->url;
    }

    public function retrieveSubscription(string $subscriptionId): \Stripe\Subscription
    {
        return $this->client->subscriptions->retrieve($subscriptionId);
    }

    /** @throws \UnexpectedValueException|\Stripe\Exception\SignatureVerificationException */
    public function constructWebhookEvent(string $payload, string $signatureHeader): \Stripe\Event
    {
        return Webhook::constructEvent($payload, $signatureHeader, $this->webhookSecret);
    }
}
