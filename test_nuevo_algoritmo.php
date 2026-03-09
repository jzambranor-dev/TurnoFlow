<?php
/**
 * Test: Nuevo algoritmo de asignación por bloques
 */

define('BASE_PATH', __DIR__);
define('APP_PATH', __DIR__ . '/app');
define('BASE_URL', '/system-horario/TurnoFlow/public');

require_once __DIR__ . '/config/database.php';
require_once APP_PATH . '/Services/ScheduleBuilder.php';

$pdo = Database::getConnection();

// Obtener schedule
$stmt = $pdo->query("SELECT * FROM schedules WHERE id = 1");
$schedule = $stmt->fetch(PDO::FETCH_ASSOC);

echo "=== REGENERANDO HORARIO CON NUEVO ALGORITMO ===\n";
echo "Periodo: {$schedule['periodo_mes']}/{$schedule['periodo_anio']}\n\n";

// Usar el nuevo builder
$builder = new \App\Services\ScheduleBuilder($pdo);
$inserted = $builder->build(
    1,
    1,
    $schedule['fecha_inicio'],
    $schedule['fecha_fin']
);

echo "Asignaciones insertadas: $inserted\n\n";

// Verificar dimensionamiento vs cobertura
echo "=== VERIFICACION DE DIMENSIONAMIENTO ===\n";
$stmt = $pdo->query("
    SELECT
        sr.fecha,
        sr.hora,
        sr.asesores_requeridos as requeridos,
        COALESCE(COUNT(sa.id), 0) as asignados,
        sr.asesores_requeridos - COALESCE(COUNT(sa.id), 0) as deficit
    FROM staffing_requirements sr
    LEFT JOIN shift_assignments sa
        ON sa.campaign_id = sr.campaign_id
        AND sa.fecha = sr.fecha
        AND sa.hora = sr.hora
        AND sa.schedule_id = 1
    WHERE sr.campaign_id = 1
    AND sr.asesores_requeridos > 0
    GROUP BY sr.fecha, sr.hora, sr.asesores_requeridos
    HAVING sr.asesores_requeridos > COALESCE(COUNT(sa.id), 0)
    ORDER BY sr.fecha, sr.hora
    LIMIT 20
");
$deficits = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($deficits)) {
    echo "EXCELENTE! Dimensionamiento 100% cubierto.\n\n";
} else {
    echo "ALERTA: Hay " . count($deficits) . " franjas con deficit:\n";
    foreach ($deficits as $d) {
        echo "  {$d['fecha']} {$d['hora']}:00 - Req: {$d['requeridos']}, Asig: {$d['asignados']}, Deficit: {$d['deficit']}\n";
    }
    echo "\n";
}

// Verificar que Vanessa NO trabaja sab/dom
echo "=== VERIFICACION DE DIAS DE DESCANSO CONFIGURADOS ===\n";
$stmt = $pdo->query("
    SELECT
        sa.fecha,
        EXTRACT(DOW FROM sa.fecha) as dia_semana,
        CASE EXTRACT(DOW FROM sa.fecha)
            WHEN 0 THEN 'Domingo'
            WHEN 6 THEN 'Sabado'
            ELSE 'Otro'
        END as dia_nombre,
        COUNT(*) as horas
    FROM shift_assignments sa
    WHERE sa.schedule_id = 1
    AND sa.advisor_id = 8  -- Vanessa
    AND EXTRACT(DOW FROM sa.fecha) IN (0, 6)  -- Sab o Dom
    GROUP BY sa.fecha
    ORDER BY sa.fecha
");
$vanessaFinSemana = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($vanessaFinSemana)) {
    echo "CORRECTO: Vanessa NO trabaja sabados ni domingos (sus dias configurados).\n\n";
} else {
    echo "ERROR: Vanessa trabaja en sus dias de descanso configurados:\n";
    foreach ($vanessaFinSemana as $v) {
        echo "  {$v['fecha']} ({$v['dia_nombre']}): {$v['horas']} horas\n";
    }
    echo "\n";
}

// Verificar estructura de horarios (bloques continuos vs fragmentados)
echo "=== ESTRUCTURA DE HORARIOS ===\n";
$stmt = $pdo->query("
    SELECT
        a.id,
        a.nombres || ' ' || a.apellidos as nombre,
        sa.fecha,
        array_agg(sa.hora ORDER BY sa.hora) as horas
    FROM shift_assignments sa
    JOIN advisors a ON a.id = sa.advisor_id
    WHERE sa.schedule_id = 1
    AND sa.fecha BETWEEN '2026-03-01' AND '2026-03-05'
    GROUP BY a.id, a.nombres, a.apellidos, sa.fecha
    ORDER BY sa.fecha, a.id
");
$horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$ultimaFecha = '';
foreach ($horarios as $h) {
    if ($h['fecha'] !== $ultimaFecha) {
        $dow = date('D', strtotime($h['fecha']));
        echo "\n--- {$h['fecha']} ($dow) ---\n";
        $ultimaFecha = $h['fecha'];
    }

    // Parsear array PostgreSQL
    $horas = trim($h['horas'], '{}');
    $horasArr = array_map('intval', explode(',', $horas));

    // Agrupar en bloques continuos
    $bloques = [];
    $inicio = $horasArr[0];
    $fin = $horasArr[0];
    for ($i = 1; $i < count($horasArr); $i++) {
        if ($horasArr[$i] === $fin + 1) {
            $fin = $horasArr[$i];
        } else {
            $bloques[] = sprintf('%02d:00-%02d:00', $inicio, $fin + 1);
            $inicio = $horasArr[$i];
            $fin = $horasArr[$i];
        }
    }
    $bloques[] = sprintf('%02d:00-%02d:00', $inicio, $fin + 1);

    $nombre = explode(' ', $h['nombre'])[0];
    echo str_pad($nombre, 12) . ": " . implode(' + ', $bloques) . " (" . count($horasArr) . "h)\n";
}

// Resumen de horas por asesor
echo "\n=== HORAS POR ASESOR ===\n";
$stmt = $pdo->query("
    SELECT
        a.nombres || ' ' || a.apellidos as nombre,
        COUNT(*) as horas_totales,
        COUNT(DISTINCT sa.fecha) as dias_trabajados,
        31 - COUNT(DISTINCT sa.fecha) as dias_libres,
        ac.dias_descanso
    FROM shift_assignments sa
    JOIN advisors a ON a.id = sa.advisor_id
    LEFT JOIN advisor_constraints ac ON ac.advisor_id = a.id
    WHERE sa.schedule_id = 1
    GROUP BY a.id, a.nombres, a.apellidos, ac.dias_descanso
    ORDER BY horas_totales DESC
");
$resumen = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($resumen as $r) {
    $tieneConfig = $r['dias_descanso'] !== '{}' && $r['dias_descanso'] !== null;
    $configStr = $tieneConfig ? "[Config: {$r['dias_descanso']}]" : "[Sin config]";
    echo sprintf("%-35s: %3d horas, %2d dias trabajados, %2d libres %s\n",
        $r['nombre'], $r['horas_totales'], $r['dias_trabajados'], $r['dias_libres'], $configStr);
}

// Totales
echo "\n=== TOTALES ===\n";
$stmt = $pdo->query("SELECT COUNT(*) FROM shift_assignments WHERE schedule_id = 1");
$totalAsig = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(asesores_requeridos) FROM staffing_requirements WHERE campaign_id = 1 AND asesores_requeridos > 0");
$totalReq = $stmt->fetchColumn();

echo "Total dimensionamiento: $totalReq horas-asesor\n";
echo "Total asignaciones: $totalAsig\n";
echo "Cobertura: " . round($totalAsig / $totalReq * 100, 1) . "%\n";

echo "\n=== TEST COMPLETADO ===\n";
