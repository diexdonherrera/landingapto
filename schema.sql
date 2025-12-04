CREATE DATABASE IF NOT EXISTS firmas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE firmas;

CREATE TABLE IF NOT EXISTS registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    document_number VARCHAR(50) NOT NULL,
    email VARCHAR(120) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    benefit_description TEXT NOT NULL,
    delivery_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    signature_path VARCHAR(255) NOT NULL,
    id_front_path VARCHAR(255) DEFAULT NULL,
    id_back_path VARCHAR(255) DEFAULT NULL,
    pdf_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
