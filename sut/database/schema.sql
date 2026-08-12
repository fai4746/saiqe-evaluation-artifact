-- FCCSMS Database Schema v2.0
-- Federal Case & Citizen Service Management System

CREATE DATABASE IF NOT EXISTS fccsms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fccsms;

-- ─────────────────────────────────────────────
-- roles
-- ─────────────────────────────────────────────
CREATE TABLE roles (
    role_id   TINYINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(20)      NOT NULL UNIQUE,
    description VARCHAR(255)   NOT NULL DEFAULT ''
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- programs
-- ─────────────────────────────────────────────
CREATE TABLE programs (
    program_id   INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    program_name VARCHAR(100) NOT NULL,
    program_code VARCHAR(20)  NOT NULL UNIQUE,
    description  TEXT,
    is_active    TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- users
-- ─────────────────────────────────────────────
CREATE TABLE users (
    user_id       INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    role_id       TINYINT UNSIGNED NOT NULL,
    first_name    VARCHAR(100)  NOT NULL,
    last_name     VARCHAR(100)  NOT NULL,
    email         VARCHAR(255)  NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    status        ENUM('active','inactive','locked') NOT NULL DEFAULT 'active',
    failed_logins TINYINT       NOT NULL DEFAULT 0,
    last_login    DATETIME      NULL,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- citizens
-- ─────────────────────────────────────────────
CREATE TABLE citizens (
    citizen_id    INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL UNIQUE,
    date_of_birth DATE         NOT NULL,
    phone         VARCHAR(20)  NOT NULL,
    address_street VARCHAR(255) NOT NULL,
    address_city   VARCHAR(100) NOT NULL,
    address_state  CHAR(2)      NOT NULL,
    address_zip    VARCHAR(10)  NOT NULL,
    ssn_last4      CHAR(4)      NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- applications
-- ─────────────────────────────────────────────
CREATE TABLE applications (
    application_id     INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    application_number VARCHAR(20)   NOT NULL UNIQUE,
    citizen_id         INT UNSIGNED  NOT NULL,
    program_id         INT UNSIGNED  NOT NULL,
    income_range       VARCHAR(50)   NOT NULL,
    household_size     TINYINT       NOT NULL,
    reason_for_request TEXT          NOT NULL,
    urgency_level      ENUM('normal','high','emergency') NOT NULL DEFAULT 'normal',
    preferred_contact  ENUM('email','phone','mail')      NOT NULL DEFAULT 'email',
    status             ENUM('Submitted','In Review','Pending Documents','Recommended for Approval','Recommended for Denial','Approved','Denied') NOT NULL DEFAULT 'Submitted',
    submitted_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address         VARCHAR(45)   NULL,
    FOREIGN KEY (citizen_id) REFERENCES citizens(citizen_id),
    FOREIGN KEY (program_id) REFERENCES programs(program_id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- cases
-- ─────────────────────────────────────────────
CREATE TABLE cases (
    case_id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    application_id      INT UNSIGNED NOT NULL UNIQUE,
    assigned_officer_id INT UNSIGNED NULL,
    status              ENUM('Submitted','In Review','Pending Documents','Recommended for Approval','Recommended for Denial','Approved','Denied','Returned for Review') NOT NULL DEFAULT 'Submitted',
    decision_comments   TEXT         NULL,
    decided_by          INT UNSIGNED NULL,
    decided_at          DATETIME     NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id)      REFERENCES applications(application_id),
    FOREIGN KEY (assigned_officer_id) REFERENCES users(user_id),
    FOREIGN KEY (decided_by)          REFERENCES users(user_id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- case_status_history
-- ─────────────────────────────────────────────
CREATE TABLE case_status_history (
    history_id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    case_id           INT UNSIGNED NOT NULL,
    old_status        VARCHAR(50)  NULL,
    new_status        VARCHAR(50)  NOT NULL,
    changed_by_user_id INT UNSIGNED NOT NULL,
    comments          TEXT         NULL,
    changed_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id)            REFERENCES cases(case_id),
    FOREIGN KEY (changed_by_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- case_notes
-- ─────────────────────────────────────────────
CREATE TABLE case_notes (
    note_id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    case_id         INT UNSIGNED NOT NULL,
    officer_user_id INT UNSIGNED NOT NULL,
    note_text       TEXT         NOT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id)         REFERENCES cases(case_id),
    FOREIGN KEY (officer_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- documents
-- ─────────────────────────────────────────────
CREATE TABLE documents (
    document_id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    application_id     INT UNSIGNED NOT NULL,
    original_filename  VARCHAR(255) NOT NULL,
    stored_filename    VARCHAR(255) NOT NULL UNIQUE,
    file_type          VARCHAR(100) NOT NULL,
    file_size_bytes    INT UNSIGNED NOT NULL,
    uploaded_by_user_id INT UNSIGNED NOT NULL,
    uploaded_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id)      REFERENCES applications(application_id),
    FOREIGN KEY (uploaded_by_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- notifications
-- ─────────────────────────────────────────────
CREATE TABLE notifications (
    notification_id   INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    recipient_user_id INT UNSIGNED  NOT NULL,
    trigger_event     VARCHAR(100)  NOT NULL,
    message           TEXT          NOT NULL,
    is_read           TINYINT(1)    NOT NULL DEFAULT 0,
    created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipient_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- audit_logs (immutable — no UPDATE/DELETE triggers)
-- ─────────────────────────────────────────────
CREATE TABLE audit_logs (
    log_id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED    NULL,
    username    VARCHAR(255)    NULL,
    role        VARCHAR(20)     NULL,
    action_type VARCHAR(100)    NOT NULL,
    entity_type VARCHAR(50)     NULL,
    entity_id   INT UNSIGNED    NULL,
    old_value   JSON            NULL,
    new_value   JSON            NULL,
    ip_address  VARCHAR(45)     NULL,
    user_agent  TEXT            NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- password_reset_tokens (Phase 2)
-- ─────────────────────────────────────────────
CREATE TABLE password_reset_tokens (
    token_id   INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- Prevent UPDATE/DELETE on audit_logs
-- ─────────────────────────────────────────────
DELIMITER $$
CREATE TRIGGER prevent_audit_update
BEFORE UPDATE ON audit_logs
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit log records are immutable';
END$$

CREATE TRIGGER prevent_audit_delete
BEFORE DELETE ON audit_logs
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit log records are immutable';
END$$
DELIMITER ;
