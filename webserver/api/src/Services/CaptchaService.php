<?php

namespace App\Services;

/**
 * Anti-spam invisible, entièrement self-hosted : aucun widget, puzzle ni
 * appel à un service tiers (reCAPTCHA, hCaptcha...). Le front récupère un
 * jeton signé au chargement du formulaire et le renvoie tel quel à la
 * soumission. Le serveur rejette un jeton absent, falsifié, ou soumis trop
 * vite (bot qui remplit et envoie le formulaire en quelques millisecondes)
 * ou trop tard. Combiné en pratique avec un champ piège (honeypot) côté
 * contrôleur, voir ContactController.
 */
class CaptchaService
{
    public function __construct(private string $secret)
    {
    }

    public function issueToken(): string
    {
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp, $this->secret);
        return base64_encode($timestamp . '.' . $signature);
    }

    public function verify(?string $token, int $minSeconds = 3, int $maxSeconds = 3600): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        $decoded = base64_decode($token, true);
        if ($decoded === false || !str_contains($decoded, '.')) {
            return false;
        }

        [$timestamp, $signature] = explode('.', $decoded, 2);
        if (!ctype_digit($timestamp)) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp, $this->secret);
        if (!hash_equals($expected, $signature)) {
            return false;
        }

        $elapsed = time() - (int) $timestamp;
        return $elapsed >= $minSeconds && $elapsed <= $maxSeconds;
    }
}
