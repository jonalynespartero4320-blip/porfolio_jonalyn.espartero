CREATE TABLE IF NOT EXISTS queue_special_cases (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    firstname VARCHAR(150) NOT NULL,
    middlename VARCHAR(150) DEFAULT NULL,
    lastname VARCHAR(150) NOT NULL,
    age INT(11) DEFAULT NULL,
    gender ENUM('Male','Female','Other') NOT NULL,
    pdl_id INT(11) NOT NULL,
    pdl_name VARCHAR(255) NOT NULL,
    dorm_no VARCHAR(100) DEFAULT NULL,
    address TEXT NOT NULL,
    id_type VARCHAR(100) DEFAULT NULL,
    id_number VARCHAR(150) DEFAULT NULL,
    reason_for_visit TEXT NOT NULL,
    approved_by VARCHAR(150) NOT NULL,
    approved_date DATE NOT NULL,
    status ENUM('approved','queued','inside','completed','rejected') NOT NULL DEFAULT 'approved',
    created_by INT(11) DEFAULT NULL,
    queue_request_id INT(11) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pdl_id (pdl_id),
    INDEX idx_approved_date (approved_date),
    INDEX idx_queue_request_id (queue_request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE queuing_requests
    MODIFY visitor_id INT(11) NULL;

ALTER TABLE queuing_requests
    ADD COLUMN IF NOT EXISTS special_case_id INT(11) DEFAULT NULL AFTER visitor_id;

ALTER TABLE queuing_requests
    ADD INDEX IF NOT EXISTS idx_special_case_id (special_case_id);

ALTER TABLE queue_display
    MODIFY visitor_id INT(11) NULL;

ALTER TABLE queue_display
    ADD COLUMN IF NOT EXISTS special_case_id INT(11) DEFAULT NULL AFTER visitor_id;

ALTER TABLE queue_display
    ADD INDEX IF NOT EXISTS idx_display_special_case_id (special_case_id);
