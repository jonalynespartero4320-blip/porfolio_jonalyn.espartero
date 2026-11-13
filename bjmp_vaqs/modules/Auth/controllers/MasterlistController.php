<?php

require_once ROOT_PATH . '/helpers/functions.php';

class MasterlistController {

    public static function ensureRelationshipTable(): void {
        global $pdo;

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS relationship_masterlist (
                id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                relationship_name VARCHAR(100) NOT NULL,
                description VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_relationship_name (relationship_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $defaults = [
            ['Father', 'Parent'],
            ['Mother', 'Parent'],
            ['Spouse', 'Husband or wife'],
            ['Son', 'Child'],
            ['Daughter', 'Child'],
            ['Brother', 'Sibling'],
            ['Sister', 'Sibling'],
            ['Grandparent', 'Grandparent'],
            ['Grandchild', 'Grandchild'],
            ['Guardian', 'Legal or recognized guardian'],
            ['Relative', 'Other family relation'],
        ];

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO relationship_masterlist (relationship_name, description)
            VALUES (?, ?)
        ");

        foreach ($defaults as $row) {
            $stmt->execute($row);
        }
    }

    public static function ensureRequirementTable(): void {
        global $pdo;

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS requirement_masterlist (
                id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                requirement_key VARCHAR(100) NOT NULL,
                requirement_name VARCHAR(150) NOT NULL,
                description VARCHAR(255) DEFAULT NULL,
                sort_order INT(11) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_requirement_key (requirement_key),
                UNIQUE KEY uniq_requirement_name (requirement_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $defaults = [
            ['valid_id', 'Valid ID', 'Primary identity document option for visitors.', 10],
            ['birth_certificate', 'Birth Certificate', 'Alternative identity document for visitors below 18 without a valid ID.', 20],
            ['marriage_contract', 'Marriage Certificate', 'Required document for wife, husband, or spouse of the Resident.', 30],
            ['cohabitation_certificate', 'Cohabitation Certificate', 'Optional partner document for live-in partner when no valid ID is submitted.', 40],
        ];

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO requirement_masterlist (requirement_key, requirement_name, description, sort_order)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($defaults as $row) {
            $stmt->execute($row);
        }
    }

    public static function ensureValidIdTypeTable(): void {
        global $pdo;

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS valid_id_type_masterlist (
                id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                id_type_name VARCHAR(150) NOT NULL,
                description VARCHAR(255) DEFAULT NULL,
                sort_order INT(11) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_id_type_name (id_type_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $defaults = [
            ['PhilID / ePhilID / Digital National ID', 'Philippine Identification System ID.', 10],
            ['Philippine Passport', 'DFA-issued passport.', 20],
            ['LTO Driver\'s License', 'Driver license issued by LTO.', 30],
            ['UMID / SSS ID', 'Unified Multi-Purpose ID or SSS card.', 40],
            ['GSIS ID / GSIS eCard', 'Government Service Insurance System ID.', 50],
            ['PRC ID', 'Professional Regulation Commission ID.', 60],
            ['Postal ID', 'PHLPost postal ID.', 70],
            ['COMELEC Voter\'s ID / Voter\'s Certificate', 'COMELEC-issued voter ID or certificate.', 80],
            ['Home ID', 'LGU-issued home ID.', 90],
            ['Professional ID', 'Professional ID.', 100],
            ['PhilHealth ID', 'PhilHealth identification card.', 110],
            ['TIN ID', 'BIR tax identification card.', 120],
            ['Barangay ID / Barangay Certification', 'Barangay-issued identification or certification.', 130],
            ['NBI Clearance', 'National Bureau of Investigation clearance.', 140],
            ['Police Clearance', 'Police clearance document.', 150],
            ['OWWA ID / OFW e-Card', 'Overseas Workers Welfare Administration ID.', 160],
            ['Seaman\'s Book / MARINA SIRB', 'Seafarer identification and record book.', 170],
            ['ACR I-Card', 'Alien Certificate of Registration identity card.', 180],
            ['PNP Firearms License / Permit', 'PNP-issued firearms license or permit.', 190],
            ['AFP / PNP / Government Office ID', 'Government office or uniformed service ID.', 200],
            ['Company ID', 'Company-issued employee ID.', 210],
            ['Student ID', 'School-issued student ID.', 220],
        ];

        $existingCount = (int) $pdo->query("SELECT COUNT(*) FROM valid_id_type_masterlist")->fetchColumn();
        if ($existingCount > 0) {
            return;
        }

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO valid_id_type_masterlist (id_type_name, description, sort_order)
            VALUES (?, ?, ?)
        ");

        foreach ($defaults as $row) {
            $stmt->execute($row);
        }
    }

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
                $before = fetch_table_record($pdo, 'case_masterlist', $id);
                $stmt = $pdo->prepare("UPDATE case_masterlist SET case_no = ?, case_type = ? WHERE id = ?");
                $stmt->execute([$case_no, $case_type, $id]);
                $after = fetch_table_record($pdo, 'case_masterlist', $id);

                archive_record_change('case_masterlist', $id, 'updated', $before, $after, "Updated case masterlist ID {$id}");
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
            $before = fetch_table_record($pdo, 'case_masterlist', $id);
            $stmt = $pdo->prepare("DELETE FROM case_masterlist WHERE id = ?");
            $stmt->execute([$id]);

            archive_record_change('case_masterlist', $id, 'deleted', $before, null, "Deleted case masterlist ID {$id}");
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
                $before = fetch_table_record($pdo, 'court_masterlist', $id);
                $stmt = $pdo->prepare("UPDATE court_masterlist SET court_name = ? WHERE id = ?");
                $stmt->execute([$court_name, $id]);
                $after = fetch_table_record($pdo, 'court_masterlist', $id);

                archive_record_change('court_masterlist', $id, 'updated', $before, $after, "Updated court masterlist ID {$id}");
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
            $before = fetch_table_record($pdo, 'court_masterlist', $id);
            $stmt = $pdo->prepare("DELETE FROM court_masterlist WHERE id = ?");
            $stmt->execute([$id]);

            archive_record_change('court_masterlist', $id, 'deleted', $before, null, "Deleted court masterlist ID {$id}");
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
                $before = fetch_table_record($pdo, 'dorm_masterlist', $id);
                $stmt = $pdo->prepare("UPDATE dorm_masterlist SET dorm_no = ?, description = ? WHERE id = ?");
                $stmt->execute([$dorm_no, $description, $id]);
                $after = fetch_table_record($pdo, 'dorm_masterlist', $id);

                archive_record_change('dorm_masterlist', $id, 'updated', $before, $after, "Updated dorm masterlist ID {$id}");
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
            $before = fetch_table_record($pdo, 'dorm_masterlist', $id);
            $stmt = $pdo->prepare("DELETE FROM dorm_masterlist WHERE id = ?");
            $stmt->execute([$id]);

            archive_record_change('dorm_masterlist', $id, 'deleted', $before, null, "Deleted dorm masterlist ID {$id}");
            log_activity('Delete Dorm', "Deleted Dorm ID: $id");

            return ['status' => 'success', 'message' => 'Dorm deleted successfully.'];

        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Error deleting dorm: ' . $e->getMessage()];
        }
    }

    /* ==================================================
       RELATIONSHIP
    ================================================== */

    public static function addRelationship($relationship_name, $description = '') {
        global $pdo;
        self::ensureRelationshipTable();

        $relationship_name = trim((string) $relationship_name);
        $description = trim((string) $description);

        if ($relationship_name !== '') {
            try {
                $stmt = $pdo->prepare("INSERT INTO relationship_masterlist (relationship_name, description) VALUES (?, ?)");
                $stmt->execute([$relationship_name, $description !== '' ? $description : null]);

                log_activity('Add Relationship', "Relationship: $relationship_name");

                return ['status' => 'success', 'message' => 'Relationship added successfully.'];
            } catch (PDOException $e) {
                if ((string) ($e->errorInfo[1] ?? '') === '1062') {
                    return ['status' => 'warning', 'message' => 'Relationship already exists.'];
                }

                return ['status' => 'error', 'message' => 'Error adding relationship: ' . $e->getMessage()];
            }
        }

        return ['status' => 'warning', 'message' => 'Relationship name is required.'];
    }

    public static function editRelationship($id, $relationship_name, $description = '') {
        global $pdo;
        self::ensureRelationshipTable();

        $id = (int) $id;
        $relationship_name = trim((string) $relationship_name);
        $description = trim((string) $description);

        if ($id <= 0) {
            return ['status' => 'error', 'message' => 'Invalid relationship selected.'];
        }

        if ($relationship_name !== '') {
            try {
                $before = fetch_table_record($pdo, 'relationship_masterlist', $id);
                $stmt = $pdo->prepare("UPDATE relationship_masterlist SET relationship_name = ?, description = ? WHERE id = ?");
                $stmt->execute([$relationship_name, $description !== '' ? $description : null, $id]);
                $after = fetch_table_record($pdo, 'relationship_masterlist', $id);

                archive_record_change('relationship_masterlist', $id, 'updated', $before, $after, "Updated relationship masterlist ID {$id}");
                log_activity('Edit Relationship', "ID: $id | Relationship: $relationship_name");

                return ['status' => 'success', 'message' => 'Relationship updated successfully.'];
            } catch (PDOException $e) {
                if ((string) ($e->errorInfo[1] ?? '') === '1062') {
                    return ['status' => 'warning', 'message' => 'Relationship already exists.'];
                }

                return ['status' => 'error', 'message' => 'Error updating relationship: ' . $e->getMessage()];
            }
        }

        return ['status' => 'warning', 'message' => 'Relationship name is required.'];
    }

    public static function deleteRelationship($id) {
        global $pdo;
        self::ensureRelationshipTable();

        $id = (int) $id;
        if ($id <= 0) {
            return ['status' => 'error', 'message' => 'Invalid relationship selected.'];
        }

        try {
            $before = fetch_table_record($pdo, 'relationship_masterlist', $id);
            $stmt = $pdo->prepare("DELETE FROM relationship_masterlist WHERE id = ?");
            $stmt->execute([$id]);

            archive_record_change('relationship_masterlist', $id, 'deleted', $before, null, "Deleted relationship masterlist ID {$id}");
            log_activity('Delete Relationship', "Deleted Relationship ID: $id");

            return ['status' => 'success', 'message' => 'Relationship deleted successfully.'];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Error deleting relationship: ' . $e->getMessage()];
        }
    }

    /* ==================================================
       REQUIREMENT
    ================================================== */

    private static function normalizeRequirementKey(string $name): string {
        $key = strtolower(trim($name));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key);
        return trim((string) $key, '_');
    }

    public static function addRequirement($requirement_name, $description = '', $is_active = 1) {
        global $pdo;
        self::ensureRequirementTable();

        $requirement_name = trim((string) $requirement_name);
        $description = trim((string) $description);
        $isActive = (int) $is_active === 1 ? 1 : 0;

        if ($requirement_name === '') {
            return ['status' => 'warning', 'message' => 'Requirement name is required.'];
        }

        $key = self::normalizeRequirementKey($requirement_name);

        try {
            $stmt = $pdo->prepare("
                INSERT INTO requirement_masterlist (requirement_key, requirement_name, description, is_active)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$key, $requirement_name, $description !== '' ? $description : null, $isActive]);

            log_activity('Add Requirement', "Requirement: $requirement_name");

            return ['status' => 'success', 'message' => 'Requirement added successfully.'];
        } catch (PDOException $e) {
            if ((string) ($e->errorInfo[1] ?? '') === '1062') {
                return ['status' => 'warning', 'message' => 'Requirement already exists.'];
            }

            return ['status' => 'error', 'message' => 'Error adding requirement: ' . $e->getMessage()];
        }
    }

    public static function editRequirement($id, $requirement_name, $description = '', $is_active = 1) {
        global $pdo;
        self::ensureRequirementTable();

        $id = (int) $id;
        $requirement_name = trim((string) $requirement_name);
        $description = trim((string) $description);
        $isActive = (int) $is_active === 1 ? 1 : 0;

        if ($id <= 0) {
            return ['status' => 'error', 'message' => 'Invalid requirement selected.'];
        }

        if ($requirement_name === '') {
            return ['status' => 'warning', 'message' => 'Requirement name is required.'];
        }

        $key = self::normalizeRequirementKey($requirement_name);

        try {
            $before = fetch_table_record($pdo, 'requirement_masterlist', $id);
            $stmt = $pdo->prepare("
                UPDATE requirement_masterlist
                SET requirement_key = ?, requirement_name = ?, description = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$key, $requirement_name, $description !== '' ? $description : null, $isActive, $id]);
            $after = fetch_table_record($pdo, 'requirement_masterlist', $id);

            archive_record_change('requirement_masterlist', $id, 'updated', $before, $after, "Updated requirement masterlist ID {$id}");
            log_activity('Edit Requirement', "ID: $id | Requirement: $requirement_name");

            return ['status' => 'success', 'message' => 'Requirement updated successfully.'];
        } catch (PDOException $e) {
            if ((string) ($e->errorInfo[1] ?? '') === '1062') {
                return ['status' => 'warning', 'message' => 'Requirement already exists.'];
            }

            return ['status' => 'error', 'message' => 'Error updating requirement: ' . $e->getMessage()];
        }
    }

    public static function deleteRequirement($id) {
        global $pdo;
        self::ensureRequirementTable();

        $id = (int) $id;
        if ($id <= 0) {
            return ['status' => 'error', 'message' => 'Invalid requirement selected.'];
        }

        try {
            $before = fetch_table_record($pdo, 'requirement_masterlist', $id);
            $stmt = $pdo->prepare("DELETE FROM requirement_masterlist WHERE id = ?");
            $stmt->execute([$id]);

            archive_record_change('requirement_masterlist', $id, 'deleted', $before, null, "Deleted requirement masterlist ID {$id}");
            log_activity('Delete Requirement', "Deleted Requirement ID: $id");

            return ['status' => 'success', 'message' => 'Requirement deleted successfully.'];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Error deleting requirement: ' . $e->getMessage()];
        }
    }

    /* ==================================================
       VALID ID TYPE
    ================================================== */

    public static function addValidIdType($id_type_name, $description = '', $is_active = 1) {
        global $pdo;
        self::ensureValidIdTypeTable();

        $idTypeName = trim((string) $id_type_name);
        $description = trim((string) $description);
        $isActive = (int) $is_active === 1 ? 1 : 0;

        if ($idTypeName === '') {
            return ['status' => 'warning', 'message' => 'Accepted ID name is required.'];
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO valid_id_type_masterlist (id_type_name, description, is_active)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$idTypeName, $description !== '' ? $description : null, $isActive]);

            log_activity('Add Accepted ID', "Accepted ID: $idTypeName");

            return ['status' => 'success', 'message' => 'Accepted ID added successfully.'];
        } catch (PDOException $e) {
            if ((string) ($e->errorInfo[1] ?? '') === '1062') {
                return ['status' => 'warning', 'message' => 'Accepted ID already exists.'];
            }

            return ['status' => 'error', 'message' => 'Error adding accepted ID: ' . $e->getMessage()];
        }
    }

    public static function editValidIdType($id, $id_type_name, $description = '', $is_active = 1) {
        global $pdo;
        self::ensureValidIdTypeTable();

        $id = (int) $id;
        $idTypeName = trim((string) $id_type_name);
        $description = trim((string) $description);
        $isActive = (int) $is_active === 1 ? 1 : 0;

        if ($id <= 0) {
            return ['status' => 'error', 'message' => 'Invalid accepted ID selected.'];
        }

        if ($idTypeName === '') {
            return ['status' => 'warning', 'message' => 'Accepted ID name is required.'];
        }

        try {
            $before = fetch_table_record($pdo, 'valid_id_type_masterlist', $id);
            $stmt = $pdo->prepare("
                UPDATE valid_id_type_masterlist
                SET id_type_name = ?, description = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$idTypeName, $description !== '' ? $description : null, $isActive, $id]);
            $after = fetch_table_record($pdo, 'valid_id_type_masterlist', $id);

            archive_record_change('valid_id_type_masterlist', $id, 'updated', $before, $after, "Updated accepted ID masterlist ID {$id}");
            log_activity('Edit Accepted ID', "ID: $id | Accepted ID: $idTypeName");

            return ['status' => 'success', 'message' => 'Accepted ID updated successfully.'];
        } catch (PDOException $e) {
            if ((string) ($e->errorInfo[1] ?? '') === '1062') {
                return ['status' => 'warning', 'message' => 'Accepted ID already exists.'];
            }

            return ['status' => 'error', 'message' => 'Error updating accepted ID: ' . $e->getMessage()];
        }
    }

    public static function deleteValidIdType($id) {
        global $pdo;
        self::ensureValidIdTypeTable();

        $id = (int) $id;
        if ($id <= 0) {
            return ['status' => 'error', 'message' => 'Invalid accepted ID selected.'];
        }

        try {
            $before = fetch_table_record($pdo, 'valid_id_type_masterlist', $id);
            $stmt = $pdo->prepare("DELETE FROM valid_id_type_masterlist WHERE id = ?");
            $stmt->execute([$id]);

            archive_record_change('valid_id_type_masterlist', $id, 'deleted', $before, null, "Deleted accepted ID masterlist ID {$id}");
            log_activity('Delete Accepted ID', "Deleted Accepted ID ID: $id");

            return ['status' => 'success', 'message' => 'Accepted ID deleted successfully.'];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Error deleting accepted ID: ' . $e->getMessage()];
        }
    }
}

?>
