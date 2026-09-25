<?php

namespace App\Controllers;

use App\Utils\Auth;
use App\Utils\Logger;
use Dotenv\Dotenv;
use PDO;

/**
 * Droits RGPD exercables directement depuis l'espace compte : accès /
 * portabilité (export) et effacement (suppression de compte).
 *
 * La suppression anonymise le compte plutôt que de le supprimer : les
 * factures déjà émises doivent être conservées 10 ans (art. L123-22 du code
 * de commerce), ce qui prime légalement sur le droit à l'effacement pour ces
 * données précises (RGPD art. 17.3.b). `invoices.user_id` porte d'ailleurs
 * une contrainte ON DELETE RESTRICT qui interdirait de toute façon un DELETE
 * direct sur `users` tant que des factures existent.
 */
class GdprController
{
    private PDO $db;

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
    }

    /** Export complet des données personnelles au format JSON (droit d'accès et de portabilité). */
    public function exportData()
    {
        $user = Auth::requireUser($this->db, allowPendingPasswordChange: true);

        $stmt = $this->db->prepare('SELECT id, email, role, created_at FROM users WHERE id = :id');
        $stmt->execute([':id' => $user['id']]);
        $account = $stmt->fetch();

        $stmt = $this->db->prepare(
            'SELECT client_type, full_name, company_name, siret, vat_number,
                    address_line1, address_line2, postal_code, city, country_code
             FROM billing_profiles WHERE user_id = :id'
        );
        $stmt->execute([':id' => $user['id']]);
        $billingProfile = $stmt->fetch() ?: null;

        $stmt = $this->db->prepare(
            'SELECT plan, status, current_period_end, cancel_at_period_end FROM subscriptions WHERE user_id = :id'
        );
        $stmt->execute([':id' => $user['id']]);
        $subscription = $stmt->fetch() ?: null;

        $stmt = $this->db->prepare(
            'SELECT number, issued_at, currency, plan, description, amount_excl_tax, vat_rate, amount_tax, amount_total
             FROM invoices WHERE user_id = :id ORDER BY issued_at DESC'
        );
        $stmt->execute([':id' => $user['id']]);
        $invoices = $stmt->fetchAll();

        $stmt = $this->db->prepare(
            "SELECT g.text_content AS title, g.created_at, g.cost_total
             FROM generations g WHERE g.user_id = :id AND g.statut = 'on' ORDER BY g.created_at DESC"
        );
        $stmt->execute([':id' => $user['id']]);
        $podcasts = $stmt->fetchAll();

        $export = [
            'exported_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'account' => $account,
            'billing_profile' => $billingProfile,
            'subscription' => $subscription,
            'invoices' => $invoices,
            'podcasts' => $podcasts,
        ];

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="vokso-donnees-personnelles.json"');
        echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /** Suppression de compte (droit à l'effacement) : anonymisation, mot de passe requis. */
    public function deleteAccount()
    {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Méthode non autorisée']);
            return;
        }

        $user = Auth::requireUser($this->db, allowPendingPasswordChange: true);

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $password = is_string($input['password'] ?? null) ? $input['password'] : '';

        $stmt = $this->db->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute([':id' => $user['id']]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($password, $hash)) {
            http_response_code(401);
            echo json_encode(['error' => 'Mot de passe incorrect.']);
            return;
        }

        try {
            $this->db->beginTransaction();

            $this->db->prepare('DELETE FROM auth_tokens WHERE user_id = :id')->execute([':id' => $user['id']]);
            $this->db->prepare('DELETE FROM billing_profiles WHERE user_id = :id')->execute([':id' => $user['id']]);

            // Anonymisation plutôt que suppression : voir la note de classe
            // (factures à conserver 10 ans). L'email est rendu unique et
            // non réutilisable ; le compte est désactivé et son mot de passe
            // invalidé.
            $this->db->prepare(
                'UPDATE users
                 SET email = :anonymized_email, password_hash = :random_hash, status = "disabled",
                     totp_secret = NULL, totp_enabled = 0, must_change_password = 0
                 WHERE id = :id'
            )->execute([
                ':anonymized_email' => sprintf('deleted-user-%d@deleted.vokso.fr', $user['id']),
                ':random_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT),
                ':id' => $user['id'],
            ]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            Logger::get()->error($e->getMessage(), ['controller' => 'gdpr', 'action' => 'delete-account', 'exception' => get_class($e)]);
            \Sentry\captureException($e);
            http_response_code(500);
            echo json_encode(['error' => 'La suppression du compte a échoué, réessayez plus tard.']);
            return;
        }

        echo json_encode(['success' => true]);
    }
}
