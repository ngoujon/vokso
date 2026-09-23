<?php

namespace App\Services;

use App\Utils\Logger;

/**
 * Client SMTP minimal, sans dépendance externe (PHPMailer...) : suffisant
 * pour parler à MailHog en local (sans authentification) et à un vrai
 * fournisseur SMTP authentifié en production (OVH...). Le chiffrement
 * implicite (SSL, port 465) s'obtient en préfixant l'hôte de "ssl://" avant
 * la connexion : fsockopen() accepte les mêmes transports que
 * stream_socket_client().
 */
class MailerService
{
    public function __construct(
        private string $host,
        private int $port,
        private string $fromAddress,
        private string $fromName = 'Vokso',
        private ?string $encryption = null,
        private ?string $username = null,
        private ?string $password = null
    ) {
    }

    /** Lit SMTP_HOST/SMTP_PORT/SMTP_ENCRYPTION/SMTP_USERNAME/SMTP_PASSWORD/MAIL_FROM(_NAME) depuis l'environnement déjà chargé (Dotenv). */
    public static function fromEnv(): self
    {
        $encryption = trim((string) ($_ENV['SMTP_ENCRYPTION'] ?? ''));
        $username = trim((string) ($_ENV['SMTP_USERNAME'] ?? ''));
        $password = (string) ($_ENV['SMTP_PASSWORD'] ?? '');

        return new self(
            $_ENV['SMTP_HOST'] ?? 'mailhog',
            (int) ($_ENV['SMTP_PORT'] ?? 1025),
            $_ENV['MAIL_FROM'] ?? 'contact@vokso.fr',
            $_ENV['MAIL_FROM_NAME'] ?? 'Vokso',
            $encryption !== '' ? $encryption : null,
            $username !== '' ? $username : null,
            $password !== '' ? $password : null
        );
    }

    public function send(string $toAddress, string $subject, string $body, ?string $replyTo = null): bool
    {
        $remote = $this->encryption === 'ssl' ? "ssl://{$this->host}" : $this->host;
        $socket = @fsockopen($remote, $this->port, $errno, $errstr, 10);
        if ($socket === false) {
            Logger::get()->error('Connexion SMTP impossible', [
                'service' => 'mailer',
                'host' => $this->host,
                'port' => $this->port,
                'reason' => $errstr,
            ]);
            return false;
        }

        try {
            $this->expect($socket, '220');
            $this->command($socket, "EHLO {$this->host}", '250');

            if ($this->username !== null && $this->password !== null) {
                $this->command($socket, 'AUTH LOGIN', '334');
                $this->command($socket, base64_encode($this->username), '334');
                $this->command($socket, base64_encode($this->password), '235');
            }

            $this->command($socket, "MAIL FROM:<{$this->fromAddress}>", '250');
            $this->command($socket, "RCPT TO:<{$toAddress}>", '250');
            $this->command($socket, 'DATA', '354');

            $headers = [
                'From: ' . $this->fromName . ' <' . $this->fromAddress . '>',
                'To: <' . $toAddress . '>',
                'Subject: ' . $subject,
            ];
            if ($replyTo !== null) {
                $headers[] = 'Reply-To: <' . $replyTo . '>';
            }
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: text/plain; charset=utf-8';

            $message = implode("\r\n", $headers) . "\r\n\r\n" . $this->escapeBody($body) . "\r\n.";
            $this->command($socket, $message, '250');
            $this->command($socket, 'QUIT', '221');

            return true;
        } catch (\RuntimeException $e) {
            Logger::get()->error($e->getMessage(), ['service' => 'mailer', 'exception' => get_class($e)]);
            return false;
        } finally {
            fclose($socket);
        }
    }

    /** Une ligne du corps commençant par "." serait lue par le serveur SMTP comme la fin du message (RFC 5321 §4.5.2). */
    private function escapeBody(string $body): string
    {
        return preg_replace('/^\./m', '..', $body);
    }

    private function command($socket, string $line, string $expectedCode): void
    {
        fwrite($socket, $line . "\r\n");
        $this->expect($socket, $expectedCode);
    }

    private function expect($socket, string $expectedCode): void
    {
        $response = '';
        while (($line = fgets($socket, 512)) !== false) {
            $response .= $line;
            // Une ligne de continuation multi-lignes s'écrit "250-...", la
            // dernière ligne du bloc "250 ...".
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        if (!str_starts_with($response, $expectedCode)) {
            throw new \RuntimeException("Réponse SMTP inattendue : {$response}");
        }
    }
}
