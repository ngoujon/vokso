-- Support des abonnements payants (Stripe), prérequis à la page /tarifs qui
-- annonçait déjà 3 formules (Découverte gratuite, Créateur, Studio) sans
-- aucun mécanisme de paiement derrière.

-- Une ligne par utilisateur : reflète l'état côté Stripe (source de vérité),
-- mis à jour uniquement par les webhooks (voir BillingController::webhook())
-- ou à la création du compte pour la formule gratuite par défaut.
CREATE TABLE IF NOT EXISTS `subscriptions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `plan` ENUM('decouverte', 'createur', 'studio') NOT NULL DEFAULT 'decouverte',
    `status` ENUM('active', 'trialing', 'past_due', 'canceled', 'incomplete') NOT NULL DEFAULT 'active',
    `stripe_customer_id` VARCHAR(255) DEFAULT NULL,
    `stripe_subscription_id` VARCHAR(255) DEFAULT NULL,
    `stripe_price_id` VARCHAR(255) DEFAULT NULL,
    `current_period_end` DATETIME DEFAULT NULL,
    `cancel_at_period_end` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `subscriptions_user_id_unique` (`user_id`),
    UNIQUE KEY `subscriptions_stripe_subscription_id_unique` (`stripe_subscription_id`),
    KEY `subscriptions_stripe_customer_id` (`stripe_customer_id`),
    CONSTRAINT `subscriptions_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Déduplication des évènements webhook Stripe : Stripe garantit "au moins
-- une fois", jamais "exactement une fois" (retries en cas de timeout côté
-- serveur), donc un event_id déjà vu doit être ignoré silencieusement.
CREATE TABLE IF NOT EXISTS `stripe_events` (
    `event_id` VARCHAR(255) NOT NULL,
    `type` VARCHAR(100) NOT NULL,
    `processed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Comptes déjà existants : formule gratuite par défaut, pour que
-- BillingController ait toujours une ligne à mettre à jour côté webhook.
INSERT IGNORE INTO `subscriptions` (`user_id`, `plan`, `status`)
SELECT `id`, 'decouverte', 'active' FROM `users`;
