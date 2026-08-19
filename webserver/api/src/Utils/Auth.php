<?php

namespace App\Utils;

use PDO;

/**
 * Authentification par jeton opaque (table auth_tokens), sans cookies : le
 * front (React) et l'API ne sont pas garantis d'être sur le même
 * sous-domaine. Le front envoie "Authorization: Bearer <token>".
 */
class Auth
{
    private const TOKEN_TTL_SECONDS = 60 * 60 * 24 * 30; // 30 jours

    public static function createToken(PDO $db, int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + self::TOKEN_TTL_SECONDS);

        $stmt = $db->prepare('INSERT INTO auth_tokens (token, user_id, expires_at) VALUES (:token, :user_id, :expires_at)');
        $stmt->execute([':token' => $token, ':user_id' => $userId, ':expires_at' => $expiresAt]);

        return $token;
    }

    public static function deleteToken(PDO $db, string $token): void
    {
        $db->prepare('DELETE FROM auth_tokens WHERE token = :token')->execute([':token' => $token]);
    }

    /** Retourne l'utilisateur associé au jeton envoyé, ou null si absent/invalide/expiré. */
    public static function currentUser(PDO $db): ?array
    {
        $token = self::bearerToken();
        if ($token === null) {
            return null;
        }

        $stmt = $db->prepare(
            'SELECT u.id, u.email, u.role, u.status
             FROM auth_tokens t
             JOIN users u ON u.id = t.user_id
             WHERE t.token = :token AND t.expires_at > NOW()'
        );
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || $user['status'] !== 'active') {
            return null;
        }

        return $user;
    }

    /** Exige un utilisateur connecté ; répond en 401 et arrête l'exécution sinon. */
    public static function requireUser(PDO $db): array
    {
        $user = self::currentUser($db);
        if (!$user) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Authentification requise']);
            exit;
        }

        return $user;
    }

    /** Exige un utilisateur connecté avec le rôle admin ; répond en 403 sinon. */
    public static function requireAdmin(PDO $db): array
    {
        $user = self::requireUser($db);
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Accès réservé aux administrateurs']);
            exit;
        }

        return $user;
    }

    private static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if ($header === '' && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $header = $headers['Authorization'] ?? '';
        }

        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
