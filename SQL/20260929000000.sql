-- Le site devient 100 % gratuit : suppression des abonnements Stripe, la
-- génération est réservée aux comptes et plafonnée par mois (voir
-- GenerationQuota), calculée à chaque requête sur generation_jobs.
ALTER TABLE `generation_jobs`
    ADD KEY `generation_jobs_user_created` (`user_id`, `created_at`);

-- Les jetons de connexion sont désormais stockés sous forme d'empreinte
-- SHA-256 (API Laravel) : les anciens jetons, en clair, ne sont plus
-- reconnus et peuvent être purgés (chaque utilisateur se reconnecte une fois).
DELETE FROM `auth_tokens`;
