<?php

namespace App\Controllers;

use App\Services\FacturX\FacturXService;
use App\Services\InvoiceSellerConfig;
use App\Utils\Auth;
use App\Utils\Logger;
use Dotenv\Dotenv;
use PDO;

/**
 * Historique de facturation de l'utilisateur connecté. Le service est gratuit
 * depuis la suppression des abonnements Stripe : plus aucune facture n'est
 * émise, mais celles déjà émises restent consultables (conservation légale
 * de 10 ans). Le PDF/A-3 Factur-X n'est jamais stocké : il est reconstruit à
 * la demande (download) à partir des données figées dans `invoices`.
 */
class InvoiceController
{
    private PDO $db;
    private FacturXService $facturX;

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

        $this->facturX = new FacturXService();
    }

    public function list()
    {
        header('Content-Type: application/json');
        $user = Auth::requireUser($this->db);

        $stmt = $this->db->prepare(
            'SELECT id, number, issued_at, currency, plan, description, amount_total
             FROM invoices WHERE user_id = :user_id ORDER BY issued_at DESC'
        );
        $stmt->execute([':user_id' => $user['id']]);

        echo json_encode(['data' => $stmt->fetchAll()]);
    }

    public function download()
    {
        $user = Auth::requireUser($this->db);

        $invoiceId = (int) ($_GET['id'] ?? 0);
        $stmt = $this->db->prepare('SELECT * FROM invoices WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':id' => $invoiceId, ':user_id' => $user['id']]);
        $invoice = $stmt->fetch();

        if (!$invoice) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Facture introuvable.']);
            return;
        }

        $seller = InvoiceSellerConfig::fromEnv();
        if ($seller === null) {
            http_response_code(503);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Le vendeur n\'est pas encore configuré, impossible de régénérer la facture.']);
            return;
        }

        $buyer = json_decode($invoice['client_snapshot'], true) ?? [];
        $vatExempt = (float) $invoice['vat_rate'] <= 0.0;

        try {
            $result = $this->facturX->generate([
                'number' => $invoice['number'],
                'issue_date' => new \DateTimeImmutable($invoice['issued_at']),
                'currency' => $invoice['currency'],
                'seller' => $seller,
                'buyer' => $buyer,
                'line_description' => $invoice['description'],
                'amount_excl_tax' => $invoice['amount_excl_tax'],
                'vat_rate' => $invoice['vat_rate'],
                'vat_exempt' => $vatExempt,
                'vat_exemption_reason' => $invoice['vat_exemption_reason'],
                'amount_tax' => $invoice['amount_tax'],
                'amount_total' => $invoice['amount_total'],
            ]);
        } catch (\Throwable $e) {
            Logger::get()->error($e->getMessage(), ['controller' => 'invoice', 'action' => 'download', 'exception' => get_class($e)]);
            \Sentry\captureException($e);
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Impossible de générer la facture pour le moment.']);
            return;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $invoice['number'] . '.pdf"');
        header('Content-Length: ' . strlen($result['pdf']));
        echo $result['pdf'];
    }
}
