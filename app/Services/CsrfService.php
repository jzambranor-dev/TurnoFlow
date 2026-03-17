<?php

declare(strict_types=1);

namespace App\Services;

class CsrfService
{
    /**
     * Generate or retrieve the current CSRF token.
     */
    public static function token(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    /**
     * Output a hidden input field with the CSRF token.
     */
    public static function field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(self::token()) . '">';
    }

    /**
     * Validate the CSRF token from the request.
     */
    public static function validate(): bool
    {
        $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return hash_equals(self::token(), $token);
    }

    /**
     * Validate and abort with 403 if invalid.
     */
    public static function validateOrFail(): void
    {
        if (!self::validate()) {
            http_response_code(403);
            echo 'Token CSRF invalido. Recarga la pagina e intenta de nuevo.';
            exit;
        }
    }
}
