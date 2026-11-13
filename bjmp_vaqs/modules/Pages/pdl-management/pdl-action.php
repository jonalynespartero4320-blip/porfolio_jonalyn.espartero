<?php

require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';
require_once ROOT_PATH . '/modules/Auth/controllers/PDLController.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function remove_uploaded_photo_if_exists($photoName)
{
    $photoName = trim((string) $photoName);
    if ($photoName === '') {
        return;
    }

    $photoPath = ROOT_PATH . '/public/demo-uploads/pdl/' . $photoName;
    if (file_exists($photoPath)) {
        @unlink($photoPath);
    }
}

function pdl_archive_snapshot(PDO $pdo, int $pdlId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM pdl_info WHERE id = ? LIMIT 1");
    $stmt->execute([$pdlId]);
    $pdl = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pdl) {
        return null;
    }

    return [
        'pdl' => $pdl,
        'authorized_visitors' => PDLController::getAuthorizedVisitors($pdo, $pdlId),
    ];
}

try {
    switch ($action) {
        case 'create':
            require_permission('pdl.create');

            $year   = date('Y');
            $sex    = trim((string) ($_POST['pdl_sex'] ?? ''));
            $gender = (strtolower($sex) === 'female') ? 'F' : 'M';

            $stmt = $pdo->prepare("
                SELECT pdl_id
                FROM pdl_info
                WHERE pdl_id LIKE ?
                ORDER BY CAST(SUBSTRING_INDEX(pdl_id, '-', -1) AS UNSIGNED) DESC, id DESC
                LIMIT 1
            ");
            $stmt->execute(["PDL-$gender-$year-%"]);
            $lastPdlId = trim((string) ($stmt->fetch(PDO::FETCH_ASSOC)['pdl_id'] ?? ''));
            $count = 1;

            if ($lastPdlId !== '') {
                $lastNumber = (int) substr($lastPdlId, strrpos($lastPdlId, '-') + 1);
                $count = $lastNumber + 1;
            }

            $pdlId = 'PDL-' . $gender . '-' . $year . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);

            $photoName = '';
            if (!empty($_FILES['pdl_photo']['name'])) {
                $ext = strtolower(pathinfo($_FILES['pdl_photo']['name'], PATHINFO_EXTENSION));

                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                    throw new Exception('Invalid photo format.');
                }

                $photoName = 'pdl_' . uniqid('', true) . '.' . $ext;
                $uploadDir = ROOT_PATH . '/public/demo-uploads/pdl/';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                if (!move_uploaded_file($_FILES['pdl_photo']['tmp_name'], $uploadDir . $photoName)) {
                    throw new Exception('Failed to upload photo.');
                }
            }

            $data = [
                'pdl_id'     => $pdlId,
                'pdl_fname'  => trim((string) ($_POST['pdl_fname'] ?? '')),
                'pdl_mname'  => trim((string) ($_POST['pdl_mname'] ?? '')),
                'pdl_lname'  => trim((string) ($_POST['pdl_lname'] ?? '')),
                'pdl_suffix' => trim((string) ($_POST['pdl_suffix'] ?? '')),
                'dorm_no'    => trim((string) ($_POST['dorm_no'] ?? '')),
                'pdl_age'    => trim((string) ($_POST['pdl_age'] ?? '')),
                'pdl_sex'    => $sex,
                'case_no'    => trim((string) ($_POST['case_no'] ?? '')),
                'court'      => trim((string) ($_POST['court'] ?? '')),
                'pdl_photo'  => $photoName,
            ];

            $visitors = $_POST['visitors'] ?? [];

            if (count($visitors) > 5) {
                remove_uploaded_photo_if_exists($photoName);
                throw new Exception('Maximum of 5 authorized visitors only.');
            }

            $result = PDLController::save($pdo, $data, $visitors, false);
            $savedPdlId = trim((string) ($result['pdl_id'] ?? $pdlId));
            if (($result['status'] ?? '') === 'success') {
                log_activity('PDL_CREATE', "Created Resident record: {$savedPdlId} | name: {$data['pdl_fname']} {$data['pdl_lname']}");
                $createdStmt = $pdo->prepare("SELECT id FROM pdl_info WHERE pdl_id = ? LIMIT 1");
                $createdStmt->execute([$savedPdlId]);
                $createdId = (int) ($createdStmt->fetchColumn() ?: 0);
                archive_record_change('pdl_info', $createdId ?: $savedPdlId, 'created', null, $createdId > 0 ? pdl_archive_snapshot($pdo, $createdId) : $data, "Created Resident record {$savedPdlId}");
            } else {
                remove_uploaded_photo_if_exists($photoName);
            }
            echo json_encode($result);
            break;

        case 'update':
            require_permission('pdl.edit');

            $existingPhoto = trim((string) ($_POST['existing_photo'] ?? ''));
            $photoName = $existingPhoto;
            $newPhotoName = '';

            if (!empty($_FILES['pdl_photo']['name'])) {
                $ext = strtolower(pathinfo($_FILES['pdl_photo']['name'], PATHINFO_EXTENSION));

                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                    throw new Exception('Invalid photo format.');
                }

                $uploadDir = ROOT_PATH . '/public/demo-uploads/pdl/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $newPhotoName = 'pdl_' . uniqid('', true) . '.' . $ext;
                $newPhotoPath = $uploadDir . $newPhotoName;

                if (!move_uploaded_file($_FILES['pdl_photo']['tmp_name'], $newPhotoPath)) {
                    throw new Exception('Failed to upload photo.');
                }

                $photoName = $newPhotoName;
            }


            $data = [
                'id'         => (int) ($_POST['id'] ?? 0),
                'pdl_id'     => trim((string) ($_POST['pdl_id'] ?? '')),
                'pdl_fname'  => trim((string) ($_POST['pdl_fname'] ?? '')),
                'pdl_mname'  => trim((string) ($_POST['pdl_mname'] ?? '')),
                'pdl_lname'  => trim((string) ($_POST['pdl_lname'] ?? '')),
                'pdl_suffix' => trim((string) ($_POST['pdl_suffix'] ?? '')),
                'dorm_no'    => trim((string) ($_POST['dorm_no'] ?? '')),
                'pdl_age'    => trim((string) ($_POST['pdl_age'] ?? '')),
                'pdl_sex'    => trim((string) ($_POST['pdl_sex'] ?? '')),
                'case_no'    => trim((string) ($_POST['case_no'] ?? '')),
                'court'      => trim((string) ($_POST['court'] ?? '')),
                'pdl_photo'  => $photoName,
            ];

            $visitors = $_POST['visitors'] ?? [];
            $beforeSnapshot = pdl_archive_snapshot($pdo, (int) $data['id']);

            if (count($visitors) > 5) {
                if ($newPhotoName !== '') {
                    remove_uploaded_photo_if_exists($newPhotoName);
                }
                throw new Exception('Maximum of 5 authorized visitors only.');
            }

            $result = PDLController::save($pdo, $data, $visitors, true);
            $savedPdlId = trim((string) ($result['pdl_id'] ?? $data['pdl_id']));
            if (($result['status'] ?? '') === 'success') {
                if ($newPhotoName !== '' && $existingPhoto !== '' && $existingPhoto !== $newPhotoName) {
                    remove_uploaded_photo_if_exists($existingPhoto);
                }
                $afterSnapshot = pdl_archive_snapshot($pdo, (int) $data['id']);
                archive_record_change('pdl_info', (int) $data['id'], 'updated', $beforeSnapshot, $afterSnapshot, "Updated Resident record {$savedPdlId}");
                log_activity('PDL_UPDATE', "Updated Resident record ID: {$data['id']} | pdl_id: {$savedPdlId}");
            } elseif ($newPhotoName !== '') {
                remove_uploaded_photo_if_exists($newPhotoName);
            }
            echo json_encode($result);
            break;

        case 'delete':
            require_permission('pdl.delete');

            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('Invalid Resident ID.');
            }

            echo json_encode(PDLController::delete($pdo, $id));
            break;

        case 'get_visitors':
            require_permission('pdl.view');

            $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('Invalid Resident ID.');
            }

            $stmt = $pdo->prepare("
                SELECT id, pdl_id, pdl_fname, pdl_mname, pdl_lname, pdl_suffix
                FROM pdl_info
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $pdl = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pdl) {
                throw new Exception('Resident record not found.');
            }

            echo json_encode([
                'status' => 'success',
                'pdl' => $pdl,
                'visitors' => PDLController::getAuthorizedVisitors($pdo, $id),
            ]);
            break;

        default:
            throw new Exception('Invalid action.');
    }
} catch (Exception $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}
