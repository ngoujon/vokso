<?php

namespace App\Services;

use PDO;

/**
 * Numérotation séquentielle et sans trou des factures (obligation légale,
 * art. 242 nonies A du CGI), remise à zéro chaque année civile. Utilise un
 * verrou de ligne (SELECT ... FOR UPDATE) dans une transaction dédiée pour
 * rester correcte sous accès concurrent : un simple AUTO_INCREMENT laisse
 * des trous en cas de ROLLBACK, ce qui est interdit ici.
 *
 * SQLite (utilisé par les tests, voir tests/Services/InvoiceNumberGeneratorTest.php)
 * ne supporte pas "FOR UPDATE" : il est omis pour ce pilote, la sérialisation
 * des écritures n'y a de toute façon pas d'intérêt hors MySQL en production.
 */
class InvoiceNumberGenerator
{
    public function __construct(private PDO $db)
    {
    }

    public function next(int $year): string
    {
        $forUpdate = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';

        $alreadyInTransaction = $this->db->inTransaction();
        if (!$alreadyInTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $stmt = $this->db->prepare('SELECT last_number FROM invoice_counters WHERE year = :year' . $forUpdate);
            $stmt->execute([':year' => $year]);
            $lastNumber = $stmt->fetchColumn();

            if ($lastNumber === false) {
                $this->db->prepare('INSERT INTO invoice_counters (year, last_number) VALUES (:year, 1)')
                    ->execute([':year' => $year]);
                $nextNumber = 1;
            } else {
                $nextNumber = (int) $lastNumber + 1;
                $this->db->prepare('UPDATE invoice_counters SET last_number = :next WHERE year = :year')
                    ->execute([':next' => $nextNumber, ':year' => $year]);
            }

            if (!$alreadyInTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if (!$alreadyInTransaction) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return sprintf('VOKSO-%d-%06d', $year, $nextNumber);
    }
}
