<?php

namespace Tests\Services;

use App\Services\InvoiceIssuer;
use App\Services\InvoiceNumberGenerator;
use PDO;
use PHPUnit\Framework\TestCase;

class InvoiceIssuerTest extends TestCase
{
    private array $envBackup = [];

    protected function setUp(): void
    {
        $this->envBackup = $_ENV;
        $_ENV['INVOICE_SELLER_NAME'] = 'Vokso';
        $_ENV['INVOICE_SELLER_ADDRESS'] = '1 rue de la Paix';
        $_ENV['INVOICE_SELLER_POSTAL_CODE'] = '75002';
        $_ENV['INVOICE_SELLER_CITY'] = 'Paris';
        $_ENV['INVOICE_SELLER_SIREN'] = '123456789';
        $_ENV['INVOICE_SELLER_VAT_NUMBER'] = '';
        $_ENV['INVOICE_SELLER_COUNTRY'] = 'FR';
        $_ENV['INVOICE_VAT_RATE'] = '0';
        $_ENV['INVOICE_VAT_EXEMPTION_REASON'] = 'TVA non applicable, art. 293 B du CGI';
    }

    protected function tearDown(): void
    {
        $_ENV = $this->envBackup;
    }

    private function makeDb(): PDO
    {
        $sqliteClass = class_exists('Pdo\\Sqlite') ? 'Pdo\\Sqlite' : PDO::class;
        $db = new $sqliteClass('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT NOT NULL)');
        $db->exec(
            'CREATE TABLE billing_profiles (
                user_id INTEGER PRIMARY KEY, client_type TEXT NOT NULL, full_name TEXT, company_name TEXT,
                siret TEXT, vat_number TEXT, address_line1 TEXT, address_line2 TEXT,
                postal_code TEXT, city TEXT, country_code TEXT
            )'
        );
        $db->exec('CREATE TABLE invoice_counters (year INTEGER PRIMARY KEY, last_number INTEGER NOT NULL DEFAULT 0)');
        $db->exec(
            'CREATE TABLE invoices (
                id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, number TEXT, stripe_invoice_id TEXT,
                issued_at TEXT, currency TEXT, plan TEXT, description TEXT, amount_excl_tax TEXT,
                vat_rate TEXT, vat_exemption_reason TEXT, amount_tax TEXT, amount_total TEXT, client_snapshot TEXT
            )'
        );

        return $db;
    }

    public function testReturnsNullWhenSellerIsNotConfigured(): void
    {
        unset($_ENV['INVOICE_SELLER_NAME']);
        $db = $this->makeDb();
        $db->exec("INSERT INTO users (id, email) VALUES (1, 'user@example.com')");
        $issuer = new InvoiceIssuer($db, new InvoiceNumberGenerator($db));

        $this->assertNull($issuer->issueForSubscriptionPayment(1, 'createur', 990, 'eur', 'in_123'));
    }

    public function testIssuesAnInvoiceForAParticulierWithVatExemption(): void
    {
        $db = $this->makeDb();
        $db->exec("INSERT INTO users (id, email) VALUES (1, 'user@example.com')");
        $issuer = new InvoiceIssuer($db, new InvoiceNumberGenerator($db));

        $number = $issuer->issueForSubscriptionPayment(1, 'createur', 990, 'eur', 'in_123');

        $this->assertSame('VOKSO-' . date('Y') . '-000001', $number);

        $stmt = $db->query('SELECT * FROM invoices WHERE stripe_invoice_id = "in_123"');
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('9.90', $invoice['amount_excl_tax']);
        $this->assertSame('0.00', $invoice['amount_tax']);
        $this->assertSame('9.90', $invoice['amount_total']);
        $this->assertSame('0.00', $invoice['vat_rate']);

        $buyer = json_decode($invoice['client_snapshot'], true);
        $this->assertSame('user@example.com', $buyer['name']);
        $this->assertFalse($buyer['is_business']);
    }

    public function testIssuesAnInvoiceForAProSplitsVatFromTheTotalPaid(): void
    {
        $_ENV['INVOICE_VAT_RATE'] = '20';
        $db = $this->makeDb();
        $db->exec("INSERT INTO users (id, email) VALUES (2, 'pro@example.com')");
        $db->exec(
            "INSERT INTO billing_profiles (user_id, client_type, full_name, company_name, siret, vat_number, address_line1, postal_code, city, country_code)
             VALUES (2, 'pro', 'Jean Dupont', 'Acme SARL', '73282932000074', 'FR40303265045', '2 avenue des Champs', '75008', 'Paris', 'FR')"
        );
        $issuer = new InvoiceIssuer($db, new InvoiceNumberGenerator($db));

        // 990 centimes TTC à 20% -> 825 HT + 165 TVA (arrondi à 2 décimales).
        $issuer->issueForSubscriptionPayment(2, 'createur', 990, 'eur', 'in_456');

        $stmt = $db->query('SELECT * FROM invoices WHERE stripe_invoice_id = "in_456"');
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('8.25', $invoice['amount_excl_tax']);
        $this->assertSame('1.65', $invoice['amount_tax']);
        $this->assertSame('9.90', $invoice['amount_total']);
        $this->assertSame('20.00', $invoice['vat_rate']);

        $buyer = json_decode($invoice['client_snapshot'], true);
        $this->assertSame('Acme SARL', $buyer['name']);
        $this->assertTrue($buyer['is_business']);
        $this->assertSame('732829320', $buyer['siren']);
    }

    public function testRetryingTheSameStripeInvoiceDoesNotCreateADuplicate(): void
    {
        $db = $this->makeDb();
        $db->exec("INSERT INTO users (id, email) VALUES (1, 'user@example.com')");
        $issuer = new InvoiceIssuer($db, new InvoiceNumberGenerator($db));

        $first = $issuer->issueForSubscriptionPayment(1, 'createur', 990, 'eur', 'in_789');
        $second = $issuer->issueForSubscriptionPayment(1, 'createur', 990, 'eur', 'in_789');

        $this->assertSame($first, $second);
        $count = (int) $db->query('SELECT COUNT(*) FROM invoices')->fetchColumn();
        $this->assertSame(1, $count);
    }
}
