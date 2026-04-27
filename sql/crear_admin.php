<?php
/**
 * TurnoFlow — Crear usuario administrador
 *
 * Uso:
 *   php sql/crear_admin.php
 *
 * Pide nombre, email y password por consola.
 * Requiere que la BD ya este creada (setup_completo.sql).
 */

// Cargar config de BD
$envFile = dirname(__DIR__) . '/.env';
if (!file_exists($envFile)) {
    echo "ERROR: No se encontro .env en " . dirname(__DIR__) . "\n";
    echo "Copia .env.example a .env y configura los datos de conexion.\n";
    exit(1);
}

$env = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    if (str_contains($line, '=')) {
        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim(trim($value), '"\'');
    }
}

$host = $env['DB_HOST'] ?? 'localhost';
$port = $env['DB_PORT'] ?? '5432';
$dbname = $env['DB_NAME'] ?? 'turnoflow';
$user = $env['DB_USER'] ?? 'turnoflow';
$pass = $env['DB_PASS'] ?? '';

echo "\n=== TurnoFlow — Crear Admin ===\n\n";

// Pedir datos
echo "Nombre: ";
$nombre = trim(fgets(STDIN));

echo "Apellido: ";
$apellido = trim(fgets(STDIN));

echo "Email: ";
$email = trim(fgets(STDIN));

echo "Password (min 8 chars): ";
$password = trim(fgets(STDIN));

if (strlen($password) < 8) {
    echo "ERROR: Password debe tener al menos 8 caracteres.\n";
    exit(1);
}

if (empty($nombre) || empty($email)) {
    echo "ERROR: Nombre y email son requeridos.\n";
    exit(1);
}

// Conectar
try {
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    echo "ERROR de conexion: " . $e->getMessage() . "\n";
    exit(1);
}

// Buscar rol admin
$stmt = $pdo->prepare("SELECT id FROM roles WHERE nombre = 'admin' LIMIT 1");
$stmt->execute();
$role = $stmt->fetch();

if (!$role) {
    // Crear rol admin si no existe
    $pdo->exec("INSERT INTO roles (nombre, descripcion) VALUES ('admin', 'Acceso total al sistema')");
    $roleId = (int)$pdo->lastInsertId();
    echo "Rol 'admin' creado (id: {$roleId})\n";

    // Asignar todos los permisos
    $pdo->exec("INSERT INTO role_permissions (rol_id, permission_id) SELECT {$roleId}, id FROM permissions ON CONFLICT DO NOTHING");
} else {
    $roleId = (int)$role['id'];
}

// Verificar que no exista el email
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
$stmt->execute([':email' => $email]);
if ($stmt->fetch()) {
    echo "ERROR: Ya existe un usuario con email {$email}\n";
    exit(1);
}

// Crear usuario
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("
    INSERT INTO users (nombre, apellido, email, password_hash, rol_id, activo)
    VALUES (:nombre, :apellido, :email, :hash, :rol_id, true)
");
$stmt->execute([
    ':nombre' => $nombre,
    ':apellido' => $apellido,
    ':email' => $email,
    ':hash' => $hash,
    ':rol_id' => $roleId,
]);

echo "\n✓ Admin creado exitosamente!\n";
echo "  Email: {$email}\n";
echo "  Rol: admin (id: {$roleId})\n";
echo "\nYa puedes iniciar sesion en TurnoFlow.\n\n";
