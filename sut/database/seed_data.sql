-- FCCSMS Seed Data v2.0
USE fccsms;

-- ─── Roles ────────────────────────────────────
INSERT INTO roles (role_id, role_name, description) VALUES
(1, 'admin',            'Full system access'),
(2, 'officer',          'Assigned case management'),
(3, 'supervisor',       'Final case decisions'),
(4, 'auditor',          'Read-only audit access'),
(5, 'citizen',          'Own applications only'),
(6, 'quality_reviewer', 'Quality review before supervisor')
ON DUPLICATE KEY UPDATE role_name=VALUES(role_name);

-- ─── Programs ─────────────────────────────────
INSERT INTO programs (program_name, program_code, description, is_active) VALUES
('Food Assistance',       'FOOD',   'Nutritional support programs',              1),
('Housing Support',       'HOUS',   'Rental and housing assistance',             1),
('Medical Aid',           'MEDI',   'Medical coverage and assistance',           1),
('Employment Services',   'EMPL',   'Job training and placement services',       1),
('Child Care Subsidy',    'CHLD',   'Subsidized child care for working parents', 1),
('Energy Assistance',     'ENRG',   'Utility and heating cost assistance',       1),
('Transportation Aid',    'TRAN',   'Transportation support services',           1);

-- ─── Users (password = Test@1234 bcrypt) ──────
-- Hash for: Test@1234
INSERT INTO users (role_id, first_name, last_name, email, password_hash, status) VALUES
(1, 'Admin',      'User',     'admin@fccsms.gov',       '$2y$12$XdLkGWueoq34UdQYHCanQOAl6Oe1YEcYwgLdGTHHKX.fDcgJtLWXi', 'active'),
(2, 'Officer',    'One',      'officer1@fccsms.gov',    '$2y$12$XdLkGWueoq34UdQYHCanQOAl6Oe1YEcYwgLdGTHHKX.fDcgJtLWXi', 'active'),
(2, 'Officer',    'Two',      'officer2@fccsms.gov',    '$2y$12$XdLkGWueoq34UdQYHCanQOAl6Oe1YEcYwgLdGTHHKX.fDcgJtLWXi', 'active'),
(3, 'Supervisor', 'One',      'supervisor@fccsms.gov',  '$2y$12$XdLkGWueoq34UdQYHCanQOAl6Oe1YEcYwgLdGTHHKX.fDcgJtLWXi', 'active'),
(4, 'Auditor',    'One',      'auditor@fccsms.gov',     '$2y$12$XdLkGWueoq34UdQYHCanQOAl6Oe1YEcYwgLdGTHHKX.fDcgJtLWXi', 'active'),
(5, 'John',       'Citizen',  'john@example.com',       '$2y$12$XdLkGWueoq34UdQYHCanQOAl6Oe1YEcYwgLdGTHHKX.fDcgJtLWXi', 'active'),
(5, 'Jane',       'Doe',      'jane@example.com',       '$2y$12$XdLkGWueoq34UdQYHCanQOAl6Oe1YEcYwgLdGTHHKX.fDcgJtLWXi', 'active'),
(6, 'Quality',    'Reviewer', 'qreviewer@fccsms.gov',   '$2y$12$XdLkGWueoq34UdQYHCanQOAl6Oe1YEcYwgLdGTHHKX.fDcgJtLWXi', 'active');

-- ─── Citizens ─────────────────────────────────
INSERT INTO citizens (user_id, date_of_birth, phone, address_street, address_city, address_state, address_zip, ssn_last4) VALUES
(6, '1985-03-15', '(555) 123-4567', '123 Main Street',  'Springfield', 'IL', '62701', '1234'),
(7, '1990-07-22', '(555) 987-6543', '456 Oak Avenue',   'Springfield', 'IL', '62702', '5678');

-- ─── Applications ─────────────────────────────
INSERT INTO applications (application_number, citizen_id, program_id, income_range, household_size, reason_for_request, urgency_level, preferred_contact, status, submitted_at, ip_address) VALUES
('APP-2026-000001', 1, 1, '$0-$15,000',     2, 'I am currently unemployed and struggling to provide food for my family. We have been without steady income for three months and need immediate assistance.',                  'high',      'email', 'Approved',                   '2026-01-10 09:00:00', '127.0.0.1'),
('APP-2026-000002', 1, 2, '$15,001-$30,000',3, 'My landlord has issued a 30-day eviction notice and I need housing assistance to find a new place. I have two children and need help urgently.',                          'emergency', 'phone', 'Denied',                     '2026-01-15 10:30:00', '127.0.0.1'),
('APP-2026-000003', 1, 3, '$0-$15,000',     2, 'I have a chronic medical condition that requires ongoing treatment and medication. My insurance lapsed when I lost my job and I need medical aid coverage.',                'normal',    'email', 'In Review',                  '2026-02-01 14:00:00', '127.0.0.1'),
('APP-2026-000004', 2, 4, '$30,001-$45,000',1, 'I am recently laid off from my manufacturing job and seeking employment services to help me retrain for a new career in technology. I have 10 years of work experience.', 'normal',    'mail',  'Submitted',                  '2026-02-10 11:00:00', '127.0.0.1'),
('APP-2026-000005', 2, 5, '$15,001-$30,000',3, 'I am a single working parent with three children under age 10. I need child care assistance to continue working and maintain my current employment position.',             'high',      'email', 'Recommended for Approval',   '2026-02-15 09:30:00', '127.0.0.1'),
('APP-2026-000006', 1, 6, '$0-$15,000',     4, 'Our household energy bills have become unmanageable this winter. We have received shutoff notices from the utility company and need emergency energy assistance.',          'emergency', 'phone', 'Pending Documents',          '2026-03-01 08:00:00', '127.0.0.1'),
('APP-2026-000007', 2, 7, '$15,001-$30,000',2, 'I do not have reliable transportation to get to work. My car broke down and repairs cost more than I can afford. Without transportation I may lose my job.',             'high',      'email', 'Recommended for Denial',     '2026-03-05 13:00:00', '127.0.0.1'),
('APP-2026-000008', 1, 1, '$0-$15,000',     1, 'As a senior living alone on a fixed income, I am having difficulty affording groceries each month. My social security only covers rent and basic utilities.',            'normal',    'mail',  'Returned for Review',        '2026-03-10 10:00:00', '127.0.0.1'),
('APP-2026-000009', 2, 2, '$30,001-$45,000',5, 'Our family of five is living in an overcrowded apartment that poses health and safety risks. We need housing support to find adequate accommodation for our family.',     'high',      'email', 'In Review',                  '2026-03-15 15:00:00', '127.0.0.1'),
('APP-2026-000010', 1, 3, '$15,001-$30,000',3, 'My youngest child has been diagnosed with a serious illness requiring specialist treatment. Our insurance does not cover the full cost of necessary medical procedures.',  'emergency', 'phone', 'Submitted',                  '2026-03-20 09:00:00', '127.0.0.1');

-- ─── Cases ────────────────────────────────────
INSERT INTO cases (application_id, assigned_officer_id, status, decision_comments, decided_by, decided_at) VALUES
(1,  2, 'Approved',                 'Application meets all requirements. Citizen verified.',      4, '2026-01-20 10:00:00'),
(2,  2, 'Denied',                   'Insufficient documentation provided. Income not verified.',  4, '2026-01-25 14:00:00'),
(3,  2, 'In Review',                NULL, NULL, NULL),
(4,  3, 'Submitted',                NULL, NULL, NULL),
(5,  2, 'Recommended for Approval', NULL, NULL, NULL),
(6,  2, 'Pending Documents',        NULL, NULL, NULL),
(7,  3, 'Recommended for Denial',   NULL, NULL, NULL),
(8,  2, 'Returned for Review',      'Missing income verification documents. Please request from citizen.', 4, '2026-03-12 11:00:00'),
(9,  3, 'In Review',                NULL, NULL, NULL),
(10, 2, 'Submitted',                NULL, NULL, NULL);

-- ─── Case Status History ──────────────────────
INSERT INTO case_status_history (case_id, old_status, new_status, changed_by_user_id, comments, changed_at) VALUES
(1, NULL,                  'Submitted',             6, 'Application submitted by citizen',          '2026-01-10 09:00:00'),
(1, 'Submitted',           'In Review',             2, 'Assigned and started review',               '2026-01-12 10:00:00'),
(1, 'In Review',           'Recommended for Approval','2','All criteria met',                       '2026-01-18 14:00:00'),
(1, 'Recommended for Approval','Approved',          4, 'Approved by supervisor',                    '2026-01-20 10:00:00'),
(2, NULL,                  'Submitted',             7, 'Application submitted by citizen',          '2026-01-15 10:30:00'),
(2, 'Submitted',           'In Review',             2, 'Under review',                             '2026-01-17 09:00:00'),
(2, 'In Review',           'Recommended for Denial','2','Missing documentation',                    '2026-01-23 11:00:00'),
(2, 'Recommended for Denial','Denied',              4, 'Denied by supervisor',                      '2026-01-25 14:00:00'),
(3, NULL,                  'Submitted',             6, 'Application submitted',                     '2026-02-01 14:00:00'),
(3, 'Submitted',           'In Review',             2, 'Review started',                           '2026-02-05 09:00:00'),
(5, NULL,                  'Submitted',             7, 'Application submitted',                     '2026-02-15 09:30:00'),
(5, 'Submitted',           'In Review',             2, 'Assigned to officer',                      '2026-02-18 10:00:00'),
(5, 'In Review',           'Recommended for Approval','2','Strong case, recommend approval',        '2026-02-25 14:00:00');

-- ─── Case Notes ───────────────────────────────
INSERT INTO case_notes (case_id, officer_user_id, note_text, created_at) VALUES
(1, 2, 'Verified income documents. All submitted materials appear authentic.', '2026-01-13 10:00:00'),
(1, 2, 'Contacted citizen for additional verification. Confirmed household size.', '2026-01-15 14:00:00'),
(2, 2, 'Requested bank statements but citizen has not responded.', '2026-01-19 09:00:00'),
(3, 2, 'Initial review shows need for additional medical documentation.', '2026-02-06 11:00:00'),
(5, 2, 'Citizen provided proof of employment and child care enrollment.', '2026-02-20 10:00:00'),
(6, 2, 'Sent document request for utility shutoff notices and income verification.', '2026-03-02 09:00:00'),
(8, 2, 'Case returned — need to follow up on income verification documents.', '2026-03-12 12:00:00');

-- ─── Notifications ────────────────────────────
INSERT INTO notifications (recipient_user_id, trigger_event, message, is_read, created_at) VALUES
(6, 'Application Submitted',   'Your application APP-2026-000001 has been successfully submitted. Track status on your dashboard.', 1, '2026-01-10 09:01:00'),
(6, 'Case Status Changed',     'Your application APP-2026-000001 status has changed to: In Review.', 1, '2026-01-12 10:01:00'),
(6, 'Case Approved',           'Congratulations! Your application APP-2026-000001 has been Approved. A case worker will contact you shortly.', 1, '2026-01-20 10:01:00'),
(6, 'Application Submitted',   'Your application APP-2026-000002 has been successfully submitted.', 1, '2026-01-15 10:31:00'),
(6, 'Case Denied',             'Your application APP-2026-000002 has been Denied. Reason: Insufficient documentation. You may appeal within 30 days.', 0, '2026-01-25 14:01:00'),
(7, 'Application Submitted',   'Your application APP-2026-000004 has been successfully submitted.', 0, '2026-02-10 11:01:00'),
(7, 'Application Submitted',   'Your application APP-2026-000005 has been successfully submitted.', 0, '2026-02-15 09:31:00'),
(2, 'Case Assigned',           'A new case (APP-2026-000003) has been assigned to you for review.', 1, '2026-02-05 09:01:00'),
(2, 'Case Returned for Review','Case APP-2026-000008 has been returned to you for additional review. Please check supervisor comments.', 0, '2026-03-12 11:01:00');

-- ─── Audit Logs ───────────────────────────────
INSERT INTO audit_logs (user_id, username, role, action_type, entity_type, entity_id, old_value, new_value, ip_address, created_at) VALUES
(1, 'admin@fccsms.gov',      'admin',    'Login',               'user',        1, NULL, NULL, '127.0.0.1', '2026-01-09 08:00:00'),
(6, 'john@example.com',      'citizen',  'Registration',        'user',        6, NULL, '{"email":"john@example.com"}', '127.0.0.1', '2026-01-09 09:00:00'),
(6, 'john@example.com',      'citizen',  'Application Submitted','application', 1, NULL, '{"application_number":"APP-2026-000001"}', '127.0.0.1', '2026-01-10 09:00:00'),
(2, 'officer1@fccsms.gov',   'officer',  'Case Status Updated',  'case',        1, '{"status":"Submitted"}', '{"status":"In Review"}', '127.0.0.1', '2026-01-12 10:00:00'),
(4, 'supervisor@fccsms.gov', 'supervisor','Case Approved',       'case',        1, '{"status":"Recommended for Approval"}', '{"status":"Approved"}', '127.0.0.1', '2026-01-20 10:00:00'),
(6, 'john@example.com',      'citizen',  'Application Submitted','application', 2, NULL, '{"application_number":"APP-2026-000002"}', '127.0.0.1', '2026-01-15 10:30:00'),
(4, 'supervisor@fccsms.gov', 'supervisor','Case Denied',         'case',        2, '{"status":"Recommended for Denial"}', '{"status":"Denied"}', '127.0.0.1', '2026-01-25 14:00:00'),
(1, 'admin@fccsms.gov',      'admin',    'User Created',         'user',        2, NULL, '{"email":"officer1@fccsms.gov","role":"officer"}', '127.0.0.1', '2026-01-08 10:00:00');

-- ═══════════════════════════════════════════════
-- v2 Training Seed Data
-- ═══════════════════════════════════════════════

-- ─── Additional Officer Review apps (for QA training) ──
-- APP-2026-000011: Officer Review, low risk — normal workflow
-- APP-2026-000012: Officer Review → Quality Review (high risk)
-- APP-2026-000013: Quality Review (critical risk, escalated)
-- APP-2026-000014: Appeal Review (denied, citizen appealed)

INSERT INTO applications
  (application_number, citizen_id, program_id, income_range, household_size,
   reason_for_request, urgency_level, preferred_contact, status, submitted_at,
   annual_income, employment_status, monthly_expenses, housing_cost_monthly,
   num_dependents, marital_status, veteran_status, disability_status, ip_address)
VALUES
('APP-2026-000011', 1, 1, '$0-$15,000', 2,
 'Recently lost employment and struggling to afford groceries. Three months behind on bills.',
 'high', 'email', 'Officer Review', '2026-05-01 09:00:00',
 14000, 'Unemployed', 1200.00, 850.00, 1, 'Single', 'Non-Veteran', 'None', '127.0.0.1'),

('APP-2026-000012', 2, 2, '$15,001-$30,000', 4,
 'Family of four facing eviction. Landlord has filed papers. Spouse disabled and unable to work.',
 'emergency', 'phone', 'Quality Review', '2026-05-05 10:00:00',
 22000, 'Part-Time', 2800.00, 1400.00, 2, 'Married', 'Non-Veteran', 'Physical Disability', '127.0.0.1'),

('APP-2026-000013', 1, 3, '$0-$15,000', 1,
 'Chronic illness requiring expensive ongoing treatment. No insurance coverage since job loss six months ago.',
 'emergency', 'email', 'Quality Review', '2026-05-10 08:30:00',
 8000, 'Disabled/Unable to Work', 950.00, 600.00, 0, 'Widowed', 'Veteran', 'Multiple Disabilities', '127.0.0.1'),

('APP-2026-000014', 2, 1, '$15,001-$30,000', 3,
 'Applied for food assistance due to recent income reduction. Was denied due to income threshold.',
 'normal', 'email', 'Appeal Review', '2026-04-01 09:00:00',
 28000, 'Part-Time', 2200.00, 900.00, 2, 'Divorced', 'Non-Veteran', 'None', '127.0.0.1');

-- ─── Cases for new applications ────────────────────────
-- Assuming applications above get IDs 11-14 (adjust if DB differs)
INSERT INTO cases (application_id, assigned_officer_id, status) VALUES
(11, 2, 'Officer Review'),
(12, 2, 'Quality Review'),
(13, 2, 'Quality Review'),
(14, 3, 'Appeal Review');

-- ─── Status history for new cases ──────────────────────
-- APP-2026-000011 (case_id=11): Submitted → Eligibility Review → Officer Review
INSERT INTO case_status_history (case_id, old_status, new_status, changed_by_user_id, comments, changed_at) VALUES
(11, NULL,               'Submitted',          6, 'Application submitted', '2026-05-01 09:00:00'),
(11, 'Submitted',        'Eligibility Review', 1, 'Auto-passed eligibility check', '2026-05-01 09:01:00'),
(11, 'Eligibility Review','Officer Review',    2, 'Assigned for review', '2026-05-02 10:00:00'),
-- APP-2026-000012 (case_id=12): → Quality Review
(12, NULL,               'Submitted',          7, 'Application submitted', '2026-05-05 10:00:00'),
(12, 'Submitted',        'Eligibility Review', 1, 'Auto-passed eligibility check', '2026-05-05 10:01:00'),
(12, 'Eligibility Review','Officer Review',    2, 'Assigned for review', '2026-05-06 09:00:00'),
(12, 'Officer Review',   'Quality Review',     2, 'High risk — elevated to QR', '2026-05-12 14:00:00'),
-- APP-2026-000013 (case_id=13): → Quality Review (critical)
(13, NULL,               'Submitted',          6, 'Application submitted', '2026-05-10 08:30:00'),
(13, 'Submitted',        'Eligibility Review', 1, 'Auto-passed eligibility check', '2026-05-10 08:31:00'),
(13, 'Eligibility Review','Officer Review',    2, 'Assigned for review', '2026-05-11 09:00:00'),
(13, 'Officer Review',   'Quality Review',     2, 'Critical risk — mandatory QR', '2026-05-15 11:00:00'),
-- APP-2026-000014 (case_id=14): Denied → Appealed → Appeal Review
(14, NULL,               'Submitted',          7, 'Application submitted', '2026-04-01 09:00:00'),
(14, 'Submitted',        'Eligibility Review', 1, 'Auto-passed', '2026-04-01 09:01:00'),
(14, 'Eligibility Review','Officer Review',    2, 'Assigned', '2026-04-02 10:00:00'),
(14, 'Officer Review',   'Supervisor Review',  2, 'Forwarded to supervisor', '2026-04-10 15:00:00'),
(14, 'Supervisor Review','Denied',             4, 'Income slightly above threshold', '2026-04-15 10:00:00'),
(14, 'Denied',           'Appealed',           7, 'Citizen filed appeal', '2026-04-20 09:00:00'),
(14, 'Appealed',         'Appeal Review',      4, 'Appeal submitted', '2026-04-20 09:01:00');

-- ─── Risk scores for QA training cases ─────────────────
-- APP-2026-000011: low risk (score=20)
-- APP-2026-000012: high risk (score=60) — requires QR
-- APP-2026-000013: critical risk (score=85) — requires QR
-- APP-2026-000014: medium risk (score=35)
INSERT INTO risk_scores (application_id, risk_score, risk_level, factors_json, computed_at) VALUES
(11, 20, 'low',      '["Income below poverty threshold"]', '2026-05-01 09:01:00'),
(12, 60, 'high',     '["Multiple dependents","Disability status","Emergency urgency","Income below threshold"]', '2026-05-05 10:01:00'),
(13, 85, 'critical', '["Veteran status","Multiple disabilities","Extremely low income","Emergency urgency","Chronic illness factor"]', '2026-05-10 08:31:00'),
(14, 35, 'medium',   '["Income near threshold","Multiple dependents"]', '2026-04-01 09:01:00');

-- ─── SLA tracking for open cases ───────────────────────
-- Link to sla_rule_id=5 (Officer Review: 10 business days) and sla_rule_id=6 (Quality Review: 5 business days)
-- Assuming sla_rules IDs match the migration seed (Officer Review=5, Quality Review=6)
INSERT INTO sla_tracking (case_id, sla_rule_id, status_name, entered_at, deadline_at, warning_date, sla_status) VALUES
(11, 5, 'Officer Review',  '2026-05-02 10:00:00', '2026-05-16 10:00:00', '2026-05-14 10:00:00', 'on_track'),
(12, 6, 'Quality Review',  '2026-05-12 14:00:00', '2026-05-19 14:00:00', '2026-05-18 14:00:00', 'warning'),
(13, 6, 'Quality Review',  '2026-05-15 11:00:00', '2026-05-22 11:00:00', '2026-05-21 11:00:00', 'on_track'),
(14, 7, 'Appeal Review',   '2026-04-20 09:01:00', '2026-05-05 09:01:00', '2026-05-03 09:01:00', 'breached');

-- ─── Appeal record for APP-2026-000014 ─────────────────
INSERT INTO appeals (application_id, citizen_id, appeal_reason, status, appeal_window_expires_at, filed_at) VALUES
(14, 2,
 'I believe the income threshold used in my denial did not account for my recent reduction in hours and significant medical expenses. My net disposable income is well below the poverty line when medical costs are deducted. I am requesting a reassessment of my eligibility under the hardship provision.',
 'Pending', '2026-05-15 09:00:00', '2026-04-20 09:00:00');
