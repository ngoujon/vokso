<?php

namespace App\Support;

/**
 * TOTP (RFC 6238) minimal, sans dépendance externe : évite d'envoyer le
 * secret 2FA à un service tiers (ex. générateur de QR code en ligne) et
 * n'ajoute pas de librairie pour ~80 lignes d'algorithme standard.
 */
class Totp
{
    private const SECRET_BYTES = 20; // 160 bits, recommandation RFC 4226
    private const PERIOD = 30;
    private const DIGITS = 6;

    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(self::SECRET_BYTES));
    }

    /** URI à saisir manuellement ou à encoder en QR côté client. */
    public static function provisioningUri(string $secret, string $accountEmail, string $issuer = 'Vokso'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($accountEmail),
            $secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD
        );
    }

    /** Vérifie un code à 6 chiffres, avec une tolérance d'une période avant/après (dérive d'horloge). */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = trim($code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $timeStep = (int) floor(time() / self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::generateCode($secret, $timeStep + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    private static function generateCode(string $secret, int $timeStep): string
    {
        $key = self::base32Decode($secret);
        $binaryTime = str_pad(pack('N', $timeStep), 8, "\x00", STR_PAD_LEFT);
        $hash = hash_hmac('sha1', $binaryTime, $key, true);

        $offset = ord($hash[19]) & 0x0F;
        $truncated = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($truncated % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $binary): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($binary) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $output .= $alphabet[bindec($chunk)];
        }

        return $output;
    }

    private static function base32Decode(string $base32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32 = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $base32));

        $bits = '';
        foreach (str_split($base32) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) < 8) {
                continue;
            }
            $binary .= chr(bindec($byte));
        }

        return $binary;
    }
}
