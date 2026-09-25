<?php

namespace App\Controllers;

use App\Utils\Auth;
use App\Utils\BillingProfileValidator;
use Dotenv\Dotenv;
use PDO;

/**
 * Coordonnées de facturation de l'utilisateur connecté (particulier ou
 * pro) : formulaire préalable à toute émission de facture, voir
 * InvoiceIssuer::buyerSnapshot() qui les fige au moment de chaque facture.
 */
class BillingProfileController
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

    public function get()
    {
        header('Content-Type: application/json');
        $user = Auth::requireUser($this->db);

        $stmt = $this->db->prepare(
            'SELECT client_type, full_name, company_name, siret, vat_number,
                    address_line1, address_line2, postal_code, city, country_code
             FROM billing_profiles WHERE user_id = :user_id'
        );
        $stmt->execute([':user_id' => $user['id']]);
        $profile = $stmt->fetch();

        echo json_encode(['profile' => $profile ?: [
            'client_type' => 'particulier',
            'full_name' => '',
            'company_name' => null,
            'siret' => null,
            'vat_number' => null,
            'address_line1' => '',
            'address_line2' => null,
            'postal_code' => '',
            'city' => '',
            'country_code' => 'FR',
        ]]);
    }

    public function update()
    {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Méthode non autorisée']);
            return;
        }

        $user = Auth::requireUser($this->db);
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $error = BillingProfileValidator::validate($input);
        if ($error !== null) {
            http_response_code(400);
            echo json_encode(['error' => $error]);
            return;
        }

        $clientType = $input['client_type'];
        $isBusiness = $clientType === 'pro';

        $stmt = $this->db->prepare(
            'INSERT INTO billing_profiles
                (user_id, client_type, full_name, company_name, siret, vat_number,
                 address_line1, address_line2, postal_code, city, country_code)
             VALUES
                (:user_id, :client_type, :full_name, :company_name, :siret, :vat_number,
                 :address_line1, :address_line2, :postal_code, :city, :country_code)
             ON DUPLICATE KEY UPDATE
                client_type = VALUES(client_type), full_name = VALUES(full_name),
                company_name = VALUES(company_name), siret = VALUES(siret), vat_number = VALUES(vat_number),
                address_line1 = VALUES(address_line1), address_line2 = VALUES(address_line2),
                postal_code = VALUES(postal_code), city = VALUES(city), country_code = VALUES(country_code)'
        );
        $stmt->execute([
            ':user_id' => $user['id'],
            ':client_type' => $clientType,
            ':full_name' => trim((string) $input['full_name']),
            ':company_name' => $isBusiness ? trim((string) $input['company_name']) : null,
            ':siret' => $isBusiness ? preg_replace('/\s+/', '', (string) $input['siret']) : null,
            ':vat_number' => $isBusiness && trim((string) ($input['vat_number'] ?? '')) !== ''
                ? strtoupper(preg_replace('/\s+/', '', (string) $input['vat_number']))
                : null,
            ':address_line1' => trim((string) $input['address_line1']),
            ':address_line2' => trim((string) ($input['address_line2'] ?? '')) !== '' ? trim((string) $input['address_line2']) : null,
            ':postal_code' => trim((string) $input['postal_code']),
            ':city' => trim((string) $input['city']),
            ':country_code' => strtoupper((string) $input['country_code']),
        ]);

        echo json_encode(['success' => true]);
    }
}
