<?php

declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $envPath = dirname(__DIR__) . '/.env';
            self::loadEnv($envPath);

            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $port = $_ENV['DB_PORT'] ?? '5432';
            $dbname = $_ENV['DB_NAME'] ?? 'turnoflow';
            $user = $_ENV['DB_USER'] ?? 'turnoflow';
            $pass = $_ENV['DB_PASS'] ?? '';

            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

            try {
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                error_log("Error de conexión a PostgreSQL: " . $e->getMessage());
                throw new Exception("No se pudo conectar a la base de datos");
            }
        }

        return self::$instance;
    }

    private static function loadEnv(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                if (!array_key_exists($key, $_ENV)) {
                    $_ENV[$key] = $value;
                    putenv("{$key}={$value}");
                }
            }
        }
    }

    public static function closeConnection(): void
    {
        self::$instance = null;
    }
}
