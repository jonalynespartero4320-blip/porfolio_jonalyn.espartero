<?php

class MasterlistController {

    // Add Case to the Masterlist
    public static function addCase($case_no, $case_type) {
        global $pdo;
        
        if (!empty($case_no) && !empty($case_type)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO case_masterlist (case_no, case_type) VALUES (?, ?)");
                $stmt->execute([$case_no, $case_type]);
                return ['status' => 'success', 'message' => 'Case added successfully.'];
            } catch (PDOException $e) {
                return ['status' => 'error', 'message' => 'Error adding case: ' . $e->getMessage()];
            }
        }
        return ['status' => 'warning', 'message' => 'Case number and type are required.'];
    }

    // Edit Case in the Masterlist
    public static function editCase($id, $case_no, $case_type) {
        global $pdo;
        
        if (!empty($case_no) && !empty($case_type)) {
            try {
                $stmt = $pdo->prepare("UPDATE case_masterlist SET case_no = ?, case_type = ? WHERE id = ?");
                $stmt->execute([$case_no, $case_type, $id]);
                return ['status' => 'success', 'message' => 'Case updated successfully.'];
            } catch (PDOException $e) {
                return ['status' => 'error', 'message' => 'Error updating case: ' . $e->getMessage()];
            }
        }
        return ['status' => 'warning', 'message' => 'Case number and type are required.'];
    }

    // Delete Case from the Masterlist
    public static function deleteCase($id) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("DELETE FROM case_masterlist WHERE id = ?");
            $stmt->execute([$id]);
            return ['status' => 'success', 'message' => 'Case deleted successfully.'];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Error deleting case: ' . $e->getMessage()];
        }
    }

    // Add Court to the Masterlist
    public static function addCourt($court_name) {
        global $pdo;
        
        if (!empty($court_name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO court_masterlist (court_name) VALUES (?)");
                $stmt->execute([$court_name]);
                return ['status' => 'success', 'message' => 'Court added successfully.'];
            } catch (PDOException $e) {
                return ['status' => 'error', 'message' => 'Error adding court: ' . $e->getMessage()];
            }
        }
        return ['status' => 'warning', 'message' => 'Court name is required.'];
    }

    // Edit Court in the Masterlist
    public static function editCourt($id, $court_name) {
        global $pdo;
        
        if (!empty($court_name)) {
            try {
                $stmt = $pdo->prepare("UPDATE court_masterlist SET court_name = ? WHERE id = ?");
                $stmt->execute([$court_name, $id]);
                return ['status' => 'success', 'message' => 'Court updated successfully.'];
            } catch (PDOException $e) {
                return ['status' => 'error', 'message' => 'Error updating court: ' . $e->getMessage()];
            }
        }
        return ['status' => 'warning', 'message' => 'Court name is required.'];
    }

    // Delete Court from the Masterlist
    public static function deleteCourt($id) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("DELETE FROM court_masterlist WHERE id = ?");
            $stmt->execute([$id]);
            return ['status' => 'success', 'message' => 'Court deleted successfully.'];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Error deleting court: ' . $e->getMessage()];
        }
    }

    // Add Dorm to the Masterlist
    public static function addDorm($dorm_no, $description) {
        global $pdo;
        
        if (!empty($dorm_no) && !empty($description)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO dorm_masterlist (dorm_no, description) VALUES (?, ?)");
                $stmt->execute([$dorm_no, $description]);
                return ['status' => 'success', 'message' => 'Dorm added successfully.'];
            } catch (PDOException $e) {
                return ['status' => 'error', 'message' => 'Error adding dorm: ' . $e->getMessage()];
            }
        }
        return ['status' => 'warning', 'message' => 'Dorm number and description are required.'];
    }

    // Edit Dorm in the Masterlist
    public static function editDorm($id, $dorm_no, $description) {
        global $pdo;
        
        if (!empty($dorm_no) && !empty($description)) {
            try {
                $stmt = $pdo->prepare("UPDATE dorm_masterlist SET dorm_no = ?, description = ? WHERE id = ?");
                $stmt->execute([$dorm_no, $description, $id]);
                return ['status' => 'success', 'message' => 'Dorm updated successfully.'];
            } catch (PDOException $e) {
                return ['status' => 'error', 'message' => 'Error updating dorm: ' . $e->getMessage()];
            }
        }
        return ['status' => 'warning', 'message' => 'Dorm number and description are required.'];
    }

    // Delete Dorm from the Masterlist
    public static function deleteDorm($id) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("DELETE FROM dorm_masterlist WHERE id = ?");
            $stmt->execute([$id]);
            return ['status' => 'success', 'message' => 'Dorm deleted successfully.'];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Error deleting dorm: ' . $e->getMessage()];
        }
    }
}

?>