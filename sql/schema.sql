-- Cyber4rAll Compliance Tracker
-- NDPR / Nigeria Data Protection Act compliance self-assessment tool
-- Run this once in phpMyAdmin (or DirectAdmin's MySQL management) against your created database.

SET NAMES utf8mb4;

-- ---------------------------------------------------------
-- Companies (tenants)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    industry VARCHAR(100) DEFAULT NULL,
    slug VARCHAR(191) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- Users (belong to a company; role = admin or staff)
-- A special platform_admin flag marks Cyber4rAll staff who can see all companies.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    full_name VARCHAR(191) NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','staff') NOT NULL DEFAULT 'admin',
    is_platform_admin TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- Question categories (the pillars of NDPR compliance)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- Questions (the master question bank — shared across all tenants)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    prompt TEXT NOT NULL,
    guidance TEXT DEFAULT NULL,
    weight INT NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- Responses (one per company per question)
-- answer: yes = fully compliant, partial = in progress, no = gap
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    question_id INT NOT NULL,
    answer ENUM('yes','partial','no') DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_company_question (company_id, question_id),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- Remediation items (generated from gaps, but can also be added manually)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS remediation_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    question_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    details TEXT DEFAULT NULL,
    status ENUM('open','in_progress','done') NOT NULL DEFAULT 'open',
    assigned_to VARCHAR(191) DEFAULT NULL,
    due_date DATE DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- Score history (snapshot each time we recompute, so dashboard can show trend)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS score_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===========================================================
-- SEED DATA: NDPR / NDPA compliance question bank
-- ===========================================================

INSERT INTO categories (name, description, sort_order) VALUES
('Governance & DPO', 'Data protection officer, DPCO registration, policies', 1),
('Lawful Basis & Consent', 'Legal grounds for processing personal data', 2),
('Data Mapping & Inventory', 'Knowing what personal data you hold and why', 3),
('Data Subject Rights', 'Handling access, correction, deletion requests', 4),
('Security Safeguards', 'Technical and organizational measures protecting data', 5),
('Third Parties & Cross-Border Transfer', 'Vendor contracts and transfers outside Nigeria', 6),
('Breach Detection & Response', 'Ability to detect, contain and report breaches', 7),
('Retention & Disposal', 'Keeping data only as long as necessary', 8),
('Training & Awareness', 'Staff understanding of data protection duties', 9);

INSERT INTO questions (category_id, prompt, guidance, weight, sort_order) VALUES
-- Governance & DPO
(1, 'Has your organization appointed a Data Protection Officer (DPO)?', 'Required for organizations processing personal data of more than 200 subjects in 12 months, or processing special category data.', 3, 1),
(1, 'Has your organization registered with the Nigeria Data Protection Commission (NDPC) as required?', 'Filing/registration obligations apply to data controllers/processors of a certain size (NDPA 2023).', 3, 2),
(1, 'Do you have a documented, board-approved data protection policy?', 'A written policy should cover scope, principles, roles and responsibilities.', 2, 3),
(1, 'Is there a designated budget/resource allocated to data protection compliance?', NULL, 1, 4),

-- Lawful Basis & Consent
(2, 'Does your organization document the lawful basis for each category of personal data processing?', 'E.g. consent, contract, legal obligation, legitimate interest.', 2, 1),
(2, 'Do you obtain clear, informed consent before collecting personal data where consent is the legal basis?', 'Consent should be freely given, specific, informed and unambiguous.', 3, 2),
(2, 'Can data subjects easily withdraw consent, and is that mechanism documented?', NULL, 2, 3),
(2, 'Is there a public-facing privacy policy/notice describing what data is collected and why?', NULL, 2, 4),

-- Data Mapping & Inventory
(3, 'Does your organization maintain a data inventory/map of what personal data is collected, stored, and processed?', 'Should cover data type, source, location, and purpose.', 3, 1),
(3, 'Have you classified personal data by sensitivity (e.g. general vs. special category data)?', NULL, 2, 2),
(3, 'Do you know every system, database, and third-party tool where personal data resides?', NULL, 2, 3),

-- Data Subject Rights
(4, 'Does your organization have a documented process for handling data subject access requests (DSARs)?', 'Includes verifying identity and responding within statutory timelines.', 2, 1),
(4, 'Can your organization fulfil requests to correct or delete personal data on request?', NULL, 2, 2),
(4, 'Is there a process for data subjects to object to or restrict processing of their data?', NULL, 1, 3),

-- Security Safeguards
(5, 'Is personal data encrypted at rest and in transit where appropriate?', NULL, 3, 1),
(5, 'Are access controls in place so only authorized staff can access personal data?', 'Role-based access, least privilege.', 3, 2),
(5, 'Are systems holding personal data regularly patched and monitored for vulnerabilities?', NULL, 2, 3),
(5, 'Is multi-factor authentication (MFA) enforced for systems containing personal data?', NULL, 2, 4),
(5, 'Are regular backups of personal data taken and periodically tested?', NULL, 2, 5),

-- Third Parties & Cross-Border Transfer
(6, 'Do contracts with vendors/processors who handle personal data include data protection clauses?', 'Data Processing Agreements (DPAs).', 2, 1),
(6, 'Is there a documented process for assessing a vendor''s data protection practices before onboarding?', NULL, 2, 2),
(6, 'If personal data is transferred outside Nigeria, is there a valid legal mechanism/adequacy basis for that transfer?', 'NDPA restricts cross-border transfers unless conditions are met.', 3, 3),

-- Breach Detection & Response
(7, 'Does your organization have a documented data breach response plan?', NULL, 3, 1),
(7, 'Do you have technical monitoring (e.g. logging/SIEM/alerts) in place to detect a breach?', NULL, 3, 2),
(7, 'Is there a defined process and timeline to notify the NDPC and affected data subjects after a breach?', 'NDPA sets notification timelines for reportable breaches.', 3, 3),
(7, 'Has your organization tested its breach response plan (tabletop exercise or drill) in the past 12 months?', NULL, 1, 4),

-- Retention & Disposal
(8, 'Does your organization have a documented data retention schedule?', 'Specifies how long each category of data is kept.', 2, 1),
(8, 'Is personal data securely disposed of (deleted/destroyed) once no longer needed?', NULL, 2, 2),

-- Training & Awareness
(9, 'Have staff received data protection / NDPR awareness training in the past 12 months?', NULL, 2, 1),
(9, 'Are new employees briefed on data protection responsibilities during onboarding?', NULL, 1, 2);
