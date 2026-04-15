<?php

declare(strict_types=1);

namespace App\Services;

use Database;

class AuditService
{
    /**
     * Registrar una accion en el audit log.
     */
    public static function log(
        string $accion,
        string $entidad,
        int $entidadId,
        array $datosBefore = [],
        array $datosAfter = []
    ): void {
        $userId = $_SESSION['user']['id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '0.0.0.0';

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            INSERT INTO audit_log (user_id, accion, entidad, entidad_id, datos_antes, datos_despues, ip)
            VALUES (:user_id, :accion, :entidad, :entidad_id, :datos_antes, :datos_despues, :ip)
        ");

        $stmt->execute([
            ':user_id'       => $userId,
            ':accion'        => $accion,
            ':entidad'       => $entidad,
            ':entidad_id'    => $entidadId,
            ':datos_antes'   => !empty($datosBefore) ? json_encode($datosBefore, JSON_UNESCAPED_UNICODE) : null,
            ':datos_despues' => !empty($datosAfter) ? json_encode($datosAfter, JSON_UNESCAPED_UNICODE) : null,
            ':ip'            => $ip,
        ]);
    }

    /**
     * Obtener registros de auditoria con filtros y paginacion.
     */
    public static function getPaginated(
        int $page = 1,
        int $perPage = 30,
        ?int $userId = null,
        ?string $entidad = null,
        ?string $fechaDesde = null,
        ?string $fechaHasta = null
    ): array {
        $pdo = Database::getConnection();
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];

        if ($userId !== null) {
            $where[] = 'a.user_id = :user_id';
            $params[':user_id'] = $userId;
        }
        if ($entidad !== null && $entidad !== '') {
            $where[] = 'a.entidad = :entidad';
            $params[':entidad'] = $entidad;
        }
        if ($fechaDesde !== null && $fechaDesde !== '') {
            $where[] = 'a.created_at >= :fecha_desde';
            $params[':fecha_desde'] = $fechaDesde . ' 00:00:00';
        }
        if ($fechaHasta !== null && $fechaHasta !== '') {
            $where[] = 'a.created_at <= :fecha_hasta';
            $params[':fecha_hasta'] = $fechaHasta . ' 23:59:59';
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_log a {$whereClause}");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        // Items
        $sql = "
            SELECT a.*, u.nombre as user_nombre, u.apellido as user_apellido
            FROM audit_log a
            LEFT JOIN users u ON u.id = a.user_id
            {$whereClause}
            ORDER BY a.created_at DESC
            LIMIT :lim OFFSET :off
        ";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':lim', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();

        return [
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int)ceil($total / max($perPage, 1)),
        ];
    }

    /**
     * Obtener registros de auditoria para una entidad especifica.
     */
    public static function getByEntity(string $entidad, int $entidadId, int $limit = 50): array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT a.*, u.nombre as user_nombre, u.apellido as user_apellido
            FROM audit_log a
            LEFT JOIN users u ON u.id = a.user_id
            WHERE a.entidad = :entidad AND a.entidad_id = :entidad_id
            ORDER BY a.created_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':entidad', $entidad);
        $stmt->bindValue(':entidad_id', $entidadId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Obtener lista de entidades unicas para filtro.
     */
    public static function getDistinctEntities(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT DISTINCT entidad FROM audit_log WHERE entidad IS NOT NULL ORDER BY entidad");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Exportar registros a CSV.
     */
    public static function exportCsv(
        ?int $userId = null,
        ?string $entidad = null,
        ?string $fechaDesde = null,
        ?string $fechaHasta = null
    ): array {
        $pdo = Database::getConnection();

        $where = [];
        $params = [];

        if ($userId !== null) {
            $where[] = 'a.user_id = :user_id';
            $params[':user_id'] = $userId;
        }
        if ($entidad !== null && $entidad !== '') {
            $where[] = 'a.entidad = :entidad';
            $params[':entidad'] = $entidad;
        }
        if ($fechaDesde !== null && $fechaDesde !== '') {
            $where[] = 'a.created_at >= :fecha_desde';
            $params[':fecha_desde'] = $fechaDesde . ' 00:00:00';
        }
        if ($fechaHasta !== null && $fechaHasta !== '') {
            $where[] = 'a.created_at <= :fecha_hasta';
            $params[':fecha_hasta'] = $fechaHasta . ' 23:59:59';
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $pdo->prepare("
            SELECT a.id, a.created_at, u.nombre as user_nombre, u.apellido as user_apellido,
                   a.accion, a.entidad, a.entidad_id, a.ip, a.datos_antes, a.datos_despues
            FROM audit_log a
            LEFT JOIN users u ON u.id = a.user_id
            {$whereClause}
            ORDER BY a.created_at DESC
            LIMIT 5000
        ");
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
