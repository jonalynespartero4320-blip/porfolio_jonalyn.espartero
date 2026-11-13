<?php

require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';
require_once ROOT_PATH . '/modules/Auth/controllers/PDLController.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();

function pdl_import_json(string $status, string $message = '', array $extra = []): void
{
    header('Content-Type: application/json');
    echo json_encode(array_merge([
        'status' => $status,
        'ok' => $status === 'success',
        'message' => $message,
    ], $extra));
    exit;
}

function pdl_import_normalize_header(string $value): string
{
    return strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $value)));
}

function pdl_import_trim(?string $value): string
{
    return trim((string) $value);
}

function pdl_import_require_mode_permission(string $mode): void
{
    require_permission($mode === 'visitors' ? 'pdl.edit' : 'pdl.create');
}

function pdl_import_download_csv(string $filename, array $rows): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

function pdl_import_download_text(string $filename, string $content): void
{
    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $content;
    exit;
}

function pdl_import_download_pdl_template(): void
{
    pdl_import_download_csv('pdl_import_template.csv', [
        ['record_key', 'pdl_id', 'pdl_fname', 'pdl_mname', 'pdl_lname', 'pdl_suffix', 'pdl_age', 'pdl_sex', 'case_no', 'court', 'dorm_no'],
        ['PDL-001', '', 'JUAN', 'SANTOS', 'DELA CRUZ', '', '32', 'Male', 'CASE-001', 'RTC BRANCH 1', 'Dorm 1'],
        ['PDL-002', '', 'ANA', 'REYES', 'SANTOS', '', '28', 'Female', 'CASE-002', 'MTC BRANCH 4', 'Dorm 2'],
    ]);
}

function pdl_import_download_pdl_outline_template(): void
{
    pdl_import_download_text('pdl_import_outline_template.txt',
        "Resident|PDL-001||JUAN|SANTOS|DELA CRUZ||32|Male|CASE-001|RTC BRANCH 1|Dorm 1\r\n" .
        "Resident|PDL-002||ANA|REYES|SANTOS||28|Female|CASE-002|MTC BRANCH 4|Dorm 2\r\n"
    );
}

function pdl_import_download_visitors_template(): void
{
    pdl_import_download_csv('pdl_visitor_import_template.csv', [
        ['visitor_fname', 'visitor_mname', 'visitor_lname', 'visitor_age', 'visitor_sex', 'visitor_relationship', 'visitor_address', 'visitor_contact'],
        ['MARIA', '', 'DELA CRUZ', '56', 'Female', 'Mother', '123 Sample Street, Demo City', '09171234567'],
        ['PEDRO', '', 'DELA CRUZ', '58', 'Male', 'Father', '123 Sample Street, Demo City', '09179876543'],
    ]);
}

function pdl_import_download_visitors_outline_template(): void
{
    pdl_import_download_text('pdl_visitor_import_outline_template.txt',
        "VISITOR|MARIA||DELA CRUZ|56|Female|Mother|123 Sample Street, Demo City|09171234567\r\n" .
        "VISITOR|PEDRO||DELA CRUZ|58|Male|Father|123 Sample Street, Demo City|09179876543\r\n"
    );
}

function pdl_import_download_guide(string $mode): void
{
    if ($mode === 'visitors') {
        pdl_import_download_text('pdl_visitor_import_guide.txt',
            "Visitor Import Guide\r\n====================\r\n\r\n" .
            "1. Open visitor import from the specific Resident row in Resident Management.\r\n" .
            "2. Upload visitors only for that selected Resident.\r\n" .
            "3. CSV columns: visitor_fname, visitor_mname, visitor_lname, visitor_age, visitor_sex, visitor_relationship, visitor_address, visitor_contact\r\n" .
            "4. TXT outline format: VISITOR|visitor_fname|visitor_mname|visitor_lname|visitor_age|visitor_sex|visitor_relationship|visitor_address|visitor_contact\r\n" .
            "5. The importer appends visitors and blocks import beyond 5 total visitors.\r\n"
        );
    }

    pdl_import_download_text('pdl_import_guide.txt',
        "Resident Import Guide\r\n================\r\n\r\n" .
        "1. This importer creates Resident records only.\r\n" .
        "2. CSV columns: record_key, pdl_id, pdl_fname, pdl_mname, pdl_lname, pdl_suffix, pdl_age, pdl_sex, case_no, court, dorm_no\r\n" .
        "3. TXT outline format: Resident|record_key|pdl_id|pdl_fname|pdl_mname|pdl_lname|pdl_suffix|pdl_age|pdl_sex|case_no|court|dorm_no\r\n" .
        "4. Leave pdl_id blank to auto-generate.\r\n" .
        "5. Import visitors later from the Import Visitors button on the Resident row.\r\n"
    );
}

function pdl_import_existing_pdl_id(PDO $pdo, string $pdlId): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM pdl_info WHERE pdl_id = ? LIMIT 1");
    $stmt->execute([$pdlId]);
    return (bool) $stmt->fetchColumn();
}

function pdl_import_find_pdl(PDO $pdo, int $pdlId): ?array
{
    $stmt = $pdo->prepare("SELECT id, pdl_id, pdl_fname, pdl_lname FROM pdl_info WHERE id = ? LIMIT 1");
    $stmt->execute([$pdlId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function pdl_import_next_pdl_id(PDO $pdo, string $sex): string
{
    static $counters = [];

    $year = date('Y');
    $gender = strtolower(trim($sex)) === 'female' ? 'F' : 'M';
    $key = $gender . '-' . $year;

    if (!isset($counters[$key])) {
        $stmt = $pdo->prepare("
            SELECT pdl_id
            FROM pdl_info
            WHERE pdl_id LIKE ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute(["PDL-$gender-$year-%"]);
        $lastPdlId = trim((string) ($stmt->fetch(PDO::FETCH_ASSOC)['pdl_id'] ?? ''));
        $counters[$key] = $lastPdlId !== '' ? (int) substr($lastPdlId, strrpos($lastPdlId, '-') + 1) : 0;
    }

    $counters[$key]++;
    return 'PDL-' . $gender . '-' . $year . '-' . str_pad((string) $counters[$key], 5, '0', STR_PAD_LEFT);
}

function pdl_import_has_visitor_data(array $row): bool
{
    foreach (['visitor_fname', 'visitor_mname', 'visitor_lname', 'visitor_age', 'visitor_sex', 'visitor_relationship', 'visitor_address', 'visitor_contact'] as $field) {
        if (pdl_import_trim($row[$field] ?? '') !== '') {
            return true;
        }
    }
    return false;
}

function pdl_import_validate_pdl_records(array $records, array &$errors): void
{
    foreach ($records as $recordKey => $record) {
        $data = $record['data'] ?? [];
        foreach (['pdl_fname', 'pdl_lname', 'pdl_age', 'pdl_sex', 'case_no', 'court', 'dorm_no'] as $requiredField) {
            if (pdl_import_trim($data[$requiredField] ?? '') === '') {
                $errors[] = 'Record ' . $recordKey . ': ' . $requiredField . ' is required.';
            }
        }
    }
}

function pdl_import_validate_visitor_records(array $visitors, array &$errors): void
{
    foreach ($visitors as $index => $visitor) {
        $fname = pdl_import_trim($visitor['fname'] ?? '');
        $lname = pdl_import_trim($visitor['lname'] ?? '');
        if ($fname === '' && $lname === '') {
            $errors[] = 'Visitor row ' . ($index + 1) . ': visitor first name or last name is required.';
        }
    }
}

function pdl_import_parse_pdl_csv(string $path): array
{
    $handle = fopen($path, 'r');
    if (!$handle) {
        throw new Exception('Unable to open the uploaded CSV file.');
    }

    $headers = fgetcsv($handle);
    if ($headers === false) {
        fclose($handle);
        throw new Exception('The uploaded CSV file is empty.');
    }

    $headers = array_map('pdl_import_normalize_header', $headers);
    $requiredColumns = ['record_key', 'pdl_id', 'pdl_fname', 'pdl_mname', 'pdl_lname', 'pdl_suffix', 'pdl_age', 'pdl_sex', 'case_no', 'court', 'dorm_no'];
    $missingColumns = array_values(array_diff($requiredColumns, $headers));
    if (!empty($missingColumns)) {
        fclose($handle);
        throw new Exception('Missing required column(s): ' . implode(', ', $missingColumns));
    }

    $records = [];
    $rowCount = 0;
    $errors = [];

    while (($row = fgetcsv($handle)) !== false) {
        $rowCount++;
        if (count(array_filter($row, static fn($value) => trim((string) $value) !== '')) === 0) {
            continue;
        }

        if (count($row) < count($headers)) {
            $row = array_pad($row, count($headers), '');
        }

        $data = array_combine($headers, array_slice($row, 0, count($headers)));
        if ($data === false) {
            $errors[] = 'Row ' . ($rowCount + 1) . ': Unable to map CSV columns.';
            continue;
        }

        $recordKey = pdl_import_trim($data['record_key'] ?? '');
        if ($recordKey === '') {
            $errors[] = 'Row ' . ($rowCount + 1) . ': record_key is required.';
            continue;
        }

        $records[$recordKey] = [
            'data' => [
                'pdl_id' => pdl_import_trim($data['pdl_id'] ?? ''),
                'pdl_fname' => pdl_import_trim($data['pdl_fname'] ?? ''),
                'pdl_mname' => pdl_import_trim($data['pdl_mname'] ?? ''),
                'pdl_lname' => pdl_import_trim($data['pdl_lname'] ?? ''),
                'pdl_suffix' => pdl_import_trim($data['pdl_suffix'] ?? ''),
                'pdl_age' => pdl_import_trim($data['pdl_age'] ?? ''),
                'pdl_sex' => pdl_import_trim($data['pdl_sex'] ?? ''),
                'case_no' => pdl_import_trim($data['case_no'] ?? ''),
                'court' => pdl_import_trim($data['court'] ?? ''),
                'dorm_no' => pdl_import_trim($data['dorm_no'] ?? ''),
                'pdl_photo' => '',
            ],
        ];
    }

    fclose($handle);
    return ['records' => $records, 'rows_processed' => $rowCount, 'errors' => $errors];
}

function pdl_import_parse_pdl_outline(string $text): array
{
    $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
    $records = [];
    $errors = [];
    $rowsProcessed = 0;
    $autoIndex = 1;

    foreach ($lines as $lineNumber => $line) {
        $rowsProcessed++;
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }

        $parts = array_map('trim', explode('|', $trimmed));
        $type = strtoupper(array_shift($parts) ?? '');
        if (!in_array($type, ['RESIDENT', 'PDL'], true)) {
            $errors[] = 'Line ' . ($lineNumber + 1) . ': line must start with Resident.';
            continue;
        }

        $parts = array_pad($parts, 11, '');
        [$recordKey, $pdlId, $pdlFname, $pdlMname, $pdlLname, $pdlSuffix, $pdlAge, $pdlSex, $caseNo, $court, $dormNo] = $parts;
        if ($recordKey === '') {
            $recordKey = 'OUTLINE-' . str_pad((string) $autoIndex, 3, '0', STR_PAD_LEFT);
            $autoIndex++;
        }

        $records[$recordKey] = [
            'data' => [
                'pdl_id' => pdl_import_trim($pdlId),
                'pdl_fname' => pdl_import_trim($pdlFname),
                'pdl_mname' => pdl_import_trim($pdlMname),
                'pdl_lname' => pdl_import_trim($pdlLname),
                'pdl_suffix' => pdl_import_trim($pdlSuffix),
                'pdl_age' => pdl_import_trim($pdlAge),
                'pdl_sex' => pdl_import_trim($pdlSex),
                'case_no' => pdl_import_trim($caseNo),
                'court' => pdl_import_trim($court),
                'dorm_no' => pdl_import_trim($dormNo),
                'pdl_photo' => '',
            ],
        ];
    }

    return ['records' => $records, 'rows_processed' => $rowsProcessed, 'errors' => $errors];
}

function pdl_import_parse_visitors_csv(string $path): array
{
    $handle = fopen($path, 'r');
    if (!$handle) {
        throw new Exception('Unable to open the uploaded CSV file.');
    }

    $headers = fgetcsv($handle);
    if ($headers === false) {
        fclose($handle);
        throw new Exception('The uploaded CSV file is empty.');
    }

    $headers = array_map('pdl_import_normalize_header', $headers);
    $requiredColumns = ['visitor_fname', 'visitor_mname', 'visitor_lname', 'visitor_age', 'visitor_sex', 'visitor_relationship', 'visitor_address', 'visitor_contact'];
    $missingColumns = array_values(array_diff($requiredColumns, $headers));
    if (!empty($missingColumns)) {
        fclose($handle);
        throw new Exception('Missing required column(s): ' . implode(', ', $missingColumns));
    }

    $visitors = [];
    $rowCount = 0;
    $errors = [];

    while (($row = fgetcsv($handle)) !== false) {
        $rowCount++;
        if (count(array_filter($row, static fn($value) => trim((string) $value) !== '')) === 0) {
            continue;
        }

        if (count($row) < count($headers)) {
            $row = array_pad($row, count($headers), '');
        }

        $data = array_combine($headers, array_slice($row, 0, count($headers)));
        if ($data === false) {
            $errors[] = 'Row ' . ($rowCount + 1) . ': Unable to map CSV columns.';
            continue;
        }

        if (!pdl_import_has_visitor_data($data)) {
            continue;
        }

        $visitors[] = [
            'fname' => pdl_import_trim($data['visitor_fname'] ?? ''),
            'mname' => pdl_import_trim($data['visitor_mname'] ?? ''),
            'lname' => pdl_import_trim($data['visitor_lname'] ?? ''),
            'age' => pdl_import_trim($data['visitor_age'] ?? ''),
            'sex' => pdl_import_trim($data['visitor_sex'] ?? ''),
            'relationship' => pdl_import_trim($data['visitor_relationship'] ?? ''),
            'address' => pdl_import_trim($data['visitor_address'] ?? ''),
            'contact' => pdl_import_trim($data['visitor_contact'] ?? ''),
        ];
    }

    fclose($handle);
    return ['visitors' => $visitors, 'rows_processed' => $rowCount, 'errors' => $errors];
}

function pdl_import_parse_visitors_outline(string $text): array
{
    $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
    $visitors = [];
    $errors = [];
    $rowsProcessed = 0;

    foreach ($lines as $lineNumber => $line) {
        $rowsProcessed++;
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }

        $parts = array_map('trim', explode('|', $trimmed));
        $type = strtoupper(array_shift($parts) ?? '');
        if ($type !== 'VISITOR') {
            $errors[] = 'Line ' . ($lineNumber + 1) . ': line must start with VISITOR.';
            continue;
        }

        $parts = array_pad($parts, 8, '');
        [$fname, $mname, $lname, $age, $sex, $relationship, $address, $contact] = $parts;
        $visitors[] = [
            'fname' => pdl_import_trim($fname),
            'mname' => pdl_import_trim($mname),
            'lname' => pdl_import_trim($lname),
            'age' => pdl_import_trim($age),
            'sex' => pdl_import_trim($sex),
            'relationship' => pdl_import_trim($relationship),
            'address' => pdl_import_trim($address),
            'contact' => pdl_import_trim($contact),
        ];
    }

    return ['visitors' => $visitors, 'rows_processed' => $rowsProcessed, 'errors' => $errors];
}

function pdl_import_detect_file_format(string $path, string $originalName): string
{
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension === 'csv') {
        return 'csv';
    }
    if (in_array($extension, ['txt', 'text'], true)) {
        return 'outline';
    }

    $sample = file_get_contents($path, false, null, 0, 512);
    $sample = trim((string) $sample);
    if ($sample !== '' && preg_match('/^(Resident|VISITOR)\s*\|/im', $sample)) {
        return 'outline';
    }
    return 'csv';
}

function pdl_import_commit_pdl_records(PDO $pdo, array $records, int $rowsProcessed): array
{
    $errors = [];
    pdl_import_validate_pdl_records($records, $errors);
    if (empty($records)) {
        throw new Exception('No valid Resident records were found in the uploaded data.');
    }
    if (!empty($errors)) {
        pdl_import_json('error', 'Please fix the import file and try again.', ['errors' => $errors]);
    }

    $pdo->beginTransaction();
    $pdlImported = 0;
    $seenPdlIds = [];

    try {
        foreach ($records as $recordKey => $record) {
            $data = $record['data'];
            if ($data['pdl_id'] === '') {
                $data['pdl_id'] = pdl_import_next_pdl_id($pdo, $data['pdl_sex']);
            }

            $normalizedPdlId = strtolower($data['pdl_id']);
            if (isset($seenPdlIds[$normalizedPdlId])) {
                throw new Exception('Duplicate pdl_id found in the import data: ' . $data['pdl_id']);
            }
            $seenPdlIds[$normalizedPdlId] = true;

            if (pdl_import_existing_pdl_id($pdo, $data['pdl_id'])) {
                throw new Exception('Record ' . $recordKey . ': pdl_id already exists: ' . $data['pdl_id']);
            }

            $result = PDLController::save($pdo, $data, [], false);
            if (($result['status'] ?? '') !== 'success') {
                throw new Exception('Record ' . $recordKey . ': ' . ($result['message'] ?? 'Import failed.'));
            }

            $pdlImported++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    log_activity('PDL_IMPORT', 'Imported Resident records: ' . $pdlImported);
    return ['pdl_imported' => $pdlImported, 'visitors_imported' => 0, 'rows_processed' => $rowsProcessed];
}

function pdl_import_commit_visitors(PDO $pdo, int $pdlId, array $visitors, int $rowsProcessed): array
{
    $pdl = pdl_import_find_pdl($pdo, $pdlId);
    if (!$pdl) {
        throw new Exception('Selected Resident record was not found.');
    }

    $errors = [];
    pdl_import_validate_visitor_records($visitors, $errors);
    if (empty($visitors)) {
        throw new Exception('No valid visitor rows were found in the uploaded data.');
    }
    if (!empty($errors)) {
        pdl_import_json('error', 'Please fix the visitor import file and try again.', ['errors' => $errors]);
    }

    $pdo->beginTransaction();
    try {
        $result = PDLController::appendVisitors($pdo, $pdlId, $visitors);
        if (($result['status'] ?? '') !== 'success') {
            throw new Exception($result['message'] ?? 'Visitor import failed.');
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    log_activity('PDL_VISITOR_IMPORT', 'Imported authorized visitors for Resident ID: ' . $pdlId . ' | pdl_code: ' . ($pdl['pdl_id'] ?? ''));
    return ['pdl_imported' => 0, 'visitors_imported' => count($visitors), 'rows_processed' => $rowsProcessed];
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$guideMode = strtolower(trim((string) ($_GET['mode'] ?? $_POST['mode'] ?? 'pdl')));
$guideMode = in_array($guideMode, ['pdl', 'visitors'], true) ? $guideMode : 'pdl';

try {
    switch ($action) {
        case 'download_pdl_template':
            pdl_import_require_mode_permission('pdl');
            pdl_import_download_pdl_template();
            break;

        case 'download_pdl_outline_template':
            pdl_import_require_mode_permission('pdl');
            pdl_import_download_pdl_outline_template();
            break;

        case 'download_visitors_template':
            pdl_import_require_mode_permission('visitors');
            pdl_import_download_visitors_template();
            break;

        case 'download_visitors_outline_template':
            pdl_import_require_mode_permission('visitors');
            pdl_import_download_visitors_outline_template();
            break;

        case 'download_guide':
            pdl_import_require_mode_permission($guideMode);
            pdl_import_download_guide($guideMode);
            break;

        case 'import_pdl_file':
            pdl_import_require_mode_permission('pdl');
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid import request.');
            }
            if (empty($_FILES['import_file']['tmp_name'])) {
                throw new Exception('Please upload a CSV or TXT import file.');
            }

            $originalName = (string) ($_FILES['import_file']['name'] ?? '');
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($extension, ['csv', 'txt', 'text'], true)) {
                throw new Exception('Only CSV and TXT files are allowed for Resident import.');
            }

            $format = pdl_import_detect_file_format($_FILES['import_file']['tmp_name'], $originalName);
            $parsed = $format === 'outline'
                ? pdl_import_parse_pdl_outline((string) file_get_contents($_FILES['import_file']['tmp_name']))
                : pdl_import_parse_pdl_csv($_FILES['import_file']['tmp_name']);

            if (!empty($parsed['errors'])) {
                pdl_import_json('error', 'Please fix the import file and try again.', ['errors' => $parsed['errors']]);
            }

            $summary = pdl_import_commit_pdl_records($pdo, $parsed['records'], (int) ($parsed['rows_processed'] ?? 0));
            pdl_import_json('success', 'Resident import completed successfully.', ['summary' => array_merge($summary, ['detected_format' => $format, 'source_file' => $originalName])]);
            break;

        case 'import_pdl_outline':
            pdl_import_require_mode_permission('pdl');
            $outlineText = trim((string) ($_POST['outline_text'] ?? ''));
            if ($outlineText === '') {
                throw new Exception('Please paste the Resident outline first.');
            }
            $parsed = pdl_import_parse_pdl_outline($outlineText);
            if (!empty($parsed['errors'])) {
                pdl_import_json('error', 'Please fix the outline format and try again.', ['errors' => $parsed['errors']]);
            }
            $summary = pdl_import_commit_pdl_records($pdo, $parsed['records'], (int) ($parsed['rows_processed'] ?? 0));
            pdl_import_json('success', 'Resident outline import completed successfully.', ['summary' => $summary]);
            break;

        case 'import_visitors_file':
            pdl_import_require_mode_permission('visitors');
            $pdlId = (int) ($_POST['pdl_id'] ?? 0);
            if ($pdlId <= 0) {
                throw new Exception('Invalid Resident selected for visitor import.');
            }
            if (empty($_FILES['import_file']['tmp_name'])) {
                throw new Exception('Please upload a CSV or TXT visitor import file.');
            }
            $originalName = (string) ($_FILES['import_file']['name'] ?? '');
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($extension, ['csv', 'txt', 'text'], true)) {
                throw new Exception('Only CSV and TXT files are allowed for visitor import.');
            }

            $format = pdl_import_detect_file_format($_FILES['import_file']['tmp_name'], $originalName);
            $parsed = $format === 'outline'
                ? pdl_import_parse_visitors_outline((string) file_get_contents($_FILES['import_file']['tmp_name']))
                : pdl_import_parse_visitors_csv($_FILES['import_file']['tmp_name']);

            if (!empty($parsed['errors'])) {
                pdl_import_json('error', 'Please fix the visitor import file and try again.', ['errors' => $parsed['errors']]);
            }

            $summary = pdl_import_commit_visitors($pdo, $pdlId, $parsed['visitors'], (int) ($parsed['rows_processed'] ?? 0));
            pdl_import_json('success', 'Visitor import completed successfully.', ['summary' => array_merge($summary, ['detected_format' => $format, 'source_file' => $originalName])]);
            break;

        case 'import_visitors_outline':
            pdl_import_require_mode_permission('visitors');
            $pdlId = (int) ($_POST['pdl_id'] ?? 0);
            if ($pdlId <= 0) {
                throw new Exception('Invalid Resident selected for visitor import.');
            }
            $outlineText = trim((string) ($_POST['outline_text'] ?? ''));
            if ($outlineText === '') {
                throw new Exception('Please paste the visitor outline first.');
            }
            $parsed = pdl_import_parse_visitors_outline($outlineText);
            if (!empty($parsed['errors'])) {
                pdl_import_json('error', 'Please fix the outline format and try again.', ['errors' => $parsed['errors']]);
            }
            $summary = pdl_import_commit_visitors($pdo, $pdlId, $parsed['visitors'], (int) ($parsed['rows_processed'] ?? 0));
            pdl_import_json('success', 'Visitor outline import completed successfully.', ['summary' => $summary]);
            break;

        default:
            throw new Exception('Invalid importer action.');
    }
} catch (Throwable $e) {
    if (in_array(($_GET['action'] ?? ''), ['download_pdl_template', 'download_pdl_outline_template', 'download_visitors_template', 'download_visitors_outline_template', 'download_guide'], true)) {
        http_response_code(500);
        echo $e->getMessage();
        exit;
    }

    pdl_import_json('error', $e->getMessage());
}
