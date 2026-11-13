<?php

require_once ROOT_PATH . '/helpers/functions.php';

class PDLController
{
    private static function genderCode(?string $sex): string
    {
        return strtolower(trim((string) $sex)) === 'female' ? 'F' : 'M';
    }

    private static function generatePdlId(PDO $pdo, ?string $sex, ?int $excludeId = null): string
    {
        $gender = self::genderCode($sex);
        $year = date('Y');
        $prefix = "PDL-{$gender}-{$year}-";

        $sql = "
            SELECT pdl_id
            FROM pdl_info
            WHERE pdl_id LIKE ?
        ";
        $params = [$prefix . '%'];

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= " AND id <> ?";
            $params[] = $excludeId;
        }

        $sql .= " ORDER BY CAST(SUBSTRING_INDEX(pdl_id, '-', -1) AS UNSIGNED) DESC, id DESC LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $lastPdlId = trim((string) ($stmt->fetchColumn() ?: ''));
        $nextNumber = 1;

        if ($lastPdlId !== '') {
            $lastNumber = (int) substr($lastPdlId, strrpos($lastPdlId, '-') + 1);
            $nextNumber = $lastNumber + 1;
        }

        return $prefix . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
    }

    private static function normalizeSuffix(?string $suffix): string
    {
        $suffix = preg_replace('/\s+/', ' ', trim((string) $suffix));
        if ($suffix === '') {
            return '';
        }

        $normalized = strtolower(rtrim($suffix, '.'));
        $map = [
            'jr' => 'Jr.',
            'sr' => 'Sr.',
            'ii' => 'II',
            'iii' => 'III',
            'iv' => 'IV',
            'v' => 'V',
        ];

        return $map[$normalized] ?? $suffix;
    }

    private static function resolveDormId(PDO $pdo, $dormValue): ?int
    {
        $dormValue = trim((string) $dormValue);
        if ($dormValue === '') {
            return null;
        }

        if (ctype_digit($dormValue)) {
            $stmt = $pdo->prepare("SELECT id FROM dorm_masterlist WHERE id = ? LIMIT 1");
            $stmt->execute([(int) $dormValue]);
            $id = $stmt->fetchColumn();
            if ($id !== false) {
                return (int) $id;
            }
        }

        $stmt = $pdo->prepare("
            SELECT id
            FROM dorm_masterlist
            WHERE LOWER(TRIM(dorm_no)) = LOWER(TRIM(?))
               OR LOWER(TRIM(description)) = LOWER(TRIM(?))
               OR LOWER(TRIM(CONCAT(dorm_no, ' - ', description))) = LOWER(TRIM(?))
            LIMIT 1
        ");
        $stmt->execute([$dormValue, $dormValue, $dormValue]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    private static function resolveCaseNo(PDO $pdo, $caseNo): ?string
    {
        $caseNo = trim((string) $caseNo);
        if ($caseNo === '') {
            return null;
        }

        if (ctype_digit($caseNo)) {
            $stmt = $pdo->prepare("SELECT case_no FROM case_masterlist WHERE id = ? LIMIT 1");
            $stmt->execute([(int) $caseNo]);
            $resolved = $stmt->fetchColumn();
            if ($resolved !== false) {
                return trim((string) $resolved);
            }
        }

        $stmt = $pdo->prepare("
            SELECT case_no
            FROM case_masterlist
            WHERE LOWER(TRIM(case_no)) = LOWER(TRIM(?))
               OR LOWER(TRIM(case_type)) = LOWER(TRIM(?))
               OR LOWER(TRIM(CONCAT(case_no, ' - ', case_type))) = LOWER(TRIM(?))
            LIMIT 1
        ");
        $stmt->execute([$caseNo, $caseNo, $caseNo]);
        $resolved = $stmt->fetchColumn();
        if ($resolved !== false) {
            return trim((string) $resolved);
        }

        return $caseNo;
    }

    private static function resolveCourtName(PDO $pdo, $courtValue): ?string
    {
        $courtValue = trim((string) $courtValue);
        if ($courtValue === '') {
            return null;
        }

        if (ctype_digit($courtValue)) {
            $stmt = $pdo->prepare("SELECT court_name FROM court_masterlist WHERE id = ? LIMIT 1");
            $stmt->execute([(int) $courtValue]);
            $resolved = $stmt->fetchColumn();
            if ($resolved !== false) {
                return trim((string) $resolved);
            }
        }

        $stmt = $pdo->prepare("
            SELECT court_name
            FROM court_masterlist
            WHERE LOWER(TRIM(court_name)) = LOWER(TRIM(?))
            LIMIT 1
        ");
        $stmt->execute([$courtValue]);
        $resolved = $stmt->fetchColumn();
        if ($resolved !== false) {
            return trim((string) $resolved);
        }

        return $courtValue;
    }

    private static function resolveRelationshipName(PDO $pdo, $relationshipValue): string
    {
        $relationshipValue = trim((string) $relationshipValue);
        if ($relationshipValue === '') {
            return '';
        }

        if (ctype_digit($relationshipValue)) {
            $stmt = $pdo->prepare("SELECT relationship_name FROM relationship_masterlist WHERE id = ? LIMIT 1");
            $stmt->execute([(int) $relationshipValue]);
            $resolved = $stmt->fetchColumn();
            if ($resolved !== false) {
                return trim((string) $resolved);
            }
        }

        $stmt = $pdo->prepare("
            SELECT relationship_name
            FROM relationship_masterlist
            WHERE LOWER(TRIM(relationship_name)) = LOWER(TRIM(?))
               OR LOWER(TRIM(description)) = LOWER(TRIM(?))
            LIMIT 1
        ");
        $stmt->execute([$relationshipValue, $relationshipValue]);
        $resolved = $stmt->fetchColumn();
        if ($resolved !== false) {
            return trim((string) $resolved);
        }

        return $relationshipValue;
    }

    private static function normalizeVisitorPayload(array $visitor): array
    {
        return [
            'id' => (int) ($visitor['id'] ?? 0),
            'fname' => trim((string) ($visitor['fname'] ?? '')),
            'mname' => trim((string) ($visitor['mname'] ?? '')),
            'lname' => trim((string) ($visitor['lname'] ?? '')),
            'suffix' => self::normalizeSuffix($visitor['suffix'] ?? ''),
            'age' => trim((string) ($visitor['age'] ?? '')),
            'sex' => trim((string) ($visitor['sex'] ?? '')),
            'relationship' => trim((string) ($visitor['relationship'] ?? '')),
            'address' => trim((string) ($visitor['address'] ?? '')),
            'contact' => trim((string) ($visitor['contact'] ?? '')),
        ];
    }

    private static function syncVisitorRegistrationFromAuthorized(PDO $pdo, int $pdlId, int $authorizedVisitorId, array $visitor, ?array $oldVisitor = null): void
    {
        if ($authorizedVisitorId <= 0) {
            return;
        }

        $params = [
            $authorizedVisitorId,
            $pdlId,
            $visitor['fname'],
            $visitor['mname'],
            $visitor['lname'],
            $visitor['suffix'],
            $visitor['age'] !== '' ? (int) $visitor['age'] : null,
            $visitor['sex'],
            $visitor['address'],
            $visitor['contact'],
            $authorizedVisitorId,
        ];

        $where = "authorized_visitor_id = ?";

        if ($oldVisitor) {
            $where .= "
                OR (
                    pdl_id = ?
                    AND LOWER(TRIM(firstname)) = LOWER(TRIM(?))
                    AND LOWER(TRIM(lastname)) = LOWER(TRIM(?))
                    AND LOWER(TRIM(COALESCE(middlename, ''))) = LOWER(TRIM(?))
                    AND LOWER(TRIM(COALESCE(suffix, ''))) = LOWER(TRIM(?))
                )
            ";
            $params[] = $pdlId;
            $params[] = trim((string) ($oldVisitor['visitor_fname'] ?? ''));
            $params[] = trim((string) ($oldVisitor['visitor_lname'] ?? ''));
            $params[] = trim((string) ($oldVisitor['visitor_mname'] ?? ''));
            $params[] = trim((string) ($oldVisitor['visitor_suffix'] ?? ''));
        }

        $stmt = $pdo->prepare("
            UPDATE visitors_registration
            SET authorized_visitor_id = ?,
                pdl_id = ?,
                firstname = ?,
                middlename = ?,
                lastname = ?,
                suffix = ?,
                age = ?,
                sex = ?,
                loc_add = ?,
                contact_no = ?,
                updated_at = NOW()
            WHERE {$where}
        ");
        $stmt->execute($params);
    }

    public static function save($pdo, $data, $visitors = [], $isEdit = false)
    {
        $pdl_id  = $data['pdl_id'] ?? null;
        $fname   = trim($data['pdl_fname'] ?? '');
        $mname   = trim($data['pdl_mname'] ?? '');
        $lname   = trim($data['pdl_lname'] ?? '');
        $suffix  = self::normalizeSuffix($data['pdl_suffix'] ?? '');
        $dorm_no = self::resolveDormId($pdo, $data['dorm_no'] ?? null);
        $age     = $data['pdl_age'] ?? null;
        $sex     = $data['pdl_sex'] ?? null;
        $case_no = self::resolveCaseNo($pdo, $data['case_no'] ?? null);
        $court   = self::resolveCourtName($pdo, $data['court'] ?? null);
        $photo   = $data['pdl_photo'] ?? '';
        $id      = $data['id'] ?? null;

        $case_type = '';
        if ($case_no) {
            $stmt = $pdo->prepare("SELECT case_type FROM case_masterlist WHERE case_no = ? LIMIT 1");
            $stmt->execute([$case_no]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $case_type = $row['case_type'] ?? '';
        }

        if ($fname === '' || $lname === '' || empty($age) || empty($sex) || empty($case_no) || empty($court) || empty($dorm_no)) {
            return [
                'status'  => 'error',
                'message' => 'Please fill all required fields.'
            ];
        }

        if (count($visitors) > 5) {
            return [
                'status'  => 'error',
                'message' => 'Maximum of 5 authorized visitors only.'
            ];
        }

        try {
            $startedTransaction = false;
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $startedTransaction = true;
            }

            if ($isEdit) {
                if (empty($id)) {
                    if ($startedTransaction) {
                        $pdo->rollBack();
                    }

                    return [
                        'status'  => 'error',
                        'message' => 'Invalid Resident record.'
                    ];
                }

                $existingStmt = $pdo->prepare("SELECT pdl_id, pdl_sex FROM pdl_info WHERE id = ? LIMIT 1");
                $existingStmt->execute([(int) $id]);
                $existingPdl = $existingStmt->fetch(PDO::FETCH_ASSOC);
                if (!$existingPdl) {
                    if ($startedTransaction) {
                        $pdo->rollBack();
                    }

                    return [
                        'status'  => 'error',
                        'message' => 'Invalid Resident record.'
                    ];
                }

                $pdlIdChangedByGender = false;
                if (self::genderCode($existingPdl['pdl_sex'] ?? '') !== self::genderCode($sex)) {
                    $pdl_id = self::generatePdlId($pdo, $sex, (int) $id);
                    $pdlIdChangedByGender = true;
                }

                $sql = "
                    UPDATE pdl_info SET
                        pdl_id     = ?,
                        pdl_fname  = ?,
                        pdl_mname  = ?,
                        pdl_lname  = ?,
                        pdl_suffix = ?,
                        dorm_no    = ?,
                        pdl_age    = ?,
                        pdl_sex    = ?,
                        case_no    = ?,
                        case_type  = ?,
                        court      = ?
                ";

                $params = [
                    $pdl_id,
                    $fname,
                    $mname,
                    $lname,
                    $suffix,
                    $dorm_no,
                    $age,
                    $sex,
                    $case_no,
                    $case_type,
                    $court,
                ];

                if (!empty($photo)) {
                    $sql .= ", pdl_photo = ?";
                    $params[] = $photo;
                }

                $sql .= " WHERE id = ?";
                $params[] = $id;

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                self::updateVisitors($pdo, $id, $visitors);

                if ($startedTransaction) {
                    $pdo->commit();
                }

                return [
                    'status'  => 'success',
                    'message' => $pdlIdChangedByGender
                        ? 'Resident record updated successfully. New Resident ID: ' . $pdl_id
                        : 'Resident record updated successfully.',
                    'pdl_id' => $pdl_id
                ];
            }

            $stmt = $pdo->prepare("
                INSERT INTO pdl_info
                    (pdl_id, pdl_fname, pdl_mname, pdl_lname, pdl_suffix,
                     dorm_no, pdl_age, pdl_sex, case_no, case_type, court, pdl_photo)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $pdl_id,
                $fname,
                $mname,
                $lname,
                $suffix,
                $dorm_no,
                $age,
                $sex,
                $case_no,
                $case_type,
                $court,
                $photo,
            ]);

            $newId = $pdo->lastInsertId();

            self::saveVisitors($pdo, $newId, $visitors);

            if ($startedTransaction) {
                $pdo->commit();
            }

            return [
                'status'  => 'success',
                'message' => 'Resident record saved successfully.',
                'pdl_id' => $pdl_id
            ];
        } catch (Exception $e) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return [
                'status'  => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    public static function saveVisitors($pdo, $pdl_id, $visitors)
    {
        $count = 0;

        foreach ($visitors as $v) {
            if ($count >= 5) {
                break;
            }

            $fname = trim($v['fname'] ?? '');
            $lname = trim($v['lname'] ?? '');
            $relationship = self::resolveRelationshipName($pdo, $v['relationship'] ?? '');
            $status = trim((string) ($v['status'] ?? 'Verified'));
            if (!in_array($status, ['Verified', 'Incomplete Requirements'], true)) {
                $status = 'Verified';
            }

            if ($fname === '' && $lname === '') {
                continue;
            }

            $stmt = $pdo->prepare("
                INSERT INTO pdl_authorized_visitors
                    (pdl_id, visitor_fname, visitor_mname, visitor_lname,
                     visitor_suffix, visitor_age, visitor_sex, visitor_relationship, visitor_address,
                     visitor_contact, visitor_status, created_at)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $pdl_id,
                $fname,
                trim($v['mname'] ?? ''),
                $lname,
                self::normalizeSuffix($v['suffix'] ?? ''),
                $v['age'] ?? null,
                $v['sex'] ?? '',
                $relationship,
                trim($v['address'] ?? ''),
                trim($v['contact'] ?? ''),
                $status,
            ]);

            $count++;
        }
    }

    public static function updateVisitors($pdo, $pdl_id, $visitors)
    {
        $stmt = $pdo->prepare("SELECT * FROM pdl_authorized_visitors WHERE pdl_id = ?");
        $stmt->execute([$pdl_id]);
        $existingVisitors = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $existingVisitors[(int) $row['id']] = $row;
        }

        $keptIds = [];
        $count = 0;

        foreach ($visitors as $v) {
            if ($count >= 5) {
                break;
            }

            $visitor = self::normalizeVisitorPayload((array) $v);
            $visitor['relationship'] = self::resolveRelationshipName($pdo, $visitor['relationship'] ?? '');

            if ($visitor['fname'] === '' && $visitor['lname'] === '') {
                continue;
            }

            if ($visitor['id'] > 0 && isset($existingVisitors[$visitor['id']])) {
                $updateStmt = $pdo->prepare("
                    UPDATE pdl_authorized_visitors
                    SET visitor_fname = ?,
                        visitor_mname = ?,
                        visitor_lname = ?,
                        visitor_suffix = ?,
                        visitor_age = ?,
                        visitor_sex = ?,
                        visitor_relationship = ?,
                        visitor_address = ?,
                        visitor_contact = ?,
                        updated_at = NOW()
                    WHERE id = ? AND pdl_id = ?
                ");
                $updateStmt->execute([
                    $visitor['fname'],
                    $visitor['mname'],
                    $visitor['lname'],
                    $visitor['suffix'],
                    $visitor['age'] !== '' ? (int) $visitor['age'] : null,
                    $visitor['sex'],
                    $visitor['relationship'],
                    $visitor['address'],
                    $visitor['contact'],
                    $visitor['id'],
                    $pdl_id,
                ]);

                self::syncVisitorRegistrationFromAuthorized($pdo, (int) $pdl_id, $visitor['id'], $visitor, $existingVisitors[$visitor['id']]);
                $keptIds[] = $visitor['id'];
            } else {
                $insertStmt = $pdo->prepare("
                    INSERT INTO pdl_authorized_visitors
                        (pdl_id, visitor_fname, visitor_mname, visitor_lname,
                         visitor_suffix, visitor_age, visitor_sex, visitor_relationship, visitor_address,
                         visitor_contact, visitor_status, created_at)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Verified', NOW())
                ");
                $insertStmt->execute([
                    $pdl_id,
                    $visitor['fname'],
                    $visitor['mname'],
                    $visitor['lname'],
                    $visitor['suffix'],
                    $visitor['age'] !== '' ? (int) $visitor['age'] : null,
                    $visitor['sex'],
                    $visitor['relationship'],
                    $visitor['address'],
                    $visitor['contact'],
                ]);

                $keptIds[] = (int) $pdo->lastInsertId();
            }

            $count++;
        }

        $removedIds = array_values(array_diff(array_keys($existingVisitors), $keptIds));
        if ($removedIds) {
            $placeholders = implode(',', array_fill(0, count($removedIds), '?'));
            $unlinkStmt = $pdo->prepare("UPDATE visitors_registration SET authorized_visitor_id = NULL, updated_at = NOW() WHERE authorized_visitor_id IN ($placeholders)");
            $unlinkStmt->execute($removedIds);

            $deleteStmt = $pdo->prepare("DELETE FROM pdl_authorized_visitors WHERE pdl_id = ? AND id IN ($placeholders)");
            $deleteStmt->execute(array_merge([$pdl_id], $removedIds));
        }
    }

    public static function appendVisitors($pdo, $pdl_id, $visitors)
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pdl_authorized_visitors WHERE pdl_id = ?");
        $stmt->execute([$pdl_id]);
        $existingCount = (int) $stmt->fetchColumn();

        if ($existingCount >= 5) {
            return [
                'status' => 'error',
                'message' => 'This Resident already has the maximum of 5 authorized visitors.'
            ];
        }

        $normalizedVisitors = [];
        foreach ($visitors as $visitor) {
            $fname = trim((string) ($visitor['fname'] ?? ''));
            $lname = trim((string) ($visitor['lname'] ?? ''));

            if ($fname === '' && $lname === '') {
                continue;
            }

            $visitor['relationship'] = self::resolveRelationshipName($pdo, $visitor['relationship'] ?? '');
            $normalizedVisitors[] = $visitor;
        }

        if (($existingCount + count($normalizedVisitors)) > 5) {
            return [
                'status' => 'error',
                'message' => 'Import exceeds the maximum of 5 authorized visitors for this Resident.'
            ];
        }

        self::saveVisitors($pdo, $pdl_id, $normalizedVisitors);

        return [
            'status' => 'success',
            'message' => 'Authorized visitors imported successfully.'
        ];
    }

    public static function delete($pdo, $id)
    {
        $startedTransaction = false;

        try {
            $stmt = $pdo->prepare("SELECT * FROM pdl_info WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $pdl = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pdl) {
                return [
                    'status'  => 'error',
                    'message' => 'Resident record not found.'
                ];
            }

            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $startedTransaction = true;
            }

            $beforeVisitors = self::getAuthorizedVisitors($pdo, $id);

            $stmt = $pdo->prepare("DELETE FROM pdl_authorized_visitors WHERE pdl_id = ?");
            $stmt->execute([$id]);

            $stmt = $pdo->prepare("DELETE FROM pdl_info WHERE id = ?");
            $stmt->execute([$id]);

            if ($startedTransaction) {
                $pdo->commit();
            }

            archive_record_change('pdl_info', $id, 'deleted', [
                'pdl' => $pdl,
                'authorized_visitors' => $beforeVisitors,
            ], null, "Deleted Resident record " . trim((string) ($pdl['pdl_id'] ?? $id)));

            $photo = trim((string) ($pdl['pdl_photo'] ?? ''));
            if ($photo !== '') {
                $photoPath = ROOT_PATH . '/public/demo-uploads/pdl/' . $photo;
                if (file_exists($photoPath)) {
                    @unlink($photoPath);
                }
            }

            log_activity(
                'PDL_DELETE',
                "Deleted Resident record ID: {$id} | pdl_id: " . trim((string) ($pdl['pdl_id'] ?? '')) .
                " | name: " . trim((string) (($pdl['pdl_fname'] ?? '') . ' ' . ($pdl['pdl_lname'] ?? '')))
            );

            return [
                'status'  => 'success',
                'message' => 'Resident record deleted successfully.'
            ];
        } catch (Exception $e) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return [
                'status'  => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    public static function getAuthorizedVisitors($pdo, $id)
    {
        $stmt = $pdo->prepare("
            SELECT
                id,
                pdl_id,
                visitor_fname,
                visitor_mname,
                visitor_lname,
                visitor_suffix,
                visitor_age,
                visitor_sex,
                visitor_relationship,
                visitor_address,
                visitor_contact,
                visitor_status,
                created_at
            FROM pdl_authorized_visitors
            WHERE pdl_id = ?
            ORDER BY id ASC
        ");
        $stmt->execute([$id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function view($pdo, $id)
    {
        $stmt = $pdo->prepare("SELECT * FROM pdl_info WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
