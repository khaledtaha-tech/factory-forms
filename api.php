<?php
/**
 * ==============================================================================
 * UNIFIED FORMS JSON API
 * Al Manar Pipes Factory | Production Forms Data Store
 * ==============================================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config.php';

// Helper for sending structured JSON responses
function sendJsonResponse(array $payload, int $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Parse request input (supports JSON payload or standard form-data)
$rawInput = file_get_contents('php://input');
$inputData = [];
if (!empty($rawInput)) {
    $parsedJson = json_decode($rawInput, true);
    if (is_array($parsedJson)) {
        $inputData = $parsedJson;
    }
}

// Merge GET and POST parameters (JSON takes precedence if present)
$params = array_merge($_GET, $_POST, $inputData);
$action = trim($params['action'] ?? '');

try {
    // Ensure table exists on first run
    ensureFormRecordsTableExists();
    $pdo = getDbConnection();

    switch ($action) {

        /**
         * --------------------------------------------------------------------------
         * ACTION: save (POST)
         * Inserts or updates a form record matching (form_type, entry_date, machine_line)
         * --------------------------------------------------------------------------
         */
        case 'save':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' && empty($inputData)) {
                sendJsonResponse([
                    'status'  => 'error',
                    'message' => 'The save action requires a POST request with payload.'
                ], 405);
            }

            $formType    = trim($params['form_type'] ?? '');
            $formRef     = trim($params['form_ref'] ?? '');
            $entryDate   = trim($params['entry_date'] ?? '');
            $machineLine = isset($params['machine_line']) ? trim($params['machine_line']) : null;
            $formData    = $params['form_data'] ?? null;

            if (empty($formType)) {
                sendJsonResponse(['status' => 'error', 'message' => 'Missing required parameter: form_type'], 400);
            }
            if (empty($entryDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate)) {
                sendJsonResponse(['status' => 'error', 'message' => 'Invalid or missing entry_date. Expected format: YYYY-MM-DD'], 400);
            }
            if ($formData === null || $formData === '') {
                sendJsonResponse(['status' => 'error', 'message' => 'Missing required parameter: form_data'], 400);
            }

            // Encode form_data to valid JSON string if provided as array/object
            $jsonString = is_string($formData) ? $formData : json_encode($formData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (json_decode($jsonString) === null && json_last_error() !== JSON_ERROR_NONE) {
                sendJsonResponse(['status' => 'error', 'message' => 'Invalid JSON provided in form_data payload'], 400);
            }

            // Normalize empty strings to null for machine_line and form_ref
            $machineLine = ($machineLine === '') ? null : $machineLine;
            $formRef     = ($formRef === '') ? null : $formRef;

            // Check for existing matching record
            if ($machineLine === null) {
                $checkStmt = $pdo->prepare('SELECT id FROM form_records WHERE form_type = ? AND entry_date = ? AND machine_line IS NULL LIMIT 1');
                $checkStmt->execute([$formType, $entryDate]);
            } else {
                $checkStmt = $pdo->prepare('SELECT id FROM form_records WHERE form_type = ? AND entry_date = ? AND machine_line = ? LIMIT 1');
                $checkStmt->execute([$formType, $entryDate, $machineLine]);
            }

            $existing = $checkStmt->fetch();

            if ($existing) {
                $recordId = (int)$existing['id'];
                $updateStmt = $pdo->prepare('UPDATE form_records SET form_ref = ?, form_data = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                $updateStmt->execute([$formRef, $jsonString, $recordId]);

                sendJsonResponse([
                    'status'           => 'success',
                    'action_performed' => 'updated',
                    'id'               => $recordId,
                    'form_type'        => $formType,
                    'entry_date'       => $entryDate,
                    'machine_line'     => $machineLine,
                    'form_ref'         => $formRef,
                    'message'          => 'Form record updated successfully.'
                ]);
            } else {
                $insertStmt = $pdo->prepare('INSERT INTO form_records (form_type, form_ref, entry_date, machine_line, form_data) VALUES (?, ?, ?, ?, ?)');
                $insertStmt->execute([$formType, $formRef, $entryDate, $machineLine, $jsonString]);
                $newId = (int)$pdo->lastInsertId();

                sendJsonResponse([
                    'status'           => 'success',
                    'action_performed' => 'created',
                    'id'               => $newId,
                    'form_type'        => $formType,
                    'entry_date'       => $entryDate,
                    'machine_line'     => $machineLine,
                    'form_ref'         => $formRef,
                    'message'          => 'Form record inserted successfully.'
                ], 201);
            }
            break;

        /**
         * --------------------------------------------------------------------------
         * ACTION: load (GET)
         * Fetches a single record by form_type, entry_date, and optional machine_line
         * --------------------------------------------------------------------------
         */
        case 'load':
            $formType    = trim($params['form_type'] ?? '');
            $entryDate   = trim($params['entry_date'] ?? '');
            $machineLine = isset($params['machine_line']) ? trim($params['machine_line']) : null;
            $machineLine = ($machineLine === '') ? null : $machineLine;

            if (empty($formType)) {
                sendJsonResponse(['status' => 'error', 'message' => 'Missing required parameter: form_type'], 400);
            }
            if (empty($entryDate)) {
                sendJsonResponse(['status' => 'error', 'message' => 'Missing required parameter: entry_date'], 400);
            }

            if ($machineLine === null) {
                $stmt = $pdo->prepare('SELECT id, form_type, form_ref, entry_date, machine_line, form_data, created_at, updated_at FROM form_records WHERE form_type = ? AND entry_date = ? ORDER BY id DESC LIMIT 1');
                $stmt->execute([$formType, $entryDate]);
            } else {
                $stmt = $pdo->prepare('SELECT id, form_type, form_ref, entry_date, machine_line, form_data, created_at, updated_at FROM form_records WHERE form_type = ? AND entry_date = ? AND machine_line = ? LIMIT 1');
                $stmt->execute([$formType, $entryDate, $machineLine]);
            }

            $record = $stmt->fetch();

            if (!$record) {
                sendJsonResponse([
                    'status'  => 'not_found',
                    'message' => 'No record found for the specified criteria.',
                    'criteria' => [
                        'form_type'    => $formType,
                        'entry_date'   => $entryDate,
                        'machine_line' => $machineLine
                    ]
                ], 404);
            }

            // Decode form_data JSON string to native structure for clean client consumption
            $decodedData = json_decode($record['form_data'], true);
            $record['form_data'] = ($decodedData !== null) ? $decodedData : $record['form_data'];

            sendJsonResponse([
                'status' => 'success',
                'record' => $record
            ]);
            break;

        /**
         * --------------------------------------------------------------------------
         * ACTION: list (GET)
         * Returns available dates and machine entries for a given form_type
         * --------------------------------------------------------------------------
         */
        case 'list':
            $formType = trim($params['form_type'] ?? '');
            $limit    = isset($params['limit']) ? max(1, min(500, (int)$params['limit'])) : 100;

            if (empty($formType)) {
                sendJsonResponse(['status' => 'error', 'message' => 'Missing required parameter: form_type'], 400);
            }

            $stmt = $pdo->prepare('SELECT id, form_type, form_ref, entry_date, machine_line, created_at, updated_at FROM form_records WHERE form_type = ? ORDER BY entry_date DESC, id DESC LIMIT ' . $limit);
            $stmt->execute([$formType]);
            $records = $stmt->fetchAll();

            sendJsonResponse([
                'status'    => 'success',
                'form_type' => $formType,
                'total'     => count($records),
                'records'   => $records
            ]);
            break;

        /**
         * --------------------------------------------------------------------------
         * ACTION: init / status (GET)
         * Validates database connection, schema status, and system metadata
         * --------------------------------------------------------------------------
         */
        case 'init':
        case 'status':
            $countStmt = $pdo->query('SELECT COUNT(*) AS total_count FROM form_records');
            $countRow  = $countStmt->fetch();

            sendJsonResponse([
                'status'       => 'success',
                'connected'    => true,
                'database'     => DB_NAME,
                'user'         => DB_USER,
                'table'        => 'form_records',
                'total_rows'   => (int)($countRow['total_count'] ?? 0),
                'server_time'  => date('Y-m-d H:i:s')
            ]);
            break;

        /**
         * --------------------------------------------------------------------------
         * ACTION: get_protection (GET / POST)
         * Returns the array of currently protected form filenames
         * --------------------------------------------------------------------------
         */
        case 'get_protection':
            $configFile = __DIR__ . '/config/protected_forms.json';
            $protectedFiles = [];
            if (file_exists($configFile)) {
                $rawJson = file_get_contents($configFile);
                $decoded = json_decode($rawJson, true);
                if (is_array($decoded)) {
                    $protectedFiles = $decoded;
                }
            }

            sendJsonResponse([
                'status'          => 'success',
                'protected_files' => $protectedFiles
            ]);
            break;

        /**
         * --------------------------------------------------------------------------
         * ACTION: update_protection (POST)
         * Receives { protected_files: ["monjez_tasks_v2.html", ...] } and updates config
         * --------------------------------------------------------------------------
         */
        case 'update_protection':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' && empty($inputData)) {
                sendJsonResponse([
                    'status'  => 'error',
                    'message' => 'The update_protection action requires a POST request with payload.'
                ], 405);
            }

            $rawFiles = $params['protected_files'] ?? null;
            if (!is_array($rawFiles)) {
                sendJsonResponse([
                    'status'  => 'error',
                    'message' => 'Missing or invalid protected_files array in payload.'
                ], 400);
            }

            $sanitizedFiles = [];
            foreach ($rawFiles as $fileItem) {
                if (is_string($fileItem) && trim($fileItem) !== '') {
                    $sanitizedFiles[] = basename(trim($fileItem));
                }
            }
            $sanitizedFiles = array_values(array_unique($sanitizedFiles));

            $configDir = __DIR__ . '/config';
            if (!is_dir($configDir)) {
                @mkdir($configDir, 0755, true);
            }
            $configFile = $configDir . '/protected_forms.json';
            $encodedJson = json_encode($sanitizedFiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            if (file_put_contents($configFile, $encodedJson) === false) {
                sendJsonResponse([
                    'status'  => 'error',
                    'message' => 'Failed to save configuration to disk.'
                ], 500);
            }

            sendJsonResponse([
                'status'          => 'success',
                'protected_files' => $sanitizedFiles
            ]);
            break;

        default:
            sendJsonResponse([
                'status'            => 'error',
                'message'           => 'Invalid or unsupported action requested.',
                'supported_actions' => ['save', 'load', 'list', 'status', 'init', 'get_protection', 'update_protection']
            ], 400);
            break;
    }

} catch (PDOException $e) {
    sendJsonResponse([
        'status'  => 'error',
        'type'    => 'DatabaseConnectionError',
        'message' => 'Database operation failed: ' . $e->getMessage()
    ], 500);
} catch (Exception $e) {
    sendJsonResponse([
        'status'  => 'error',
        'type'    => 'ApplicationError',
        'message' => $e->getMessage()
    ], 500);
}
