-- Le site devient 100 % gratuit : suppression des abonnements Stripe, la
-- génération est réservée aux comptes et plafonnée par mois (voir
-- GenerationQuota), calculée à chaque requête sur generation_jobs.
ALTER TABLE `generation_jobs`
    ADD KEY `generation_jobs_user_created` (`user_id`, `created_at`);

-- Les tables `subscriptions` et `stripe_events` ne sont plus lues ni écrites
-- par l'application. Elles sont volontairement conservées (historique des
-- paiements lié aux factures déjà émises) ; à supprimer à la main si besoin :
--   DROP TABLE `stripe_events`;
--   DROP TABLE `subscriptions`;
