<?php

declare(strict_types=1);

namespace App\Services;

use Database;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PDO;
use Throwable;

class AdvisorImportService
{
    /**
     * Process an uploaded Excel file to import advisors.
     *
     * @return array{created: int, updated: int, errors: array, credentials: array}
     */
    public static function processExcel(string $filePath, int $userId): array
    {
        $pdo = Database::getConnection();

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();

        $created = 0;
        $updated = 0;
        $errors = [];
        $credentials = [];

        // Cache campaign lookups to avoid repeated queries
        $campaignCache = [];

        // Get asesor role ID once
        $stmtRole = $pdo->prepare("SELECT id FROM roles WHERE nombre = 'asesor' LIMIT 1");
        $stmtRole->execute();
        $asesorRoleId = $stmtRole->fetchColumn();

        // Prepare statements once
        $stmtFindCampaign = $pdo->prepare(
            "SELECT id FROM campaigns WHERE LOWER(nombre) = LOWER(:name) AND estado = 'activa' LIMIT 1"
        );
        $stmtFindAdvisor = $pdo->prepare(
            "SELECT id FROM advisors WHERE cedula = :cedula LIMIT 1"
        );
        $stmtUpdateAdvisor = $pdo->prepare("
            UPDATE advisors SET
                nombres = :nombres,
                apellidos = :apellidos,
                campaign_id = :campaign_id,
                tipo_contrato = :tipo_contrato
            WHERE id = :id
        ");
        $stmtInsertAdvisor = $pdo->prepare("
            INSERT INTO advisors (nombres, apellidos, cedula, campaign_id, tipo_contrato)
            VALUES (:nombres, :apellidos, :cedula, :campaign_id, :tipo_contrato)
            RETURNING id
        ");
        $stmtInsertConstraints = $pdo->prepare("
            INSERT INTO advisor_constraints (advisor_id, tiene_vpn, permite_extras, max_horas_dia, modalidad_trabajo)
            VALUES (:advisor_id, :tiene_vpn, :permite_extras, 10, 'mixto')
        ");
        $stmtFindEmail = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmtInsertUser = $pdo->prepare("
            INSERT INTO users (nombre, apellido, email, password_hash, rol_id, activo)
            VALUES (:nombre, :apellido, :email, :password_hash, :rol_id, true)
        ");

        $pdo->beginTransaction();

        try {
            for ($row = 2; $row <= $highestRow; $row++) {
                $nombres = trim((string)$sheet->getCell('A' . $row)->getValue());
                $apellidos = trim((string)$sheet->getCell('B' . $row)->getValue());
                $cedula = trim((string)$sheet->getCell('C' . $row)->getValue());
                $emailRaw = trim((string)$sheet->getCell('D' . $row)->getValue());
                $campaignName = trim((string)$sheet->getCell('E' . $row)->getValue());
                $contratoRaw = strtolower(trim((string)$sheet->getCell('F' . $row)->getValue()));
                $vpnRaw = strtolower(trim((string)$sheet->getCell('G' . $row)->getValue()));
                $extrasRaw = strtolower(trim((string)$sheet->getCell('H' . $row)->getValue()));

                // Skip completely empty rows
                if ($nombres === '' && $apellidos === '' && $campaignName === '') {
                    continue;
                }

                // Validate required fields
                if ($nombres === '' || $apellidos === '') {
                    $errors[] = ['row' => $row, 'message' => 'Nombres y/o Apellidos vacios'];
                    continue;
                }

                if ($campaignName === '') {
                    $errors[] = ['row' => $row, 'message' => 'Campana vacia'];
                    continue;
                }

                // Look up campaign (cached)
                $campaignKey = mb_strtolower($campaignName);
                if (!isset($campaignCache[$campaignKey])) {
                    $stmtFindCampaign->execute([':name' => $campaignName]);
                    $campaignId = $stmtFindCampaign->fetchColumn();
                    $campaignCache[$campaignKey] = $campaignId !== false ? (int)$campaignId : null;
                }

                $campaignId = $campaignCache[$campaignKey];
                if ($campaignId === null) {
                    $errors[] = [
                        'row' => $row,
                        'message' => "Campana '" . $campaignName . "' no encontrada",
                    ];
                    continue;
                }

                // Parse optional fields
                $tipoContrato = in_array($contratoRaw, ['parcial', 'partial'], true)
                    ? 'parcial' : 'completo';
                $tieneVpn = in_array($vpnRaw, ['si', 'sí', 'yes', '1'], true);
                $permiteExtras = $extrasRaw === ''
                    ? true
                    : in_array($extrasRaw, ['si', 'sí', 'yes', '1'], true);

                // Check if advisor exists by cedula
                $advisorId = null;
                $isUpdate = false;

                if ($cedula !== '') {
                    $stmtFindAdvisor->execute([':cedula' => $cedula]);
                    $existingId = $stmtFindAdvisor->fetchColumn();

                    if ($existingId !== false) {
                        $advisorId = (int)$existingId;
                        $isUpdate = true;

                        try {
                            $stmtUpdateAdvisor->execute([
                                ':nombres' => $nombres,
                                ':apellidos' => $apellidos,
                                ':campaign_id' => $campaignId,
                                ':tipo_contrato' => $tipoContrato,
                                ':id' => $advisorId,
                            ]);
                            $updated++;
                        } catch (Throwable $e) {
                            $errors[] = ['row' => $row, 'message' => 'Error actualizando asesor: ' . $e->getMessage()];
                            continue;
                        }
                    }
                }

                // Insert new advisor
                if (!$isUpdate) {
                    try {
                        $stmtInsertAdvisor->execute([
                            ':nombres' => $nombres,
                            ':apellidos' => $apellidos,
                            ':cedula' => $cedula !== '' ? $cedula : null,
                            ':campaign_id' => $campaignId,
                            ':tipo_contrato' => $tipoContrato,
                        ]);
                        $advisorId = (int)$stmtInsertAdvisor->fetchColumn();

                        $stmtInsertConstraints->execute([
                            ':advisor_id' => $advisorId,
                            ':tiene_vpn' => $tieneVpn ? 'true' : 'false',
                            ':permite_extras' => $permiteExtras ? 'true' : 'false',
                        ]);

                        $created++;
                    } catch (Throwable $e) {
                        $detail = $e->getMessage();
                        if (strpos($detail, 'advisors_cedula_key') !== false) {
                            $errors[] = ['row' => $row, 'message' => "Cedula '{$cedula}' duplicada"];
                        } else {
                            $errors[] = ['row' => $row, 'message' => 'Error insertando asesor: ' . $detail];
                        }
                        continue;
                    }
                }

                // Create user account
                if (!$asesorRoleId || $advisorId === null) {
                    continue;
                }

                // Determine email
                $email = '';
                if ($emailRaw !== '') {
                    $email = $emailRaw;
                } elseif ($cedula !== '') {
                    $email = 'asesor' . preg_replace('/[^0-9a-zA-Z]/', '', $cedula) . '@asesor_snc.com';
                } else {
                    $email = 'asesor' . $advisorId . '@asesor_snc.com';
                }
                $email = strtolower($email);

                // Check if email already exists
                $stmtFindEmail->execute([':email' => $email]);
                if ($stmtFindEmail->fetchColumn()) {
                    // User already exists, skip creation
                    continue;
                }

                // Create user
                try {
                    $passwordPlain = bin2hex(random_bytes(4));
                    $passwordHash = password_hash($passwordPlain, PASSWORD_DEFAULT);

                    $stmtInsertUser->execute([
                        ':nombre' => $nombres,
                        ':apellido' => $apellidos,
                        ':email' => $email,
                        ':password_hash' => $passwordHash,
                        ':rol_id' => (int)$asesorRoleId,
                    ]);

                    $credentials[] = [
                        'nombres' => $nombres,
                        'apellidos' => $apellidos,
                        'email' => $email,
                        'password' => $passwordPlain,
                    ];
                } catch (Throwable $e) {
                    // User creation failure is non-fatal for the advisor import
                    $errors[] = [
                        'row' => $row,
                        'message' => 'Asesor importado pero error creando usuario: ' . $e->getMessage(),
                    ];
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
            'credentials' => $credentials,
        ];
    }

    /**
     * Generate an Excel template for advisor imports.
     */
    public static function generateTemplate(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Asesores');

        // Headers
        $headers = ['Nombres', 'Apellidos', 'Cedula', 'Email', 'Campana', 'Contrato', 'VPN', 'Extras'];
        foreach ($headers as $col => $header) {
            $cell = $sheet->getCellByColumnAndRow($col + 1, 1);
            $cell->setValue($header);
            $cell->getStyle()->getFont()->setBold(true);
        }

        // Example row
        $example = ['Juan', 'Perez', '1234567890', '', 'Videollamada', 'completo', 'no', 'si'];
        foreach ($example as $col => $value) {
            $sheet->setCellValueByColumnAndRow($col + 1, 2, $value);
        }

        // Auto-size columns
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * Generate a credentials Excel from the import results.
     *
     * @param array $credentials Array of ['nombres', 'apellidos', 'email', 'password']
     */
    public static function generateCredentialsExcel(array $credentials): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Credenciales');

        // Headers
        $headers = ['Nombres', 'Apellidos', 'Email', 'Contrasena Temporal'];
        foreach ($headers as $col => $header) {
            $cell = $sheet->getCellByColumnAndRow($col + 1, 1);
            $cell->setValue($header);
            $cell->getStyle()->getFont()->setBold(true);
        }

        // Data rows
        foreach ($credentials as $i => $cred) {
            $row = $i + 2;
            $sheet->setCellValueByColumnAndRow(1, $row, $cred['nombres']);
            $sheet->setCellValueByColumnAndRow(2, $row, $cred['apellidos']);
            $sheet->setCellValueByColumnAndRow(3, $row, $cred['email']);
            $sheet->setCellValueByColumnAndRow(4, $row, $cred['password']);
        }

        // Auto-size columns
        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }
}
