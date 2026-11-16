CREATE TABLE IF NOT EXISTS relationship_masterlist (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    relationship_name VARCHAR(100) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_relationship_name (relationship_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO relationship_masterlist (relationship_name, description) VALUES
('Father', 'Parent'),
('Mother', 'Parent'),
('Spouse', 'Husband or wife'),
('Son', 'Child'),
('Daughter', 'Child'),
('Brother', 'Sibling'),
('Sister', 'Sibling'),
('Grandparent', 'Grandparent'),
('Grandchild', 'Grandchild'),
('Guardian', 'Legal or recognized guardian'),
('Relative', 'Other family relation');
