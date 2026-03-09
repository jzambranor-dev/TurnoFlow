<?php
/**
 * ScheduleBuilder - Motor de generación de horarios
 *
 * Algoritmo basado en el análisis de horarios reales del supervisor:
 * 1. Rotación semanal de velada entre asesores elegibles
 * 2. Asignación de bloques continuos (máximo 2 por día)
 * 3. Distribución inteligente de días libres
 * 4. Prioridad absoluta: cubrir el dimensionamiento al 100%
 */

namespace App\Services;

use PDO;

class ScheduleBuilder
{
    private PDO $pdo;
    private array $campaign;
    private array $advisors;
    private array $requirements; // [fecha][hora] => requeridos
    private array $assignments;  // [fecha][hora] => [advisor_ids]
    private array $advisorHours; // [advisor_id][fecha] => [horas asignadas]
    private array $advisorMonthHours; // [advisor_id] => total horas mes
    private array $veladaEligible;
    private int $scheduleId;
    private int $campaignId;

    // Configuración de velada
    private bool $tieneVelada;
    private int $horaFinVelada;
    private array $horasVelada;      // [0,1,2,3,4,5,6,7]
    private array $horasTransicion;  // [22,23]

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Genera las asignaciones del horario
     */
    public function build(
        int $scheduleId,
        int $campaignId,
        string $fechaInicio,
        string $fechaFin
    ): int {
        $this->scheduleId = $scheduleId;
        $this->campaignId = $campaignId;

        // Cargar datos
        if (!$this->loadCampaign($campaignId)) {
            return 0;
        }

        if (!$this->loadAdvisors($campaignId)) {
            return 0;
        }

        if (!$this->loadRequirements($campaignId, $fechaInicio, $fechaFin)) {
            return 0;
        }

        // Limpiar asignaciones existentes
        $this->cleanupAssignments($scheduleId, $campaignId, $fechaInicio, $fechaFin);

        // Inicializar estructuras
        $this->assignments = [];
        $this->advisorHours = [];
        $this->advisorMonthHours = [];
        foreach ($this->advisors as $advisor) {
            $this->advisorMonthHours[$advisor['id']] = 0;
        }

        // Configurar velada
        $this->setupVelada();

        // Generar lista de fechas
        $fechas = $this->generateDateRange($fechaInicio, $fechaFin);

        // PASO 1: Planificar días libres
        $diasLibres = $this->planificarDiasLibres($fechas);

        // PASO 2: Para cada día, asignar horarios
        foreach ($fechas as $fecha) {
            $this->asignarDia($fecha, $diasLibres);
        }

        // PASO 3: Insertar todas las asignaciones en la BD
        return $this->insertAssignments();
    }

    /**
     * Carga la campaña
     */
    private function loadCampaign(int $campaignId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                tiene_velada,
                hora_inicio_operacion,
                hora_fin_operacion,
                requiere_vpn_nocturno,
                hora_inicio_nocturno,
                hora_fin_nocturno,
                max_horas_dia,
                COALESCE(hora_fin_velada, 8) AS hora_fin_velada,
                COALESCE(hora_inicio_teletrabajo, 0) AS hora_inicio_teletrabajo,
                COALESCE(hora_fin_teletrabajo_manana, 9) AS hora_fin_teletrabajo_manana,
                COALESCE(hora_inicio_presencial, 9) AS hora_inicio_presencial,
                COALESCE(hora_fin_presencial, 19) AS hora_fin_presencial
            FROM campaigns
            WHERE id = :id
        ");
        $stmt->execute([':id' => $campaignId]);
        $this->campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        return !empty($this->campaign);
    }

    /**
     * Carga los asesores de la campaña
     */
    private function loadAdvisors(int $campaignId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT
                a.id,
                a.nombres,
                a.apellidos,
                a.hora_inicio_contrato,
                a.hora_fin_contrato,
                COALESCE(ac.tiene_vpn, false) AS tiene_vpn,
                COALESCE(ac.disponible_velada, false) AS disponible_velada,
                COALESCE(ac.permite_extras, true) AS permite_extras,
                COALESCE(ac.max_horas_dia, :max_horas) AS max_horas_dia,
                COALESCE(ac.permite_horario_partido, true) AS permite_horario_partido,
                COALESCE(ac.dias_descanso::text, '{}') AS dias_descanso
            FROM advisors a
            LEFT JOIN advisor_constraints ac ON ac.advisor_id = a.id
            WHERE a.campaign_id = :campaign_id
              AND a.estado = 'activo'
            ORDER BY a.id ASC
        ");
        $stmt->execute([
            ':campaign_id' => $campaignId,
            ':max_horas' => (int)$this->campaign['max_horas_dia'],
        ]);
        $advisors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($advisors)) {
            return false;
        }

        // Procesar cada asesor
        $this->advisors = [];
        foreach ($advisors as $advisor) {
            $advisor['dias_descanso_parsed'] = $this->parseSmallIntArray($advisor['dias_descanso']);
            $advisor['tiene_descanso_configurado'] = !empty($advisor['dias_descanso_parsed']);
            $advisor['max_horas_dia'] = (int)$advisor['max_horas_dia'];
            $advisor['permite_extras'] = $this->toBool($advisor['permite_extras']);
            $advisor['tiene_vpn'] = $this->toBool($advisor['tiene_vpn']);
            $advisor['disponible_velada'] = $this->toBool($advisor['disponible_velada']);
            $advisor['permite_horario_partido'] = $this->toBool($advisor['permite_horario_partido']);
            $advisor['hora_inicio_contrato'] = $advisor['hora_inicio_contrato'] !== null
                ? (int)$advisor['hora_inicio_contrato'] : 0;
            $advisor['hora_fin_contrato'] = $advisor['hora_fin_contrato'] !== null
                ? (int)$advisor['hora_fin_contrato'] : 23;

            $this->advisors[$advisor['id']] = $advisor;
        }

        return true;
    }

    /**
     * Carga los requerimientos de dimensionamiento
     */
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
        $stmt->execute([
            ':campaign_id' => $campaignId,
            ':fecha_inicio' => $fechaInicio,
            ':fecha_fin' => $fechaFin,
        ]);

        $this->requirements = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $fecha = $row['fecha'];
            $hora = (int)$row['hora'];
            if (!isset($this->requirements[$fecha])) {
                $this->requirements[$fecha] = [];
            }
            $this->requirements[$fecha][$hora] = (int)$row['asesores_requeridos'];
        }

        return !empty($this->requirements);
    }

    /**
     * Limpia asignaciones existentes
     */
    private function cleanupAssignments(int $scheduleId, int $campaignId, string $fechaInicio, string $fechaFin): void
    {
        // Eliminar de otros borradores
        $stmt = $this->pdo->prepare("
            DELETE FROM shift_assignments sa
            USING schedules s
            WHERE sa.schedule_id = s.id
              AND sa.campaign_id = :campaign_id
              AND sa.fecha BETWEEN :fecha_inicio AND :fecha_fin
              AND s.id <> :schedule_id
              AND s.status IN ('borrador', 'rechazado')
        ");
        $stmt->execute([
            ':campaign_id' => $campaignId,
            ':fecha_inicio' => $fechaInicio,
            ':fecha_fin' => $fechaFin,
            ':schedule_id' => $scheduleId,
        ]);

        // Eliminar del schedule actual
        $stmt = $this->pdo->prepare("DELETE FROM shift_assignments WHERE schedule_id = :schedule_id");
        $stmt->execute([':schedule_id' => $scheduleId]);
    }

    /**
     * Configura el sistema de velada
     */
    private function setupVelada(): void
    {
        $this->tieneVelada = $this->toBool($this->campaign['tiene_velada']);
        $this->horaFinVelada = (int)$this->campaign['hora_fin_velada'];
        // Horas de velada EXCLUSIVAS (madrugada): 0-6.
        // La hora 7 puede ser cubierta por turnos normales temprano
        $this->horasVelada = range(0, 6);
        $this->horasTransicion = [22, 23];

        $this->veladaEligible = [];
        if ($this->tieneVelada) {
            $requiereVpn = $this->toBool($this->campaign['requiere_vpn_nocturno']);
            foreach ($this->advisors as $advisor) {
                if ($advisor['disponible_velada'] && (!$requiereVpn || $advisor['tiene_vpn'])) {
                    $this->veladaEligible[] = $advisor['id'];
                }
            }
        }
    }

    /**
     * Obtiene el asesor de velada para una semana
     */
    private function getVeladaAdvisorForWeek(string $fecha): ?int
    {
        if (empty($this->veladaEligible)) {
            return null;
        }
        $weekNumber = (int)date('W', strtotime($fecha));
        $index = $weekNumber % count($this->veladaEligible);
        return $this->veladaEligible[$index];
    }

    /**
     * Genera rango de fechas
     */
    private function generateDateRange(string $inicio, string $fin): array
    {
        $fechas = [];
        $current = new \DateTime($inicio);
        $end = new \DateTime($fin);

        while ($current <= $end) {
            $fechas[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }

        return $fechas;
    }

    /**
     * Planifica los días libres de cada asesor
     * Objetivo: 4-5 días libres por mes, preferiblemente días de baja demanda
     * IMPORTANTE: Los asesores de velada NO deben tener día libre durante su semana de velada
     */
    private function planificarDiasLibres(array $fechas): array
    {
        // Target de días libres por asesor
        // Asesores sin config: 3-4 días libres (preferir días de baja demanda)
        // Asesores con config (como Vanessa sab/dom): respetan su configuración
        $diasLibresTarget = 3;

        $diasLibres = []; // [advisor_id] => [fechas libres]
        $libresContadoPorFecha = []; // [fecha] => count

        // Calcular demanda por día y capacidad máxima de asesores por día
        $demandaPorDia = [];
        $demandaMaxHora = [];  // máximo de asesores requeridos en una hora del día
        foreach ($fechas as $fecha) {
            $reqDia = $this->requirements[$fecha] ?? [];
            $demandaPorDia[$fecha] = array_sum($reqDia);
            $demandaMaxHora[$fecha] = !empty($reqDia) ? max($reqDia) : 0;
            $libresContadoPorFecha[$fecha] = 0;
        }

        // Contar cuántos asesores trabajan fines de semana (excluir los que tienen sab/dom libre)
        $asesoresConDescansoFDS = 0;
        foreach ($this->advisors as $advisor) {
            if ($advisor['tiene_descanso_configurado']) {
                // Verificar si tiene sábado (5) o domingo (6) configurado
                if (in_array(5, $advisor['dias_descanso_parsed'], true) ||
                    in_array(6, $advisor['dias_descanso_parsed'], true)) {
                    $asesoresConDescansoFDS++;
                }
            }
        }

        // Identificar qué semanas le corresponde velada a cada asesor elegible
        $semanasVeladaPorAsesor = [];
        if ($this->tieneVelada && !empty($this->veladaEligible)) {
            foreach ($fechas as $fecha) {
                $weekNumber = (int)date('W', strtotime($fecha));
                $index = $weekNumber % count($this->veladaEligible);
                $veladaAdvisorId = $this->veladaEligible[$index];
                if (!isset($semanasVeladaPorAsesor[$veladaAdvisorId])) {
                    $semanasVeladaPorAsesor[$veladaAdvisorId] = [];
                }
                $semanasVeladaPorAsesor[$veladaAdvisorId][] = $fecha;
            }
        }

        // Ordenar fechas por demanda (menor demanda primero - mejores días para dar libre)
        $fechasOrdenadas = $fechas;
        usort($fechasOrdenadas, function($a, $b) use ($demandaPorDia) {
            return $demandaPorDia[$a] <=> $demandaPorDia[$b];
        });

        // Contar asesores totales (excluyendo los que ya tienen descanso configurado en FDS)
        $totalAsesores = count($this->advisors);

        foreach ($this->advisors as $advisorId => $advisor) {
            $diasLibres[$advisorId] = [];

            // Fechas que NO puede tener libre (semanas de velada)
            $fechasVeladaAsesor = $semanasVeladaPorAsesor[$advisorId] ?? [];

            // Si tiene días de descanso configurados, usarlos (pero respetar velada)
            if ($advisor['tiene_descanso_configurado']) {
                foreach ($fechas as $fecha) {
                    $dow = (int)date('N', strtotime($fecha)) - 1; // 0=Lun, 6=Dom
                    if (in_array($dow, $advisor['dias_descanso_parsed'], true)) {
                        // Si es su semana de velada, NO dar libre
                        if (!in_array($fecha, $fechasVeladaAsesor, true)) {
                            $diasLibres[$advisorId][] = $fecha;
                            $libresContadoPorFecha[$fecha] = ($libresContadoPorFecha[$fecha] ?? 0) + 1;
                        }
                    }
                }
            } else {
                // Asignar días libres automáticamente
                // Usar offset basado en el ID para distribuir diferentes días a cada asesor
                $libresAsignados = 0;
                $offset = ($advisorId * 5) % count($fechasOrdenadas);

                for ($i = 0; $i < count($fechasOrdenadas) && $libresAsignados < $diasLibresTarget; $i++) {
                    $idx = ($i + $offset) % count($fechasOrdenadas);
                    $fechaCandidata = $fechasOrdenadas[$idx];

                    // Si es su semana de velada, NO dar libre
                    if (in_array($fechaCandidata, $fechasVeladaAsesor, true)) {
                        continue;
                    }

                    // Verificar si el día ya tiene demasiados libres
                    $dow = (int)date('w', strtotime($fechaCandidata)); // 0=dom, 6=sab
                    $asesoresDelDia = ($dow == 0 || $dow == 6)
                        ? $totalAsesores - $asesoresConDescansoFDS
                        : $totalAsesores;

                    $libresActuales = $libresContadoPorFecha[$fechaCandidata] ?? 0;

                    // Determinar máximo de libres según demanda del día
                    // Todos los días: máximo 1 libre para asegurar cobertura
                    // El asesor de velada no cuenta porque ya está excluido arriba
                    $maxLibresPermitidos = 1;

                    // Solo dar libre si no excedemos el límite y quedan suficientes asesores
                    $asesoresRestantes = $asesoresDelDia - $libresActuales - 1;
                    if ($libresActuales < $maxLibresPermitidos && $asesoresRestantes >= $demandaMaxHora[$fechaCandidata]) {
                        $diasLibres[$advisorId][] = $fechaCandidata;
                        $libresContadoPorFecha[$fechaCandidata]++;
                        $libresAsignados++;
                    }
                }
            }
        }

        return $diasLibres;
    }

    /**
     * Asigna horarios para un día específico
     */
    private function asignarDia(string $fecha, array $diasLibres): void
    {
        if (!isset($this->requirements[$fecha])) {
            return; // No hay dimensionamiento para este día
        }

        $reqDia = $this->requirements[$fecha]; // [hora] => requeridos
        $horasOrdenadas = array_keys($reqDia);
        sort($horasOrdenadas);

        // Obtener asesor de velada para hoy
        $veladaAdvisorId = $this->tieneVelada ? $this->getVeladaAdvisorForWeek($fecha) : null;

        // Determinar quién trabaja hoy
        $asesoresDisponibles = [];
        foreach ($this->advisors as $advisorId => $advisor) {
            // ¿Está de descanso hoy?
            if (in_array($fecha, $diasLibres[$advisorId] ?? [], true)) {
                continue;
            }
            $asesoresDisponibles[$advisorId] = $advisor;
        }

        // PASO 1: Asignar velada primero
        if ($veladaAdvisorId && isset($asesoresDisponibles[$veladaAdvisorId])) {
            $this->asignarVelada($fecha, $veladaAdvisorId, $reqDia);
        }

        // PASO 2: Calcular horas restantes por cubrir
        $horasPorCubrir = [];
        foreach ($reqDia as $hora => $requeridos) {
            $asignados = count($this->assignments[$fecha][$hora] ?? []);
            $faltantes = $requeridos - $asignados;
            if ($faltantes > 0) {
                $horasPorCubrir[$hora] = $faltantes;
            }
        }

        if (empty($horasPorCubrir)) {
            return; // Todo cubierto con velada
        }

        // PASO 3: Asignar bloques - estrategia:
        // 1. Primero asesores con contrato restringido (ej: Vanessa 9-18) para usar su capacidad
        // 2. Luego asesores flexibles distribuidos entre franjas temprano/tarde/central

        // Ordenar asesores por horas mensuales (distribución equitativa)
        $asesoresOrdenados = $asesoresDisponibles;
        uasort($asesoresOrdenados, function ($a, $b) {
            $horasA = $this->advisorMonthHours[$a['id']] ?? 0;
            $horasB = $this->advisorMonthHours[$b['id']] ?? 0;
            return $horasA <=> $horasB;
        });

        // Separar asesores con contrato restringido vs flexibles
        $asesoresRestringidos = [];
        $asesoresFlexibles = [];

        foreach ($asesoresOrdenados as $advisorId => $advisor) {
            if ($advisorId === $veladaAdvisorId) continue;

            // Un asesor es "restringido" si no puede cubrir todo el rango 7-21
            $esRestringido = $advisor['hora_inicio_contrato'] > 7 || $advisor['hora_fin_contrato'] < 21;

            if ($esRestringido) {
                $asesoresRestringidos[] = $advisorId;
            } else {
                $asesoresFlexibles[] = $advisorId;
            }
        }

        // Paso 3a: Asignar primero asesores restringidos (con bloque completo)
        foreach ($asesoresRestringidos as $advisorId) {
            $advisor = $this->advisors[$advisorId];
            // forceFullBlock = true para que use todo el rango del contrato
            $bloque = $this->calcularBloqueSegunFranja($fecha, $advisor, $horasPorCubrir, 'central', true);

            if (!empty($bloque)) {
                $this->asignarBloque($fecha, $advisorId, $bloque);
                foreach ($bloque as $hora) {
                    if (isset($horasPorCubrir[$hora])) {
                        $horasPorCubrir[$hora]--;
                        if ($horasPorCubrir[$hora] <= 0) {
                            unset($horasPorCubrir[$hora]);
                        }
                    }
                }
            }
        }

        // Calcular necesidades por franja (después de asignar restringidos)
        $necesidadTarde = 0;
        $necesidadTemprano = 0;
        foreach ($horasPorCubrir as $hora => $req) {
            if ($hora >= 19 && $hora <= 21) $necesidadTarde = max($necesidadTarde, $req);
            if ($hora >= 7 && $hora <= 9) $necesidadTemprano = max($necesidadTemprano, $req);
        }

        // Paso 3b: Asignar asesores flexibles según necesidad
        $asignadosTarde = 0;
        $asignadosTemprano = 0;

        foreach ($asesoresFlexibles as $advisorId) {
            $totalFaltante = array_sum($horasPorCubrir);
            if ($totalFaltante <= 0) break;

            $advisor = $this->advisors[$advisorId];

            // Decidir franja según necesidad actual
            $franja = 'central';
            if ($asignadosTarde < $necesidadTarde) {
                $franja = 'tarde';
                $asignadosTarde++;
            } elseif ($asignadosTemprano < $necesidadTemprano) {
                $franja = 'temprano';
                $asignadosTemprano++;
            }

            $bloque = $this->calcularBloqueSegunFranja($fecha, $advisor, $horasPorCubrir, $franja);

            if (!empty($bloque)) {
                $this->asignarBloque($fecha, $advisorId, $bloque);
                foreach ($bloque as $hora) {
                    if (isset($horasPorCubrir[$hora])) {
                        $horasPorCubrir[$hora]--;
                        if ($horasPorCubrir[$hora] <= 0) {
                            unset($horasPorCubrir[$hora]);
                        }
                    }
                }
            }
        }

        // PASO 4: Segunda pasada para cubrir huecos restantes
        // Si todavía hay horas sin cubrir, asignar a quien pueda aunque cree horario partido
        if (!empty($horasPorCubrir)) {
            $this->cubrirHuecos($fecha, $asesoresOrdenados, $horasPorCubrir, $veladaAdvisorId);
        }

        // PASO 5: Tercera pasada - si aún hay déficit, permitir que el asesor de velada cubra horas diurnas
        if (!empty($horasPorCubrir) && $veladaAdvisorId && isset($this->advisors[$veladaAdvisorId])) {
            $veladaAdvisor = $this->advisors[$veladaAdvisorId];
            foreach ($horasPorCubrir as $hora => $faltantes) {
                if ($faltantes <= 0) continue;

                // Solo horas diurnas (8-21) que el asesor de velada pueda cubrir
                if ($hora >= 8 && $hora <= 21) {
                    // Verificar capacidad
                    $horasHoy = count($this->advisorHours[$veladaAdvisorId][$fecha] ?? []);
                    if ($horasHoy < $veladaAdvisor['max_horas_dia']) {
                        $yaAsignado = in_array($veladaAdvisorId, $this->assignments[$fecha][$hora] ?? [], true);
                        if (!$yaAsignado) {
                            $this->registrarAsignacion($fecha, $veladaAdvisorId, $hora);
                            $horasPorCubrir[$hora]--;
                        }
                    }
                }
            }
        }

        // PASO 6: Cuarta pasada - extender bloques de asesores que ya trabajan para cubrir horas tardías
        // Priorizar horas 19-21 que son las más problemáticas
        $horasTardias = array_filter($horasPorCubrir, fn($h) => $h >= 19 && $h <= 21, ARRAY_FILTER_USE_KEY);
        if (!empty($horasTardias)) {
            foreach ($asesoresOrdenados as $advisorId => $advisor) {
                if ($advisorId === $veladaAdvisorId) continue;

                // Solo asesores que pueden trabajar hasta las 21
                if ($advisor['hora_fin_contrato'] < 21) continue;

                $horasHoy = count($this->advisorHours[$advisorId][$fecha] ?? []);
                if ($horasHoy >= $advisor['max_horas_dia']) continue;
                if (!$advisor['permite_extras'] && $horasHoy >= 8) continue;

                foreach ($horasTardias as $hora => $faltantes) {
                    if ($faltantes <= 0) continue;

                    $yaAsignado = in_array($advisorId, $this->assignments[$fecha][$hora] ?? [], true);
                    if ($yaAsignado) continue;

                    // Verificar capacidad actual
                    $horasActuales = count($this->advisorHours[$advisorId][$fecha] ?? []);
                    if ($horasActuales >= $advisor['max_horas_dia']) break;
                    if (!$advisor['permite_extras'] && $horasActuales >= 8) break;

                    // Asignar la hora
                    $this->registrarAsignacion($fecha, $advisorId, $hora);
                    $horasTardias[$hora]--;
                    if ($horasTardias[$hora] <= 0) {
                        unset($horasTardias[$hora]);
                    }
                }

                if (empty($horasTardias)) break;
            }
        }
    }

    /**
     * Asigna las horas de velada
     */
    private function asignarVelada(string $fecha, int $advisorId, array $reqDia): void
    {
        $advisor = $this->advisors[$advisorId];
        $horasAsignadas = [];

        // Asignar horas de madrugada (00:00 - hora_fin_velada)
        foreach ($this->horasVelada as $hora) {
            if (isset($reqDia[$hora]) && $reqDia[$hora] > 0) {
                $horasAsignadas[] = $hora;
            }
        }

        // Asignar horas de transición (22:00 - 23:59)
        foreach ($this->horasTransicion as $hora) {
            if (isset($reqDia[$hora]) && $reqDia[$hora] > 0) {
                $horasAsignadas[] = $hora;
            }
        }

        // Registrar asignaciones
        foreach ($horasAsignadas as $hora) {
            $this->registrarAsignacion($fecha, $advisorId, $hora);
        }
    }

    /**
     * Calcula bloque según la franja horaria asignada al asesor
     * Si forceFullBlock es true, construye el bloque máximo posible aunque sobre-cubra algunas horas
     */
    private function calcularBloqueSegunFranja(string $fecha, array $advisor, array $horasPorCubrir, string $franja, bool $forceFullBlock = false): array
    {
        $advisorId = $advisor['id'];
        $horasYaAsignadas = $this->advisorHours[$advisorId][$fecha] ?? [];
        $horasHoy = count($horasYaAsignadas);
        $maxHorasDia = $advisor['max_horas_dia'];
        $horasDisponibles = $maxHorasDia - $horasHoy;

        if ($horasDisponibles <= 0) return [];
        if (!$advisor['permite_extras'] && $horasHoy >= 8) return [];
        if (!$advisor['permite_extras']) $horasDisponibles = min($horasDisponibles, 8 - $horasHoy);

        $horaInicio = $advisor['hora_inicio_contrato'];
        $horaFin = $advisor['hora_fin_contrato'];
        $tieneVpn = $advisor['tiene_vpn'];

        // Construir todas las horas que el asesor PUEDE trabajar (según contrato)
        $horasContrato = [];
        for ($h = $horaInicio; $h <= $horaFin; $h++) {
            if (in_array($h, $this->horasVelada, true)) continue;
            if (in_array($h, $this->horasTransicion, true) && !$tieneVpn) continue;
            $horasContrato[] = $h;
        }

        // Filtrar horas que necesitan cobertura O (si forceFullBlock) todas las del contrato
        $horasElegibles = [];
        foreach ($horasContrato as $hora) {
            // Si forceFullBlock, incluir la hora aunque no necesite cobertura
            // Si no, solo incluir horas que necesitan cobertura
            if ($forceFullBlock || isset($horasPorCubrir[$hora])) {
                $horasElegibles[] = $hora;
            }
        }

        if (empty($horasElegibles)) return [];
        sort($horasElegibles);

        // Determinar punto de partida según franja
        switch ($franja) {
            case 'temprano':
                // Empezar lo más temprano posible
                $inicioBloque = min($horasElegibles);
                return $this->construirBloqueDesde($horasElegibles, $inicioBloque, $horasDisponibles);

            case 'tarde':
                // Terminar lo más tarde posible
                $finBloque = max($horasElegibles);
                return $this->construirBloqueHasta($horasElegibles, $finBloque, $horasDisponibles);

            default: // central
                // Para asesores con contrato restringido, usar todo su rango
                if ($horaInicio > 7 || $horaFin < 21) {
                    // Empezar desde la primera hora del contrato
                    $inicioBloque = min($horasElegibles);
                    return $this->construirBloqueDesde($horasElegibles, $inicioBloque, $horasDisponibles);
                }
                // Para asesores flexibles, centrar en las horas pico (10-17)
                $horasCentrales = array_filter($horasElegibles, fn($h) => $h >= 10 && $h <= 17);
                if (!empty($horasCentrales)) {
                    $inicioBloque = min($horasCentrales);
                    return $this->construirBloqueDesde($horasElegibles, $inicioBloque, $horasDisponibles);
                }
                $inicioBloque = min($horasElegibles);
                return $this->construirBloqueDesde($horasElegibles, $inicioBloque, $horasDisponibles);
        }
    }

    /**
     * Construye un bloque continuo hacia adelante desde una hora de inicio
     */
    private function construirBloqueDesde(array $horasElegibles, int $inicio, int $maxHoras): array
    {
        $bloque = [];
        $hora = $inicio;
        while (in_array($hora, $horasElegibles, true) && count($bloque) < $maxHoras) {
            $bloque[] = $hora;
            $hora++;
        }
        return $bloque;
    }

    /**
     * Construye un bloque continuo hacia atrás desde una hora de fin
     */
    private function construirBloqueHasta(array $horasElegibles, int $fin, int $maxHoras): array
    {
        $bloque = [];
        $hora = $fin;
        while (in_array($hora, $horasElegibles, true) && count($bloque) < $maxHoras) {
            array_unshift($bloque, $hora);
            $hora--;
        }
        return $bloque;
    }

    /**
     * Calcula el bloque óptimo de horas para un asesor
     * @param bool $forzarTurnoTardio Si es true, buscar bloque que incluya horas 20-21
     */
    private function calcularBloqueOptimo(string $fecha, array $advisor, array $horasPorCubrir, bool $forzarTurnoTardio = false): array
    {
        $advisorId = $advisor['id'];
        $horasYaAsignadas = $this->advisorHours[$advisorId][$fecha] ?? [];
        $horasHoy = count($horasYaAsignadas);
        $maxHorasDia = $advisor['max_horas_dia'];
        $horasDisponibles = $maxHorasDia - $horasHoy;

        if ($horasDisponibles <= 0) {
            return [];
        }

        // Límite por extras
        if (!$advisor['permite_extras'] && $horasHoy >= 8) {
            return [];
        }
        if (!$advisor['permite_extras']) {
            $horasDisponibles = min($horasDisponibles, 8 - $horasHoy);
        }

        // Rango de horas permitido por contrato
        $horaInicio = $advisor['hora_inicio_contrato'];
        $horaFin = $advisor['hora_fin_contrato'];

        // Filtrar horas que necesitan cobertura Y están en el rango del asesor
        $horasElegibles = [];
        $tieneVpn = $advisor['tiene_vpn'];
        foreach ($horasPorCubrir as $hora => $faltantes) {
            if ($hora >= $horaInicio && $hora <= $horaFin) {
                // No asignar horas de VELADA (madrugada 0-7) a asesores no-velada
                if (in_array($hora, $this->horasVelada, true)) {
                    continue;
                }
                // Las horas de transición (22-23) se pueden asignar a asesores con VPN
                if (in_array($hora, $this->horasTransicion, true) && !$tieneVpn) {
                    continue;
                }
                $horasElegibles[] = $hora;
            }
        }

        if (empty($horasElegibles)) {
            return [];
        }

        sort($horasElegibles);

        // Si debemos forzar turno tardío, buscar bloque que incluya horas 20-21
        if ($forzarTurnoTardio) {
            $horasTardias = array_filter($horasElegibles, fn($h) => $h >= 20 && $h <= 21);
            if (!empty($horasTardias)) {
                // Encontrar el bloque que contiene las horas tardías
                $bloqueConTardias = $this->encontrarBloqueConHoras($horasElegibles, $horasTardias, $horasDisponibles);
                if (!empty($bloqueConTardias)) {
                    return $bloqueConTardias;
                }
            }
        }

        // Si no permite horario partido, buscar el bloque continuo más largo
        if (!$advisor['permite_horario_partido']) {
            return $this->encontrarBloqueContinuo($horasElegibles, $horasDisponibles);
        }

        // Si permite partido, asignar hasta 2 bloques
        return $this->encontrarBloques($horasElegibles, $horasDisponibles, 2);
    }

    /**
     * Encuentra un bloque continuo que incluya las horas especificadas
     */
    private function encontrarBloqueConHoras(array $horasElegibles, array $horasObligatorias, int $maxHoras): array
    {
        if (empty($horasObligatorias)) {
            return [];
        }

        // La hora obligatoria más alta (ej: 21)
        $horaObligatoriaMax = max($horasObligatorias);

        // Buscar hacia atrás desde la hora obligatoria para formar un bloque continuo
        $bloque = [];
        $horaActual = $horaObligatoriaMax;

        // Primero agregar todas las horas obligatorias que son continuas
        while (in_array($horaActual, $horasElegibles, true) && count($bloque) < $maxHoras) {
            array_unshift($bloque, $horaActual);
            $horaActual--;
        }

        // Continuar hacia atrás hasta completar el bloque o llegar a maxHoras
        while (in_array($horaActual, $horasElegibles, true) && count($bloque) < $maxHoras) {
            array_unshift($bloque, $horaActual);
            $horaActual--;
        }

        return $bloque;
    }

    /**
     * Encuentra el bloque continuo más largo
     */
    private function encontrarBloqueContinuo(array $horas, int $maxHoras): array
    {
        if (empty($horas)) {
            return [];
        }

        $mejorBloque = [];
        $bloqueActual = [$horas[0]];

        for ($i = 1; $i < count($horas); $i++) {
            if ($horas[$i] === $horas[$i-1] + 1) {
                $bloqueActual[] = $horas[$i];
            } else {
                if (count($bloqueActual) > count($mejorBloque)) {
                    $mejorBloque = $bloqueActual;
                }
                $bloqueActual = [$horas[$i]];
            }
        }

        if (count($bloqueActual) > count($mejorBloque)) {
            $mejorBloque = $bloqueActual;
        }

        // Limitar al máximo de horas
        return array_slice($mejorBloque, 0, $maxHoras);
    }

    /**
     * Encuentra hasta N bloques para cubrir las horas
     */
    private function encontrarBloques(array $horas, int $maxHoras, int $maxBloques): array
    {
        if (empty($horas) || $maxHoras <= 0) {
            return [];
        }

        // Identificar todos los bloques continuos
        $bloques = [];
        $bloqueActual = [$horas[0]];

        for ($i = 1; $i < count($horas); $i++) {
            if ($horas[$i] === $horas[$i-1] + 1) {
                $bloqueActual[] = $horas[$i];
            } else {
                $bloques[] = $bloqueActual;
                $bloqueActual = [$horas[$i]];
            }
        }
        $bloques[] = $bloqueActual;

        // Ordenar bloques por tamaño (mayor primero)
        usort($bloques, function ($a, $b) {
            return count($b) <=> count($a);
        });

        // Tomar los N bloques más grandes hasta completar maxHoras
        $resultado = [];
        $horasAsignadas = 0;
        $bloquesUsados = 0;

        foreach ($bloques as $bloque) {
            if ($bloquesUsados >= $maxBloques || $horasAsignadas >= $maxHoras) {
                break;
            }

            $horasAgregar = min(count($bloque), $maxHoras - $horasAsignadas);
            $resultado = array_merge($resultado, array_slice($bloque, 0, $horasAgregar));
            $horasAsignadas += $horasAgregar;
            $bloquesUsados++;
        }

        sort($resultado);
        return $resultado;
    }

    /**
     * Asigna un bloque de horas a un asesor
     */
    private function asignarBloque(string $fecha, int $advisorId, array $horas): void
    {
        foreach ($horas as $hora) {
            $this->registrarAsignacion($fecha, $advisorId, $hora);
        }
    }

    /**
     * Registra una asignación hora por hora
     */
    private function registrarAsignacion(string $fecha, int $advisorId, int $hora): void
    {
        // Registrar en assignments
        if (!isset($this->assignments[$fecha])) {
            $this->assignments[$fecha] = [];
        }
        if (!isset($this->assignments[$fecha][$hora])) {
            $this->assignments[$fecha][$hora] = [];
        }
        $this->assignments[$fecha][$hora][] = $advisorId;

        // Registrar en advisorHours
        if (!isset($this->advisorHours[$advisorId])) {
            $this->advisorHours[$advisorId] = [];
        }
        if (!isset($this->advisorHours[$advisorId][$fecha])) {
            $this->advisorHours[$advisorId][$fecha] = [];
        }
        $this->advisorHours[$advisorId][$fecha][] = $hora;

        // Actualizar contador mensual
        $this->advisorMonthHours[$advisorId] = ($this->advisorMonthHours[$advisorId] ?? 0) + 1;
    }

    /**
     * Cubre huecos restantes (segunda pasada)
     */
    private function cubrirHuecos(string $fecha, array $asesores, array $horasPorCubrir, ?int $veladaAdvisorId): void
    {
        foreach ($horasPorCubrir as $hora => $faltantes) {
            while ($faltantes > 0) {
                $asignado = false;

                foreach ($asesores as $advisorId => $advisor) {
                    if ($advisorId === $veladaAdvisorId) {
                        continue;
                    }

                    // Verificar si puede tomar esta hora
                    if (!$this->puedeTomarHora($fecha, $advisor, $hora)) {
                        continue;
                    }

                    // Verificar si ya está asignado a esta hora
                    $yaAsignado = in_array($advisorId, $this->assignments[$fecha][$hora] ?? [], true);
                    if ($yaAsignado) {
                        continue;
                    }

                    // Asignar
                    $this->registrarAsignacion($fecha, $advisorId, $hora);
                    $faltantes--;
                    $asignado = true;
                    break;
                }

                if (!$asignado) {
                    break; // No hay más asesores disponibles para esta hora
                }
            }
        }
    }

    /**
     * Verifica si un asesor puede tomar una hora específica
     */
    private function puedeTomarHora(string $fecha, array $advisor, int $hora): bool
    {
        $advisorId = $advisor['id'];

        // Verificar rango de contrato
        if ($hora < $advisor['hora_inicio_contrato'] || $hora > $advisor['hora_fin_contrato']) {
            return false;
        }

        // No asignar horas de velada (madrugada) a asesores normales
        if (in_array($hora, $this->horasVelada, true)) {
            return false;
        }

        // Horas de transición (22-23) solo para asesores con VPN
        if (in_array($hora, $this->horasTransicion, true) && !$advisor['tiene_vpn']) {
            return false;
        }

        // Verificar máximo de horas del día
        $horasHoy = count($this->advisorHours[$advisorId][$fecha] ?? []);
        if ($horasHoy >= $advisor['max_horas_dia']) {
            return false;
        }

        // Verificar extras
        if (!$advisor['permite_extras'] && $horasHoy >= 8) {
            return false;
        }

        // Verificar horario partido
        if (!$advisor['permite_horario_partido']) {
            $horasAsignadas = $this->advisorHours[$advisorId][$fecha] ?? [];
            if (!empty($horasAsignadas)) {
                // Verificar si esta hora es adyacente
                $adyacente = in_array($hora - 1, $horasAsignadas, true) ||
                             in_array($hora + 1, $horasAsignadas, true);
                if (!$adyacente) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Inserta todas las asignaciones en la base de datos
     */
    private function insertAssignments(): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO shift_assignments (
                schedule_id, advisor_id, campaign_id, fecha, hora, tipo, es_extra, modalidad
            ) VALUES (
                :schedule_id, :advisor_id, :campaign_id, :fecha, :hora, :tipo, :es_extra, :modalidad
            )
            ON CONFLICT (advisor_id, fecha, hora) DO NOTHING
        ");

        $count = 0;

        foreach ($this->assignments as $fecha => $horasData) {
            foreach ($horasData as $hora => $advisorIds) {
                foreach ($advisorIds as $advisorId) {
                    $horasHoy = count($this->advisorHours[$advisorId][$fecha] ?? []);
                    $esExtra = $horasHoy > 8;

                    // Determinar tipo
                    $tipo = 'normal';
                    if (in_array($hora, $this->horasVelada, true) || in_array($hora, $this->horasTransicion, true)) {
                        $tipo = 'nocturno';
                    } elseif ($esExtra) {
                        $tipo = 'extra';
                    }

                    // Determinar modalidad
                    $modalidad = $this->getModalidad($hora);

                    $stmt->execute([
                        ':schedule_id' => $this->scheduleId,
                        ':advisor_id' => $advisorId,
                        ':campaign_id' => $this->campaignId,
                        ':fecha' => $fecha,
                        ':hora' => $hora,
                        ':tipo' => $tipo,
                        ':es_extra' => $esExtra ? 'true' : 'false',
                        ':modalidad' => $modalidad,
                    ]);

                    if ($stmt->rowCount() > 0) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Determina la modalidad de trabajo según la hora
     */
    private function getModalidad(int $hora): string
    {
        $finTeletrabajoManana = (int)$this->campaign['hora_fin_teletrabajo_manana'];
        $inicioPresencial = (int)$this->campaign['hora_inicio_presencial'];
        $finPresencial = (int)$this->campaign['hora_fin_presencial'];

        if ($hora < $finTeletrabajoManana) {
            return 'teletrabajo';
        }
        if ($hora >= $inicioPresencial && $hora < $finPresencial) {
            return 'presencial';
        }
        return 'teletrabajo';
    }

    /**
     * Parsea un array de PostgreSQL a PHP
     */
    private function parseSmallIntArray(string $pgArray): array
    {
        $trimmed = trim($pgArray, '{}');
        if ($trimmed === '') {
            return [];
        }
        return array_map('intval', array_filter(explode(',', $trimmed), 'strlen'));
    }

    /**
     * Convierte a booleano
     */
    private function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['t', 'true', '1', 'yes'], true);
        }
        return (bool)$value;
    }
}
