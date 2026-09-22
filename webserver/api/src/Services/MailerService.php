<?php

namespace App\Services;

use App\Utils\Logger;

/**
 * Client SMTP minimal, sans authentification : suffisant pour parler à
 * MailHog en local (voir docker-compose.yml). Un vrai fournisseur SMTP (avec
 * authentification) reste à choisir avant la mise en production sur
 * vokso.fr.
 */
class MailerService
{
    public function __construct(
        private string $host,
        private int $port,
        private string $fromAddress,
        private string $fromName = 'Vokso'
    ) {
    }

    public function send(string $toAddress, string $subject, string $body): bool
    {
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, 5);
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
            $this->command($socket, "MAIL FROM:<{$this->fromAddress}>", '250');
            $this->command($socket, "RCPT TO:<{$toAddress}>", '250');
            $this->command($socket, 'DATA', '354');

            $headers = [
                'From: ' . $this->fromName . ' <' . $this->fromAddress . '>',
                'To: <' . $toAddress . '>',
                'Subject: ' . $subject,
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=utf-8',
            ];
            $message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
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
