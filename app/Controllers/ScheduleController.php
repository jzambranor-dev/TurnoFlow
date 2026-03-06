<?php

declare(strict_types=1);

namespace App\Controllers;

use Database;

class ScheduleController
{
    public function index(): void
    {
        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        if ($user['rol'] === 'coordinador') {
            $stmt = $pdo->query("
                SELECT s.*, c.nombre as campaign_nombre,
                       u.nombre || ' ' || u.apellido as generado_por_nombre
                FROM schedules s
                JOIN campaigns c ON c.id = s.campaign_id
                LEFT JOIN users u ON u.id = s.generado_por
                ORDER BY s.created_at DESC
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT s.*, c.nombre as campaign_nombre,
                       u.nombre || ' ' || u.apellido as generado_por_nombre
                FROM schedules s
                JOIN campaigns c ON c.id = s.campaign_id
                LEFT JOIN users u ON u.id = s.generado_por
                WHERE c.supervisor_id = :uid
                ORDER BY s.created_at DESC
            ");
            $stmt->execute([':uid' => $user['id']]);
        }

        $schedules = $stmt->fetchAll();

        $pageTitle = 'Horarios';
        $currentPage = 'schedules';

        include APP_PATH . '/Views/schedules/index.php';
    }

    public function showImport(): void
    {
        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        if ($user['rol'] === 'coordinador') {
            $stmt = $pdo->query("SELECT id, nombre FROM campaigns WHERE estado = 'activa' ORDER BY nombre");
        } else {
            $stmt = $pdo->prepare("SELECT id, nombre FROM campaigns WHERE supervisor_id = :uid AND estado = 'activa' ORDER BY nombre");
            $stmt->execute([':uid' => $user['id']]);
        }

        $campaigns = $stmt->fetchAll();

        $pageTitle = 'Importar Dimensionamiento';
        $currentPage = 'schedules';

        include APP_PATH . '/Views/schedules/import.php';
    }

    public function import(): void
    {
        // TODO: Implementar importación de Excel
        header('Location: ' . BASE_URL . '/schedules');
        exit;
    }

    public function show(int $id): void
    {
        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT s.*, c.nombre as campaign_nombre
            FROM schedules s
            JOIN campaigns c ON c.id = s.campaign_id
            WHERE s.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $schedule = $stmt->fetch();

        if (!$schedule) {
            header('Location: ' . BASE_URL . '/schedules');
            exit;
        }

        // Obtener asignaciones de este horario
        $stmt = $pdo->prepare("
            SELECT sa.*, a.nombres, a.apellidos
            FROM shift_assignments sa
            JOIN advisors a ON a.id = sa.advisor_id
            WHERE sa.schedule_id = :schedule_id
            ORDER BY sa.fecha, sa.hora, a.apellidos
        ");
        $stmt->execute([':schedule_id' => $id]);
        $assignments = $stmt->fetchAll();

        $pageTitle = 'Ver Horario';
        $currentPage = 'schedules';

        include APP_PATH . '/Views/schedules/show.php';
    }

    public function submit(int $id): void
    {
        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            UPDATE schedules SET status = 'enviado'
            WHERE id = :id AND status = 'borrador'
        ");
        $stmt->execute([':id' => $id]);

        header('Location: ' . BASE_URL . '/schedules');
        exit;
    }

    public function approve(int $id): void
    {
        $user = $_SESSION['user'];

        if ($user['rol'] !== 'coordinador') {
            header('Location: ' . BASE_URL . '/schedules');
            exit;
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            UPDATE schedules SET
                status = 'aprobado',
                aprobado_por = :aprobado_por,
                aprobado_at = NOW()
            WHERE id = :id AND status = 'enviado'
        ");
        $stmt->execute([
            ':id' => $id,
            ':aprobado_por' => $user['id']
        ]);

        header('Location: ' . BASE_URL . '/schedules');
        exit;
    }

    public function reject(int $id): void
    {
        $user = $_SESSION['user'];

        if ($user['rol'] !== 'coordinador') {
            header('Location: ' . BASE_URL . '/schedules');
            exit;
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            UPDATE schedules SET status = 'rechazado'
            WHERE id = :id AND status = 'enviado'
        ");
        $stmt->execute([':id' => $id]);

        header('Location: ' . BASE_URL . '/schedules');
        exit;
    }

    public function mySchedule(): void
    {
        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        // Buscar el advisor asociado a este usuario por email
        $stmt = $pdo->prepare("
            SELECT a.* FROM advisors a
            WHERE LOWER(a.cedula) = LOWER(:email) OR EXISTS (
                SELECT 1 FROM users u WHERE u.id = :user_id AND
                (LOWER(u.email) LIKE LOWER(CONCAT('%', a.cedula, '%')) OR
                 LOWER(a.nombres || ' ' || a.apellidos) = LOWER(u.nombre || ' ' || u.apellido))
            )
            LIMIT 1
        ");
        $stmt->execute([':email' => $user['email'], ':user_id' => $user['id']]);
        $advisor = $stmt->fetch();

        // Si no encontramos por email, buscar por nombre completo
        if (!$advisor) {
            $stmt = $pdo->prepare("
                SELECT a.* FROM advisors a
                WHERE LOWER(a.nombres || ' ' || a.apellidos) = LOWER(:nombre)
                   OR LOWER(a.apellidos || ' ' || a.nombres) = LOWER(:nombre)
                LIMIT 1
            ");
            $stmt->execute([':nombre' => $user['nombre'] . ' ' . $user['apellido']]);
            $advisor = $stmt->fetch();
        }

        $assignments = [];
        $currentSchedule = null;

        if ($advisor) {
            // Obtener horarios aprobados del mes actual
            $currentMonth = date('n');
            $currentYear = date('Y');

            $stmt = $pdo->prepare("
                SELECT sa.*, s.periodo_mes, s.periodo_anio, s.status,
                       c.nombre as campaign_nombre
                FROM shift_assignments sa
                JOIN schedules s ON s.id = sa.schedule_id
                JOIN campaigns c ON c.id = sa.campaign_id
                WHERE sa.advisor_id = :advisor_id
                  AND s.status = 'aprobado'
                  AND s.periodo_mes = :mes
                  AND s.periodo_anio = :anio
                ORDER BY sa.fecha, sa.hora
            ");
            $stmt->execute([
                ':advisor_id' => $advisor['id'],
                ':mes' => $currentMonth,
                ':anio' => $currentYear
            ]);
            $assignments = $stmt->fetchAll();

            // Obtener info del horario actual
            $stmt = $pdo->prepare("
                SELECT s.*, c.nombre as campaign_nombre
                FROM schedules s
                JOIN campaigns c ON c.id = s.campaign_id
                WHERE s.campaign_id = :campaign_id
                  AND s.status = 'aprobado'
                  AND s.periodo_mes = :mes
                  AND s.periodo_anio = :anio
                LIMIT 1
            ");
            $stmt->execute([
                ':campaign_id' => $advisor['campaign_id'],
                ':mes' => $currentMonth,
                ':anio' => $currentYear
            ]);
            $currentSchedule = $stmt->fetch();
        }

        $pageTitle = 'Mi Horario';
        $currentPage = 'my-schedule';

        include APP_PATH . '/Views/schedules/my-schedule.php';
    }
}
