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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO requirement_masterlist (requirement_key, requirement_name, description, sort_order) VALUES
('valid_id', 'Valid ID', 'Primary identity document option for visitors.', 10),
('birth_certificate', 'Birth Certificate', 'Alternative identity document for visitors below 18 without a valid ID.', 20),
('marriage_contract', 'Marriage Certificate', 'Required document for wife, husband, or spouse of the PDL.', 30),
('cohabitation_certificate', 'Cohabitation Certificate', 'Optional partner document for live-in partner when no valid ID is submitted.', 40);

CREATE TABLE IF NOT EXISTS valid_id_type_masterlist (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_type_name VARCHAR(150) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    sort_order INT(11) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_id_type_name (id_type_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO valid_id_type_masterlist (id_type_name, description, sort_order) VALUES
('PhilID / ePhilID / Digital National ID', 'Philippine Identification System ID.', 10),
('Philippine Passport', 'DFA-issued passport.', 20),
('LTO Driver''s License', 'Driver license issued by LTO.', 30),
('UMID / SSS ID', 'Unified Multi-Purpose ID or SSS card.', 40),
('GSIS ID / GSIS eCard', 'Government Service Insurance System ID.', 50),
('PRC ID', 'Professional Regulation Commission ID.', 60),
('Postal ID', 'PHLPost postal ID.', 70),
('COMELEC Voter''s ID / Voter''s Certificate', 'COMELEC-issued voter ID or certificate.', 80),
('Senior Citizen ID', 'LGU-issued senior citizen ID.', 90),
('PWD ID', 'Persons with Disability ID.', 100),
('PhilHealth ID', 'PhilHealth identification card.', 110),
('TIN ID', 'BIR tax identification card.', 120),
('Barangay ID / Barangay Certification', 'Barangay-issued identification or certification.', 130),
('NBI Clearance', 'National Bureau of Investigation clearance.', 140),
('Police Clearance', 'Police clearance document.', 150),
('OWWA ID / OFW e-Card', 'Overseas Workers Welfare Administration ID.', 160),
('Seaman''s Book / MARINA SIRB', 'Seafarer identification and record book.', 170),
('ACR I-Card', 'Alien Certificate of Registration identity card.', 180),
('PNP Firearms License / Permit', 'PNP-issued firearms license or permit.', 190),
('AFP / PNP / Government Office ID', 'Government office or uniformed service ID.', 200),
('Company ID', 'Company-issued employee ID.', 210),
('Student ID', 'School-issued student ID.', 220);

ALTER TABLE visitors_registration
    ADD COLUMN IF NOT EXISTS valid_id VARCHAR(255) DEFAULT NULL AFTER visitor_photo,
    ADD COLUMN IF NOT EXISTS valid_id_type VARCHAR(150) DEFAULT NULL AFTER valid_id,
    ADD COLUMN IF NOT EXISTS valid_id_no VARCHAR(100) DEFAULT NULL AFTER valid_id_type;
