
CREATE DATABASE gov_enterprise;

USE gov_enterprise;

CREATE TABLE users(
id INT AUTO_INCREMENT PRIMARY KEY,
username VARCHAR(50),
password VARCHAR(255)
);

CREATE TABLE permissions(
id INT AUTO_INCREMENT PRIMARY KEY,
user_id INT,
permission VARCHAR(50)
);

CREATE TABLE password_requests(
id INT AUTO_INCREMENT PRIMARY KEY,
username VARCHAR(50),
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users(username,password)
VALUES('admin','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9H2Q4jE3JqXWj8nB8s8Y3K');

INSERT INTO permissions(user_id,permission) VALUES(1,'admin');
