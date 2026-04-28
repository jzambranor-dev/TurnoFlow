<?php
/**
 * TurnoFlow Web Installer
 * Self-contained multi-step installer wizard.
 * No external dependencies required — works before .env or vendor/ exist.
 */

// ─── Security: Block if already installed ────────────────────────────────────
$lockFile = dirname(__DIR__) . '/.installed';
if (file_exists($lockFile)) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>TurnoFlow</title></head>';
    echo '<body style="font-family:Inter,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f1f5f9">';
    echo '<div style="text-align:center;padding:40px;background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.08)">';
    echo '<h1 style="color:#2563eb">TurnoFlow</h1>';
    echo '<p style="color:#64748b;font-size:18px">TurnoFlow ya esta instalado.</p>';
    echo '<p style="color:#94a3b8;font-size:14px">Si necesitas reinstalar, elimina el archivo <code>.installed</code> del directorio raiz del proyecto.</p>';
    echo '</div></body></html>';
    exit;
}

session_start();

// ─── CSRF helpers ────────────────────────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_validate(): bool {
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals(csrf_token(), $token);
}

// ─── Session install data helpers ────────────────────────────────────────────
function install_set(string $key, mixed $value): void {
    $_SESSION['install'][$key] = $value;
}

function install_get(string $key, mixed $default = null): mixed {
    return $_SESSION['install'][$key] ?? $default;
}

// ─── AJAX endpoint: test DB connection ───────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'test_db') {
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }
    $host = $input['host'] ?? '';
    $port = $input['port'] ?? '';
    $name = $input['name'] ?? '';
    $user = $input['user'] ?? '';
    $pass = $input['pass'] ?? '';
    if (!$host || !$port || !$name || !$user) {
        echo json_encode(['success' => false, 'message' => 'Todos los campos son requeridos (excepto password).']);
        exit;
    }
    try {
        $dsn = "pgsql:host={$host};port={$port};dbname={$name}";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $version = $pdo->query('SELECT version()')->fetchColumn();
        echo json_encode(['success' => true, 'message' => 'Conexion exitosa.', 'version' => $version]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error de conexion: ' . $e->getMessage()]);
    }
    exit;
}

// ─── Step routing ────────────────────────────────────────────────────────────
$step = (int)($_GET['step'] ?? $_POST['step'] ?? 1);
if ($step < 1 || $step > 7) $step = 1;

$error = '';
$success = '';

// ─── Handle POST submissions ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $error = 'Token CSRF invalido. Recarga la pagina e intenta de nuevo.';
    } else {
        switch ($step) {
            case 3: // Save DB config
                $dbHost = trim($_POST['db_host'] ?? 'localhost');
                $dbPort = trim($_POST['db_port'] ?? '5432');
                $dbName = trim($_POST['db_name'] ?? 'turnoflow');
                $dbUser = trim($_POST['db_user'] ?? '');
                $dbPass = $_POST['db_pass'] ?? '';
                if (!$dbHost || !$dbPort || !$dbName || !$dbUser) {
                    $error = 'Todos los campos son requeridos (excepto password).';
                } else {
                    install_set('db_host', $dbHost);
                    install_set('db_port', $dbPort);
                    install_set('db_name', $dbName);
                    install_set('db_user', $dbUser);
                    install_set('db_pass', $dbPass);
                    header('Location: install.php?step=4');
                    exit;
                }
                break;

            case 4: // Execute SQL schema
                $dbHost = install_get('db_host');
                $dbPort = install_get('db_port');
                $dbName = install_get('db_name');
                $dbUser = install_get('db_user');
                $dbPass = install_get('db_pass');
                if (!$dbHost || !$dbName || !$dbUser) {
                    header('Location: install.php?step=3');
                    exit;
                }
                try {
                    $dsn = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}";
                    $pdo = new PDO($dsn, $dbUser, $dbPass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    ]);
                } catch (PDOException $e) {
                    $error = 'No se pudo conectar a la base de datos: ' . $e->getMessage();
                    $step = 3;
                    break;
                }
                $sqlDir = dirname(__DIR__) . '/sql/';
                $sqlFiles = [
                    'schema.sql',
                    'permissions.sql',
                    'migration_break.sql',
                    'migration_campaign_activities.sql',
                    'migration_modalidad_advisor.sql',
                    'migration_shared_advisors.sql',
                    'migrations/003_add_advisor_checkins.sql',
                    'migrations/004_holidays_and_system_params.sql',
                    'migrations/005_api_tokens.sql',
                    'migrations/006_notifications.sql',
                    'migrations/007_audit_log.sql',
                    'migrations/008_performance_indexes.sql',
                    'migrations/009_settings_edit_permission.sql',
                    'migrations/010_break_compliance.sql',
                    'migrations/011_break_rules_and_daily_data.sql',
                    'migrations/012_break_imports_date_based.sql',
                ];
                $results = [];
                foreach ($sqlFiles as $file) {
                    $path = $sqlDir . $file;
                    if (!file_exists($path)) {
                        $results[] = ['file' => $file, 'ok' => false, 'message' => 'Archivo no encontrado'];
                        continue;
                    }
                    $sql = file_get_contents($path);
                    try {
                        $statements = splitPostgresSQL($sql);
                        foreach ($statements as $stmt) {
                            $stmt = trim($stmt);
                            if ($stmt === '' || str_starts_with($stmt, '--')) continue;
                            $pdo->exec($stmt);
                        }
                        $results[] = ['file' => $file, 'ok' => true, 'message' => 'OK'];
                    } catch (PDOException $e) {
                        $results[] = ['file' => $file, 'ok' => false, 'message' => $e->getMessage()];
                    }
                }
                install_set('sql_results', $results);
                $allOk = !array_filter($results, fn($r) => !$r['ok']);
                install_set('schema_done', $allOk);
                break;

            case 5: // Create admin user
                $nombre   = trim($_POST['nombre'] ?? '');
                $apellido = trim($_POST['apellido'] ?? '');
                $email    = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $confirm  = $_POST['password_confirm'] ?? '';
                if (!$nombre || !$apellido || !$email || !$password) {
                    $error = 'Todos los campos son requeridos.';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Email invalido.';
                } elseif (strlen($password) < 8) {
                    $error = 'La contrasena debe tener al menos 8 caracteres.';
                } elseif ($password !== $confirm) {
                    $error = 'Las contrasenas no coinciden.';
                } else {
                    try {
                        $pdo = getInstallerPDO();
                        // Ensure admin role exists
                        $stmt = $pdo->prepare("SELECT id FROM roles WHERE nombre = :nombre");
                        $stmt->execute([':nombre' => 'admin']);
                        $roleId = $stmt->fetchColumn();
                        if (!$roleId) {
                            $pdo->exec("INSERT INTO roles (nombre, descripcion) VALUES ('admin', 'Super administrador del sistema - acceso total')");
                            $roleId = $pdo->lastInsertId();
                        }
                        // Assign all permissions to admin role
                        $pdo->exec("INSERT INTO role_permissions (rol_id, permission_id)
                                    SELECT {$roleId}, p.id FROM permissions p
                                    WHERE NOT EXISTS (
                                        SELECT 1 FROM role_permissions rp WHERE rp.rol_id = {$roleId} AND rp.permission_id = p.id
                                    )");
                        // Create user
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (nombre, apellido, email, password_hash, rol_id)
                                               VALUES (:nombre, :apellido, :email, :hash, :rol_id)
                                               ON CONFLICT (email) DO UPDATE SET
                                                   nombre = EXCLUDED.nombre,
                                                   apellido = EXCLUDED.apellido,
                                                   password_hash = EXCLUDED.password_hash,
                                                   rol_id = EXCLUDED.rol_id");
                        $stmt->execute([
                            ':nombre'   => $nombre,
                            ':apellido' => $apellido,
                            ':email'    => $email,
                            ':hash'     => $hash,
                            ':rol_id'   => (int)$roleId,
                        ]);
                        install_set('admin_email', $email);
                        install_set('admin_done', true);
                        header('Location: install.php?step=6');
                        exit;
                    } catch (PDOException $e) {
                        $error = 'Error al crear usuario: ' . $e->getMessage();
                    }
                }
                break;

            case 6: // Generate .env
                $secret  = bin2hex(random_bytes(32));
                $proto   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $script  = $_SERVER['SCRIPT_NAME'] ?? '';
                $baseUrl = $proto . '://' . $host . str_replace('/install.php', '', $script);
                $uploadPath = str_replace('\\', '/', dirname(__DIR__) . '/uploads');

                $envContent = "DB_HOST=" . install_get('db_host') . "\n"
                    . "DB_PORT=" . install_get('db_port') . "\n"
                    . "DB_NAME=" . install_get('db_name') . "\n"
                    . "DB_USER=" . install_get('db_user') . "\n"
                    . "DB_PASS=" . install_get('db_pass') . "\n"
                    . "\n"
                    . "APP_ENV=production\n"
                    . "APP_URL={$baseUrl}\n"
                    . "APP_SECRET={$secret}\n"
                    . "APP_TIMEZONE=America/Guayaquil\n"
                    . "\n"
                    . "UPLOAD_MAX_MB=10\n"
                    . "UPLOAD_PATH={$uploadPath}\n";

                $envPath = dirname(__DIR__) . '/.env';
                $written = file_put_contents($envPath, $envContent);
                if ($written === false) {
                    $error = 'No se pudo escribir el archivo .env. Verifica los permisos del directorio.';
                } else {
                    install_set('env_content', $envContent);
                    install_set('env_done', true);
                    header('Location: install.php?step=7');
                    exit;
                }
                break;

            case 7: // Finalize
                // Create lock file
                file_put_contents($lockFile, date('Y-m-d H:i:s') . "\nInstalled by: " . (install_get('admin_email') ?? 'unknown'));
                install_set('finalized', true);
                break;
        }
    }
}

// ─── SQL splitter that respects $$ delimiters ────────────────────────────────
function splitPostgresSQL(string $sql): array {
    $statements = [];
    $current = '';
    $inDollar = false;
    $lines = explode("\n", $sql);

    foreach ($lines as $line) {
        $trimmed = trim($line);
        // Skip pure comment lines when not inside a $$ block
        if (!$inDollar && str_starts_with($trimmed, '--')) {
            continue;
        }
        $current .= $line . "\n";

        // Count $$ occurrences in this line to track dollar-quoted blocks
        $dollarCount = substr_count($line, '$$');
        if ($dollarCount > 0) {
            // Each pair of $$ toggles the state
            for ($i = 0; $i < $dollarCount; $i++) {
                $inDollar = !$inDollar;
            }
        }

        // If not inside a dollar-quoted block and line ends with ;
        if (!$inDollar && str_ends_with($trimmed, ';')) {
            $stmt = trim($current);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $current = '';
        }
    }

    // Leftover (shouldn't happen in well-formed SQL)
    $leftover = trim($current);
    if ($leftover !== '') {
        $statements[] = $leftover;
    }

    return $statements;
}

// ─── PDO helper using session-stored credentials ─────────────────────────────
function getInstallerPDO(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = "pgsql:host=" . install_get('db_host') . ";port=" . install_get('db_port') . ";dbname=" . install_get('db_name');
    $pdo = new PDO($dsn, install_get('db_user'), install_get('db_pass'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    return $pdo;
}

// ─── Steps metadata ──────────────────────────────────────────────────────────
$steps = [
    1 => 'Bienvenida',
    2 => 'Requisitos',
    3 => 'Base de datos',
    4 => 'Esquema SQL',
    5 => 'Administrador',
    6 => 'Configuracion',
    7 => 'Completado',
];

// Determine completed steps
$completedSteps = [];
if (install_get('db_host')) $completedSteps[] = 3;
if (install_get('schema_done')) $completedSteps[] = 4;
if (install_get('admin_done')) $completedSteps[] = 5;
if (install_get('env_done')) $completedSteps[] = 6;
if (install_get('finalized')) $completedSteps[] = 7;
// Steps 1 and 2 are always "completable" by visiting them
if ($step > 1) $completedSteps[] = 1;
if ($step > 2) $completedSteps[] = 2;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador - TurnoFlow</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            min-height: 100vh;
        }

        /* ─── Progress bar ─── */
        .progress-bar-top {
            position: fixed; top: 0; left: 0; right: 0; height: 4px;
            background: #e2e8f0; z-index: 100;
        }
        .progress-bar-fill {
            height: 100%; background: #2563eb; transition: width .4s ease;
            border-radius: 0 2px 2px 0;
        }

        /* ─── Layout ─── */
        .installer-layout {
            display: flex; min-height: 100vh;
        }

        /* ─── Sidebar ─── */
        .sidebar {
            width: 280px; background: #0f172a; color: #cbd5e1;
            padding: 32px 0; flex-shrink: 0;
            display: flex; flex-direction: column;
        }
        .sidebar-brand {
            padding: 0 24px 32px; border-bottom: 1px solid #1e293b;
            text-align: center;
        }
        .sidebar-brand h1 {
            font-size: 24px; font-weight: 700; color: #fff;
            letter-spacing: -0.5px;
        }
        .sidebar-brand h1 span { color: #2563eb; }
        .sidebar-brand p {
            font-size: 12px; color: #64748b; margin-top: 4px;
            text-transform: uppercase; letter-spacing: 1px;
        }
        .sidebar-steps { padding: 24px 0; flex: 1; }
        .step-item {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 24px; font-size: 14px; transition: all .2s;
        }
        .step-item.active { background: rgba(37,99,235,.15); color: #fff; }
        .step-item.completed .step-icon { background: #22c55e; color: #fff; }
        .step-item.active .step-icon { background: #2563eb; color: #fff; }
        .step-icon {
            width: 28px; height: 28px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 600; flex-shrink: 0;
            background: #334155; color: #94a3b8; transition: all .2s;
        }
        .step-label { font-weight: 500; }
        .sidebar-footer {
            padding: 16px 24px; border-top: 1px solid #1e293b;
            font-size: 11px; color: #475569; text-align: center;
        }

        /* ─── Main content ─── */
        .main-content {
            flex: 1; padding: 48px; display: flex;
            align-items: flex-start; justify-content: center;
        }
        .content-card {
            background: #fff; border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,.06);
            padding: 40px; max-width: 680px; width: 100%;
        }
        .content-card h2 {
            font-size: 24px; font-weight: 700; margin-bottom: 8px;
            color: #0f172a;
        }
        .content-card .subtitle {
            color: #64748b; font-size: 15px; margin-bottom: 28px;
            line-height: 1.5;
        }

        /* ─── Forms ─── */
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block; font-size: 13px; font-weight: 600;
            color: #374151; margin-bottom: 6px;
        }
        .form-group input, .form-group select {
            width: 100%; padding: 10px 14px; border: 1.5px solid #d1d5db;
            border-radius: 8px; font-size: 14px; font-family: inherit;
            transition: border-color .2s; background: #fff;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.1);
        }
        .form-row { display: flex; gap: 16px; }
        .form-row .form-group { flex: 1; }

        /* ─── Buttons ─── */
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 24px; border: none; border-radius: 8px;
            font-size: 14px; font-weight: 600; font-family: inherit;
            cursor: pointer; transition: all .2s; text-decoration: none;
        }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-primary:disabled { background: #93c5fd; cursor: not-allowed; }
        .btn-secondary { background: #e2e8f0; color: #475569; }
        .btn-secondary:hover { background: #cbd5e1; }
        .btn-success { background: #22c55e; color: #fff; }
        .btn-success:hover { background: #16a34a; }
        .btn-warning { background: #f59e0b; color: #fff; }
        .btn-danger { background: #ef4444; color: #fff; }
        .btn-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 32px; }

        /* ─── Checks ─── */
        .check-list { list-style: none; }
        .check-item {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 16px; border-radius: 8px; margin-bottom: 8px;
            font-size: 14px; background: #f8fafc;
        }
        .check-item.pass { background: #f0fdf4; }
        .check-item.fail { background: #fef2f2; }
        .check-icon {
            width: 24px; height: 24px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; font-weight: 700; flex-shrink: 0;
        }
        .check-icon.pass { background: #dcfce7; color: #16a34a; }
        .check-icon.fail { background: #fee2e2; color: #dc2626; }
        .check-hint { font-size: 12px; color: #94a3b8; margin-left: 36px; margin-top: -4px; margin-bottom: 8px; }

        /* ─── Alerts ─── */
        .alert {
            padding: 14px 18px; border-radius: 8px; margin-bottom: 20px;
            font-size: 14px; line-height: 1.5;
        }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .alert-info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
        .alert-warning { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }

        /* ─── SQL results ─── */
        .sql-results { margin: 16px 0; }
        .sql-item {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 14px; border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
        }
        .sql-item:last-child { border-bottom: none; }
        .sql-file { font-family: 'Courier New', monospace; font-weight: 500; flex: 1; }
        .sql-status { font-weight: 600; font-size: 12px; padding: 2px 10px; border-radius: 12px; }
        .sql-status.ok { background: #dcfce7; color: #166534; }
        .sql-status.err { background: #fee2e2; color: #991b1b; }
        .sql-error-msg { font-size: 11px; color: #dc2626; margin-left: 34px; word-break: break-all; }

        /* ─── Env preview ─── */
        .env-preview {
            background: #0f172a; color: #a5f3fc; padding: 20px;
            border-radius: 10px; font-family: 'Courier New', monospace;
            font-size: 13px; line-height: 1.8; overflow-x: auto;
            white-space: pre-wrap; word-break: break-all;
        }

        /* ─── Welcome features ─── */
        .features-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
            margin: 24px 0;
        }
        .feature-card {
            padding: 16px; border-radius: 10px; background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .feature-card h4 { font-size: 14px; color: #0f172a; margin-bottom: 4px; }
        .feature-card p { font-size: 12px; color: #64748b; line-height: 1.5; }

        /* ─── Done page ─── */
        .done-icon {
            width: 72px; height: 72px; border-radius: 50%;
            background: #dcfce7; color: #16a34a;
            display: flex; align-items: center; justify-content: center;
            font-size: 36px; margin: 0 auto 24px;
        }

        /* ─── Loading spinner ─── */
        .spinner {
            width: 18px; height: 18px; border: 2.5px solid #e2e8f0;
            border-top-color: #2563eb; border-radius: 50%;
            animation: spin .6s linear infinite; display: inline-block;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ─── Connection test result ─── */
        #db-test-result {
            margin-top: 12px; padding: 10px 14px; border-radius: 8px;
            font-size: 13px; display: none;
        }

        /* ─── Responsive ─── */
        @media (max-width: 768px) {
            .installer-layout { flex-direction: column; }
            .sidebar {
                width: 100%; flex-direction: row; padding: 16px;
                align-items: center; overflow-x: auto;
            }
            .sidebar-brand { padding: 0 16px 0 0; border-bottom: none; border-right: 1px solid #1e293b; }
            .sidebar-brand p { display: none; }
            .sidebar-steps { display: flex; padding: 0 8px; gap: 4px; }
            .step-item { padding: 8px 12px; }
            .step-label { display: none; }
            .sidebar-footer { display: none; }
            .main-content { padding: 24px 16px; }
            .content-card { padding: 24px; }
            .form-row { flex-direction: column; gap: 0; }
            .features-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- Progress bar -->
<div class="progress-bar-top">
    <div class="progress-bar-fill" style="width: <?= round(($step / 7) * 100) ?>%"></div>
</div>

<div class="installer-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <h1>Turno<span>Flow</span></h1>
            <p>Instalador</p>
        </div>
        <nav class="sidebar-steps">
            <?php foreach ($steps as $num => $label): ?>
                <?php
                    $class = '';
                    if ($num === $step) $class = 'active';
                    elseif (in_array($num, $completedSteps)) $class = 'completed';
                ?>
                <div class="step-item <?= $class ?>">
                    <div class="step-icon">
                        <?php if (in_array($num, $completedSteps) && $num !== $step): ?>
                            &#10003;
                        <?php else: ?>
                            <?= $num ?>
                        <?php endif; ?>
                    </div>
                    <span class="step-label"><?= htmlspecialchars($label) ?></span>
                </div>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            TurnoFlow &copy; <?= date('Y') ?><br>
            Sistema de Gestion de Horarios
        </div>
    </aside>

    <!-- Main content -->
    <main class="main-content">
        <div class="content-card">
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php
            // ═══════════════════════════════════════════════
            // STEP 1: Welcome
            // ═══════════════════════════════════════════════
            if ($step === 1): ?>
                <h2>Bienvenido al instalador de TurnoFlow</h2>
                <p class="subtitle">
                    Este asistente te guiara paso a paso para configurar TurnoFlow en tu servidor.
                    El proceso incluye verificar requisitos, configurar la base de datos, crear el esquema,
                    el usuario administrador y generar la configuracion del sistema.
                </p>

                <div class="features-grid">
                    <div class="feature-card">
                        <h4>Requisitos del sistema</h4>
                        <p>Verificacion automatica de PHP, extensiones y dependencias necesarias.</p>
                    </div>
                    <div class="feature-card">
                        <h4>Base de datos PostgreSQL</h4>
                        <p>Configuracion y prueba de conexion a tu servidor PostgreSQL.</p>
                    </div>
                    <div class="feature-card">
                        <h4>Esquema completo</h4>
                        <p>Creacion automatica de 22+ tablas, vistas, indices y permisos.</p>
                    </div>
                    <div class="feature-card">
                        <h4>Listo para usar</h4>
                        <p>Usuario administrador y archivo de configuracion generados automaticamente.</p>
                    </div>
                </div>

                <div class="alert alert-info">
                    Asegurate de tener una base de datos PostgreSQL creada y las credenciales de acceso antes de continuar.
                </div>

                <div class="btn-actions">
                    <div></div>
                    <a href="install.php?step=2" class="btn btn-primary">Siguiente &rarr;</a>
                </div>

            <?php
            // ═══════════════════════════════════════════════
            // STEP 2: Requirements check
            // ═══════════════════════════════════════════════
            elseif ($step === 2):
                $checks = [];
                // PHP version
                $phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');
                $checks[] = ['label' => 'PHP >= 8.2', 'detail' => 'Version actual: ' . PHP_VERSION, 'pass' => $phpOk, 'required' => true];

                // Extensions
                $requiredExt = ['pdo_pgsql', 'pgsql', 'gd', 'zip', 'intl', 'mbstring', 'xml', 'json', 'openssl'];
                foreach ($requiredExt as $ext) {
                    $loaded = extension_loaded($ext);
                    $checks[] = ['label' => "Extension: {$ext}", 'detail' => $loaded ? 'Cargada' : 'No disponible', 'pass' => $loaded, 'required' => true];
                }

                // Vendor
                $vendorExists = file_exists(dirname(__DIR__) . '/vendor/autoload.php');
                $checks[] = ['label' => 'Composer (vendor/autoload.php)', 'detail' => $vendorExists ? 'Encontrado' : 'No encontrado', 'pass' => $vendorExists, 'required' => true, 'hint' => !$vendorExists ? "Ejecuta 'composer install' en el directorio del proyecto" : null];

                // Uploads dir
                $uploadsDir = dirname(__DIR__) . '/uploads';
                $uploadsOk = is_dir($uploadsDir) && is_writable($uploadsDir);
                $checks[] = ['label' => 'Directorio uploads/ (escribible)', 'detail' => $uploadsOk ? 'OK' : (!is_dir($uploadsDir) ? 'No existe' : 'No es escribible'), 'pass' => $uploadsOk, 'required' => true];

                // .env should NOT exist
                $envNotExists = !file_exists(dirname(__DIR__) . '/.env');
                $checks[] = ['label' => 'Archivo .env no existe (instalacion nueva)', 'detail' => $envNotExists ? 'Correcto — instalacion limpia' : 'Ya existe un .env — eliminalo para reinstalar', 'pass' => $envNotExists, 'required' => true];

                $allPass = !array_filter($checks, fn($c) => $c['required'] && !$c['pass']);
            ?>
                <h2>Verificacion de requisitos</h2>
                <p class="subtitle">El sistema verifica que tu servidor cumpla con todos los requisitos necesarios.</p>

                <ul class="check-list">
                    <?php foreach ($checks as $c): ?>
                        <li class="check-item <?= $c['pass'] ? 'pass' : 'fail' ?>">
                            <div class="check-icon <?= $c['pass'] ? 'pass' : 'fail' ?>">
                                <?= $c['pass'] ? '&#10003;' : '&#10007;' ?>
                            </div>
                            <div>
                                <strong><?= htmlspecialchars($c['label']) ?></strong>
                                <br><span style="font-size:12px;color:#64748b"><?= htmlspecialchars($c['detail']) ?></span>
                            </div>
                        </li>
                        <?php if (!empty($c['hint'])): ?>
                            <div class="check-hint"><?= htmlspecialchars($c['hint']) ?></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>

                <div class="btn-actions">
                    <a href="install.php?step=1" class="btn btn-secondary">&larr; Atras</a>
                    <?php if ($allPass): ?>
                        <a href="install.php?step=3" class="btn btn-primary">Siguiente &rarr;</a>
                    <?php else: ?>
                        <button class="btn btn-primary" disabled>Siguiente &rarr;</button>
                    <?php endif; ?>
                </div>

            <?php
            // ═══════════════════════════════════════════════
            // STEP 3: Database configuration
            // ═══════════════════════════════════════════════
            elseif ($step === 3): ?>
                <h2>Configuracion de base de datos</h2>
                <p class="subtitle">Ingresa los datos de conexion a tu servidor PostgreSQL. La base de datos debe existir previamente.</p>

                <form method="POST" action="install.php?step=3" id="db-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="step" value="3">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="db_host">Host</label>
                            <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars(install_get('db_host', 'localhost')) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="db_port">Puerto</label>
                            <input type="text" id="db_port" name="db_port" value="<?= htmlspecialchars(install_get('db_port', '5432')) ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="db_name">Nombre de la base de datos</label>
                        <input type="text" id="db_name" name="db_name" value="<?= htmlspecialchars(install_get('db_name', 'turnoflow')) ?>" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="db_user">Usuario</label>
                            <input type="text" id="db_user" name="db_user" value="<?= htmlspecialchars(install_get('db_user', '')) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="db_pass">Contrasena</label>
                            <input type="password" id="db_pass" name="db_pass" value="">
                        </div>
                    </div>

                    <button type="button" class="btn btn-secondary" id="btn-test-db" onclick="testConnection()">
                        Probar Conexion
                    </button>
                    <div id="db-test-result"></div>

                    <div class="btn-actions">
                        <a href="install.php?step=2" class="btn btn-secondary">&larr; Atras</a>
                        <button type="submit" class="btn btn-primary" id="btn-next-db" disabled>Siguiente &rarr;</button>
                    </div>
                </form>

                <script>
                async function testConnection() {
                    const btn = document.getElementById('btn-test-db');
                    const result = document.getElementById('db-test-result');
                    const nextBtn = document.getElementById('btn-next-db');

                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner"></span> Probando...';
                    result.style.display = 'none';

                    const payload = {
                        host: document.getElementById('db_host').value,
                        port: document.getElementById('db_port').value,
                        name: document.getElementById('db_name').value,
                        user: document.getElementById('db_user').value,
                        pass: document.getElementById('db_pass').value,
                    };

                    try {
                        const res = await fetch('install.php?action=test_db', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(payload),
                        });
                        const data = await res.json();

                        result.style.display = 'block';
                        if (data.success) {
                            result.className = 'alert alert-success';
                            result.innerHTML = data.message + (data.version ? '<br><small style="opacity:.7">' + data.version + '</small>' : '');
                            nextBtn.disabled = false;
                        } else {
                            result.className = 'alert alert-error';
                            result.textContent = data.message;
                            nextBtn.disabled = true;
                        }
                    } catch (err) {
                        result.style.display = 'block';
                        result.className = 'alert alert-error';
                        result.textContent = 'Error de red: ' + err.message;
                        nextBtn.disabled = true;
                    }

                    btn.disabled = false;
                    btn.innerHTML = 'Probar Conexion';
                }
                </script>

            <?php
            // ═══════════════════════════════════════════════
            // STEP 4: SQL Schema creation
            // ═══════════════════════════════════════════════
            elseif ($step === 4):
                $sqlResults = install_get('sql_results');
            ?>
                <h2>Creacion del esquema SQL</h2>
                <p class="subtitle">Se ejecutaran los archivos SQL para crear tablas, vistas, indices y permisos.</p>

                <?php if ($sqlResults === null): ?>
                    <div class="alert alert-info">
                        Se ejecutaran 13 archivos SQL en orden. Este proceso creara todas las tablas necesarias para TurnoFlow.
                    </div>
                    <form method="POST" action="install.php?step=4" id="schema-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="step" value="4">
                        <div class="btn-actions">
                            <a href="install.php?step=3" class="btn btn-secondary">&larr; Atras</a>
                            <button type="submit" class="btn btn-primary" id="btn-exec-sql" onclick="this.disabled=true;this.innerHTML='<span class=spinner></span> Ejecutando...';this.form.submit();">
                                Ejecutar esquema &rarr;
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <?php
                        $allOk = !array_filter($sqlResults, fn($r) => !$r['ok']);
                    ?>
                    <div class="sql-results">
                        <?php foreach ($sqlResults as $r): ?>
                            <div class="sql-item">
                                <span class="check-icon <?= $r['ok'] ? 'pass' : 'fail' ?>" style="width:20px;height:20px;font-size:11px;">
                                    <?= $r['ok'] ? '&#10003;' : '&#10007;' ?>
                                </span>
                                <span class="sql-file"><?= htmlspecialchars($r['file']) ?></span>
                                <span class="sql-status <?= $r['ok'] ? 'ok' : 'err' ?>"><?= $r['ok'] ? 'OK' : 'ERROR' ?></span>
                            </div>
                            <?php if (!$r['ok']): ?>
                                <div class="sql-error-msg"><?= htmlspecialchars($r['message']) ?></div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($allOk): ?>
                        <div class="alert alert-success">Todas las migraciones se ejecutaron correctamente.</div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            Algunas migraciones fallaron. Revisa los errores arriba.
                            Puedes intentar corregir los problemas y ejecutar de nuevo.
                        </div>
                    <?php endif; ?>

                    <div class="btn-actions">
                        <?php if (!$allOk): ?>
                            <?php
                                // Reset so user can retry
                                install_set('sql_results', null);
                                install_set('schema_done', false);
                            ?>
                            <a href="install.php?step=3" class="btn btn-secondary">&larr; Atras</a>
                            <a href="install.php?step=4" class="btn btn-warning">Reintentar</a>
                        <?php else: ?>
                            <a href="install.php?step=3" class="btn btn-secondary">&larr; Atras</a>
                            <a href="install.php?step=5" class="btn btn-primary">Siguiente &rarr;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php
            // ═══════════════════════════════════════════════
            // STEP 5: Create admin user
            // ═══════════════════════════════════════════════
            elseif ($step === 5): ?>
                <h2>Crear usuario administrador</h2>
                <p class="subtitle">Este sera el primer usuario del sistema con acceso completo (rol admin).</p>

                <form method="POST" action="install.php?step=5" id="admin-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="step" value="5">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="nombre">Nombre</label>
                            <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="apellido">Apellido</label>
                            <input type="text" id="apellido" name="apellido" value="<?= htmlspecialchars($_POST['apellido'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Contrasena (min. 8 caracteres)</label>
                            <input type="password" id="password" name="password" minlength="8" required>
                        </div>
                        <div class="form-group">
                            <label for="password_confirm">Confirmar contrasena</label>
                            <input type="password" id="password_confirm" name="password_confirm" minlength="8" required>
                        </div>
                    </div>

                    <div id="pw-mismatch" class="alert alert-error" style="display:none">Las contrasenas no coinciden.</div>

                    <div class="btn-actions">
                        <a href="install.php?step=4" class="btn btn-secondary">&larr; Atras</a>
                        <button type="submit" class="btn btn-primary" id="btn-admin">Crear administrador &rarr;</button>
                    </div>
                </form>

                <script>
                document.getElementById('admin-form').addEventListener('submit', function(e) {
                    const pw = document.getElementById('password').value;
                    const confirm = document.getElementById('password_confirm').value;
                    const msg = document.getElementById('pw-mismatch');
                    if (pw.length < 8) {
                        e.preventDefault();
                        msg.textContent = 'La contrasena debe tener al menos 8 caracteres.';
                        msg.style.display = 'block';
                        return;
                    }
                    if (pw !== confirm) {
                        e.preventDefault();
                        msg.textContent = 'Las contrasenas no coinciden.';
                        msg.style.display = 'block';
                        return;
                    }
                    msg.style.display = 'none';
                });
                </script>

            <?php
            // ═══════════════════════════════════════════════
            // STEP 6: Generate .env
            // ═══════════════════════════════════════════════
            elseif ($step === 6):
                $envContent = install_get('env_content');
            ?>
                <h2>Generar configuracion</h2>
                <p class="subtitle">Se generara el archivo <code>.env</code> con la configuracion del sistema.</p>

                <?php if ($envContent): ?>
                    <div class="alert alert-success">Archivo .env generado correctamente.</div>
                    <div class="env-preview"><?= htmlspecialchars($envContent) ?></div>
                    <div class="btn-actions">
                        <a href="install.php?step=5" class="btn btn-secondary">&larr; Atras</a>
                        <a href="install.php?step=7" class="btn btn-primary">Siguiente &rarr;</a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        Se generara automaticamente un <code>APP_SECRET</code> seguro y se detectara la URL del sistema.
                    </div>
                    <form method="POST" action="install.php?step=6">
                        <?= csrf_field() ?>
                        <input type="hidden" name="step" value="6">
                        <div class="btn-actions">
                            <a href="install.php?step=5" class="btn btn-secondary">&larr; Atras</a>
                            <button type="submit" class="btn btn-primary">Generar .env &rarr;</button>
                        </div>
                    </form>
                <?php endif; ?>

            <?php
            // ═══════════════════════════════════════════════
            // STEP 7: Done!
            // ═══════════════════════════════════════════════
            elseif ($step === 7): ?>
                <?php if (!install_get('finalized')): ?>
                    <div style="text-align:center">
                        <div class="done-icon">&#10003;</div>
                        <h2>Instalacion lista</h2>
                        <p class="subtitle">Todo esta configurado. Haz clic en "Finalizar" para completar la instalacion.</p>
                    </div>
                    <form method="POST" action="install.php?step=7">
                        <?= csrf_field() ?>
                        <input type="hidden" name="step" value="7">
                        <div class="btn-actions" style="justify-content:center">
                            <button type="submit" class="btn btn-success" style="font-size:16px;padding:14px 40px">
                                Finalizar Instalacion
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div style="text-align:center">
                        <div class="done-icon">&#10003;</div>
                        <h2>TurnoFlow instalado correctamente!</h2>
                        <p class="subtitle">
                            El sistema esta listo para usar. Puedes acceder con el usuario administrador que creaste.
                        </p>
                    </div>

                    <div class="alert alert-success">
                        <strong>Usuario admin:</strong> <?= htmlspecialchars(install_get('admin_email', '')) ?>
                    </div>

                    <div class="alert alert-warning">
                        <strong>Importante:</strong> Por seguridad, elimina este archivo (<code>install.php</code>) o restringelo
                        mediante configuracion del servidor. El archivo <code>.installed</code> impide que se ejecute de nuevo,
                        pero es mejor eliminar el instalador en produccion.
                    </div>

                    <?php
                        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                        $script = $_SERVER['SCRIPT_NAME'] ?? '';
                        $loginUrl = $proto . '://' . $host . str_replace('/install.php', '/index.php', $script);
                    ?>
                    <div class="btn-actions" style="justify-content:center; margin-top: 24px;">
                        <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn btn-primary" style="font-size:16px;padding:14px 40px">
                            Ir al login &rarr;
                        </a>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>
