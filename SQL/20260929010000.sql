-- Suppression des tables des anciens abonnements Stripe : le service est
-- gratuit et aucun paiement n'a jamais été enregistré en production
-- (stripe_events vide, aucun client Stripe dans subscriptions). La facturation
-- (SQL/20260925000000.sql, jamais appliquée) a été retirée du dépôt.
DROP TABLE IF EXISTS `stripe_events`;
DROP TABLE IF EXISTS `subscriptions`;
