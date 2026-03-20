<?php
/**
 * ScheduleBuilder v3 - Motor de generación de horarios
 *
 * Filosofía: "Piensa como humano, ejecuta como máquina"
 *
 * Estrategia:
 * 1. Calcular cuota justa de horas y días libres
 * 2. Distribuir días libres equitativamente respetando cobertura
 * 3. Asignar velada con rotación semanal
 * 4. Para cada día, asignar hora por hora siempre al asesor con menos horas,
 *    priorizando continuidad de bloque (turnos reales, no horas sueltas)
 * 5. Reparación final para garantizar 100%
 */

namespace App\Services;

use PDO;

class ScheduleBuilder
{
    private PDO $pdo;
    private array $campaign;
    private array $advisors;
    private array $requirements;    // [fecha][hora] => requeridos
    private array $assignments;     // [fecha][hora] => [advisor_ids]
    private array $advisorSchedule; // [advisor_id][fecha] => [horas]
    private array $advisorMonthHours; // [advisor_id] => total horas mes
    private array $diasLibres;      // [advisor_id] => [fecha => true]
    private array $veladaEligible;
    private array $activityAssignments;
    private array $reservaNocturna;   // [fecha] => horas nocturnas que necesitan VPN
    private array $veladaMap;         // [fecha] => advisor_id de velada ese día
    private int $scheduleId;
    private int $campaignId;
    private int $totalDiasPeriodo;

    // Velada
    private bool $tieneVelada;
    private int $horaFinVelada;
    private array $horasVelada;
    private array $horasTransicion;

    // Break
    private bool $tieneBreak = false;
    private float $breakFraccion = 0.5; // Fracción de hora (30min = 0.5)
    private array $breakAssignments = []; // [advisor_id][fecha] => hora del break

    // Asesores compartidos (solo para referencia: no entran al pool de asignación general,
    // solo trabajan via actividades fijas)
    private array $sharedAdvisorIds = [];     // [advisor_id] => true — IDs de asesores prestados a esta campaña
    private array $externalHours = [];        // [advisor_id] => total horas comprometidas en otras campañas

    // Capacidad individual por asesor
    private array $advisorCapacity;      // [advisor_id] => horas totales esperadas en el mes
    private array $advisorDailyCapacity; // [advisor_id] => horas máximas por día (ventana contrato vs jornada)
    private array $advisorFreeTarget;    // [advisor_id] => días libres objetivo individual

    // Configuración de jornada
    private int $jornadaObjetivo = 8;  // Horas ideales por día trabajado
    private int $jornadaMinima = 6;    // Mínimo aceptable
    private int $veladaDescansoMinimo = 12; // Horas mínimas de descanso tras madrugada (06→18)

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function build(
        int $scheduleId,
        int $campaignId,
        string $fechaInicio,
        string $fechaFin
    ): int {
        $this->scheduleId = $scheduleId;
        $this->campaignId = $campaignId;

        if (!$this->loadCampaign($campaignId)) return 0;
        if (!$this->loadAdvisors($campaignId)) return 0;
        $this->loadSharedAdvisorIds($campaignId);
        if (!$this->loadRequirements($campaignId, $fechaInicio, $fechaFin)) return 0;
        $this->loadActivityAssignments($campaignId);
        $this->cleanupAssignments($scheduleId, $campaignId, $fechaInicio, $fechaFin);
        $this->setupVelada();

        // Inicializar
        $this->assignments = [];
        $this->advisorSchedule = [];
        $this->advisorMonthHours = [];
        $this->diasLibres = [];
        $this->advisorCapacity = [];
        $this->advisorDailyCapacity = [];
        $this->advisorFreeTarget = [];
        foreach ($this->advisors as $id => $adv) {
            $this->advisorMonthHours[$id] = 0;
            $this->advisorSchedule[$id] = [];
            $this->diasLibres[$id] = [];
        }
        // Inicializar también asesores compartidos (para actividades fijas)
        foreach ($this->sharedAdvisorIds as $id => $_) {
            if (!isset($this->advisorMonthHours[$id])) {
                $this->advisorMonthHours[$id] = 0;
                $this->advisorSchedule[$id] = [];
            }
        }
        $this->veladaMap = [];

        // Cargar horas que asesores propios tienen comprometidas en otras campañas
        $this->loadSharedOutCommitments($campaignId, $fechaInicio, $fechaFin);

        $fechas = $this->generateDateRange($fechaInicio, $fechaFin);
        $totalDias = count($fechas);
        $this->totalDiasPeriodo = $totalDias;

        // Calcular cuotas individuales basadas en capacidad real
        $totalHorasReq = 0;
        foreach ($this->requirements as $f => $hrs) {
            $totalHorasReq += array_sum($hrs);
        }
        $numAsesores = count($this->advisors);
        $horasCuotaMensual = $totalHorasReq / $numAsesores;

        // Calcular máximo de libres por día según dimensionamiento (para limitar freeTargets)
        $maxLibresTotalPeriodo = 0;
        foreach ($this->requirements as $f => $hrs) {
            $totalHorasDia = array_sum($hrs);
            $picoHora = max($hrs);
            $asesoresMinDia = max($picoHora, (int)ceil($totalHorasDia / $this->jornadaObjetivo));
            $maxLibresTotalPeriodo += max(0, $numAsesores - $asesoresMinDia);
        }

        // Calcular capacidad diaria y días libres individuales por asesor
        foreach ($this->advisors as $id => $adv) {
            $ventanaContrato = $adv['hora_fin_contrato'] - $adv['hora_inicio_contrato'] + 1;
            $dailyCap = min($ventanaContrato, $this->jornadaObjetivo);
            $this->advisorDailyCapacity[$id] = $dailyCap;

            // Horas externas comprometidas en otras campañas
            $externas = $this->externalHours[$id] ?? 0;

            // Días libres proporcionales a su capacidad diaria
            // Ajustar cuota para asesores con horas externas
            $cuotaAjustada = $horasCuotaMensual + $externas;
            $diasTrabajo = ($dailyCap > 0) ? (int)ceil($cuotaAjustada / $dailyCap) : $totalDias;
            $freeTarget = max(4, min(8, $totalDias - $diasTrabajo));
            $this->advisorFreeTarget[$id] = $freeTarget;

            // Capacidad mensual = días trabajados * capacidad diaria - horas externas
            $this->advisorCapacity[$id] = max(1, ($totalDias - $freeTarget) * $dailyCap - $externas);
        }

        // Validar que el total de libres solicitados no exceda lo que permite el dimensionamiento
        $totalLibresSolicitados = array_sum($this->advisorFreeTarget);
        if ($totalLibresSolicitados > $maxLibresTotalPeriodo) {
            // Distribuir los libres disponibles proporcionalmente a los targets
            $libresDisponibles = $maxLibresTotalPeriodo;
            foreach ($this->advisors as $id => $adv) {
                $proporcion = $this->advisorFreeTarget[$id] / max(1, $totalLibresSolicitados);
                $nuevoTarget = max(1, (int)round($libresDisponibles * $proporcion));
                $this->advisorFreeTarget[$id] = $nuevoTarget;
                $dailyCap = $this->advisorDailyCapacity[$id];
                $externas = $this->externalHours[$id] ?? 0;
                $this->advisorCapacity[$id] = max(1, ($totalDias - $nuevoTarget) * $dailyCap - $externas);
            }
        }

        // FASE 1: Días libres equitativos (con targets individuales)
        $this->distribuirDiasLibres($fechas);

        // FASE 2: Velada rotativa
        $this->asignarVeladaRotativa($fechas);

        // FASE 3: Actividades fijas (solo asesores propios)
        foreach ($fechas as $fecha) {
            $this->asignarActividadesFijas($fecha, false);
        }

        // Pre-análisis: calcular reserva nocturna por fecha
        // Para días con demanda en H22-23, reservar capacidad en asesores VPN
        $this->reservaNocturna = [];
        foreach ($fechas as $fecha) {
            $reqDia = $this->requirements[$fecha] ?? [];
            $horasNocReq = 0;
            foreach ($reqDia as $hora => $req) {
                if ($this->esHoraNocturna($hora) && $req > 0) {
                    $horasNocReq += $req;
                }
            }
            $this->reservaNocturna[$fecha] = $horasNocReq;
        }

        // FASE 4: Asignación principal — hora por hora con equidad
        foreach ($fechas as $fecha) {
            $this->asignarDia($fecha);
        }

        // FASE 5: Consolidar + Reparar iterativamente
        for ($ciclo = 0; $ciclo < 5; $ciclo++) {
            $this->repararDeficit($fechas);
            $cortas = $this->contarJornadasCortas($fechas);
            if ($cortas === 0) break;
            $this->consolidarJornadasCortas($fechas);
        }
        // Reparación final para garantizar 100%
        $this->repararDeficit($fechas);

        // FASE 6: Limpiar multi-gaps residuales
        $this->limpiarMultiGaps($fechas);
        $this->repararDeficit($fechas);

        // FASE 7: Asesores compartidos (prestados) — solo cubren déficit residual
        if (!empty($this->sharedAdvisorIds)) {
            foreach ($fechas as $fecha) {
                $this->asignarActividadesFijas($fecha, true);
            }

            // FASE 7b: Delegar jornadas triviales de asesores propios a compartidos
            $this->delegarJornadasTriviales($fechas);

            // Reparar cualquier déficit creado por la delegación
            $this->repararDeficit($fechas);
        }

        // FASE 8: Asignar breaks (si la campaña lo tiene)
        $this->tieneBreak = $this->toBool($this->campaign['tiene_break'] ?? false);
        if ($this->tieneBreak) {
            $durMin = (int)($this->campaign['duracion_break_min'] ?? 30);
            $this->breakFraccion = round($durMin / 60, 2); // 30min = 0.5
            $this->breakAssignments = [];
            $this->asignarBreaks($fechas);
        }

        // FASE 9: Insertar
        return $this->insertAssignments();
    }

    // ==========================================================
    // BUILD PARCIAL — regenera solo desde una fecha en adelante
    // ==========================================================

    /**
     * Regenera asignaciones solo desde $fromDate en adelante,
     * preservando intactas las asignaciones anteriores a esa fecha.
     */
    public function buildPartial(
        int $scheduleId,
        int $campaignId,
        string $fechaInicio,
        string $fechaFin,
        string $fromDate
    ): int {
        $this->scheduleId = $scheduleId;
        $this->campaignId = $campaignId;

        if (!$this->loadCampaign($campaignId)) return 0;
        if (!$this->loadAdvisors($campaignId)) return 0;
        $this->loadSharedAdvisorIds($campaignId);
        if (!$this->loadRequirements($campaignId, $fechaInicio, $fechaFin)) return 0;
        $this->loadActivityAssignments($campaignId);
        $this->setupVelada();

        // Inicializar estructuras
        $this->assignments = [];
        $this->advisorSchedule = [];
        $this->advisorMonthHours = [];
        $this->diasLibres = [];
        $this->advisorCapacity = [];
        $this->advisorDailyCapacity = [];
        $this->advisorFreeTarget = [];
        foreach ($this->advisors as $id => $adv) {
            $this->advisorMonthHours[$id] = 0;
            $this->advisorSchedule[$id] = [];
            $this->diasLibres[$id] = [];
        }
        foreach ($this->sharedAdvisorIds as $id => $_) {
            if (!isset($this->advisorMonthHours[$id])) {
                $this->advisorMonthHours[$id] = 0;
                $this->advisorSchedule[$id] = [];
            }
        }
        $this->veladaMap = [];

        // Cargar compromisos externos
        $this->loadSharedOutCommitments($campaignId, $fechaInicio, $fechaFin);

        // === PARCIAL: Cargar asignaciones existentes (fecha < fromDate) ===
        $this->loadExistingAssignments($scheduleId, $fromDate);

        // === PARCIAL: Eliminar solo asignaciones >= fromDate ===
        $this->cleanupAssignmentsPartial($scheduleId, $campaignId, $fechaInicio, $fechaFin, $fromDate);

        // Generar rango completo y separar fechas futuras
        $todasFechas = $this->generateDateRange($fechaInicio, $fechaFin);
        $fechasPrevias = [];
        $fechasFuturas = [];
        foreach ($todasFechas as $f) {
            if ($f < $fromDate) {
                $fechasPrevias[] = $f;
            } else {
                $fechasFuturas[] = $f;
            }
        }

        $totalDias = count($todasFechas);
        $this->totalDiasPeriodo = $totalDias;

        // Calcular cuotas (sobre todo el periodo)
        $totalHorasReq = 0;
        foreach ($this->requirements as $f => $hrs) {
            $totalHorasReq += array_sum($hrs);
        }
        $numAsesores = count($this->advisors);
        $horasCuotaMensual = $totalHorasReq / $numAsesores;

        $maxLibresTotalPeriodo = 0;
        foreach ($this->requirements as $f => $hrs) {
            $totalHorasDia = array_sum($hrs);
            $picoHora = max($hrs);
            $asesoresMinDia = max($picoHora, (int)ceil($totalHorasDia / $this->jornadaObjetivo));
            $maxLibresTotalPeriodo += max(0, $numAsesores - $asesoresMinDia);
        }

        foreach ($this->advisors as $id => $adv) {
            $ventanaContrato = $adv['hora_fin_contrato'] - $adv['hora_inicio_contrato'] + 1;
            $dailyCap = min($ventanaContrato, $this->jornadaObjetivo);
            $this->advisorDailyCapacity[$id] = $dailyCap;

            $externas = $this->externalHours[$id] ?? 0;
            $cuotaAjustada = $horasCuotaMensual + $externas;
            $diasTrabajo = ($dailyCap > 0) ? (int)ceil($cuotaAjustada / $dailyCap) : $totalDias;
            $freeTarget = max(4, min(8, $totalDias - $diasTrabajo));
            $this->advisorFreeTarget[$id] = $freeTarget;
            $this->advisorCapacity[$id] = max(1, ($totalDias - $freeTarget) * $dailyCap - $externas);
        }

        $totalLibresSolicitados = array_sum($this->advisorFreeTarget);
        if ($totalLibresSolicitados > $maxLibresTotalPeriodo) {
            $libresDisponibles = $maxLibresTotalPeriodo;
            foreach ($this->advisors as $id => $adv) {
                $proporcion = $this->advisorFreeTarget[$id] / max(1, $totalLibresSolicitados);
                $nuevoTarget = max(1, (int)round($libresDisponibles * $proporcion));
                $this->advisorFreeTarget[$id] = $nuevoTarget;
                $dailyCap = $this->advisorDailyCapacity[$id];
                $externas = $this->externalHours[$id] ?? 0;
                $this->advisorCapacity[$id] = max(1, ($totalDias - $nuevoTarget) * $dailyCap - $externas);
            }
        }

        // === Detectar días libres ya usados en periodo previo ===
        foreach ($this->advisors as $id => $adv) {
            foreach ($fechasPrevias as $f) {
                $horasEnDia = $this->advisorSchedule[$id][$f] ?? [];
                if (empty($horasEnDia)) {
                    // El asesor no trabajó ese día = día libre
                    $this->diasLibres[$id][$f] = true;
                }
            }
        }

        // Ajustar freeTarget restando libres ya usados, para solo distribuir los faltantes
        $freeTargetRestante = [];
        foreach ($this->advisors as $id => $adv) {
            $libresUsados = count($this->diasLibres[$id]);
            $freeTargetRestante[$id] = max(0, $this->advisorFreeTarget[$id] - $libresUsados);
        }

        // FASE 1: Días libres — solo en fechas futuras, con targets ajustados
        $savedFreeTargets = $this->advisorFreeTarget;
        foreach ($this->advisors as $id => $adv) {
            $this->advisorFreeTarget[$id] = $freeTargetRestante[$id];
        }
        $this->distribuirDiasLibres($fechasFuturas);
        $this->advisorFreeTarget = $savedFreeTargets;

        // FASE 2: Velada rotativa — solo fechas futuras
        $this->asignarVeladaRotativa($fechasFuturas);

        // FASE 3: Actividades fijas (asesores propios) — solo fechas futuras
        foreach ($fechasFuturas as $fecha) {
            $this->asignarActividadesFijas($fecha, false);
        }

        // Pre-análisis nocturna
        $this->reservaNocturna = [];
        foreach ($fechasFuturas as $fecha) {
            $reqDia = $this->requirements[$fecha] ?? [];
            $horasNocReq = 0;
            foreach ($reqDia as $hora => $req) {
                if ($this->esHoraNocturna($hora) && $req > 0) {
                    $horasNocReq += $req;
                }
            }
            $this->reservaNocturna[$fecha] = $horasNocReq;
        }

        // FASE 4: Asignación principal — solo fechas futuras
        foreach ($fechasFuturas as $fecha) {
            $this->asignarDia($fecha);
        }

        // FASE 5: Consolidar + Reparar
        for ($ciclo = 0; $ciclo < 5; $ciclo++) {
            $this->repararDeficit($fechasFuturas);
            $cortas = $this->contarJornadasCortas($fechasFuturas);
            if ($cortas === 0) break;
            $this->consolidarJornadasCortas($fechasFuturas);
        }
        $this->repararDeficit($fechasFuturas);

        // FASE 6: Multi-gaps
        $this->limpiarMultiGaps($fechasFuturas);
        $this->repararDeficit($fechasFuturas);

        // FASE 7: Asesores compartidos
        if (!empty($this->sharedAdvisorIds)) {
            foreach ($fechasFuturas as $fecha) {
                $this->asignarActividadesFijas($fecha, true);
            }
            $this->delegarJornadasTriviales($fechasFuturas);
            $this->repararDeficit($fechasFuturas);
        }

        // FASE 8: Breaks
        $this->tieneBreak = $this->toBool($this->campaign['tiene_break'] ?? false);
        if ($this->tieneBreak) {
            $durMin = (int)($this->campaign['duracion_break_min'] ?? 30);
            $this->breakFraccion = round($durMin / 60, 2);
            $this->breakAssignments = [];
            $this->asignarBreaks($fechasFuturas);
        }

        // FASE 9: Insertar — solo las nuevas asignaciones (fechas futuras)
        return $this->insertAssignmentsPartial($fromDate);
    }

    /**
     * Carga asignaciones existentes del schedule para fechas < fromDate.
     * Las registra en advisorSchedule y advisorMonthHours (pero NO en assignments,
     * porque no queremos re-insertarlas).
     */
    private function loadExistingAssignments(int $scheduleId, string $fromDate): void
    {
        $stmt = $this->pdo->prepare("
            SELECT advisor_id, fecha::text AS fecha, hora
            FROM shift_assignments
            WHERE schedule_id = :sid AND fecha < :from_date
            ORDER BY advisor_id, fecha, hora
        ");
        $stmt->execute([':sid' => $scheduleId, ':from_date' => $fromDate]);

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $advId = (int)$row['advisor_id'];
            $fecha = $row['fecha'];
            $hora = (int)$row['hora'];

            if (!isset($this->advisorSchedule[$advId])) {
                $this->advisorSchedule[$advId] = [];
            }
            if (!isset($this->advisorSchedule[$advId][$fecha])) {
                $this->advisorSchedule[$advId][$fecha] = [];
            }
            if (!isset($this->advisorSchedule[$advId][$fecha][$hora])) {
                $this->advisorSchedule[$advId][$fecha][$hora] = true;
                $this->advisorMonthHours[$advId] = ($this->advisorMonthHours[$advId] ?? 0) + 1;
            }
        }
    }

    /**
     * Elimina asignaciones solo desde $fromDate en adelante.
     */
    private function cleanupAssignmentsPartial(
        int $scheduleId,
        int $campaignId,
        string $fechaInicio,
        string $fechaFin,
        string $fromDate
    ): void {
        // Limpiar asignaciones de otros schedules borrador para esta campaña (solo >= fromDate)
        $stmt = $this->pdo->prepare("
            DELETE FROM shift_assignments sa USING schedules s
            WHERE sa.schedule_id = s.id AND sa.campaign_id = :cid
              AND sa.fecha BETWEEN :from_date AND :ff AND s.id <> :sid
              AND s.status IN ('borrador', 'rechazado')
        ");
        $stmt->execute([':cid' => $campaignId, ':from_date' => $fromDate, ':ff' => $fechaFin, ':sid' => $scheduleId]);

        // Limpiar asignaciones de este schedule solo >= fromDate
        $stmt = $this->pdo->prepare("
            DELETE FROM shift_assignments WHERE schedule_id = :sid AND fecha >= :from_date
        ");
        $stmt->execute([':sid' => $scheduleId, ':from_date' => $fromDate]);

        // Limpiar asignaciones de asesores compartidos en otras campañas (solo >= fromDate)
        if (!empty($this->sharedAdvisorIds)) {
            $sharedIds = array_keys($this->sharedAdvisorIds);
            $placeholders = implode(',', array_fill(0, count($sharedIds), '?'));
            $params = array_merge(
                array_map('intval', $sharedIds),
                [$fromDate, $fechaFin, $campaignId]
            );

            $stmt = $this->pdo->prepare("
                DELETE FROM shift_assignments sa
                USING schedules s
                WHERE sa.schedule_id = s.id
                  AND sa.advisor_id IN ($placeholders)
                  AND sa.fecha BETWEEN ? AND ?
                  AND sa.campaign_id <> ?
                  AND s.status IN ('borrador', 'rechazado')
            ");
            $stmt->execute($params);
        }
    }

    /**
     * Inserta solo asignaciones para fechas >= $fromDate.
     */
    private function insertAssignmentsPartial(string $fromDate): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO shift_assignments (schedule_id, advisor_id, campaign_id, fecha, hora, tipo, es_extra, modalidad)
            VALUES (:sid, :aid, :cid, :fecha, :hora, :tipo, :extra, :mod)
            ON CONFLICT (advisor_id, fecha, hora) DO NOTHING
        ");

        $count = 0;
        foreach ($this->assignments as $fecha => $horasData) {
            if ($fecha < $fromDate) continue; // Solo insertar fechas nuevas

            foreach ($horasData as $hora => $advisorIds) {
                foreach ($advisorIds as $advId => $_partial) {
                    $horasHoy = count($this->advisorSchedule[$advId][$fecha] ?? []);
                    $esExtra = $horasHoy > 8;

                    $esBreak = isset($this->breakAssignments[$advId][$fecha])
                        && $this->breakAssignments[$advId][$fecha] === $hora;

                    if ($esBreak) {
                        $tipo = 'break';
                    } else {
                        $tipo = $this->esHoraNocturna($hora) ? 'nocturno' : ($esExtra ? 'extra' : 'normal');
                    }

                    $stmt->execute([
                        ':sid' => $this->scheduleId,
                        ':aid' => $advId,
                        ':cid' => $this->campaignId,
                        ':fecha' => $fecha,
                        ':hora' => $hora,
                        ':tipo' => $tipo,
                        ':extra' => $esBreak ? 'false' : ($esExtra ? 'true' : 'false'),
                        ':mod' => $this->getModalidad($hora),
                    ]);
                    if ($stmt->rowCount() > 0) $count++;
                }
            }
        }
        return $count;
    }

    // ==========================================================
    // FASE 1: DÍAS LIBRES
    // ==========================================================

    private function distribuirDiasLibres(array $fechas): void
    {
        $numAsesores = count($this->advisors);
        $totalDias = count($fechas);

        // Calcular asesores mínimos requeridos por día para cubrir dimensionamiento
        // Usar tanto el pico (max simultáneo) como el total de horas-persona / jornada
        $demandaPico = [];
        $asesoresMinDia = [];
        foreach ($fechas as $f) {
            $req = $this->requirements[$f] ?? [];
            $demandaPico[$f] = !empty($req) ? max($req) : 0;
            $totalHorasDia = !empty($req) ? array_sum($req) : 0;
            // Mínimo de asesores = max(pico simultáneo, ceil(horas_totales / jornada_objetivo))
            $asesoresMinDia[$f] = max(
                $demandaPico[$f],
                (int)ceil($totalHorasDia / $this->jornadaObjetivo)
            );
        }

        // Máximo de libres permitidos por día = numAsesores - asesoresMinDia
        $maxLibresDia = [];
        foreach ($fechas as $f) {
            $maxLibresDia[$f] = max(0, $numAsesores - $asesoresMinDia[$f]);
        }

        $libresEnFecha = array_fill_keys($fechas, 0);

        // Limitar freeTarget global: no dar más libres de los que permite el dimensionamiento
        $totalLibresPermitidos = array_sum($maxLibresDia);
        $totalLibresSolicitados = array_sum($this->advisorFreeTarget);
        if ($totalLibresSolicitados > $totalLibresPermitidos) {
            foreach ($this->advisors as $id => $adv) {
                $proporcion = $this->advisorFreeTarget[$id] / max(1, $totalLibresSolicitados);
                $this->advisorFreeTarget[$id] = max(1, (int)round($totalLibresPermitidos * $proporcion));
            }
        }

        // Separar pool en fines de semana y días de semana
        $weekendDays = [];
        $weekDays = [];
        foreach ($fechas as $f) {
            $dow = (int)date('N', strtotime($f));
            if ($dow >= 6) {
                $weekendDays[] = $f;
            } else {
                $weekDays[] = $f;
            }
        }

        // Ordenar cada pool por mayor margen de libres (mejores para dar libre)
        usort($weekendDays, fn($a, $b) => ($maxLibresDia[$b] ?? 0) <=> ($maxLibresDia[$a] ?? 0));
        usort($weekDays, fn($a, $b) => ($maxLibresDia[$b] ?? 0) <=> ($maxLibresDia[$a] ?? 0));

        $advisorIds = array_keys($this->advisors);

        // FASE 1: Respetar días de descanso configurados (con target individual)
        foreach ($this->advisors as $advisorId => $advisor) {
            if (!$advisor['tiene_descanso_configurado']) continue;
            $target = $this->advisorFreeTarget[$advisorId];

            foreach ($fechas as $fecha) {
                if (count($this->diasLibres[$advisorId]) >= $target) break;

                $dow = (int)date('N', strtotime($fecha)) - 1;
                if (!in_array($dow, $advisor['dias_descanso_parsed'], true)) continue;

                // Guarda estricta: respetar máximo de libres por día según dimensionamiento
                if ($libresEnFecha[$fecha] >= $maxLibresDia[$fecha]) continue;

                $this->diasLibres[$advisorId][$fecha] = true;
                $libresEnFecha[$fecha]++;
            }
        }

        // FASE 2: Distribuir restantes con spacing uniforme y stagger entre asesores
        shuffle($advisorIds);

        foreach ($advisorIds as $idx => $advisorId) {
            $target = $this->advisorFreeTarget[$advisorId];
            $faltantes = $target - count($this->diasLibres[$advisorId]);
            if ($faltantes <= 0) continue;

            // Distribuir proporcionalmente entre fines de semana y días de semana
            $weekendTarget = (count($weekendDays) > 0) ? max(1, (int)round($faltantes * count($weekendDays) / $totalDias)) : 0;
            $weekTarget = $faltantes - $weekendTarget;

            // Asignar fines de semana con spacing
            $offset = ($idx * 2) % max(1, count($weekendDays));
            $asignados = $this->pickSpacedDays($weekendDays, $weekendTarget, $advisorId, $libresEnFecha, $maxLibresDia, $offset);

            // Asignar días de semana con spacing (offset stagger por asesor)
            $offset = ($idx * 3) % max(1, count($weekDays));
            $faltantesWeek = $faltantes - $asignados;
            $this->pickSpacedDays($weekDays, $faltantesWeek, $advisorId, $libresEnFecha, $maxLibresDia, $offset);
        }
    }

    /**
     * Elige días espaciados uniformemente de un pool para dar como libres.
     * Usa un offset para que cada asesor empiece en un punto distinto (stagger).
     * La guarda maxLibresDia garantiza que nunca se comprometa el dimensionamiento.
     * Retorna cuántos días se asignaron.
     */
    private function pickSpacedDays(array $pool, int $cantidad, int $advisorId, array &$libresEnFecha, array $maxLibresDia, int $offset): int
    {
        if ($cantidad <= 0 || empty($pool)) return 0;

        $poolSize = count($pool);
        $interval = max(1, (int)floor($poolSize / $cantidad));
        $asignados = 0;

        // Primera pasada: intervalos uniformes desde offset
        for ($i = 0; $i < $poolSize && $asignados < $cantidad; $i++) {
            $pos = ($offset + $i * $interval) % $poolSize;
            $fecha = $pool[$pos];

            if (isset($this->diasLibres[$advisorId][$fecha])) continue;

            // Guarda estricta: no exceder máximo de libres del dimensionamiento
            if ($libresEnFecha[$fecha] >= ($maxLibresDia[$fecha] ?? 0)) continue;

            $this->diasLibres[$advisorId][$fecha] = true;
            $libresEnFecha[$fecha]++;
            $asignados++;
        }

        // Segunda pasada: si no se completó, recorrer secuencialmente
        if ($asignados < $cantidad) {
            for ($i = 0; $i < $poolSize && $asignados < $cantidad; $i++) {
                $fecha = $pool[($i + $offset) % $poolSize];
                if (isset($this->diasLibres[$advisorId][$fecha])) continue;

                if ($libresEnFecha[$fecha] >= ($maxLibresDia[$fecha] ?? 0)) continue;

                $this->diasLibres[$advisorId][$fecha] = true;
                $libresEnFecha[$fecha]++;
                $asignados++;
            }
        }

        return $asignados;
    }

    // ==========================================================
    // FASE 2: VELADA
    // ==========================================================

    private function asignarVeladaRotativa(array $fechas): void
    {
        if (!$this->tieneVelada || empty($this->veladaEligible)) return;

        // Rotación equitativa: distribuir velada proporcionalmente
        // Con 6 asesores en 31 días → ~5 días cada uno
        $numEligible = count($this->veladaEligible);
        $diasPorTurno = max(1, (int)round(count($fechas) / $numEligible));

        // Mapear cada fecha a su asesor de velada
        $this->veladaMap = [];
        foreach ($fechas as $idx => $fecha) {
            $asesorIdx = (int)floor($idx / $diasPorTurno) % $numEligible;
            $candidato = $this->veladaEligible[$asesorIdx];

            // Si tiene día libre, cancelarlo — la velada tiene prioridad
            unset($this->diasLibres[$candidato][$fecha]);

            $this->veladaMap[$fecha] = $candidato;
        }

        foreach ($fechas as $fecha) {
            $reqDia = $this->requirements[$fecha] ?? [];
            $veladaId = $this->veladaMap[$fecha];
            if (!$veladaId) continue;
            if (isset($this->diasLibres[$veladaId][$fecha])) continue;

            // Turno fijo de velada: madrugada (0-6) + transición (22-23)
            // Total: 9 horas con 16h de descanso continuo entre bloques

            // Bloque madrugada: 0-6 (solo horas con demanda)
            foreach ($this->horasVelada as $h) {
                if (isset($reqDia[$h]) && $reqDia[$h] > 0) {
                    $this->registrarAsignacion($fecha, $veladaId, $h);
                }
            }

            // Bloque transición: 22-23 (solo si hay demanda)
            foreach ($this->horasTransicion as $h) {
                $requeridos = $reqDia[$h] ?? 0;
                $asignados = count($this->assignments[$fecha][$h] ?? []);
                if ($requeridos > $asignados) {
                    $this->registrarAsignacion($fecha, $veladaId, $h);
                }
            }
            // El asesor de velada NO recibe más horas ese día (descanso 06-22)
        }
    }

    // ==========================================================
    // FASE 4: ASIGNACIÓN PRINCIPAL — BLOQUES CONTINUOS
    // ==========================================================

    /**
     * Asigna un día completo construyendo bloques continuos.
     *
     * Estrategia: en lugar de asignar hora por hora (que crea huecos),
     * determinar para cada asesor un bloque continuo óptimo (hora_inicio → hora_fin)
     * que cubra la mayor cantidad de demanda posible.
     *
     * 1. Ordenar asesores por menos horas en el mes (equidad)
     * 2. Para cada asesor, buscar el mejor bloque continuo de jornadaObjetivo horas
     *    donde el déficit sea máximo
     * 3. Asignar el bloque completo
     * 4. Repetir hasta que no haya más déficit
     */
    private function asignarDia(string $fecha): void
    {
        $reqDia = $this->requirements[$fecha] ?? [];
        if (empty($reqDia)) return;

        $veladaId = $this->tieneVelada ? ($this->veladaMap[$fecha] ?? null) : null;

        // Obtener asesores disponibles (no libres, no velada)
        $disponibles = [];
        foreach ($this->advisors as $advId => $adv) {
            if ($advId === $veladaId) continue;
            if (isset($this->diasLibres[$advId][$fecha])) continue;
            $horasYa = count($this->advisorSchedule[$advId][$fecha] ?? []);
            if ($horasYa >= $adv['max_horas_dia']) continue;
            $disponibles[$advId] = $adv;
        }

        // Iterar: asignar un bloque por ronda hasta cubrir todo
        $maxRondas = count($disponibles) * 2;
        for ($ronda = 0; $ronda < $maxRondas; $ronda++) {
            // Calcular déficit actual
            $deficit = [];
            foreach ($reqDia as $hora => $requeridos) {
                $asignados = count($this->assignments[$fecha][$hora] ?? []);
                if ($asignados < $requeridos) {
                    $deficit[$hora] = $requeridos - $asignados;
                }
            }
            if (empty($deficit)) break;

            // Ordenar disponibles por fairness ratio (menor ratio = más infrautilizado)
            // Pre-calcular ratios para evitar recalcular en cada comparación
            $ratiosCache = [];
            foreach ($disponibles as $advId => $adv) {
                $ratiosCache[$advId] = $this->getFairnessRatio($advId);
            }
            uasort($disponibles, function ($a, $b) use ($ratiosCache) {
                return $ratiosCache[$a['id']] <=> $ratiosCache[$b['id']];
            });

            $asignado = false;
            foreach ($disponibles as $advId => $adv) {
                $horasHoy = array_keys($this->advisorSchedule[$advId][$fecha] ?? []);
                $cantidadHoy = count($horasHoy);
                if ($cantidadHoy >= $adv['max_horas_dia']) continue;

                $capacidad = min($adv['max_horas_dia'] - $cantidadHoy, $this->jornadaObjetivo - $cantidadHoy);
                if ($capacidad <= 0) $capacidad = $adv['max_horas_dia'] - $cantidadHoy;
                if ($capacidad <= 0) continue;

                // Si ya tiene horas (actividad fija), extender desde su bloque
                if (!empty($horasHoy)) {
                    $bloque = $this->extenderBloqueExistente($fecha, $advId, $adv, $horasHoy, $deficit, $capacidad);
                } else {
                    // Si tiene VPN y hay déficit nocturno, construir bloque desde la noche hacia atrás
                    $deficitNocturno = false;
                    if ($adv['tiene_vpn']) {
                        foreach ([23, 22] as $hNoc) {
                            if (isset($deficit[$hNoc]) && $deficit[$hNoc] > 0) {
                                $deficitNocturno = true;
                                break;
                            }
                        }
                    }
                    if ($deficitNocturno) {
                        $bloque = $this->buscarBloqueNocturno($fecha, $advId, $adv, $deficit, $capacidad);
                    } else {
                        $bloque = $this->buscarMejorBloqueContinuo($fecha, $advId, $adv, $deficit, $capacidad);
                    }
                }

                if (!empty($bloque)) {
                    foreach ($bloque as $h) {
                        $this->registrarAsignacion($fecha, $advId, $h);
                    }
                    $asignado = true;
                    break; // Re-calcular déficit
                }
            }

            if (!$asignado) break; // Nadie pudo tomar más horas
        }
    }

    /**
     * Extiende un bloque existente hacia adelante y/o atrás.
     * Solo extiende en la dirección que mantiene continuidad.
     */
    private function extenderBloqueExistente(string $fecha, int $advId, array $adv, array $horasHoy, array $deficit, int $capacidad): array
    {
        sort($horasHoy);
        $maxH = max($horasHoy);
        $minH = min($horasHoy);
        $nuevas = [];

        // Extender hacia adelante (solo si hay déficit continuo)
        for ($h = $maxH + 1; $h <= $adv['hora_fin_contrato'] && count($nuevas) < $capacidad; $h++) {
            if (!$this->puedeTrabajarHora($adv, $h)) break;
            if ($this->esHoraNocturna($h) && !$adv['tiene_vpn']) break;
            if (isset($this->assignments[$fecha][$h][$advId])) break;
            if (isset($this->advisorSchedule[$advId][$fecha][$h])) break;
            if (isset($deficit[$h]) && $deficit[$h] > 0) {
                $nuevas[] = $h;
            } else {
                break; // Parar para mantener continuidad
            }
        }

        // Extender hacia atrás
        for ($h = $minH - 1; $h >= $adv['hora_inicio_contrato'] && count($nuevas) < $capacidad; $h--) {
            if (!$this->puedeTrabajarHora($adv, $h)) break;
            if ($this->esHoraNocturna($h) && !$adv['tiene_vpn']) break;
            if (isset($this->assignments[$fecha][$h][$advId])) break;
            if (isset($this->advisorSchedule[$advId][$fecha][$h])) break;
            if (isset($deficit[$h]) && $deficit[$h] > 0) {
                $nuevas[] = $h;
            } else {
                break;
            }
        }

        return $nuevas;
    }

    /**
     * Busca el mejor bloque continuo de hasta $capacidad horas
     * donde cubra la mayor cantidad de déficit posible
     */
    private function buscarMejorBloqueContinuo(string $fecha, int $advId, array $adv, array $deficit, int $capacidad): array
    {
        $mejorBloque = [];
        $mejorScore = 0;

        // Hora mínima y máxima del contrato
        $hMin = $adv['hora_inicio_contrato'];
        $hMax = $adv['hora_fin_contrato'];

        // Reservar capacidad para horas nocturnas
        $reservaNoc = 0;
        if ($adv['tiene_vpn'] && ($this->reservaNocturna[$fecha] ?? 0) > 0) {
            $vpnDisponibles = $this->contarVpnDisponibles($fecha);
            $reservaNoc = $vpnDisponibles > 0 ? (int)ceil(($this->reservaNocturna[$fecha]) / $vpnDisponibles) : 0;
            $capacidad = max($this->jornadaMinima, $capacidad - $reservaNoc);
        }

        // Probar cada hora inicio posible
        for ($inicio = $hMin; $inicio <= $hMax; $inicio++) {
            if (!$this->puedeTrabajarHora($adv, $inicio)) continue;
            if ($this->esHoraNocturna($inicio) && !$adv['tiene_vpn']) continue;

            $bloque = [];
            $score = 0;

            for ($h = $inicio; $h <= $hMax && count($bloque) < $capacidad; $h++) {
                if (!$this->puedeTrabajarHora($adv, $h)) break;
                if ($this->esHoraNocturna($h) && !$adv['tiene_vpn']) break;
                if (isset($this->assignments[$fecha][$h][$advId])) break;
                if (isset($this->advisorSchedule[$advId][$fecha][$h])) break;

                if (isset($deficit[$h]) && $deficit[$h] > 0) {
                    $bloque[] = $h;
                    $score += $deficit[$h] * 10;
                } elseif (!empty($bloque)) {
                    break;
                }
            }

            if (count($bloque) >= $this->jornadaMinima || (count($bloque) > 0 && count($bloque) >= count($deficit))) {
                if ($score > $mejorScore) {
                    $mejorScore = $score;
                    $mejorBloque = $bloque;
                }
            }
        }

        // Si no encontramos un bloque >= jornadaMinima, aceptar bloques más cortos
        if (empty($mejorBloque)) {
            for ($inicio = $hMin; $inicio <= $hMax; $inicio++) {
                if (!$this->puedeTrabajarHora($adv, $inicio)) continue;
                if ($this->esHoraNocturna($inicio) && !$adv['tiene_vpn']) continue;
                $bloque = [];
                $score = 0;
                for ($h = $inicio; $h <= $hMax && count($bloque) < $capacidad; $h++) {
                    if (!$this->puedeTrabajarHora($adv, $h)) break;
                    if ($this->esHoraNocturna($h) && !$adv['tiene_vpn']) break;
                    if (isset($this->assignments[$fecha][$h][$advId])) break;
                    if (isset($this->advisorSchedule[$advId][$fecha][$h])) break;
                    if (isset($deficit[$h]) && $deficit[$h] > 0) {
                        $bloque[] = $h;
                        $score += $deficit[$h] * 10;
                    } elseif (!empty($bloque)) {
                        break;
                    }
                }
                if (count($bloque) >= 3 && $score > $mejorScore) {
                    $mejorScore = $score;
                    $mejorBloque = $bloque;
                }
            }
        }

        return $mejorBloque;
    }

    /**
     * Construye un bloque continuo desde las horas nocturnas (23,22) hacia atrás
     * para asesores VPN que necesitan cubrir la noche sin huecos
     */
    private function buscarBloqueNocturno(string $fecha, int $advId, array $adv, array $deficit, int $capacidad): array
    {
        $bloque = [];

        // Empezar desde H23 hacia abajo
        for ($h = min(23, $adv['hora_fin_contrato']); $h >= $adv['hora_inicio_contrato'] && count($bloque) < $capacidad; $h--) {
            if (!$this->puedeTrabajarHora($adv, $h)) continue;
            if (isset($this->assignments[$fecha][$h][$advId])) break;
            if (isset($this->advisorSchedule[$advId][$fecha][$h])) break;
            if (isset($deficit[$h]) && $deficit[$h] > 0) {
                $bloque[] = $h;
            } elseif (!empty($bloque)) {
                break; // Mantener continuidad
            }
        }

        sort($bloque);

        // Debe alcanzar jornadaMinima o cubrir todo el déficit nocturno
        if (count($bloque) >= $this->jornadaMinima || count($bloque) >= 3) {
            return $bloque;
        }

        return [];
    }

    // ==========================================================
    // FASE 5: CONSOLIDACIÓN DE JORNADAS CORTAS
    // ==========================================================

    /**
     * Si un asesor trabaja menos de la jornada mínima en un día:
     * 1. Intentar extenderle horas adyacentes donde hay sobreasignación
     * 2. Si no se puede completar, quitar sus horas, reasignarlas a otros
     *    y darle el día libre
     */
    private function consolidarJornadasCortas(array $fechas): void
    {
        $this->recalcularCapacity();
        $maxIteraciones = 3; // Iterar porque redistribuir puede crear nuevas jornadas cortas

        for ($iter = 0; $iter < $maxIteraciones; $iter++) {
            $cambios = 0;

            foreach ($fechas as $fecha) {
                foreach ($this->advisors as $advId => $adv) {
                    $horasMap = $this->advisorSchedule[$advId][$fecha] ?? [];
                    if (empty($horasMap)) continue;
                    $horas = array_keys($horasMap);

                    $cantHoras = count($horas);
                    if ($cantHoras >= $this->jornadaMinima) continue;

                    // Es asesor de velada hoy — su turno fijo (0-6+22-23) puede ser <6h
                    // si la demanda nocturna es baja, eso está ok
                    $esVelada = isset($this->veladaMap[$fecha]) && $this->veladaMap[$fecha] === $advId;
                    if ($esVelada) continue;

                    // Paso 1: intentar extender adyacente (primero con déficit, luego sin)
                    sort($horas);
                    $minH = min($horas);
                    $maxH = max($horas);
                    $faltantes = $this->jornadaMinima - $cantHoras;
                    $extendido = 0;

                    // Primera ronda: solo donde hay déficit de cobertura
                    for ($h = $maxH + 1; $h <= min(23, $adv['hora_fin_contrato']) && $extendido < $faltantes; $h++) {
                        if (!$this->puedeTrabajarHora($adv, $h)) break;
                        if ($this->esHoraNocturna($h) && !$adv['tiene_vpn']) break;
                        $req = $this->requirements[$fecha][$h] ?? 0;
                        $asig = count($this->assignments[$fecha][$h] ?? []);
                        if ($req > $asig) {
                            if ($this->registrarAsignacion($fecha, $advId, $h)) {
                                $extendido++;
                            }
                        }
                    }
                    for ($h = $minH - 1; $h >= max(0, $adv['hora_inicio_contrato']) && $extendido < $faltantes; $h--) {
                        if (!$this->puedeTrabajarHora($adv, $h)) break;
                        if ($this->esHoraNocturna($h) && !$adv['tiene_vpn']) break;
                        $req = $this->requirements[$fecha][$h] ?? 0;
                        $asig = count($this->assignments[$fecha][$h] ?? []);
                        if ($req > $asig) {
                            if ($this->registrarAsignacion($fecha, $advId, $h)) {
                                $extendido++;
                            }
                        }
                    }

                    // Segunda ronda: swap con asesores que tienen horas de sobra
                    // Solo hacia adelante y atrás del bloque actual, manteniendo continuidad
                    $horasActualizadas = array_keys($this->advisorSchedule[$advId][$fecha] ?? []);
                    sort($horasActualizadas);
                    $maxH2 = !empty($horasActualizadas) ? max($horasActualizadas) : $maxH;
                    $minH2 = !empty($horasActualizadas) ? min($horasActualizadas) : $minH;
                    for ($h = $maxH2 + 1; $h <= min(23, $adv['hora_fin_contrato']) && $extendido < $faltantes; $h++) {
                        if (!$this->puedeTrabajarHora($adv, $h)) break;
                        if ($this->esHoraNocturna($h) && !$adv['tiene_vpn']) break;
                        $req = $this->requirements[$fecha][$h] ?? 0;
                        if ($req <= 0) break; // Mantener continuidad
                        $victima = $this->buscarVictimaSwap($fecha, $h, $advId);
                        if ($victima !== null) {
                            $this->quitarAsignacion($fecha, $victima, $h);
                            $this->registrarAsignacion($fecha, $advId, $h);
                            $extendido++;
                        } else {
                            break; // No romper continuidad
                        }
                    }
                    for ($h = $minH2 - 1; $h >= max(0, $adv['hora_inicio_contrato']) && $extendido < $faltantes; $h--) {
                        if (!$this->puedeTrabajarHora($adv, $h)) break;
                        if ($this->esHoraNocturna($h) && !$adv['tiene_vpn']) break;
                        $req = $this->requirements[$fecha][$h] ?? 0;
                        if ($req <= 0) break;
                        $victima = $this->buscarVictimaSwap($fecha, $h, $advId);
                        if ($victima !== null) {
                            $this->quitarAsignacion($fecha, $victima, $h);
                            $this->registrarAsignacion($fecha, $advId, $h);
                            $extendido++;
                        } else {
                            break;
                        }
                    }

                    // Re-evaluar
                    $cantFinal = count($this->advisorSchedule[$advId][$fecha] ?? []);
                    if ($cantFinal >= $this->jornadaMinima) {
                        $cambios++;
                        continue;
                    }

                    // Paso 2: no se pudo completar → quitar horas y redistribuir
                    $horasAQuitar = array_keys($this->advisorSchedule[$advId][$fecha] ?? []);
                    foreach ($horasAQuitar as $h) {
                        $this->quitarAsignacion($fecha, $advId, $h);
                    }

                    // Darle libre
                    if (!isset($this->diasLibres[$advId][$fecha])) {
                        $this->diasLibres[$advId][$fecha] = true;
                    }

                    // Redistribuir esas horas a otros asesores
                    foreach ($horasAQuitar as $h) {
                        $req = $this->requirements[$fecha][$h] ?? 0;
                        $asig = count($this->assignments[$fecha][$h] ?? []);
                        if ($asig < $req) {
                            // Buscar otro asesor que pueda absorber esta hora
                            $mejor = $this->buscarReemplazo($fecha, $h, $advId);
                            if ($mejor !== null) {
                                $this->registrarAsignacion($fecha, $mejor, $h);
                            }
                        }
                    }
                    $cambios++;
                }
            }

            if ($cambios === 0) break;
        }
    }

    /**
     * Busca un asesor en el slot dado que puede ceder su hora (swap)
     * Preferir al que tenga más horas ese día y que no quede con jornada corta
     */
    private function buscarVictimaSwap(string $fecha, int $hora, int $excluirId): ?int
    {
        $asignados = $this->assignments[$fecha][$hora] ?? [];
        $mejorId = null;
        $mejorScore = -1.0;

        foreach ($asignados as $advId => $_) {
            if ($advId === $excluirId) continue;
            $horasVictima = array_keys($this->advisorSchedule[$advId][$fecha] ?? []);
            $cantHoras = count($horasVictima);
            if ($cantHoras - 1 < $this->jornadaMinima) continue;

            $esVelada = isset($this->veladaMap[$fecha]) && $this->veladaMap[$fecha] === $advId;
            if ($esVelada) continue;

            // Solo quitar si la hora está al borde del bloque (para no crear huecos)
            sort($horasVictima);
            $minV = min($horasVictima);
            $maxV = max($horasVictima);
            if ($hora !== $minV && $hora !== $maxV) continue; // Solo bordes

            // Preferir víctima con mayor fairness ratio (más sobreutilizada) y más horas hoy
            $score = $this->getFairnessRatio($advId) * 100 + $cantHoras;
            if ($score > $mejorScore) {
                $mejorScore = $score;
                $mejorId = $advId;
            }
        }

        return $mejorId;
    }

    /**
     * Elimina horas aisladas que crean multi-gaps.
     * Si un asesor tiene >1 gap, quitar las horas del bloque más pequeño.
     */
    private function limpiarMultiGaps(array $fechas): void
    {
        foreach ($fechas as $fecha) {
            foreach ($this->advisors as $advId => $adv) {
                $horas = array_keys($this->advisorSchedule[$advId][$fecha] ?? []);
                if (count($horas) < 3) continue;
                sort($horas);

                // Encontrar bloques
                $bloques = [[$horas[0]]];
                for ($i = 1; $i < count($horas); $i++) {
                    if ($horas[$i] === $horas[$i-1] + 1) {
                        $bloques[count($bloques)-1][] = $horas[$i];
                    } else {
                        $bloques[] = [$horas[$i]];
                    }
                }

                if (count($bloques) <= 2) continue; // 0 o 1 gap está ok

                // Más de 1 gap: quedarse con los 2 bloques más grandes, quitar el resto
                usort($bloques, fn($a, $b) => count($b) <=> count($a));
                $conservar = array_merge($bloques[0], $bloques[1]);

                foreach ($horas as $h) {
                    if (!in_array($h, $conservar, true)) {
                        $this->quitarAsignacion($fecha, $advId, $h);
                    }
                }
            }
        }
    }

    private function contarJornadasCortas(array $fechas): int
    {
        $count = 0;
        foreach ($fechas as $fecha) {
            foreach ($this->advisors as $advId => $adv) {
                $horas = $this->advisorSchedule[$advId][$fecha] ?? [];
                if (empty($horas)) continue;
                $esVelada = isset($this->veladaMap[$fecha]) && $this->veladaMap[$fecha] === $advId;
                if ($esVelada) continue;
                if (count($horas) < $this->jornadaMinima) $count++;
            }
        }
        return $count;
    }

    /**
     * Quita una asignación existente
     */
    private function quitarAsignacion(string $fecha, int $advisorId, int $hora): void
    {
        // Quitar de assignments[fecha][hora]
        unset($this->assignments[$fecha][$hora][$advisorId]);

        // Quitar de advisorSchedule[advisor][fecha]
        unset($this->advisorSchedule[$advisorId][$fecha][$hora]);
        if (empty($this->advisorSchedule[$advisorId][$fecha])) {
            unset($this->advisorSchedule[$advisorId][$fecha]);
        }

        // Decrementar horas mensuales
        $this->advisorMonthHours[$advisorId] = max(0, ($this->advisorMonthHours[$advisorId] ?? 0) - 1);
    }

    /**
     * Busca un asesor de reemplazo para cubrir una hora específica
     */
    private function buscarReemplazo(string $fecha, int $hora, int $excluirId): ?int
    {
        $mejorId = null;
        $mejorScore = PHP_INT_MIN;

        foreach ($this->advisors as $advId => $adv) {
            if ($advId === $excluirId) continue;
            if (isset($this->diasLibres[$advId][$fecha])) continue;
            if (isset($this->assignments[$fecha][$hora][$advId])) continue;
            if (!$this->puedeTrabajarHora($adv, $hora)) continue;
            if ($this->esHoraNocturna($hora) && !$adv['tiene_vpn']) continue;

            // Velada: solo horas 0-6 y 22-23
            $esVelada = isset($this->veladaMap[$fecha]) && $this->veladaMap[$fecha] === $advId;
            if ($esVelada && !in_array($hora, $this->horasVelada, true) && !in_array($hora, $this->horasTransicion, true)) continue;

            $horasHoy = count($this->advisorSchedule[$advId][$fecha] ?? []);
            if ($horasHoy >= $adv['max_horas_dia']) continue;

            // SOLO redistribuir a asesores que ya tienen jornada mínima ese día
            // para no crear nuevas jornadas cortas
            if ($horasHoy < $this->jornadaMinima) continue;

            // Preferir asesor que ya trabaja hoy (adyacente) y tiene menor fairness ratio
            $score = (1.0 - $this->getFairnessRatio($advId)) * 1000;

            $horasExistentes = $this->advisorSchedule[$advId][$fecha] ?? [];
            if (isset($horasExistentes[$hora - 1]) || isset($horasExistentes[$hora + 1])) {
                $score += 200;
            }

            if ($score > $mejorScore) {
                $mejorScore = $score;
                $mejorId = $advId;
            }
        }

        return $mejorId;
    }

    // ==========================================================
    // FASE 6: REPARACIÓN FINAL
    // ==========================================================

    private function repararDeficit(array $fechas): void
    {
        $this->recalcularCapacity();
        // Pasada 1: respetar días libres y todas las restricciones
        // Pasada 2: cancelar días libres si es necesario
        // Pasada 3: relajar VPN en horas de transición (22-23) si no hay opción
        // Pasada 4: relajar restricción de multi-gap (permitir 2+ gaps)
        // Pasada 5: permitir +1 hora extra sobre max_horas_dia (último recurso)
        for ($pasada = 1; $pasada <= 5; $pasada++) {
            foreach ($fechas as $fecha) {
                $reqDia = $this->requirements[$fecha] ?? [];

                // Procesar horas tardías primero (son las más difíciles de cubrir)
                krsort($reqDia);
                foreach ($reqDia as $hora => $requeridos) {
                    $asignados = count($this->assignments[$fecha][$hora] ?? []);
                    $faltantes = $requeridos - $asignados;

                    for ($i = 0; $i < $faltantes; $i++) {
                        $mejor = null;
                        $mejorCap = PHP_INT_MIN;

                        foreach ($this->advisors as $advId => $adv) {
                            // Asesor de velada: solo horas 0-6 y 22-23
                            $esVelada = isset($this->veladaMap[$fecha]) && $this->veladaMap[$fecha] === $advId;
                            if ($esVelada && !in_array($hora, $this->horasVelada, true) && !in_array($hora, $this->horasTransicion, true)) continue;

                            $estaLibre = isset($this->diasLibres[$advId][$fecha]);

                            // Pasada 1: respetar días libres
                            // Pasada 2: cancelar días libres
                            // Pasada 3: además relajar VPN para horas 22-23
                            if ($pasada <= 1 && $estaLibre) continue;

                            if (isset($this->assignments[$fecha][$hora][$advId])) continue;
                            // Verificar si la hora ya está ocupada externamente (otra campaña)
                            if (isset($this->advisorSchedule[$advId][$fecha][$hora])) continue;
                            if (!$this->puedeTrabajarHora($adv, $hora)) continue;

                            // VPN: en pasada 3, solo exigir VPN para madrugada real (0-6)
                            if ($pasada < 3) {
                                if ($this->esHoraNocturna($hora) && !$adv['tiene_vpn']) continue;
                            } else {
                                // Solo exigir VPN para horas 0-6 (madrugada real)
                                if (in_array($hora, $this->horasVelada, true) && !$adv['tiene_vpn']) continue;
                            }

                            $horasHoy = count($this->advisorSchedule[$advId][$fecha] ?? []);
                            $maxHoy = $pasada >= 5 ? $adv['max_horas_dia'] + 1 : $adv['max_horas_dia'];
                            if ($horasHoy >= $maxHoy) continue;

                            $horasExist = $this->advisorSchedule[$advId][$fecha] ?? [];
                            $esAdyacente = false;
                            $creariaMultiGap = false;
                            if (!empty($horasExist)) {
                                $esAdyacente = isset($horasExist[$hora - 1]) || isset($horasExist[$hora + 1]);
                                if (!$esAdyacente) {
                                    // Verificar si crearía más de 1 gap (inaceptable)
                                    $testHoras = array_merge(array_keys($horasExist), [$hora]);
                                    sort($testHoras);
                                    $gaps = 0;
                                    for ($g = 1; $g < count($testHoras); $g++) {
                                        if ($testHoras[$g] > $testHoras[$g-1] + 1) $gaps++;
                                    }
                                    if ($gaps > 1) $creariaMultiGap = true;
                                }
                            }

                            // No crear turnos con más de 1 gap (excepto pasada 4: priorizar cobertura)
                            if ($pasada < 4 && $horasHoy > 0 && $creariaMultiGap) continue;

                            $cap = ($adv['max_horas_dia'] - $horasHoy) * 10 + (int)((1.0 - $this->getFairnessRatio($advId)) * 500);

                            if ($horasHoy >= $this->jornadaMinima) {
                                $cap += 300;
                            } elseif ($horasHoy > 0) {
                                $cap += 100;
                            } else {
                                $cap -= 200;
                            }

                            if ($esAdyacente) $cap += 150;
                            if ($estaLibre) $cap -= 100;

                            if ($cap > $mejorCap) {
                                $mejorCap = $cap;
                                $mejor = $advId;
                            }
                        }

                        if ($mejor !== null) {
                            // Si estaba libre, cancelar su día libre
                            unset($this->diasLibres[$mejor][$fecha]);
                            $this->registrarAsignacion($fecha, $mejor, $hora);
                        }
                    }
                }
            }
        }
    }

    // ==========================================================
    // EQUIDAD Y CAPACIDAD
    // ==========================================================

    /**
     * Retorna el ratio de equidad: horas_asignadas / capacidad_mensual.
     * Un ratio más bajo significa que el asesor está más "infrautilizado".
     * Permite comparar asesores con distintas capacidades en términos relativos.
     */
    private function getFairnessRatio(int $advisorId): float
    {
        $capacity = $this->advisorCapacity[$advisorId] ?? 1;
        if ($capacity <= 0) $capacity = 1;
        return ($this->advisorMonthHours[$advisorId] ?? 0) / $capacity;
    }

    /**
     * Recalcula advisorCapacity basándose en los días libres reales actuales.
     * Llamar después de que los días libres hayan sido modificados por consolidación o reparación.
     */
    private function recalcularCapacity(): void
    {
        foreach ($this->advisors as $id => $adv) {
            $diasLibresReales = count($this->diasLibres[$id] ?? []);
            $diasTrabajo = $this->totalDiasPeriodo - $diasLibresReales;
            $dailyCap = $this->advisorDailyCapacity[$id] ?? $this->jornadaObjetivo;
            // Restar horas externas comprometidas en otras campañas
            $externas = $this->externalHours[$id] ?? 0;
            $this->advisorCapacity[$id] = max(1, $diasTrabajo * $dailyCap - $externas);
        }
    }

    // ==========================================================
    // SOPORTE
    // ==========================================================

    private function getVeladaAdvisorForWeek(string $fecha): ?int
    {
        if (empty($this->veladaEligible)) return null;
        $weekNumber = (int)date('W', strtotime($fecha));
        $index = $weekNumber % count($this->veladaEligible);
        return $this->veladaEligible[$index];
    }

    private function esHoraNocturna(int $hora): bool
    {
        return in_array($hora, $this->horasVelada, true) || in_array($hora, $this->horasTransicion, true);
    }

    private function registrarAsignacion(string $fecha, int $advisorId, int $hora): bool
    {
        if (isset($this->assignments[$fecha][$hora][$advisorId])) return false;

        // Verificar si la hora ya está ocupada por un compromiso externo (otra campaña)
        // loadSharedOutCommitments pre-carga horas en advisorSchedule sin agregarlas a assignments
        if (isset($this->advisorSchedule[$advisorId][$fecha][$hora])) return false;

        if (!isset($this->assignments[$fecha])) $this->assignments[$fecha] = [];
        if (!isset($this->assignments[$fecha][$hora])) $this->assignments[$fecha][$hora] = [];
        $this->assignments[$fecha][$hora][$advisorId] = true;

        if (!isset($this->advisorSchedule[$advisorId][$fecha])) {
            $this->advisorSchedule[$advisorId][$fecha] = [];
        }
        $this->advisorSchedule[$advisorId][$fecha][$hora] = true;

        $this->advisorMonthHours[$advisorId] = ($this->advisorMonthHours[$advisorId] ?? 0) + 1;
        return true;
    }

    private function contarVpnDisponibles(string $fecha): int
    {
        $count = 0;
        foreach ($this->advisors as $advId => $adv) {
            if (!$adv['tiene_vpn']) continue;
            if (isset($this->diasLibres[$advId][$fecha])) continue;
            $horasHoy = count($this->advisorSchedule[$advId][$fecha] ?? []);
            if ($horasHoy < $adv['max_horas_dia']) $count++;
        }
        return max(1, $count);
    }

    private function contarHuecos(array $horas): int
    {
        if (count($horas) <= 1) return 0;
        sort($horas);
        $huecos = 0;
        for ($i = 1; $i < count($horas); $i++) {
            if ($horas[$i] > $horas[$i - 1] + 1) $huecos++;
        }
        return $huecos;
    }

    private function tieneActividadFija(int $advisorId, string $fecha): bool
    {
        if (empty($this->activityAssignments[$advisorId])) return false;
        $dow = (int)date('N', strtotime($fecha)) - 1;
        foreach ($this->activityAssignments[$advisorId] as $asg) {
            if (in_array($dow, $asg['dias_semana'], true)) return true;
        }
        return false;
    }

    /**
     * Asigna actividades fijas.
     * @param bool $soloCompartidos true = solo asesores prestados (y solo donde hay déficit)
     *                               false = solo asesores propios de la campaña
     */
    private function asignarActividadesFijas(string $fecha, bool $soloCompartidos = false): void
    {
        if (empty($this->activityAssignments)) return;
        $dow = (int)date('N', strtotime($fecha)) - 1;

        foreach ($this->activityAssignments as $advisorId => $assignments) {
            $esPropio = isset($this->advisors[$advisorId]);
            $esCompartido = isset($this->sharedAdvisorIds[$advisorId]);

            if ($soloCompartidos) {
                if (!$esCompartido) continue;
            } else {
                if (!$esPropio) continue;
            }

            if (isset($this->diasLibres[$advisorId][$fecha])) continue;

            foreach ($assignments as $asg) {
                if (!in_array($dow, $asg['dias_semana'], true)) continue;
                for ($h = $asg['hora_inicio']; $h < $asg['hora_fin']; $h++) {
                    if ($soloCompartidos) {
                        // Solo asignar si hay déficit real en esta hora
                        $requeridos = $this->requirements[$fecha][$h] ?? 0;
                        $asignados = count($this->assignments[$fecha][$h] ?? []);
                        if ($asignados >= $requeridos) continue;
                    }
                    $this->registrarAsignacion($fecha, $advisorId, $h);
                }
            }
        }
    }

    /**
     * FASE 7b: Delegar jornadas triviales de asesores propios a compartidos.
     *
     * Si un asesor propio tiene 1-2 horas en un día, y TODAS esas horas ya tienen
     * cobertura suficiente (asignados >= requeridos), entonces quita al asesor propio
     * y deja que el compartido (si ya está cubriendo) o lo asigna. Esto libera capacidad
     * del asesor propio para que la reparación posterior pueda usarlo en otros días.
     *
     * Solo delega si no se pierde cobertura neta.
     */
    private function delegarJornadasTriviales(array $fechas): void
    {
        $umbralTrivial = 2; // Jornadas de 1-2 horas se consideran triviales
        $dow_cache = [];
        foreach ($fechas as $f) {
            $dow_cache[$f] = (int)date('N', strtotime($f)) - 1;
        }

        foreach ($fechas as $fecha) {
            $dow = $dow_cache[$fecha];

            foreach ($this->advisors as $advId => $adv) {
                $horasMap = $this->advisorSchedule[$advId][$fecha] ?? [];
                if (empty($horasMap)) continue;
                $horas = array_keys($horasMap);
                if (count($horas) > $umbralTrivial) continue;

                // No tocar velada
                if (isset($this->veladaMap[$fecha]) && $this->veladaMap[$fecha] === $advId) continue;

                // Verificar que TODAS las horas triviales pueden ser cubiertas por compartidos
                // y que la cobertura no se pierde (hay exceso o el compartido ya está cubriendo)
                $delegaciones = []; // [hora => sharedAdvisorId]
                $puedeDelegarTodo = true;

                foreach ($horas as $hora) {
                    $requeridos = $this->requirements[$fecha][$hora] ?? 0;
                    $asignados = count($this->assignments[$fecha][$hora] ?? []);

                    // Buscar compartido que pueda cubrir esta hora
                    $compartidoEncontrado = null;

                    foreach ($this->sharedAdvisorIds as $sharedId => $_) {
                        if (empty($this->activityAssignments[$sharedId])) continue;

                        $cubreHora = false;
                        foreach ($this->activityAssignments[$sharedId] as $asg) {
                            if (!in_array($dow, $asg['dias_semana'], true)) continue;
                            if ($hora >= $asg['hora_inicio'] && $hora < $asg['hora_fin']) {
                                $cubreHora = true;
                                break;
                            }
                        }
                        if (!$cubreHora) continue;

                        // ¿Ya está asignado en esa hora en esta campaña?
                        $yaAsignado = isset($this->assignments[$fecha][$hora][$sharedId]);

                        if ($yaAsignado) {
                            // El compartido ya cubre esta hora — simplemente quitar al propio
                            // Solo si hay exceso (asignados > requeridos), para no perder cobertura
                            if ($asignados > $requeridos) {
                                $compartidoEncontrado = -1; // Sentinel: solo quitar, no agregar
                                break;
                            }
                        } else {
                            // Compartido disponible — podemos reemplazar: quitar propio, agregar compartido
                            // Verificar que no está ya ocupado en otra campaña a esa hora
                            $ocupadoOtraCampaña = isset($this->advisorSchedule[$sharedId][$fecha][$hora]);
                            if ($ocupadoOtraCampaña) continue;

                            $compartidoEncontrado = $sharedId;
                            break;
                        }
                    }

                    if ($compartidoEncontrado === null) {
                        $puedeDelegarTodo = false;
                        break;
                    }
                    $delegaciones[$hora] = $compartidoEncontrado;
                }

                if (!$puedeDelegarTodo) continue;

                // Ejecutar la delegación
                foreach ($delegaciones as $hora => $sharedId) {
                    $this->quitarAsignacion($fecha, $advId, $hora);
                    if ($sharedId > 0) {
                        // Reemplazar: propio sale, compartido entra
                        $this->registrarAsignacion($fecha, $sharedId, $hora);
                    }
                    // Si sharedId === -1, solo se quita el propio (compartido ya está cubriendo)
                }

                // Dar libre al asesor propio en este día
                if (!isset($this->diasLibres[$advId][$fecha])) {
                    $this->diasLibres[$advId][$fecha] = true;
                }
            }
        }
    }

    // ==========================================================
    // CARGA DE DATOS
    // ==========================================================

    private function loadCampaign(int $campaignId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT id, tiene_velada, hora_inicio_operacion, hora_fin_operacion,
                requiere_vpn_nocturno, hora_inicio_nocturno, hora_fin_nocturno,
                max_horas_dia,
                COALESCE(hora_fin_velada, 8) AS hora_fin_velada,
                COALESCE(hora_inicio_teletrabajo, 0) AS hora_inicio_teletrabajo,
                COALESCE(hora_fin_teletrabajo_manana, 9) AS hora_fin_teletrabajo_manana,
                COALESCE(hora_inicio_presencial, 9) AS hora_inicio_presencial,
                COALESCE(hora_fin_presencial, 19) AS hora_fin_presencial,
                COALESCE(tiene_break, false) AS tiene_break,
                COALESCE(duracion_break_min, 30) AS duracion_break_min
            FROM campaigns WHERE id = :id
        ");
        $stmt->execute([':id' => $campaignId]);
        $this->campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        return !empty($this->campaign);
    }

    private function loadAdvisors(int $campaignId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT a.id, a.nombres, a.apellidos, a.hora_inicio_contrato, a.hora_fin_contrato,
                COALESCE(ac.tiene_vpn, false) AS tiene_vpn,
                COALESCE(ac.disponible_velada, false) AS disponible_velada,
                COALESCE(ac.permite_extras, true) AS permite_extras,
                COALESCE(ac.max_horas_dia, :max_horas) AS max_horas_dia,
                COALESCE(ac.permite_horario_partido, true) AS permite_horario_partido,
                COALESCE(ac.dias_descanso::text, '{}') AS dias_descanso,
                COALESCE(ac.modalidad_trabajo::text, 'mixto') AS modalidad_trabajo
            FROM advisors a
            LEFT JOIN advisor_constraints ac ON ac.advisor_id = a.id
            WHERE a.campaign_id = :campaign_id AND a.estado = 'activo'
            ORDER BY a.id ASC
        ");
        $stmt->execute([
            ':campaign_id' => $campaignId,
            ':max_horas' => (int)$this->campaign['max_horas_dia'],
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) return false;

        $this->advisors = [];
        foreach ($rows as $a) {
            $a['dias_descanso_parsed'] = $this->parseSmallIntArray($a['dias_descanso']);
            $a['tiene_descanso_configurado'] = !empty($a['dias_descanso_parsed']);
            $a['max_horas_dia'] = (int)$a['max_horas_dia'];
            $a['permite_extras'] = $this->toBool($a['permite_extras']);
            $a['tiene_vpn'] = $this->toBool($a['tiene_vpn']);
            $a['disponible_velada'] = $this->toBool($a['disponible_velada']);
            $a['permite_horario_partido'] = $this->toBool($a['permite_horario_partido']);
            $a['hora_inicio_contrato'] = $a['hora_inicio_contrato'] !== null ? (int)$a['hora_inicio_contrato'] : 0;
            $a['hora_fin_contrato'] = $a['hora_fin_contrato'] !== null ? (int)$a['hora_fin_contrato'] : 23;
            $this->advisors[$a['id']] = $a;
        }
        return true;
    }

    /**
     * Carga IDs de asesores compartidos (prestados a esta campaña).
     * No los agrega al pool general — solo trabajan via actividades fijas.
     */
    private function loadSharedAdvisorIds(int $campaignId): void
    {
        $this->sharedAdvisorIds = [];
        $stmt = $this->pdo->prepare("
            SELECT sa.advisor_id
            FROM shared_advisors sa
            JOIN advisors a ON a.id = sa.advisor_id
            WHERE sa.target_campaign_id = :campaign_id AND sa.estado = 'activo' AND a.estado = 'activo'
        ");
        $stmt->execute([':campaign_id' => $campaignId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $this->sharedAdvisorIds[(int)$id] = true;
        }
    }

    /**
     * Para la campaña primaria: carga horas que sus asesores ya tienen comprometidas
     * en otras campañas (como prestados) y las pre-registra como ocupadas.
     */
    private function loadSharedOutCommitments(int $campaignId, string $fechaInicio, string $fechaFin): void
    {
        $this->externalHours = [];

        // Buscar asesores de ESTA campaña que están prestados a otras
        $stmt = $this->pdo->prepare("
            SELECT sa.advisor_id, sa.target_campaign_id
            FROM shared_advisors sa
            JOIN advisors a ON a.id = sa.advisor_id
            WHERE sa.source_campaign_id = :campaign_id AND sa.estado = 'activo' AND a.estado = 'activo'
        ");
        $stmt->execute([':campaign_id' => $campaignId]);
        $outgoing = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($outgoing)) return;

        $advisorIds = array_unique(array_column($outgoing, 'advisor_id'));
        $placeholders = implode(',', array_fill(0, count($advisorIds), '?'));
        $params = array_merge(
            array_map('intval', $advisorIds),
            [$fechaInicio, $fechaFin, $campaignId]
        );

        $stmt = $this->pdo->prepare("
            SELECT advisor_id, fecha::text AS fecha, hora
            FROM shift_assignments
            WHERE advisor_id IN ($placeholders)
              AND fecha BETWEEN ? AND ?
              AND campaign_id <> ?
        ");
        $stmt->execute($params);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $advId = (int)$row['advisor_id'];
            $fecha = $row['fecha'];
            $hora = (int)$row['hora'];

            // Pre-registrar como ya asignado para que el builder no asigne esas horas
            if (!isset($this->advisorSchedule[$advId])) {
                $this->advisorSchedule[$advId] = [];
            }
            if (!isset($this->advisorSchedule[$advId][$fecha])) {
                $this->advisorSchedule[$advId][$fecha] = [];
            }
            if (!isset($this->advisorSchedule[$advId][$fecha][$hora])) {
                $this->advisorSchedule[$advId][$fecha][$hora] = true;
                // Contar horas externas por separado (no inflar advisorMonthHours)
                $this->externalHours[$advId] = ($this->externalHours[$advId] ?? 0) + 1;
            }
        }
    }

    private function loadRequirements(int $campaignId, string $fechaInicio, string $fechaFin): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT fecha::text AS fecha, hora, asesores_requeridos
            FROM staffing_requirements
            WHERE campaign_id = :campaign_id
              AND fecha BETWEEN :fecha_inicio AND :fecha_fin
              AND asesores_requeridos > 0
            ORDER BY fecha, hora
        ");
        $stmt->execute([':campaign_id' => $campaignId, ':fecha_inicio' => $fechaInicio, ':fecha_fin' => $fechaFin]);

        $this->requirements = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $this->requirements[$row['fecha']][(int)$row['hora']] = (int)$row['asesores_requeridos'];
        }
        return !empty($this->requirements);
    }

    private function loadActivityAssignments(int $campaignId): void
    {
        $this->activityAssignments = [];
        $stmt = $this->pdo->prepare("
            SELECT aaa.advisor_id, aaa.hora_inicio, aaa.hora_fin, aaa.dias_semana
            FROM advisor_activity_assignments aaa
            JOIN campaign_activities ca ON ca.id = aaa.activity_id
            WHERE ca.campaign_id = :campaign_id AND ca.estado = 'activa' AND aaa.activo = true
        ");
        $stmt->execute([':campaign_id' => $campaignId]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $advId = (int)$row['advisor_id'];
            $this->activityAssignments[$advId][] = [
                'hora_inicio' => (int)$row['hora_inicio'],
                'hora_fin' => (int)$row['hora_fin'],
                'dias_semana' => $this->parseSmallIntArray($row['dias_semana'] ?? '{}'),
            ];
        }
    }

    private function cleanupAssignments(int $scheduleId, int $campaignId, string $fechaInicio, string $fechaFin): void
    {
        // Limpiar asignaciones previas de esta campaña en otros schedules borrador
        $stmt = $this->pdo->prepare("
            DELETE FROM shift_assignments sa USING schedules s
            WHERE sa.schedule_id = s.id AND sa.campaign_id = :cid
              AND sa.fecha BETWEEN :fi AND :ff AND s.id <> :sid
              AND s.status IN ('borrador', 'rechazado')
        ");
        $stmt->execute([':cid' => $campaignId, ':fi' => $fechaInicio, ':ff' => $fechaFin, ':sid' => $scheduleId]);

        // Limpiar asignaciones de este schedule
        $stmt = $this->pdo->prepare("DELETE FROM shift_assignments WHERE schedule_id = :sid");
        $stmt->execute([':sid' => $scheduleId]);

        // Limpiar asignaciones de asesores compartidos (prestados a esta campaña)
        // en sus campañas fuente (borrador), para evitar conflictos de UNIQUE(advisor_id, fecha, hora)
        // Estas campañas fuente serán regeneradas después por regenerarCampanasFuente()
        if (!empty($this->sharedAdvisorIds)) {
            $sharedIds = array_keys($this->sharedAdvisorIds);
            $placeholders = implode(',', array_fill(0, count($sharedIds), '?'));
            $params = array_merge(
                array_map('intval', $sharedIds),
                [$fechaInicio, $fechaFin, $campaignId]
            );

            // Solo borrar de schedules en borrador de OTRAS campañas
            $stmt = $this->pdo->prepare("
                DELETE FROM shift_assignments sa
                USING schedules s
                WHERE sa.schedule_id = s.id
                  AND sa.advisor_id IN ($placeholders)
                  AND sa.fecha BETWEEN ? AND ?
                  AND sa.campaign_id <> ?
                  AND s.status IN ('borrador', 'rechazado')
            ");
            $stmt->execute($params);
        }
    }

    private function setupVelada(): void
    {
        $this->tieneVelada = $this->toBool($this->campaign['tiene_velada']);
        $this->horaFinVelada = (int)$this->campaign['hora_fin_velada'];
        $this->horasVelada = range(0, 6);
        $this->horasTransicion = [22, 23];

        $this->veladaEligible = [];
        if ($this->tieneVelada) {
            $reqVpn = $this->toBool($this->campaign['requiere_vpn_nocturno']);
            foreach ($this->advisors as $adv) {
                if ($adv['disponible_velada'] && (!$reqVpn || $adv['tiene_vpn'])) {
                    $this->veladaEligible[] = $adv['id'];
                }
            }
        }
    }

    private function generateDateRange(string $inicio, string $fin): array
    {
        $fechas = [];
        $c = new \DateTime($inicio);
        $e = new \DateTime($fin);
        while ($c <= $e) {
            $fechas[] = $c->format('Y-m-d');
            $c->modify('+1 day');
        }
        return $fechas;
    }

    // ==========================================================
    // FASE 7: BREAKS
    // ==========================================================

    /**
     * Asigna un break (descanso) a cada asesor en cada día trabajado.
     * El break se coloca en el medio del bloque de trabajo más largo.
     * Cuenta como asignación para el dimensionamiento (ocupa un slot).
     */
    private function asignarBreaks(array $fechas): void
    {
        foreach ($fechas as $fecha) {
            foreach ($this->advisors as $advId => $adv) {
                $horasTrabajo = array_keys($this->advisorSchedule[$advId][$fecha] ?? []);
                if (count($horasTrabajo) < 4) continue; // No dar break si trabaja pocas horas

                sort($horasTrabajo);

                // Encontrar el bloque continuo más largo
                $bloques = [];
                $bloqueActual = [$horasTrabajo[0]];
                for ($i = 1; $i < count($horasTrabajo); $i++) {
                    if ($horasTrabajo[$i] === $horasTrabajo[$i - 1] + 1) {
                        $bloqueActual[] = $horasTrabajo[$i];
                    } else {
                        $bloques[] = $bloqueActual;
                        $bloqueActual = [$horasTrabajo[$i]];
                    }
                }
                $bloques[] = $bloqueActual;

                // Tomar el bloque más largo
                usort($bloques, fn($a, $b) => count($b) - count($a));
                $bloquePrincipal = $bloques[0];

                if (count($bloquePrincipal) < 3) continue;

                // Colocar el break en el medio del bloque
                $midIdx = (int)floor(count($bloquePrincipal) / 2);
                $horaBreak = $bloquePrincipal[$midIdx];

                $this->breakAssignments[$advId][$fecha] = $horaBreak;
            }
        }
    }

    // ==========================================================
    // INSERCIÓN
    // ==========================================================

    private function insertAssignments(): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO shift_assignments (schedule_id, advisor_id, campaign_id, fecha, hora, tipo, es_extra, modalidad)
            VALUES (:sid, :aid, :cid, :fecha, :hora, :tipo, :extra, :mod)
            ON CONFLICT (advisor_id, fecha, hora) DO NOTHING
        ");

        $this->pdo->beginTransaction();
        try {
            $count = 0;
            foreach ($this->assignments as $fecha => $horasData) {
                foreach ($horasData as $hora => $advisorIds) {
                    foreach ($advisorIds as $advId => $_) {
                        $horasHoy = count($this->advisorSchedule[$advId][$fecha] ?? []);
                        $esExtra = $horasHoy > 8;

                        // Verificar si esta hora es un break para este asesor
                        $esBreak = isset($this->breakAssignments[$advId][$fecha])
                            && $this->breakAssignments[$advId][$fecha] === $hora;

                        if ($esBreak) {
                            $tipo = 'break';
                        } else {
                            $tipo = $this->esHoraNocturna($hora) ? 'nocturno' : ($esExtra ? 'extra' : 'normal');
                        }

                        $stmt->execute([
                            ':sid' => $this->scheduleId,
                            ':aid' => $advId,
                            ':cid' => $this->campaignId,
                            ':fecha' => $fecha,
                            ':hora' => $hora,
                            ':tipo' => $tipo,
                            ':extra' => $esBreak ? 'false' : ($esExtra ? 'true' : 'false'),
                            ':mod' => $this->getModalidad($hora),
                        ]);
                        if ($stmt->rowCount() > 0) $count++;
                    }
                }
            }
            $this->pdo->commit();
            return $count;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Verifica si un asesor puede trabajar en una hora específica
     * según su contrato Y su modalidad de trabajo.
     */
    private function puedeTrabajarHora(array $adv, int $hora): bool
    {
        // Restricción de contrato
        if ($hora < $adv['hora_inicio_contrato'] || $hora > $adv['hora_fin_contrato']) return false;

        $modalidad = $adv['modalidad_trabajo'] ?? 'mixto';
        $iniPres = (int)$this->campaign['hora_inicio_presencial'];
        $finPres = (int)$this->campaign['hora_fin_presencial'];

        if ($modalidad === 'presencial') {
            // Solo puede trabajar en horario presencial de la campaña
            if ($hora < $iniPres || $hora >= $finPres) return false;
        }
        // teletrabajo y mixto: pueden trabajar en cualquier hora dentro de su contrato

        return true;
    }

    private function getModalidad(int $hora): string
    {
        $fin = (int)$this->campaign['hora_fin_teletrabajo_manana'];
        $ini = (int)$this->campaign['hora_inicio_presencial'];
        $finP = (int)$this->campaign['hora_fin_presencial'];
        if ($hora < $fin) return 'teletrabajo';
        if ($hora >= $ini && $hora < $finP) return 'presencial';
        return 'teletrabajo';
    }

    private function parseSmallIntArray(string $pgArray): array
    {
        $t = trim($pgArray, '{}');
        if ($t === '') return [];
        return array_map('intval', array_filter(explode(',', $t), 'strlen'));
    }

    private function toBool($value): bool
    {
        if (is_bool($value)) return $value;
        if (is_string($value)) return in_array(strtolower($value), ['t', 'true', '1', 'yes'], true);
        return (bool)$value;
    }
}
