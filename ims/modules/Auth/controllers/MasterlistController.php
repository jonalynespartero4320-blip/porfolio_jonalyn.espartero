<?php

class MasterlistController {

    /* ==================================================
       CASE
    ================================================== */

    public static function addCase($case_no, $case_type) {
        global $pdo;
        $case_no = trim((string) $case_no);
        $case_type = trim((string) $case_type);
        
        if (!empty($case_no) && !empty($case_type)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO case_masterlist (case_no, case_type) VALUES (?, ?)");
                $stmt->execute([$case_no, $case_type]);

                log_activity('Add Case', "Case No: $case_no | Type: $case_type");

                return ['status' => 'success', 'message' => 'Case added successfully.'];

            } catch (PDOException $e) {
                return ['status' => 'error', 'message' => 'Error adding case: ' . $e->getMessage()];
            }
        }

        return ['status' => 'warning', 'message' => 'Case number and type are required.'];
    }


    public static function editCase($id, $case_no, $case_type) {
        global $pdo;
        $case_no = trim((string) $case_no);
        $case_type = trim((string) $case_type);
        $id = (int) $id;
        
        if (!empty($case_no) && !empty($case_type)) {
            try {
                $stmt = $pdo->prepare("UPDATE case_masterlist SET case_no = ?, case_type = ? WHERE id = ?");
                $stmt->execute([$case_no, $case_type, $id]);

                log_activity('Edit Case', "ID: $id | Case No: $case_no | Type: $case_type");

                return ['status' => 'success', 'message' => 'Case updated successfully.'];

            } catch (PDOException $e) {
                return ['status' => 'error', 'message' => 'Error updating case: ' . $e->getMessage()];
            }
        }

        return ['status' => 'warning', 'message' => 'Case number and type are required.'];
    }


    public static function deleteCase($id) {
        global $pdo;
        $id = (int) $id;
        
        try {
            $stmt = $pdo->prepare("DELETE FROM case_masterlist WHERE id = ?");
            $stmt->execute([$id]);

            log_activity('Delete Case', "Deleted Case ID: $id");

            return ['status' => 'success', 'message' => 'Case deleted successfully.'];

        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Error deleting case: ' . $e->getMessage()];
        }
    }


    /* ==================================================
       COURT
    ================================================== */

    public static function addCourt($court_name) {
        global $pdo;
        $court_name = trim((string) $court_name);
        
        if (!empty($court_name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO court_masterlist (court_name) VALUES (?)");
                $stmt->execute([$court_name]);

                log_activity('Add Court', "Court Name: $court_name");

                return ['status' => 'success', 'message' => 'Court added successfully.'];

            } catch (PDOException $e) {
                return ['status' => 'error', 'message' => 'Error adding court: ' . $e->getMessage()];
            }
        }

        return ['status' => 'warning', 'message' => 'Court name is required.'];
    }


    public static function editCourt($id, $court_name) {
        global $pdo;
        $court_name = trim((string) $court_name);
        $id = (int) $id;
        
        if (!empty($court_name)) {
            try {
                $stmt = $pdo->prepare("UPDATE court_masterlist SET court_name = ? WHERE id = ?");
                $stmt->execute([$court_name, $id]);

                log_activity('Edit Court', "ID: $id | Court Name: $court_name");

                return ['status' => 'success', 'message' => 'Court updated successfully.'];

            } catch (PDOException $e) {
                return ['status' => 'error', 'message' => 'Error updating court: ' . $e->getMessage()];
            }
        }

        return ['status' => 'warning', 'message' => 'Court name is required.'];
    }


    public static function deleteCourt($id) {
        global $pdo;
        $id = (int) $id;
        
        try {
            $stmt = $pdo->prepare("DELETE FROM court_masterlist WHERE id = ?");
            $stmt->execute([$id]);

            log_activity('Delete Court', "Deleted Court ID: $id");

            return ['status' => 'success', 'message' => 'Court deleted successfully.'];

        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Error deleting court: ' . $e->getMessage()];
        }
    }


    /* ==================================================
       DORM
    ================================================== */

    public static function addDorm($dorm_no, $description) {
        global $pdo;
        $dorm_no = trim((string) $dorm_no);
        $description = trim((string) $description);
        
        if (!empty($dorm_no) && !empty($description)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO dorm_masterlist (dorm_no, description) VALUES (?, ?)");
                $stmt->execute([$dorm_no, $description]);

                log_activity('Add Dorm', "Dorm No: $dorm_no | Description: $description");

                return ['status' => 'success', 'message' => 'Dorm added successfully.'];

            } catch (PDOException $e) {
                return ['status' => 'error', 'message' => 'Error adding dorm: ' . $e->getMessage()];
            }
        }

        return ['status' => 'warning', 'message' => 'Dorm number and description are required.'];
    }


    public static function editDorm($id, $dorm_no, $description) {
        global $pdo;
        $dorm_no = trim((string) $dorm_no);
        $description = trim((string) $description);
        $id = (int) $id;
        
        if (!empty($dorm_no) && !empty($description)) {
            try {
                $stmt = $pdo->prepare("UPDATE dorm_masterlist SET dorm_no = ?, description = ? WHERE id = ?");
                $stmt->execute([$dorm_no, $description, $id]);

                log_activity('Edit Dorm', "ID: $id | Dorm No: $dorm_no");

                return ['status' => 'success', 'message' => 'Dorm updated successfully.'];

            } catch (PDOException $e) {
                return ['status' => 'error', 'message' => 'Error updating dorm: ' . $e->getMessage()];
            }
        }

        return ['status' => 'warning', 'message' => 'Dorm number and description are required.'];
    }


    public static function deleteDorm($id) {
        global $pdo;
        $id = (int) $id;
        
        try {
            $stmt = $pdo->prepare("DELETE FROM dorm_masterlist WHERE id = ?");
            $stmt->execute([$id]);

            log_activity('Delete Dorm', "Deleted Dorm ID: $id");

            return ['status' => 'success', 'message' => 'Dorm deleted successfully.'];

        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Error deleting dorm: ' . $e->getMessage()];
        }
    }
}

?>
