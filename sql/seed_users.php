<?php
/**
 * Script para insertar usuarios de prueba en la base de datos
 * Ejecutar UNA vez: php sql/seed_users.php
 * O abrir en navegador: http://localhost/system-horario/TurnoFlow/sql/seed_users.php
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = Database::getConnection();

    // Verificar roles existentes
    $roles = $pdo->query("SELECT id, nombre FROM roles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    echo "<h3>Roles existentes:</h3><pre>";
    print_r($roles);
    echo "</pre>";

    // Mapear roles por nombre
    $roleMap = [];
    foreach ($roles as $r) {
        $roleMap[$r['nombre']] = $r['id'];
    }

    // Crear roles faltantes
    $rolesNeeded = [
        'admin' => 'Acceso total al sistema',
        'coordinador' => 'Aprueba horarios, gestiona campañas y asesores',
        'supervisor' => 'Gestión operativa: importa dimensionamiento, genera y envía horarios',
        'asesor' => 'Consulta su horario personal',
    ];
    foreach ($rolesNeeded as $rolName => $rolDesc) {
        if (!isset($roleMap[$rolName])) {
            $pdo->exec("INSERT INTO roles (nombre, descripcion) VALUES ('$rolName', '$rolDesc') ON CONFLICT (nombre) DO NOTHING");
            $stmt = $pdo->query("SELECT id FROM roles WHERE nombre = '$rolName'");
            $roleMap[$rolName] = $stmt->fetchColumn();
            echo "<p>Rol '$rolName' creado con ID: {$roleMap[$rolName]}</p>";
        }
    }

    // Usuarios de prueba
    $users = [
        [
            'nombre' => 'Ricardo',
            'apellido' => 'Admin',
            'email' => 'zajo.deox@gmail.com',
            'password' => 'Zick913!',
            'rol' => 'admin'
        ],
        [
            'nombre' => 'Carlos',
            'apellido' => 'Mendoza',
            'email' => 'carlos@turnoflow.com',
            'password' => 'coord123',
            'rol' => 'coordinador'
        ],
        [
            'nombre' => 'Maria',
            'apellido' => 'López',
            'email' => 'maria@turnoflow.com',
            'password' => 'supervisor123',
            'rol' => 'supervisor'
        ],
        [
            'nombre' => 'Diana',
            'apellido' => 'Valero',
            'email' => 'diana@turnoflow.com',
            'password' => 'asesor123',
            'rol' => 'asesor'
        ],
    ];

    $stmt = $pdo->prepare("
        INSERT INTO users (nombre, apellido, email, password_hash, rol_id, activo)
        VALUES (:nombre, :apellido, :email, :password_hash, :rol_id, true)
        ON CONFLICT (email) DO UPDATE SET
            password_hash = EXCLUDED.password_hash,
            rol_id = EXCLUDED.rol_id
    ");

    echo "<h3>Insertando usuarios:</h3>";
    foreach ($users as $u) {
        $hash = password_hash($u['password'], PASSWORD_DEFAULT);
        $rolId = $roleMap[$u['rol']] ?? null;

        if (!$rolId) {
            echo "<p style='color:red'>❌ Rol '{$u['rol']}' no encontrado para {$u['email']}</p>";
            continue;
        }

        $stmt->execute([
            ':nombre' => $u['nombre'],
            ':apellido' => $u['apellido'],
            ':email' => $u['email'],
            ':password_hash' => $hash,
            ':rol_id' => $rolId,
        ]);

        echo "<p style='color:green'>✅ {$u['email']} ({$u['rol']}) - Password: {$u['password']}</p>";
    }

    // Mostrar todos los usuarios
    echo "<h3>Usuarios en la base de datos:</h3><pre>";
    $all = $pdo->query("SELECT u.id, u.nombre, u.apellido, u.email, r.nombre as rol, u.activo FROM users u JOIN roles r ON r.id = u.rol_id ORDER BY u.id")->fetchAll(PDO::FETCH_ASSOC);
    print_r($all);
    echo "</pre>";

    echo "<h3 style='color:green'>¡Listo! Ahora puedes iniciar sesión con cualquiera de los usuarios de prueba.</h3>";

} catch (Exception $e) {
    echo "<h3 style='color:red'>Error: " . htmlspecialchars($e->getMessage()) . "</h3>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
