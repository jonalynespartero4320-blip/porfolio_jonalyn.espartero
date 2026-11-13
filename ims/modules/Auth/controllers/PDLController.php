<?php

class PDLController
{
    public static function save($pdo, $data, $visitors = [], $isEdit = false)
    {
        $pdl_id  = $data['pdl_id'] ?? null;
        $fname   = trim($data['pdl_fname'] ?? '');
        $mname   = trim($data['pdl_mname'] ?? '');
        $lname   = trim($data['pdl_lname'] ?? '');
        $suffix  = trim($data['pdl_suffix'] ?? '');
        $dorm_no = $data['dorm_no'] ?? null;
        $age     = $data['pdl_age'] ?? null;
        $sex     = $data['pdl_sex'] ?? null;
        $case_no = $data['case_no'] ?? null;
        $court   = $data['court'] ?? null;
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
                    'message' => 'Resident record updated successfully.'
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
                'message' => 'Resident record saved successfully.'
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

            if ($fname === '' && $lname === '') {
                continue;
            }

            $stmt = $pdo->prepare("
                INSERT INTO pdl_authorized_visitors
                    (pdl_id, visitor_fname, visitor_mname, visitor_lname,
                     visitor_age, visitor_sex, visitor_relationship, visitor_address,
                     visitor_contact, visitor_status, created_at)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");

            $stmt->execute([
                $pdl_id,
                $fname,
                trim($v['mname'] ?? ''),
                $lname,
                $v['age'] ?? null,
                $v['sex'] ?? '',
                trim($v['relationship'] ?? ''),
                trim($v['address'] ?? ''),
                trim($v['contact'] ?? ''),
            ]);

            $count++;
        }
    }

    public static function updateVisitors($pdo, $pdl_id, $visitors)
    {
        $stmt = $pdo->prepare("DELETE FROM pdl_authorized_visitors WHERE pdl_id = ?");
        $stmt->execute([$pdl_id]);

        self::saveVisitors($pdo, $pdl_id, $visitors);
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
            $stmt = $pdo->prepare("SELECT pdl_photo, pdl_id, pdl_fname, pdl_lname FROM pdl_info WHERE id = ? LIMIT 1");
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

            $stmt = $pdo->prepare("DELETE FROM pdl_authorized_visitors WHERE pdl_id = ?");
            $stmt->execute([$id]);

            $stmt = $pdo->prepare("DELETE FROM pdl_info WHERE id = ?");
            $stmt->execute([$id]);

            if ($startedTransaction) {
                $pdo->commit();
            }

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
