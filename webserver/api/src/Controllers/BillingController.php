<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Utils\Auth;
use App\Utils\Logger;
use Dotenv\Dotenv;
use PDO;

/**
 * Abonnements payants des formules "Créateur" et "Studio" (voir /tarifs) :
 * Checkout Session pour souscrire, Billing Portal pour gérer/résilier, et le
 * webhook qui tient la table `subscriptions` à jour — elle est la seule
 * source de vérité lue par le reste de l'app (GenerationController pour le
 * quota, front pour l'affichage), jamais interrogée en direct chez Stripe.
 */
class BillingController
{
    private const PLAN_PRICE_ENV = [
        'createur' => 'STRIPE_PRICE_CREATEUR',
        'studio' => 'STRIPE_PRICE_STUDIO',
    ];

    private PDO $db;
    private ?StripeService $stripe;
    private string $frontendUrl;

    public function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        $this->db = new PDO(
            'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'] . ';charset=utf8mb4',
            $_ENV['DB_USER'],
            $_ENV['DB_PASS']
        );
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $secretKey = $_ENV['STRIPE_SECRET_KEY'] ?? '';
        $this->stripe = $secretKey !== ''
            ? new StripeService($secretKey, $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '')
            : null;

        $this->frontendUrl = rtrim($_ENV['FRONTEND_URL'] ?? 'http://localhost:3000', '/');
    }

    /** Démarre un abonnement : renvoie l'URL de la Checkout Session Stripe à suivre. */
    public function checkout()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Méthode non autorisée']);
            return;
        }

        if (!$this->requireStripeConfigured()) {
            return;
        }

        $user = Auth::requireUser($this->db);

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $plan = is_string($input['plan'] ?? null) ? $input['plan'] : '';
        if (!isset(self::PLAN_PRICE_ENV[$plan])) {
            http_response_code(400);
            echo json_encode(['error' => 'Formule inconnue.']);
            return;
        }

        $priceId = $_ENV[self::PLAN_PRICE_ENV[$plan]] ?? '';
        if ($priceId === '') {
            http_response_code(503);
            echo json_encode(['error' => 'Cette formule n\'est pas encore disponible à l\'achat.']);
            return;
        }

        try {
            $customerId = $this->stripe->findOrCreateCustomer($user['email'], (int) $user['id']);
            $this->saveCustomerId((int) $user['id'], $customerId);

            $url = $this->stripe->createCheckoutSession(
                $customerId,
                $priceId,
                (int) $user['id'],
                $this->frontendUrl . '/dashboard?checkout=success',
                $this->frontendUrl . '/tarifs?checkout=cancel'
            );

            echo json_encode(['url' => $url]);
        } catch (\Throwable $e) {
            Logger::get()->error($e->getMessage(), ['controller' => 'billing', 'action' => 'checkout', 'exception' => get_class($e)]);
            \Sentry\captureException($e);
            http_response_code(502);
            echo json_encode(['error' => 'Impossible de contacter Stripe pour le moment.']);
        }
    }

    /** Gestion de l'abonnement en cours (moyen de paiement, résiliation, factures) via le portail Stripe. */
    public function portal()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Méthode non autorisée']);
            return;
        }

        if (!$this->requireStripeConfigured()) {
            return;
        }

        $user = Auth::requireUser($this->db);

        $stmt = $this->db->prepare('SELECT stripe_customer_id FROM subscriptions WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $user['id']]);
        $customerId = $stmt->fetchColumn();

        if (!$customerId) {
            http_response_code(400);
            echo json_encode(['error' => 'Aucun abonnement Stripe associé à ce compte.']);
            return;
        }

        try {
            $url = $this->stripe->createPortalSession($customerId, $this->frontendUrl . '/dashboard');
            echo json_encode(['url' => $url]);
        } catch (\Throwable $e) {
            Logger::get()->error($e->getMessage(), ['controller' => 'billing', 'action' => 'portal', 'exception' => get_class($e)]);
            \Sentry\captureException($e);
            http_response_code(502);
            echo json_encode(['error' => 'Impossible de contacter Stripe pour le moment.']);
        }
    }

    /** Formule courante de l'utilisateur connecté, pour l'affichage dans son espace. */
    public function status()
    {
        header('Content-Type: application/json');
        $user = Auth::requireUser($this->db);

        $stmt = $this->db->prepare(
            'SELECT plan, status, current_period_end, cancel_at_period_end, stripe_customer_id IS NOT NULL AS has_stripe_customer
             FROM subscriptions WHERE user_id = :user_id'
        );
        $stmt->execute([':user_id' => $user['id']]);
        $subscription = $stmt->fetch();

        echo json_encode(['subscription' => $subscription ?: [
            'plan' => 'decouverte',
            'status' => 'active',
            'current_period_end' => null,
            'cancel_at_period_end' => false,
            'has_stripe_customer' => false,
        ]]);
    }

    /** Point d'entrée appelé par Stripe (pas par le front) : tient `subscriptions` à jour. */
    public function webhook()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $this->stripe === null) {
            http_response_code(405);
            echo json_encode(['error' => 'Méthode non autorisée']);
            return;
        }

        $payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        try {
            $event = $this->stripe->constructWebhookEvent($payload, $signature);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Signature webhook invalide.']);
            return;
        }

        $stmt = $this->db->prepare('SELECT 1 FROM stripe_events WHERE event_id = :id');
        $stmt->execute([':id' => $event->id]);
        if ($stmt->fetchColumn()) {
            // Déjà traité lors d'une précédente tentative (Stripe retry) : no-op.
            echo json_encode(['received' => true]);
            return;
        }

        try {
            $this->applyEvent($event);
            $this->db->prepare('INSERT INTO stripe_events (event_id, type) VALUES (:id, :type)')
                ->execute([':id' => $event->id, ':type' => $event->type]);
            echo json_encode(['received' => true]);
        } catch (\Throwable $e) {
            Logger::get()->error($e->getMessage(), [
                'controller' => 'billing',
                'action' => 'webhook',
                'event_type' => $event->type,
                'exception' => get_class($e),
            ]);
            \Sentry\captureException($e);
            // 500 : Stripe retentera cet évènement plus tard.
            http_response_code(500);
            echo json_encode(['error' => 'Traitement de l\'évènement échoué.']);
        }
    }

    private function applyEvent(\Stripe\Event $event): void
    {
        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;
                if (!$session->subscription) {
                    break; // mode=payment ou autre, non utilisé ici
                }
                $userId = (int) ($session->client_reference_id ?? 0);
                $subscription = $this->stripe->retrieveSubscription($session->subscription);
                $this->upsertFromStripeSubscription($userId, $session->customer, $subscription);
                break;

            case 'customer.subscription.updated':
            case 'customer.subscription.created':
                $subscription = $event->data->object;
                $userId = (int) ($subscription->metadata->user_id ?? 0);
                $this->upsertFromStripeSubscription($userId, $subscription->customer, $subscription);
                break;

            case 'customer.subscription.deleted':
                $subscription = $event->data->object;
                $this->db->prepare(
                    "UPDATE subscriptions
                     SET plan = 'decouverte', status = 'canceled', stripe_subscription_id = NULL,
                         stripe_price_id = NULL, cancel_at_period_end = 0
                     WHERE stripe_subscription_id = :sub_id"
                )->execute([':sub_id' => $subscription->id]);
                break;

            case 'invoice.payment_failed':
                $invoice = $event->data->object;
                $this->db->prepare(
                    "UPDATE subscriptions SET status = 'past_due' WHERE stripe_customer_id = :customer_id"
                )->execute([':customer_id' => $invoice->customer]);
                break;

            default:
                // Évènement non pertinent pour ce modèle de facturation : ignoré.
                break;
        }
    }

    private function upsertFromStripeSubscription(int $userId, string $customerId, \Stripe\Subscription $subscription): void
    {
        $priceId = $subscription->items->data[0]->price->id ?? '';
        $plan = $this->planForPrice($priceId);
        $periodEnd = date('Y-m-d H:i:s', $subscription->current_period_end);

        $params = [
            ':customer_id' => $customerId,
            ':subscription_id' => $subscription->id,
            ':price_id' => $priceId,
            ':plan' => $plan,
            ':status' => $subscription->status,
            ':period_end' => $periodEnd,
            ':cancel_at_period_end' => $subscription->cancel_at_period_end ? 1 : 0,
        ];

        if ($userId > 0) {
            $params[':user_id'] = $userId;
            $this->db->prepare(
                'INSERT INTO subscriptions (user_id, plan, status, stripe_customer_id, stripe_subscription_id, stripe_price_id, current_period_end, cancel_at_period_end)
                 VALUES (:user_id, :plan, :status, :customer_id, :subscription_id, :price_id, :period_end, :cancel_at_period_end)
                 ON DUPLICATE KEY UPDATE
                    plan = VALUES(plan), status = VALUES(status), stripe_customer_id = VALUES(stripe_customer_id),
                    stripe_subscription_id = VALUES(stripe_subscription_id), stripe_price_id = VALUES(stripe_price_id),
                    current_period_end = VALUES(current_period_end), cancel_at_period_end = VALUES(cancel_at_period_end)'
            )->execute($params);
            return;
        }

        // Repli si le webhook n'a pas l'id utilisateur en métadonnée (ex : mise
        // à jour d'un abonnement existant hors flux checkout) : on retrouve la
        // ligne par le client Stripe, déjà associé lors du premier paiement.
        $this->db->prepare(
            'UPDATE subscriptions
             SET plan = :plan, status = :status, stripe_subscription_id = :subscription_id,
                 stripe_price_id = :price_id, current_period_end = :period_end,
                 cancel_at_period_end = :cancel_at_period_end
             WHERE stripe_customer_id = :customer_id'
        )->execute($params);
    }

    private function planForPrice(string $priceId): string
    {
        foreach (self::PLAN_PRICE_ENV as $plan => $envKey) {
            if ($priceId !== '' && $priceId === ($_ENV[$envKey] ?? null)) {
                return $plan;
            }
        }

        return 'decouverte';
    }

    private function saveCustomerId(int $userId, string $customerId): void
    {
        $this->db->prepare(
            'INSERT INTO subscriptions (user_id, plan, status, stripe_customer_id) VALUES (:user_id, "decouverte", "active", :customer_id)
             ON DUPLICATE KEY UPDATE stripe_customer_id = VALUES(stripe_customer_id)'
        )->execute([':user_id' => $userId, ':customer_id' => $customerId]);
    }

    private function requireStripeConfigured(): bool
    {
        if ($this->stripe !== null) {
            return true;
        }

        http_response_code(503);
        echo json_encode(['error' => 'Le paiement n\'est pas encore configuré.']);
        return false;
    }
}
