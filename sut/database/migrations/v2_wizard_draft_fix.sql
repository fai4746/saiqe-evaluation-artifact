-- Fix: allow NULL program_id for wizard draft applications
-- Run once: mysql -u root fccsms < database/migrations/v2_wizard_draft_fix.sql

USE fccsms;

ALTER TABLE applications
    MODIFY COLUMN program_id    INT UNSIGNED  NULL DEFAULT NULL,
    MODIFY COLUMN income_range  VARCHAR(50)   NULL DEFAULT NULL,
    MODIFY COLUMN household_size TINYINT      NULL DEFAULT NULL,
    MODIFY COLUMN reason_for_request TEXT     NULL;
