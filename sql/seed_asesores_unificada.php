<?php
/**
 * Script para insertar 40 asesores en la campaña "Unificada"
 * Ejecutar: php sql/seed_asesores_unificada.php
 * O abrir en navegador: http://localhost/system-horario/TurnoFlow/sql/seed_asesores_unificada.php
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = Database::getConnection();

    // Buscar la campaña "Unificada"
    $stmt = $pdo->prepare("SELECT id, nombre FROM campaigns WHERE LOWER(nombre) LIKE '%unificad%'");
    $stmt->execute();
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$campaign) {
        echo "<p style='color:red'>No se encontró la campaña 'Unificada'. Campañas existentes:</p><pre>";
        $all = $pdo->query("SELECT id, nombre FROM campaigns ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        print_r($all);
        echo "</pre>";
        echo "<p>Ingresa el ID de la campaña a usar (modifica la variable \$campaignId en el script).</p>";

        // Si hay campañas, usar la primera que tenga "unificad" o pedir al usuario
        if (empty($all)) {
            die("<p style='color:red'>No hay campañas en la base de datos.</p>");
        }

        // Intentar buscar por nombre parcial
        $campaignId = null;
        foreach ($all as $c) {
            if (stripos($c['nombre'], 'unificad') !== false) {
                $campaignId = $c['id'];
                break;
            }
        }

        if (!$campaignId) {
            echo "<p>Usando la primera campaña disponible: ID={$all[0]['id']} - {$all[0]['nombre']}</p>";
            $campaignId = $all[0]['id'];
        }
    } else {
        $campaignId = $campaign['id'];
        echo "<p style='color:green'>Campaña encontrada: ID={$campaign['id']} - {$campaign['nombre']}</p>";
    }

    // Obtener rol_id de 'asesor'
    $stmt = $pdo->query("SELECT id FROM roles WHERE nombre = 'asesor'");
    $rolAsesor = $stmt->fetchColumn();
    if (!$rolAsesor) {
        die("<p style='color:red'>No se encontró el rol 'asesor'. Ejecuta primero seed_users.php</p>");
    }

    // Lista de asesores: [APELLIDO1 APELLIDO2 NOMBRE1 NOMBRE2]
    $asesoresRaw = [
        'ALENCASTRO DELGADO BRIGITTE SCARLET',
        'ALENCASTRO DELGADO BRITANY SAMANTA',
        'AQUINO ORELLANA OTILDA FRANCISCA',
        'ARIAS FARINANGO ARIEL FERNANDO',
        'ARIAS NARVAEZ ISIS ESCARLETH',
        'CABASCANGO BUSE MARITZA ELIZABETH',
        'COLCHA TOAPANTA CINTHYA VANESSA',
        'DEL CASTILLO MANCHENO ANDRES DARIO',
        'MORENO NORIEGA JORGE OSVALDO',
        'PALOMO RAMIREZ MAYERLI RAQUEL',
        'QUISPE PALACIOS JOSELYN YADIRA',
        'ROMERO CARRION CARLOS HUMBERTO',
        'SAMPEDRO BUÑAY ANA MARIA',
        'TUAPANTA CHANATAXI DANILO JAVIER',
        'ULLCU COCHA WILMER MARIO',
        'ZAMBRANO SOLANO DIEGO ALEXANDER',
        'UCHUPANTA CUASPA JONATHAN JOSE',
        'CAICEDO SIMBAÑA MATEO ANDRES',
        'MORALES LIMA ERIKA ALEXANDRA',
        'TACURI QUICHIMBO DAYANA LIZETH',
        'ZUÑIGA MORENO MISHELL STEFANIA',
        'VALENCIA GARZON BRYAN ORLANDO',
        'ALMEIDA MAZON DEISI DEL ROCIO',
        'CHUSQUILLO SALAZAR MACIEL LESLIE',
        'BENALCAZAR FIALLOS KEVIN ENRIQUE',
        'CANELOS SALAZAR DANA SOFIA',
        'CANAR LUCERO VICTOR HUGO',
        'CEVALLOS SOLORZANO BRANDON AARON',
        'MALLA CUENCA BAYRON AUGUSTO',
        'NAVARRO TAPIA ANDRES ALEJANDRO',
        'ASPIAZU PILLAJO ANGELICA PAULINA',
        'CHINGA PITIUR TATIANA LIZBETH',
        'MENA RIERA LUIS FERNANDO',
        'MUÑOZ MORENO THOMAS SEBASTIAN',
        'ENRIQUEZ JARAMILLO WENDY JAZMIN',
        'ZAMBRANO NAPA TANIA LISBETH',
        'GUANOLUISA REMACHE CHRISTIAN ALEXANDER',
        'LOAIZA SISA MISHELLE JAZMIN',
        'NICOLALDE GARCIA DAIVERLYN IVETH',
        'BENALCAZAR DUARTE JEANNETH GISSELA',
    ];

    // Parsear nombres: formato "APELLIDO1 APELLIDO2 NOMBRE1 NOMBRE2"
    // Casos especiales: "DEL CASTILLO" es un apellido compuesto
    // "ALMEIDA MAZON DEISI DEL ROCIO" - "DEL ROCIO" es parte del nombre
    function parsearNombre($raw) {
        $parts = explode(' ', trim($raw));

        // Casos especiales de apellidos compuestos
        if ($parts[0] === 'DEL' && count($parts) >= 4) {
            // DEL CASTILLO MANCHENO ANDRES DARIO
            $apellidos = $parts[0] . ' ' . $parts[1] . ' ' . $parts[2];
            $nombres = implode(' ', array_slice($parts, 3));
            return [$nombres, $apellidos];
        }

        // Caso general: primeras 2 palabras = apellidos, resto = nombres
        $apellidos = $parts[0] . ' ' . $parts[1];
        $nombreParts = array_slice($parts, 2);
        $nombres = implode(' ', $nombreParts);

        return [$nombres, $apellidos];
    }

    // Función para generar email único
    function generarEmail($nombres, $apellidos) {
        $nombre = strtolower(explode(' ', trim($nombres))[0]);
        $apellido = strtolower(explode(' ', trim($apellidos))[0]);
        // Reemplazar caracteres especiales
        $nombre = str_replace(['ñ', 'á', 'é', 'í', 'ó', 'ú', 'ü'], ['n', 'a', 'e', 'i', 'o', 'u', 'u'], $nombre);
        $apellido = str_replace(['ñ', 'á', 'é', 'í', 'ó', 'ú', 'ü'], ['n', 'a', 'e', 'i', 'o', 'u', 'u'], $apellido);
        return $nombre . '.' . $apellido . '@turnoflow.local';
    }

    $pdo->beginTransaction();

    $stmtAdvisor = $pdo->prepare("
        INSERT INTO advisors (nombres, apellidos, cedula, campaign_id, tipo_contrato, hora_inicio_contrato, hora_fin_contrato, estado, fecha_ingreso)
        VALUES (:nombres, :apellidos, :cedula, :campaign_id, 'completo', 0, 23, 'activo', CURRENT_DATE)
        ON CONFLICT (cedula) DO UPDATE SET
            nombres = EXCLUDED.nombres,
            apellidos = EXCLUDED.apellidos,
            campaign_id = EXCLUDED.campaign_id
        RETURNING id
    ");

    $stmtConstraints = $pdo->prepare("
        INSERT INTO advisor_constraints (advisor_id, tiene_vpn, permite_extras, max_horas_dia, tiene_restriccion_medica)
        VALUES (:advisor_id, false, true, 10, false)
        ON CONFLICT (advisor_id) DO NOTHING
    ");

    $stmtUser = $pdo->prepare("
        INSERT INTO users (nombre, apellido, email, password_hash, rol_id, activo)
        VALUES (:nombre, :apellido, :email, :password_hash, :rol_id, true)
        ON CONFLICT (email) DO NOTHING
    ");

    $defaultPassword = password_hash('asesor123', PASSWORD_DEFAULT);

    echo "<h3>Insertando 40 asesores en campaña ID={$campaignId}:</h3>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>#</th><th>Nombres</th><th>Apellidos</th><th>Cédula</th><th>Email</th><th>Estado</th></tr>";

    $count = 0;
    $emailsUsados = [];

    foreach ($asesoresRaw as $i => $raw) {
        [$nombres, $apellidos] = parsearNombre($raw);

        // Generar cédula ficticia única
        $cedula = 'UNI' . str_pad($i + 1, 4, '0', STR_PAD_LEFT);

        // Generar email único
        $email = generarEmail($nombres, $apellidos);
        // Si el email ya se usó, agregar número
        if (in_array($email, $emailsUsados)) {
            $email = str_replace('@', ($i + 1) . '@', $email);
        }
        $emailsUsados[] = $email;

        try {
            // Insertar asesor
            $stmtAdvisor->execute([
                ':nombres' => ucwords(strtolower($nombres)),
                ':apellidos' => ucwords(strtolower($apellidos)),
                ':cedula' => $cedula,
                ':campaign_id' => $campaignId,
            ]);
            $advisorId = $stmtAdvisor->fetchColumn();

            // Insertar constraints por defecto
            $stmtConstraints->execute([':advisor_id' => $advisorId]);

            // Insertar usuario con rol asesor
            $stmtUser->execute([
                ':nombre' => ucwords(strtolower($nombres)),
                ':apellido' => ucwords(strtolower($apellidos)),
                ':email' => $email,
                ':password_hash' => $defaultPassword,
                ':rol_id' => $rolAsesor,
            ]);

            $count++;
            echo "<tr><td>{$count}</td><td>" . ucwords(strtolower($nombres)) . "</td><td>" . ucwords(strtolower($apellidos)) . "</td><td>{$cedula}</td><td>{$email}</td><td style='color:green'>OK</td></tr>";
        } catch (Exception $e) {
            echo "<tr><td>" . ($i + 1) . "</td><td>" . ucwords(strtolower($nombres)) . "</td><td>" . ucwords(strtolower($apellidos)) . "</td><td>{$cedula}</td><td>{$email}</td><td style='color:red'>" . htmlspecialchars($e->getMessage()) . "</td></tr>";
        }
    }

    echo "</table>";

    $pdo->commit();

    echo "<h3 style='color:green'>Se insertaron {$count} asesores correctamente.</h3>";
    echo "<p>Password por defecto para todos: <strong>asesor123</strong></p>";

    // Mostrar resumen
    $total = $pdo->prepare("SELECT COUNT(*) FROM advisors WHERE campaign_id = :cid");
    $total->execute([':cid' => $campaignId]);
    echo "<p>Total de asesores en la campaña: <strong>" . $total->fetchColumn() . "</strong></p>";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<h3 style='color:red'>Error: " . htmlspecialchars($e->getMessage()) . "</h3>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
