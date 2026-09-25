-- Facturation légale (Factur-X) + RGPD : informations de facturation par
-- utilisateur (particulier/pro), factures émises et compteur de numérotation
-- séquentielle sans trou (obligation légale française, art. 242 nonies A du
-- CGI). Prérequis à FacturXService / InvoiceController / GdprController.

-- Une ligne par utilisateur, éditable depuis l'espace compte. Les champs
-- société ne sont obligatoires côté application que si client_type = 'pro'
-- (voir BillingProfileValidator) : la contrainte n'est pas portée en base
-- pour rester tolérant à un futur changement de type sans purge des champs.
CREATE TABLE IF NOT EXISTS `billing_profiles` (
    `user_id` INT(11) NOT NULL,
    `client_type` ENUM('particulier', 'pro') NOT NULL DEFAULT 'particulier',
    `full_name` VARCHAR(190) NOT NULL DEFAULT '',
    `company_name` VARCHAR(190) DEFAULT NULL,
    `siret` VARCHAR(14) DEFAULT NULL,
    `vat_number` VARCHAR(20) DEFAULT NULL,
    `address_line1` VARCHAR(190) NOT NULL DEFAULT '',
    `address_line2` VARCHAR(190) DEFAULT NULL,
    `postal_code` VARCHAR(20) NOT NULL DEFAULT '',
    `city` VARCHAR(120) NOT NULL DEFAULT '',
    `country_code` CHAR(2) NOT NULL DEFAULT 'FR',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    CONSTRAINT `billing_profiles_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Une ligne par année : verrou applicatif (SELECT ... FOR UPDATE dans une
-- transaction) pour garantir des numéros de facture consécutifs même sous
-- accès concurrent, ce qu'un simple AUTO_INCREMENT ne garantit pas (les
-- ROLLBACK y créent des trous, interdits par la loi sur la facturation).
CREATE TABLE IF NOT EXISTS `invoice_counters` (
    `year` SMALLINT(6) NOT NULL,
    `last_number` INT(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Une ligne par facture émise (créée par BillingController::applyEvent sur
-- l'évènement Stripe "invoice.paid"). client_snapshot fige au format JSON les
-- informations de facturation au moment de l'émission : une facture légale
-- ne doit jamais changer rétroactivement si l'utilisateur modifie ensuite son
-- profil de facturation. ON DELETE RESTRICT interdit toute suppression d'un
-- compte utilisateur ayant des factures : la conservation légale (10 ans,
-- art. L123-22 du code de commerce) prime sur le droit à l'effacement RGPD,
-- d'où l'anonymisation (GdprController::deleteAccount) plutôt qu'un DELETE.
CREATE TABLE IF NOT EXISTS `invoices` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `number` VARCHAR(30) NOT NULL,
    `stripe_invoice_id` VARCHAR(255) DEFAULT NULL,
    `issued_at` DATETIME NOT NULL,
    `currency` CHAR(3) NOT NULL DEFAULT 'EUR',
    `plan` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `amount_excl_tax` DECIMAL(10, 2) NOT NULL,
    `vat_rate` DECIMAL(5, 2) NOT NULL DEFAULT 0,
    `vat_exemption_reason` VARCHAR(255) DEFAULT NULL,
    `amount_tax` DECIMAL(10, 2) NOT NULL DEFAULT 0,
    `amount_total` DECIMAL(10, 2) NOT NULL,
    `client_snapshot` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `invoices_number_unique` (`number`),
    UNIQUE KEY `invoices_stripe_invoice_id_unique` (`stripe_invoice_id`),
    KEY `invoices_user_id` (`user_id`),
    CONSTRAINT `invoices_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
