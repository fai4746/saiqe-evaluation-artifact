-- ============================================================
-- FCCSMS v2 Enhancement Migration
-- Safe to run on an existing v1 installation.
-- Uses IF NOT EXISTS, MODIFY COLUMN, ADD COLUMN IF NOT EXISTS.
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;
SET sql_mode = '';

-- ────────────────────────────────────────────────────────────
-- 1. APPLICATIONS TABLE — status data migration + alter
-- ────────────────────────────────────────────────────────────

-- Temporarily relax ENUM so we can update values first
ALTER TABLE applications MODIFY COLUMN status VARCHAR(60) NOT NULL DEFAULT 'Submitted';
ALTER TABLE cases        MODIFY COLUMN status VARCHAR(60) NOT NULL DEFAULT 'Submitted';

-- Map old v1 status values → new v2 values
UPDATE applications SET status = 'Officer Review'    WHERE status = 'In Review';
UPDATE applications SET status = 'Pending Verification' WHERE status = 'Pending Documents';
UPDATE applications SET status = 'Supervisor Review' WHERE status = 'Recommended for Approval';
UPDATE applications SET status = 'Supervisor Review' WHERE status = 'Recommended for Denial';
UPDATE applications SET status = 'Officer Review'    WHERE status = 'Returned for Review';

UPDATE cases SET status = 'Officer Review'      WHERE status = 'In Review';
UPDATE cases SET status = 'Pending Verification' WHERE status = 'Pending Documents';
UPDATE cases SET status = 'Supervisor Review'   WHERE status = 'Recommended for Approval';
UPDATE cases SET status = 'Supervisor Review'   WHERE status = 'Recommended for Denial';
UPDATE cases SET status = 'Officer Review'      WHERE status = 'Returned for Review';

-- Re-apply as proper ENUM
ALTER TABLE applications MODIFY COLUMN status ENUM(
    'Draft','Submitted','Eligibility Review','Pending Verification',
    'Document Requested','Document Received','Officer Review','Quality Review',
    'Supervisor Review','Approved','Denied','Closed','Appealed',
    'Appeal Review','Final Disposition','Auto-Denied'
) NOT NULL DEFAULT 'Draft';

ALTER TABLE cases MODIFY COLUMN status ENUM(
    'Draft','Submitted','Eligibility Review','Pending Verification',
    'Document Requested','Document Received','Officer Review','Quality Review',
    'Supervisor Review','Approved','Denied','Closed','Appealed',
    'Appeal Review','Final Disposition','Auto-Denied'
) NOT NULL DEFAULT 'Submitted';

-- Add wizard / eligibility columns to applications
ALTER TABLE applications
    ADD COLUMN IF NOT EXISTS wizard_step_completed          TINYINT        NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS application_data_json          LONGTEXT       NULL,
    ADD COLUMN IF NOT EXISTS certification_checked          TINYINT(1)     NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS certification_timestamp        DATETIME       NULL,
    ADD COLUMN IF NOT EXISTS preliminary_eligibility_result ENUM('pending','passed','flagged','failed') NOT NULL DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS annual_income                  DECIMAL(12,2)  NULL,
    ADD COLUMN IF NOT EXISTS employment_status              VARCHAR(50)    NULL,
    ADD COLUMN IF NOT EXISTS monthly_expenses               DECIMAL(12,2)  NULL,
    ADD COLUMN IF NOT EXISTS housing_cost_monthly           DECIMAL(12,2)  NULL,
    ADD COLUMN IF NOT EXISTS num_dependents                 TINYINT        NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS marital_status                 ENUM('Single','Married','Divorced','Widowed','Separated') NULL,
    ADD COLUMN IF NOT EXISTS veteran_status                 ENUM('Yes','No') NULL,
    ADD COLUMN IF NOT EXISTS disability_status              ENUM('Yes','No','Prefer not to say') NULL,
    ADD COLUMN IF NOT EXISTS wizard_started_at              DATETIME       NULL;

-- Add eligibility_questions column to programs
ALTER TABLE programs
    ADD COLUMN IF NOT EXISTS eligibility_questions JSON NULL;

-- Add document_category to documents
ALTER TABLE documents
    ADD COLUMN IF NOT EXISTS document_category VARCHAR(60) NULL;

-- ────────────────────────────────────────────────────────────
-- 2. ELIGIBILITY RESULTS TABLE
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS eligibility_results (
    result_id      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    rule_name      VARCHAR(100) NOT NULL,
    rule_result    ENUM('passed','flagged','failed') NOT NULL,
    reason_text    TEXT         NULL,
    evaluated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(application_id) ON DELETE CASCADE,
    INDEX idx_er_app (application_id)
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- 3. RISK SCORES TABLE
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS risk_scores (
    score_id              INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    application_id        INT UNSIGNED  NOT NULL UNIQUE,
    total_score           INT           NOT NULL DEFAULT 0,
    risk_level            ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
    score_breakdown_json  LONGTEXT      NULL,
    computed_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    recomputed_at         DATETIME      NULL,
    FOREIGN KEY (application_id) REFERENCES applications(application_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- 4. QUALITY REVIEW ACTIONS TABLE
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS quality_review_actions (
    action_id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    case_id           INT UNSIGNED NOT NULL,
    reviewer_user_id  INT UNSIGNED NOT NULL,
    action_type       ENUM('passed_to_supervisor','returned_to_officer','doc_marked_complete','doc_marked_incomplete') NOT NULL,
    notes             TEXT         NULL,
    action_timestamp  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id)          REFERENCES cases(case_id),
    FOREIGN KEY (reviewer_user_id) REFERENCES users(user_id),
    INDEX idx_qra_case (case_id)
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- 5. APPEALS TABLE
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS appeals (
    appeal_id                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    application_id           INT UNSIGNED NOT NULL,
    citizen_id               INT UNSIGNED NOT NULL,
    appeal_reason            TEXT         NOT NULL,
    submitted_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decision                 ENUM('pending','upheld','denied') NOT NULL DEFAULT 'pending',
    decision_notes           TEXT         NULL,
    decided_by_user_id       INT UNSIGNED NULL,
    decided_at               DATETIME     NULL,
    appeal_window_expires_at DATETIME     NOT NULL,
    FOREIGN KEY (application_id)     REFERENCES applications(application_id),
    FOREIGN KEY (citizen_id)         REFERENCES citizens(citizen_id),
    FOREIGN KEY (decided_by_user_id) REFERENCES users(user_id),
    INDEX idx_appeal_app (application_id)
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- 6. APPEAL DOCUMENTS TABLE
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS appeal_documents (
    doc_id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    appeal_id            INT UNSIGNED NOT NULL,
    original_filename    VARCHAR(255) NOT NULL,
    stored_filename      VARCHAR(255) NOT NULL UNIQUE,
    file_type            VARCHAR(100) NOT NULL,
    file_size_bytes      INT UNSIGNED NOT NULL,
    uploaded_by_user_id  INT UNSIGNED NOT NULL,
    uploaded_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appeal_id)            REFERENCES appeals(appeal_id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by_user_id)  REFERENCES users(user_id)
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- 7. SLA RULES TABLE + SEED DATA
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sla_rules (
    sla_id                  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    status_name             VARCHAR(60)  NOT NULL UNIQUE,
    business_days_allowed   TINYINT      NOT NULL,
    warning_threshold_days  TINYINT      NOT NULL,
    role_responsible        VARCHAR(50)  NOT NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO sla_rules (status_name, business_days_allowed, warning_threshold_days, role_responsible) VALUES
    ('Eligibility Review',   5,  3, 'officer'),
    ('Pending Verification', 3,  2, 'officer'),
    ('Document Requested',   10, 7, 'citizen'),
    ('Officer Review',       10, 7, 'officer'),
    ('Quality Review',       5,  3, 'quality_reviewer'),
    ('Supervisor Review',    5,  3, 'supervisor'),
    ('Appeal Review',        15, 10,'supervisor');

-- ────────────────────────────────────────────────────────────
-- 8. SLA TRACKING TABLE
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sla_tracking (
    tracking_id  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    case_id      INT UNSIGNED NOT NULL,
    status_name  VARCHAR(60)  NOT NULL,
    entered_at   DATETIME     NOT NULL,
    sla_deadline DATETIME     NOT NULL,
    warning_date DATETIME     NOT NULL,
    is_breached  TINYINT(1)   NOT NULL DEFAULT 0,
    breached_at  DATETIME     NULL,
    FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE,
    INDEX idx_sla_case   (case_id),
    INDEX idx_sla_breach (is_breached, sla_deadline)
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- 9. CASE ASSIGNMENTS TABLE
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS case_assignments (
    assignment_id       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    case_id             INT UNSIGNED NOT NULL,
    assigned_to_user_id INT UNSIGNED NOT NULL,
    assigned_by_user_id INT UNSIGNED NULL,
    assignment_method   VARCHAR(30)  NOT NULL DEFAULT 'manual',
    assigned_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    unassigned_at       DATETIME     NULL,
    notes               TEXT         NULL,
    FOREIGN KEY (case_id)             REFERENCES cases(case_id),
    FOREIGN KEY (assigned_to_user_id) REFERENCES users(user_id),
    FOREIGN KEY (assigned_by_user_id) REFERENCES users(user_id),
    INDEX idx_ca_case   (case_id),
    INDEX idx_ca_officer(assigned_to_user_id)
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- 10. ALTER USERS TABLE
-- ────────────────────────────────────────────────────────────
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS program_specialization VARCHAR(255) NULL;

-- ────────────────────────────────────────────────────────────
-- 11. ALTER AUDIT_LOGS TABLE
-- ────────────────────────────────────────────────────────────
ALTER TABLE audit_logs
    ADD COLUMN IF NOT EXISTS reason_for_change VARCHAR(500) NULL,
    ADD COLUMN IF NOT EXISTS session_id        VARCHAR(128) NULL,
    ADD COLUMN IF NOT EXISTS request_uri       VARCHAR(500) NULL;

-- ────────────────────────────────────────────────────────────
-- 12. ALTER NOTIFICATIONS TABLE
-- ────────────────────────────────────────────────────────────
ALTER TABLE notifications
    ADD COLUMN IF NOT EXISTS notification_type ENUM(
        'application_submitted','document_requested','document_received',
        'eligibility_passed','eligibility_denied','case_assigned','case_approved',
        'case_denied','appeal_submitted','appeal_upheld','appeal_denied',
        'sla_warning','sla_breach','general'
    ) NOT NULL DEFAULT 'general';

-- ────────────────────────────────────────────────────────────
-- 13. TRAINING DEFECTS TABLE + SEED DATA
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS training_defects (
    defect_id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    defect_key           VARCHAR(100) NOT NULL UNIQUE,
    defect_name          VARCHAR(200) NOT NULL,
    defect_description   TEXT         NULL,
    defect_category      ENUM('validation','business_rule','status_transition','api_authorization','accessibility','database_integrity') NOT NULL,
    is_active            TINYINT(1)   NOT NULL DEFAULT 0,
    activated_at         DATETIME     NULL,
    activated_by_user_id INT UNSIGNED NULL,
    FOREIGN KEY (activated_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO training_defects (defect_key, defect_name, defect_description, defect_category, is_active) VALUES
    ('missing_phone_validation',       'Phone field accepts any text',                      'Phone field accepts any text without format validation (no (NNN) NNN-NNNN enforcement)',               'validation',          0),
    ('duplicate_app_limit_bypass',     'Active application limit not enforced',             'Citizens can submit a 4th active application (3-application limit bypassed)',                          'business_rule',       0),
    ('invalid_status_transition_allowed','Officer can set case directly to Approved',       'Officer can transition a case directly to Approved, skipping Quality Review and Supervisor',           'status_transition',   0),
    ('api_citizen_sees_all_apps',      'Citizen API returns all citizens'' applications',   'GET /api/v1/applications returns every application in the system, not just the authenticated citizen''s','api_authorization', 0),
    ('missing_csrf_token',             'State-changing forms skip CSRF validation',         'Forms that change server state do not validate the CSRF token on submission',                           'validation',          0),
    ('missing_form_labels',            'Submit Application form uses placeholders only',    'Submit Application form renders placeholder text but no visible <label> elements',                     'accessibility',       0),
    ('audit_log_deletable',            'DELETE /api/v1/audit-logs/{id} deletes records',   'API endpoint returns 200 and removes the audit log entry, violating immutability requirement',         'database_integrity',  0),
    ('password_plaintext_logged',      'Failed logins log attempted password in plaintext', 'Failed login audit log entries include the raw attempted password in new_value',                        'database_integrity',  0),
    ('sla_calculation_ignores_weekends','SLA counts calendar days instead of business days','SLA deadline calculation uses ADDDATE with calendar days rather than skipping weekends',               'business_rule',       0),
    ('risk_score_not_recomputed',      'Risk score not updated after document upload',      'Uploading new documents does not trigger risk score recomputation, leaving stale scores',              'business_rule',       0),
    ('supervisor_no_comment_required', 'Supervisor can decide without decision comments',   'Supervisor approval/denial form allows submission without entering decision comments',                  'business_rule',       0),
    ('file_type_extension_only',       'Document upload checks extension only',             'File type validation relies on extension (.jpg, .pdf) rather than MIME type — allows disguised files', 'validation',          0);

-- ────────────────────────────────────────────────────────────
-- 14. INSERT quality_reviewer ROLE
-- ────────────────────────────────────────────────────────────
INSERT IGNORE INTO roles (role_id, role_name, description)
    VALUES (6, 'quality_reviewer', 'Reviews officer decisions before escalation to supervisor');

-- ────────────────────────────────────────────────────────────
-- 15. SYSTEM SETTINGS TABLE + SEED DATA
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key   VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value TEXT         NOT NULL DEFAULT '',
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
    ('assignment_method',  'least_loaded'),
    ('rr_last_officer_id', '0'),
    ('sla_last_check',     '2000-01-01 00:00:00');

-- ────────────────────────────────────────────────────────────
-- DONE
-- ────────────────────────────────────────────────────────────
SET foreign_key_checks = 1;
