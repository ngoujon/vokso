# Checklist de Vérification : Configuration DNS pour les Emails

## État Actuel

### ❌ Enregistrements DNS (Non configurés - Production requis)

Le projet **envoie des emails** via la classe `MailerService` (newsletters, confirmations). Aucun enregistrement DNS SPF/DKIM/DMARC n'existe actuellement.

**Configuration locale (développement):**
- Service: MailHog (via Docker)
- Host: `mailhog`
- Port: `1025`
- From: `contact@vokso.fr`

## À Implémenter avant Production

### 1. SPF (Sender Policy Framework)

**Objectif:** Autoriser uniquement le serveur SMTP officiel à envoyer des emails depuis le domaine.

**Enregistrement DNS requis:**
```dns
vokso.fr TXT "v=spf1 mx ~all"
```

ou si SMTP provider externe (ex: SendGrid, Mailgun):
```dns
vokso.fr TXT "v=spf1 include:sendgrid.net ~all"
```

**À choisir avant implémentation:**
- Quel serveur SMTP utilisera-t-on ? (actuel: MailHog local, futur: ?)
- Qui contrôle les enregistrements DNS ? (registrar, hébergeur, autre)

### 2. DKIM (DomainKeys Identified Mail)

**Objectif:** Signer cryptographiquement chaque email pour prouver l'authentification.

**Processus (simplifié):**
1. Générer une paire de clés RSA (2048+ bits)
2. Publier la clé publique en DNS: `default._domainkey.vokso.fr TXT "v=DKIM1; k=rsa; p=<public-key>"`
3. Configurer le serveur SMTP pour signer les emails avec la clé privée

**Fichier de configuration:**
- Nécessite stockage sécurisé de la clé privée (variable d'environnement ou vault)
- `MailerService` actuel ne supporte pas DKIM → à améliorer lors du passage en production

### 3. DMARC (Domain-based Message Authentication)

**Objectif:** Politiques pour rejeter/mettre en quarantaine les emails usurpant le domaine.

**Enregistrement DNS minimal:**
```dns
_dmarc.vokso.fr TXT "v=DMARC1; p=none; rua=mailto:dmarc@vokso.fr"
```

**Recommandation progressive:**
- Phase 1: `p=none` → monitoring sans rejet
- Phase 2: `p=quarantine` → mettre en quarantaine les emails suspects
- Phase 3: `p=reject` → rejeter strictement une fois SPF/DKIM validés

## Dépendances Bloquantes

| Élément | État | Impact |
|---------|------|--------|
| **Nom de domaine production** | ❌ Pas choisi | Impossible configurer DNS |
| **Accès DNS/registrar** | ❌ À déterminer | Impossible créer enregistrements |
| **Serveur SMTP final** | ❌ Pas choisi | SPF dépend du provider SMTP |
| **Gestion clés DKIM** | ❌ Non implémentée | Sécurité des clés privées |
| **Amélioration MailerService** | ❌ Non implémentée | Support DKIM/authentification |

## Plan d'Implémentation

### Phase 1 (Avant production)
- [ ] Choisir domaine production final
- [ ] Choisir serveur SMTP (provider SaaS ou auto-hébergé)
- [ ] Documenter accès DNS/registrar
- [ ] Générer paire de clés DKIM + stocker clé privée sécurisée

### Phase 2 (Avant lancement)
- [ ] Créer enregistrement SPF en DNS
- [ ] Créer enregistrement DKIM en DNS
- [ ] Créer enregistrement DMARC en DNS (`p=none` initial)
- [ ] Améliorer `MailerService` pour supporter DKIM (ou migrer vers librairie: PHPMailer, SwiftMailer)
- [ ] Tester délivrabilité (outils: MXToolbox, mail-tester.com)

### Phase 3 (Post-lancement)
- [ ] Monitorer rapports DMARC à `dmarc@vokso.fr`
- [ ] Progresser `p=none` → `p=quarantine` → `p=reject`
- [ ] Mettre en place alertes sur délivrabilité

## Fichiers Concernés

- `webserver/api/src/Services/MailerService.php` → À améliorer pour DKIM/auth
- `webserver/api/.env.example` → À enrichir avec config SMTP production
- `.env` (production) → À sécuriser (variables d'env pour clés DKIM)

## Notes de Sécurité

⚠️ **Clé DKIM privée:**
- Ne JAMAIS commiter dans le dépôt
- Stocker en variable d'environnement chiffrée ou vault (ex: HashiCorp Vault)
- Rotation recommandée chaque 1-2 ans

⚠️ **Domaine de bounce:**
- Considérer un sous-domaine pour bounces (ex: `bounce.vokso.fr`)
- Évite polluer la boîte `contact@vokso.fr`

## Références

- [RFC 7208 - SPF](https://tools.ietf.org/html/rfc7208)
- [RFC 6376 - DKIM](https://tools.ietf.org/html/rfc6376)
- [RFC 7489 - DMARC](https://tools.ietf.org/html/rfc7489)
- [MXToolbox DKIM Check](https://mxtoolbox.com/dkim.aspx)
- [mail-tester.com](https://www.mail-tester.com/) - Test complet SPF/DKIM/DMARC
