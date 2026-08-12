-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: fccsms
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `appeal_documents`
--

DROP TABLE IF EXISTS `appeal_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appeal_documents` (
  `doc_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `appeal_id` int(10) unsigned NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_filename` varchar(255) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `file_size_bytes` int(10) unsigned NOT NULL,
  `uploaded_by_user_id` int(10) unsigned NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`doc_id`),
  UNIQUE KEY `stored_filename` (`stored_filename`),
  KEY `appeal_id` (`appeal_id`),
  KEY `uploaded_by_user_id` (`uploaded_by_user_id`),
  CONSTRAINT `appeal_documents_ibfk_1` FOREIGN KEY (`appeal_id`) REFERENCES `appeals` (`appeal_id`) ON DELETE CASCADE,
  CONSTRAINT `appeal_documents_ibfk_2` FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appeal_documents`
--

LOCK TABLES `appeal_documents` WRITE;
/*!40000 ALTER TABLE `appeal_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `appeal_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appeals`
--

DROP TABLE IF EXISTS `appeals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appeals` (
  `appeal_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` int(10) unsigned NOT NULL,
  `citizen_id` int(10) unsigned NOT NULL,
  `appeal_reason` text NOT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `decision` enum('pending','upheld','denied') NOT NULL DEFAULT 'pending',
  `decision_notes` text DEFAULT NULL,
  `decided_by_user_id` int(10) unsigned DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `appeal_window_expires_at` datetime NOT NULL,
  PRIMARY KEY (`appeal_id`),
  KEY `citizen_id` (`citizen_id`),
  KEY `decided_by_user_id` (`decided_by_user_id`),
  KEY `idx_appeal_app` (`application_id`),
  CONSTRAINT `appeals_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`),
  CONSTRAINT `appeals_ibfk_2` FOREIGN KEY (`citizen_id`) REFERENCES `citizens` (`citizen_id`),
  CONSTRAINT `appeals_ibfk_3` FOREIGN KEY (`decided_by_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appeals`
--

LOCK TABLES `appeals` WRITE;
/*!40000 ALTER TABLE `appeals` DISABLE KEYS */;
/*!40000 ALTER TABLE `appeals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `applications`
--

DROP TABLE IF EXISTS `applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `applications` (
  `application_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_number` varchar(20) NOT NULL,
  `citizen_id` int(10) unsigned NOT NULL,
  `program_id` int(10) unsigned DEFAULT NULL,
  `income_range` varchar(50) DEFAULT NULL,
  `household_size` tinyint(4) DEFAULT NULL,
  `reason_for_request` text DEFAULT NULL,
  `urgency_level` enum('normal','high','emergency') NOT NULL DEFAULT 'normal',
  `preferred_contact` enum('email','phone','mail') NOT NULL DEFAULT 'email',
  `status` enum('Draft','Submitted','Eligibility Review','Pending Verification','Document Requested','Document Received','Officer Review','Quality Review','Supervisor Review','Approved','Denied','Closed','Appealed','Appeal Review','Final Disposition','Auto-Denied') NOT NULL DEFAULT 'Draft',
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `wizard_step_completed` tinyint(4) NOT NULL DEFAULT 0,
  `application_data_json` longtext DEFAULT NULL,
  `certification_checked` tinyint(1) NOT NULL DEFAULT 0,
  `certification_timestamp` datetime DEFAULT NULL,
  `preliminary_eligibility_result` enum('pending','passed','flagged','failed') NOT NULL DEFAULT 'pending',
  `annual_income` decimal(12,2) DEFAULT NULL,
  `employment_status` varchar(50) DEFAULT NULL,
  `monthly_expenses` decimal(12,2) DEFAULT NULL,
  `housing_cost_monthly` decimal(12,2) DEFAULT NULL,
  `num_dependents` tinyint(4) NOT NULL DEFAULT 0,
  `marital_status` varchar(50) DEFAULT NULL,
  `veteran_status` varchar(60) DEFAULT NULL,
  `disability_status` varchar(80) DEFAULT NULL,
  `wizard_started_at` datetime DEFAULT NULL,
  PRIMARY KEY (`application_id`),
  UNIQUE KEY `application_number` (`application_number`),
  KEY `citizen_id` (`citizen_id`),
  KEY `program_id` (`program_id`),
  CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`citizen_id`) REFERENCES `citizens` (`citizen_id`),
  CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `applications`
--

LOCK TABLES `applications` WRITE;
/*!40000 ALTER TABLE `applications` DISABLE KEYS */;
INSERT INTO `applications` VALUES (1,'APP-2026-000001',1,1,'$0-$15,000',2,'I am currently unemployed and struggling to provide food for my family. We have been without steady income for three months and need immediate assistance.','high','email','Approved','2026-01-10 09:00:00','127.0.0.1',0,NULL,0,NULL,'pending',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL),(2,'APP-2026-000002',1,2,'$15,001-$30,000',3,'My landlord has issued a 30-day eviction notice and I need housing assistance to find a new place. I have two children and need help urgently.','emergency','phone','Denied','2026-01-15 10:30:00','127.0.0.1',0,NULL,0,NULL,'pending',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL),(3,'APP-2026-000003',1,3,'$0-$15,000',2,'I have a chronic medical condition that requires ongoing treatment and medication. My insurance lapsed when I lost my job and I need medical aid coverage.','normal','email','Quality Review','2026-02-01 14:00:00','127.0.0.1',0,NULL,0,NULL,'pending',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL),(4,'APP-2026-000004',2,4,'$30,001-$45,000',1,'I am recently laid off from my manufacturing job and seeking employment services to help me retrain for a new career in technology. I have 10 years of work experience.','normal','mail','Submitted','2026-02-10 11:00:00','127.0.0.1',0,NULL,0,NULL,'pending',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL),(5,'APP-2026-000005',2,5,'$15,001-$30,000',3,'I am a single working parent with three children under age 10. I need child care assistance to continue working and maintain my current employment position.','high','email','Approved','2026-02-15 09:30:00','127.0.0.1',0,NULL,0,NULL,'pending',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL),(6,'APP-2026-000006',1,6,'$0-$15,000',4,'Our household energy bills have become unmanageable this winter. We have received shutoff notices from the utility company and need emergency energy assistance.','emergency','phone','Approved','2026-03-01 08:00:00','127.0.0.1',0,NULL,0,NULL,'pending',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL),(7,'APP-2026-000007',2,7,'$15,001-$30,000',2,'I do not have reliable transportation to get to work. My car broke down and repairs cost more than I can afford. Without transportation I may lose my job.','high','email','Supervisor Review','2026-03-05 13:00:00','127.0.0.1',0,NULL,0,NULL,'pending',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL),(8,'APP-2026-000008',1,1,'$0-$15,000',1,'As a senior living alone on a fixed income, I am having difficulty affording groceries each month. My social security only covers rent and basic utilities.','normal','mail','Approved','2026-03-10 10:00:00','127.0.0.1',0,NULL,0,NULL,'pending',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL),(9,'APP-2026-000009',2,2,'$30,001-$45,000',5,'Our family of five is living in an overcrowded apartment that poses health and safety risks. We need housing support to find adequate accommodation for our family.','high','email','Officer Review','2026-03-15 15:00:00','127.0.0.1',0,NULL,0,NULL,'pending',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL),(10,'APP-2026-000010',1,3,'$15,001-$30,000',3,'My youngest child has been diagnosed with a serious illness requiring specialist treatment. Our insurance does not cover the full cost of necessary medical procedures.','emergency','phone','Denied','2026-03-20 09:00:00','127.0.0.1',0,NULL,0,NULL,'pending',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL),(16,'APP-2026-000011',1,NULL,NULL,NULL,NULL,'normal','email','Draft','2026-06-02 12:07:48',NULL,3,'{\"step1\":{\"csrf_token\":\"9ca462f3102ae8d3df03e7cc7f4a6dcd378f3154975491b437fde2ed061980e0\",\"first_name\":\"John\",\"last_name\":\"Citizen\",\"dob\":\"1985-03-15\",\"ssn_last4\":\"1234\",\"email\":\"john@example.com\",\"phone\":\"(555) 123-4567\",\"street\":\"123 Main Street\",\"city\":\"Springfield\",\"state\":\"IL\",\"zip\":\"62701\"},\"step2\":{\"csrf_token\":\"9ca462f3102ae8d3df03e7cc7f4a6dcd378f3154975491b437fde2ed061980e0\",\"marital_status\":\"Married\",\"household_size\":\"2\",\"num_dependents\":\"1\",\"veteran_status\":\"Active Duty\",\"disability_status\":\"None\",\"housing_situation\":\"Renting\",\"housing_cost_monthly\":\"1500\",\"monthly_expenses\":\"2500\"},\"step3\":{\"csrf_token\":\"9ca462f3102ae8d3df03e7cc7f4a6dcd378f3154975491b437fde2ed061980e0\",\"employment_status\":\"Full-Time\",\"employer_name\":\"\",\"annual_income\":\"25000\",\"income_source\":\"Employment\",\"pay_frequency\":\"Bi-Weekly\",\"total_assets\":\"5000\",\"receiving_benefits\":\"0\",\"current_benefits\":\"\"}}',0,NULL,'pending',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-06-02 12:07:48');
/*!40000 ALTER TABLE `applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `log_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `role` varchar(20) DEFAULT NULL,
  `action_type` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(10) unsigned DEFAULT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_value`)),
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_value`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reason_for_change` varchar(500) DEFAULT NULL,
  `session_id` varchar(128) DEFAULT NULL,
  `request_uri` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB AUTO_INCREMENT=116 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,'admin@fccsms.gov','admin','Login','user',1,NULL,NULL,'127.0.0.1',NULL,'2026-01-09 08:00:00',NULL,NULL,NULL),(2,6,'john@example.com','citizen','Registration','user',6,NULL,'{\"email\":\"john@example.com\"}','127.0.0.1',NULL,'2026-01-09 09:00:00',NULL,NULL,NULL),(3,6,'john@example.com','citizen','Application Submitted','application',1,NULL,'{\"application_number\":\"APP-2026-000001\"}','127.0.0.1',NULL,'2026-01-10 09:00:00',NULL,NULL,NULL),(4,2,'officer1@fccsms.gov','officer','Case Status Updated','case',1,'{\"status\":\"Submitted\"}','{\"status\":\"In Review\"}','127.0.0.1',NULL,'2026-01-12 10:00:00',NULL,NULL,NULL),(5,4,'supervisor@fccsms.gov','supervisor','Case Approved','case',1,'{\"status\":\"Recommended for Approval\"}','{\"status\":\"Approved\"}','127.0.0.1',NULL,'2026-01-20 10:00:00',NULL,NULL,NULL),(6,6,'john@example.com','citizen','Application Submitted','application',2,NULL,'{\"application_number\":\"APP-2026-000002\"}','127.0.0.1',NULL,'2026-01-15 10:30:00',NULL,NULL,NULL),(7,4,'supervisor@fccsms.gov','supervisor','Case Denied','case',2,'{\"status\":\"Recommended for Denial\"}','{\"status\":\"Denied\"}','127.0.0.1',NULL,'2026-01-25 14:00:00',NULL,NULL,NULL),(8,1,'admin@fccsms.gov','admin','User Created','user',2,NULL,'{\"email\":\"officer1@fccsms.gov\",\"role\":\"officer\"}','127.0.0.1',NULL,'2026-01-08 10:00:00',NULL,NULL,NULL),(9,1,'admin@fccsms.gov','admin','Failed Login','user',1,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-01 23:52:10',NULL,NULL,NULL),(10,1,'admin@fccsms.gov','admin','Failed Login','user',1,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-01 23:52:35',NULL,NULL,NULL),(11,2,'officer1@fccsms.gov','officer','Failed Login','user',2,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-01 23:52:56',NULL,NULL,NULL),(12,1,'admin@fccsms.gov','admin','Login','user',1,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-01 23:57:47',NULL,NULL,NULL),(13,1,'admin@fccsms.gov','admin','Case Assigned','case',1,NULL,'{\"officer_id\":3}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-01 23:58:24',NULL,NULL,NULL),(14,1,'admin@fccsms.gov','admin','Logout','user',1,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-01 23:59:08',NULL,NULL,NULL),(15,2,'officer1@fccsms.gov','officer','Login','user',2,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-01 23:59:40',NULL,NULL,NULL),(16,2,'officer1@fccsms.gov','officer','Case Status Updated','case',10,'{\"status\":\"Submitted\"}','{\"status\":\"In Review\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 00:00:27',NULL,NULL,NULL),(17,2,'officer1@fccsms.gov','officer','Case Status Updated','case',10,'{\"status\":\"In Review\"}','{\"status\":\"Recommended for Approval\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 00:00:36',NULL,NULL,NULL),(18,2,'officer1@fccsms.gov','officer','Logout','user',2,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 00:21:44',NULL,NULL,NULL),(19,6,'john@example.com','citizen','Login','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 00:22:02',NULL,NULL,NULL),(20,6,'john@example.com','citizen','Login','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 11:11:16',NULL,NULL,NULL),(21,6,'john@example.com','citizen','Document Uploaded','application',10,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 11:11:34',NULL,NULL,NULL),(22,6,'john@example.com','citizen','Logout','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 11:13:20',NULL,NULL,NULL),(23,6,'john@example.com','citizen','Login','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 11:13:28',NULL,NULL,NULL),(24,6,'john@example.com','citizen','Logout','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 11:21:01',NULL,NULL,NULL),(25,2,'officer1@fccsms.gov','officer','Login','user',2,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 11:21:05',NULL,NULL,NULL),(26,2,'officer1@fccsms.gov','officer','Case Status Updated','case',10,'{\"status\":\"Supervisor Review\"}','{\"status\":\"Denied\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 11:21:15',NULL,NULL,NULL),(27,2,'officer1@fccsms.gov','officer','Case Status Updated','case',8,'{\"status\":\"Officer Review\"}','{\"status\":\"Quality Review\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 11:21:27',NULL,NULL,NULL),(28,2,'officer1@fccsms.gov','officer','Case Status Updated','case',8,'{\"status\":\"Quality Review\"}','{\"status\":\"Officer Review\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 11:21:32',NULL,NULL,NULL),(29,2,'officer1@fccsms.gov','officer','Case Status Updated','case',8,'{\"status\":\"Officer Review\"}','{\"status\":\"Quality Review\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 11:21:35',NULL,NULL,NULL),(30,6,'john@example.com','citizen','Login','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 11:31:20',NULL,NULL,NULL),(31,2,'officer1@fccsms.gov','officer','Logout','user',2,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 11:31:51',NULL,NULL,NULL),(32,6,'john@example.com','citizen','Login','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 11:32:08',NULL,NULL,NULL),(33,6,'john@example.com','citizen','Logout','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 11:32:30',NULL,NULL,NULL),(34,1,'admin@fccsms.gov','admin','Failed Login','user',1,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 11:33:48',NULL,NULL,NULL),(35,2,'officer1@fccsms.gov','officer','Login','user',2,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 11:34:14',NULL,NULL,NULL),(36,2,'officer1@fccsms.gov','officer','Logout','user',2,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 11:36:57',NULL,NULL,NULL),(37,1,'admin@fccsms.gov','admin','Login','user',1,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 11:37:12',NULL,NULL,NULL),(38,1,'admin@fccsms.gov','admin','training_defect_enabled','training_defect',1,NULL,'{\"defect_key\":\"missing_phone_validation\",\"is_active\":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 11:37:35',NULL,NULL,NULL),(39,1,'admin@fccsms.gov','admin','training_defect_enabled','training_defect',2,NULL,'{\"defect_key\":\"duplicate_app_limit_bypass\",\"is_active\":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 11:37:44',NULL,NULL,NULL),(40,1,'admin@fccsms.gov','admin','training_defect_disabled','training_defect',2,NULL,'{\"defect_key\":\"duplicate_app_limit_bypass\",\"is_active\":0}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 11:37:49',NULL,NULL,NULL),(41,1,'admin@fccsms.gov','admin','training_defect_disabled','training_defect',1,NULL,'{\"defect_key\":\"missing_phone_validation\",\"is_active\":0}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 11:37:52',NULL,NULL,NULL),(42,1,'admin@fccsms.gov','admin','Logout','user',1,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 11:39:22',NULL,NULL,NULL),(43,6,'john@example.com','citizen','Login','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 11:39:25',NULL,NULL,NULL),(44,6,'john@example.com','citizen','Logout','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 12:04:53',NULL,NULL,NULL),(45,6,'john@example.com','citizen','Login','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 12:04:56',NULL,NULL,NULL),(46,6,'john@example.com','citizen','Logout','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 12:37:54',NULL,NULL,NULL),(47,2,'officer1@fccsms.gov','officer','Login','user',2,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 12:37:57',NULL,NULL,NULL),(48,2,'officer1@fccsms.gov','officer','Case Status Updated','case',8,'{\"status\":\"Quality Review\"}','{\"status\":\"Supervisor Review\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 12:38:57',NULL,NULL,NULL),(49,2,'officer1@fccsms.gov','officer','Case Status Updated','case',8,'{\"status\":\"Supervisor Review\"}','{\"status\":\"Approved\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 12:38:59',NULL,NULL,NULL),(50,6,'john@example.com','citizen','Logout','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 16:31:21',NULL,NULL,NULL),(51,6,'john@example.com','citizen','Login','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 16:32:01',NULL,NULL,NULL),(52,6,'john@example.com','citizen','Logout','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 16:32:13',NULL,NULL,NULL),(53,2,'officer1@fccsms.gov','officer','Login','user',2,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 16:32:20',NULL,NULL,NULL),(54,2,'officer1@fccsms.gov','officer','Case Status Updated','case',6,'{\"status\":\"Pending Verification\"}','{\"status\":\"Officer Review\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 16:32:39',NULL,NULL,NULL),(55,2,'officer1@fccsms.gov','officer','Case Status Updated','case',6,'{\"status\":\"Officer Review\"}','{\"status\":\"Quality Review\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 16:32:43',NULL,NULL,NULL),(56,2,'officer1@fccsms.gov','officer','Case Status Updated','case',6,'{\"status\":\"Quality Review\"}','{\"status\":\"Officer Review\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 16:32:50',NULL,NULL,NULL),(57,2,'officer1@fccsms.gov','officer','Logout','user',2,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 16:33:07',NULL,NULL,NULL),(58,1,'admin@fccsms.gov','admin','Login','user',1,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 16:33:16',NULL,NULL,NULL),(59,1,'admin@fccsms.gov','admin','training_defect_enabled','training_defect',1,NULL,'{\"defect_key\":\"missing_phone_validation\",\"is_active\":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 16:33:34',NULL,NULL,NULL),(60,1,'admin@fccsms.gov','admin','training_defect_disabled','training_defect',1,NULL,'{\"defect_key\":\"missing_phone_validation\",\"is_active\":0}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 16:33:46',NULL,NULL,NULL),(61,1,'admin@fccsms.gov','admin','Logout','user',1,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 16:34:41',NULL,NULL,NULL),(62,6,'john@example.com','citizen','Login','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 16:34:44',NULL,NULL,NULL),(63,6,'john@example.com','citizen','Logout','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 17:27:03',NULL,NULL,NULL),(64,6,'john@example.com','citizen','Login','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 17:27:25',NULL,NULL,NULL),(65,6,'john@example.com','citizen','Logout','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 17:27:41',NULL,NULL,NULL),(66,2,'officer1@fccsms.gov','officer','Login','user',2,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 17:27:44',NULL,NULL,NULL),(67,2,'officer1@fccsms.gov','officer','Logout','user',2,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 17:28:41',NULL,NULL,NULL),(68,6,'john@example.com','citizen','Login','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 17:41:05',NULL,NULL,NULL),(69,6,'john@example.com','citizen','Logout','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 17:41:41',NULL,NULL,NULL),(70,5,'auditor@fccsms.gov','auditor','Login','user',5,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 17:43:24',NULL,NULL,NULL),(71,5,'auditor@fccsms.gov','auditor','Logout','user',5,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 17:43:28',NULL,NULL,NULL),(72,2,'officer1@fccsms.gov','officer','Logout','user',2,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-02 17:48:35',NULL,NULL,NULL),(73,NULL,'admin',NULL,'Failed Login','user',NULL,NULL,'{\"email\":\"admin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-04 17:55:12',NULL,NULL,NULL),(74,5,'auditor@fccsms.gov','auditor','Login','user',5,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-04 17:55:20',NULL,NULL,NULL),(75,5,'auditor@fccsms.gov','auditor','Logout','user',5,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-04 17:55:33',NULL,NULL,NULL),(76,6,'john@example.com','citizen','Login','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-04 17:55:36',NULL,NULL,NULL),(77,6,'john@example.com','citizen','Logout','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-04 17:55:50',NULL,NULL,NULL),(78,2,'officer1@fccsms.gov','officer','Login','user',2,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-04 17:55:53',NULL,NULL,NULL),(79,6,'john@example.com','citizen','Login','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-05 23:09:01',NULL,NULL,NULL),(80,6,'john@example.com','citizen','Logout','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-05 23:09:26',NULL,NULL,NULL),(81,2,'officer1@fccsms.gov','officer','Login','user',2,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-05 23:09:30',NULL,NULL,NULL),(82,2,'officer1@fccsms.gov','officer','Case Status Updated','case',6,'{\"status\":\"Officer Review\"}','{\"status\":\"Quality Review\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-05 23:09:46',NULL,NULL,NULL),(83,2,'officer1@fccsms.gov','officer','Case Status Updated','case',6,'{\"status\":\"Quality Review\"}','{\"status\":\"Supervisor Review\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-05 23:09:53',NULL,NULL,NULL),(84,2,'officer1@fccsms.gov','officer','Case Status Updated','case',6,'{\"status\":\"Supervisor Review\"}','{\"status\":\"Approved\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-05 23:10:00',NULL,NULL,NULL),(85,2,'officer1@fccsms.gov','officer','Logout','user',2,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-05 23:10:13',NULL,NULL,NULL),(86,4,'supervisor@fccsms.gov','supervisor','Login','user',4,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-05 23:10:18',NULL,NULL,NULL),(87,4,'supervisor@fccsms.gov','supervisor','Supervisor Decision','case',5,'{\"status\":\"Supervisor Review\"}','{\"status\":\"Approved\",\"comments\":\"vnv\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-05 23:10:51',NULL,NULL,NULL),(88,4,'supervisor@fccsms.gov','supervisor','Logout','user',4,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-05 23:11:04',NULL,NULL,NULL),(89,1,'admin@fccsms.gov','admin','Login','user',1,NULL,NULL,'::1','PostmanRuntime/7.37.0','2026-06-08 17:14:17',NULL,NULL,NULL),(90,1,'admin@fccsms.gov','admin','Login','user',1,NULL,NULL,'::1','PostmanRuntime/7.37.0','2026-06-08 17:52:08',NULL,NULL,NULL),(91,1,'admin@fccsms.gov','admin','Login','user',1,NULL,NULL,'::1','PostmanRuntime/7.37.0','2026-06-08 17:55:31',NULL,NULL,NULL),(92,1,'admin@fccsms.gov','admin','Login','user',1,NULL,NULL,'::1','PostmanRuntime/7.37.0','2026-06-08 17:57:47',NULL,NULL,NULL),(93,NULL,'admin@fccsms.gov',NULL,'Failed Login','user',NULL,NULL,NULL,'::1','PostmanRuntime/7.37.0','2026-06-08 17:58:36',NULL,NULL,NULL),(94,1,'admin@fccsms.gov','admin','Login','user',1,NULL,NULL,'::1','PostmanRuntime/7.37.0','2026-06-08 17:58:40',NULL,NULL,NULL),(95,9,'jane.smith@email.com','citizen','Registration','user',9,NULL,NULL,'::1','PostmanRuntime/7.37.0','2026-06-08 18:00:36',NULL,NULL,NULL),(96,1,'admin@fccsms.gov','admin','Login','user',1,NULL,NULL,'::1','PostmanRuntime/7.37.0','2026-06-08 18:02:20',NULL,NULL,NULL),(97,1,'admin@fccsms.gov','admin','Login','user',1,NULL,NULL,'::1','PostmanRuntime/7.37.0','2026-06-08 18:08:32',NULL,NULL,NULL),(98,1,'admin@fccsms.gov','admin','Login','user',1,NULL,NULL,'::1','PostmanRuntime/7.37.0','2026-06-08 18:12:55',NULL,NULL,NULL),(99,6,'john@example.com','citizen','Login','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-08 18:37:35',NULL,NULL,NULL),(100,6,'john@example.com','citizen','Logout','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-08 18:37:43',NULL,NULL,NULL),(101,4,'supervisor@fccsms.gov','supervisor','Login','user',4,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-08 18:37:46',NULL,NULL,NULL),(102,1,'admin@fccsms.gov','admin','Login','user',1,NULL,NULL,'::1','PostmanRuntime/7.37.0','2026-06-08 18:39:55',NULL,NULL,NULL),(103,1,'admin@fccsms.gov','admin','Login','user',1,NULL,NULL,'::1','PostmanRuntime/7.37.0','2026-06-08 18:40:05',NULL,NULL,NULL),(104,10,'jane.smh@email.com','citizen','Registration','user',10,NULL,NULL,'::1','PostmanRuntime/7.37.0','2026-06-08 18:40:26',NULL,NULL,NULL),(105,NULL,'john',NULL,'Failed Login','user',NULL,NULL,'{\"email\":\"john\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-07-07 14:53:34',NULL,NULL,NULL),(106,NULL,'admin',NULL,'Failed Login','user',NULL,NULL,'{\"email\":\"admin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-07-07 14:53:36',NULL,NULL,NULL),(107,6,'john@example.com','citizen','Login','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-07-07 14:53:44',NULL,NULL,NULL),(108,6,'john@example.com','citizen','Logout','user',6,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-07-07 14:53:54',NULL,NULL,NULL),(109,5,'auditor@fccsms.gov','auditor','Login','user',5,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-07-07 14:54:02',NULL,NULL,NULL),(110,5,'auditor@fccsms.gov','auditor','Logout','user',5,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-07-07 14:55:00',NULL,NULL,NULL),(111,2,'officer1@fccsms.gov','officer','Login','user',2,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-07-07 14:55:02',NULL,NULL,NULL),(112,NULL,'student@qa.test',NULL,'Failed Login','user',NULL,NULL,NULL,'::1','PostmanRuntime/7.37.3','2026-08-05 13:42:28',NULL,NULL,NULL),(113,NULL,'student@qa.test',NULL,'Failed Login','user',NULL,NULL,NULL,'::1','PostmanRuntime/7.37.3','2026-08-05 13:45:25',NULL,NULL,NULL),(114,NULL,'admin@qa.test',NULL,'Failed Login','user',NULL,NULL,NULL,'::1','PostmanRuntime/7.37.3','2026-08-05 13:49:36',NULL,NULL,NULL),(115,NULL,'student@qa.test',NULL,'Failed Login','user',NULL,NULL,NULL,'::1','PostmanRuntime/7.37.3','2026-08-05 13:49:42',NULL,NULL,NULL);
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER prevent_audit_update
BEFORE UPDATE ON audit_logs
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit log records are immutable';
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER prevent_audit_delete
BEFORE DELETE ON audit_logs
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit log records are immutable';
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `case_assignments`
--

DROP TABLE IF EXISTS `case_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `case_assignments` (
  `assignment_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `case_id` int(10) unsigned NOT NULL,
  `assigned_to_user_id` int(10) unsigned NOT NULL,
  `assigned_by_user_id` int(10) unsigned DEFAULT NULL,
  `assignment_method` varchar(30) NOT NULL DEFAULT 'manual',
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `unassigned_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`assignment_id`),
  KEY `assigned_by_user_id` (`assigned_by_user_id`),
  KEY `idx_ca_case` (`case_id`),
  KEY `idx_ca_officer` (`assigned_to_user_id`),
  CONSTRAINT `case_assignments_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `cases` (`case_id`),
  CONSTRAINT `case_assignments_ibfk_2` FOREIGN KEY (`assigned_to_user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `case_assignments_ibfk_3` FOREIGN KEY (`assigned_by_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `case_assignments`
--

LOCK TABLES `case_assignments` WRITE;
/*!40000 ALTER TABLE `case_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `case_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `case_notes`
--

DROP TABLE IF EXISTS `case_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `case_notes` (
  `note_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `case_id` int(10) unsigned NOT NULL,
  `officer_user_id` int(10) unsigned NOT NULL,
  `note_text` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`note_id`),
  KEY `case_id` (`case_id`),
  KEY `officer_user_id` (`officer_user_id`),
  CONSTRAINT `case_notes_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `cases` (`case_id`),
  CONSTRAINT `case_notes_ibfk_2` FOREIGN KEY (`officer_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `case_notes`
--

LOCK TABLES `case_notes` WRITE;
/*!40000 ALTER TABLE `case_notes` DISABLE KEYS */;
INSERT INTO `case_notes` VALUES (1,1,2,'Verified income documents. All submitted materials appear authentic.','2026-01-13 10:00:00'),(2,1,2,'Contacted citizen for additional verification. Confirmed household size.','2026-01-15 14:00:00'),(3,2,2,'Requested bank statements but citizen has not responded.','2026-01-19 09:00:00'),(4,3,2,'Initial review shows need for additional medical documentation.','2026-02-06 11:00:00'),(5,5,2,'Citizen provided proof of employment and child care enrollment.','2026-02-20 10:00:00'),(6,6,2,'Sent document request for utility shutoff notices and income verification.','2026-03-02 09:00:00'),(7,8,2,'Case returned ÔÇö need to follow up on income verification documents.','2026-03-12 12:00:00');
/*!40000 ALTER TABLE `case_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `case_status_history`
--

DROP TABLE IF EXISTS `case_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `case_status_history` (
  `history_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `case_id` int(10) unsigned NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `changed_by_user_id` int(10) unsigned NOT NULL,
  `comments` text DEFAULT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`history_id`),
  KEY `case_id` (`case_id`),
  KEY `changed_by_user_id` (`changed_by_user_id`),
  CONSTRAINT `case_status_history_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `cases` (`case_id`),
  CONSTRAINT `case_status_history_ibfk_2` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `case_status_history`
--

LOCK TABLES `case_status_history` WRITE;
/*!40000 ALTER TABLE `case_status_history` DISABLE KEYS */;
INSERT INTO `case_status_history` VALUES (1,1,NULL,'Submitted',6,'Application submitted by citizen','2026-01-10 09:00:00'),(2,1,'Submitted','In Review',2,'Assigned and started review','2026-01-12 10:00:00'),(3,1,'In Review','Recommended for Approval',2,'All criteria met','2026-01-18 14:00:00'),(4,1,'Recommended for Approval','Approved',4,'Approved by supervisor','2026-01-20 10:00:00'),(5,2,NULL,'Submitted',7,'Application submitted by citizen','2026-01-15 10:30:00'),(6,2,'Submitted','In Review',2,'Under review','2026-01-17 09:00:00'),(7,2,'In Review','Recommended for Denial',2,'Missing documentation','2026-01-23 11:00:00'),(8,2,'Recommended for Denial','Denied',4,'Denied by supervisor','2026-05-28 16:38:33'),(9,3,NULL,'Submitted',6,'Application submitted','2026-02-01 14:00:00'),(10,3,'Submitted','In Review',2,'Review started','2026-02-05 09:00:00'),(11,5,NULL,'Submitted',7,'Application submitted','2026-02-15 09:30:00'),(12,5,'Submitted','In Review',2,'Assigned to officer','2026-02-18 10:00:00'),(13,5,'In Review','Recommended for Approval',2,'Strong case, recommend approval','2026-02-25 14:00:00'),(14,10,'Submitted','In Review',2,'','2026-06-02 00:00:27'),(15,10,'In Review','Recommended for Approval',2,'','2026-06-02 00:00:36'),(16,10,'Supervisor Review','Denied',2,'','2026-06-02 11:21:15'),(17,8,'Officer Review','Quality Review',2,'','2026-06-02 11:21:27'),(18,8,'Quality Review','Officer Review',2,'','2026-06-02 11:21:32'),(19,8,'Officer Review','Quality Review',2,'','2026-06-02 11:21:35'),(20,8,'Quality Review','Supervisor Review',2,'','2026-06-02 12:38:57'),(21,8,'Supervisor Review','Approved',2,'','2026-06-02 12:38:59'),(22,6,'Pending Verification','Officer Review',2,'','2026-06-02 16:32:39'),(23,6,'Officer Review','Quality Review',2,'','2026-06-02 16:32:43'),(24,6,'Quality Review','Officer Review',2,'','2026-06-02 16:32:50'),(26,3,'Officer Review','Quality Review',2,'Review complete – forwarding for quality check','2026-06-02 16:38:53'),(27,6,'Officer Review','Quality Review',2,'','2026-06-05 23:09:46'),(28,6,'Quality Review','Supervisor Review',2,'','2026-06-05 23:09:53'),(29,6,'Supervisor Review','Approved',2,'','2026-06-05 23:10:00'),(30,5,'Supervisor Review','Approved',4,'vnv','2026-06-05 23:10:51');
/*!40000 ALTER TABLE `case_status_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cases`
--

DROP TABLE IF EXISTS `cases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cases` (
  `case_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` int(10) unsigned NOT NULL,
  `assigned_officer_id` int(10) unsigned DEFAULT NULL,
  `status` enum('Draft','Submitted','Eligibility Review','Pending Verification','Document Requested','Document Received','Officer Review','Quality Review','Supervisor Review','Approved','Denied','Closed','Appealed','Appeal Review','Final Disposition','Auto-Denied') NOT NULL DEFAULT 'Submitted',
  `decision_comments` text DEFAULT NULL,
  `decided_by` int(10) unsigned DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`case_id`),
  UNIQUE KEY `application_id` (`application_id`),
  KEY `assigned_officer_id` (`assigned_officer_id`),
  KEY `decided_by` (`decided_by`),
  CONSTRAINT `cases_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`),
  CONSTRAINT `cases_ibfk_2` FOREIGN KEY (`assigned_officer_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `cases_ibfk_3` FOREIGN KEY (`decided_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cases`
--

LOCK TABLES `cases` WRITE;
/*!40000 ALTER TABLE `cases` DISABLE KEYS */;
INSERT INTO `cases` VALUES (1,1,3,'Approved','Application meets all requirements. Citizen verified.',4,'2026-01-20 10:00:00','2026-06-01 23:43:07','2026-06-01 23:58:24'),(2,2,2,'Denied','Insufficient documentation provided. Income not verified.',4,'2026-05-28 16:38:33','2026-06-01 23:43:07','2026-06-02 16:38:33'),(3,3,2,'Quality Review',NULL,NULL,NULL,'2026-06-01 23:43:07','2026-06-02 16:38:53'),(4,4,3,'Submitted',NULL,NULL,NULL,'2026-06-01 23:43:07','2026-06-01 23:43:07'),(5,5,2,'Approved',NULL,NULL,NULL,'2026-06-01 23:43:07','2026-06-05 23:10:51'),(6,6,2,'Approved',NULL,NULL,NULL,'2026-06-01 23:43:07','2026-06-05 23:10:00'),(7,7,3,'Supervisor Review',NULL,NULL,NULL,'2026-06-01 23:43:07','2026-06-02 00:36:30'),(8,8,2,'Approved','Missing income verification documents. Please request from citizen.',4,'2026-03-12 11:00:00','2026-06-01 23:43:07','2026-06-02 12:38:59'),(9,9,3,'Officer Review',NULL,NULL,NULL,'2026-06-01 23:43:07','2026-06-02 00:36:30'),(10,10,2,'Denied',NULL,NULL,NULL,'2026-06-01 23:43:07','2026-06-02 11:21:15');
/*!40000 ALTER TABLE `cases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `citizens`
--

DROP TABLE IF EXISTS `citizens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `citizens` (
  `citizen_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `date_of_birth` date NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address_street` varchar(255) NOT NULL,
  `address_city` varchar(100) NOT NULL,
  `address_state` char(2) NOT NULL,
  `address_zip` varchar(10) NOT NULL,
  `ssn_last4` char(4) NOT NULL,
  PRIMARY KEY (`citizen_id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `citizens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `citizens`
--

LOCK TABLES `citizens` WRITE;
/*!40000 ALTER TABLE `citizens` DISABLE KEYS */;
INSERT INTO `citizens` VALUES (1,6,'1985-03-15','(555) 123-4567','123 Main Street','Springfield','IL','62701','1234'),(2,7,'1990-07-22','(555) 987-6543','456 Oak Avenue','Springfield','IL','62702','5678');
/*!40000 ALTER TABLE `citizens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `document_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` int(10) unsigned NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_filename` varchar(255) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `file_size_bytes` int(10) unsigned NOT NULL,
  `uploaded_by_user_id` int(10) unsigned NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `document_category` varchar(60) DEFAULT NULL,
  PRIMARY KEY (`document_id`),
  UNIQUE KEY `stored_filename` (`stored_filename`),
  KEY `application_id` (`application_id`),
  KEY `uploaded_by_user_id` (`uploaded_by_user_id`),
  CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`),
  CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
INSERT INTO `documents` VALUES (1,10,'Receipt A1T-20260601-0015 - Agile1Tech Payment System.pdf','553e13535d2b98a27ad2385fd6335684.pdf','application/pdf',89109,6,'2026-06-02 11:11:34',NULL);
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eligibility_results`
--

DROP TABLE IF EXISTS `eligibility_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eligibility_results` (
  `result_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` int(10) unsigned NOT NULL,
  `rule_name` varchar(100) NOT NULL,
  `rule_result` enum('passed','flagged','failed') NOT NULL,
  `reason_text` text DEFAULT NULL,
  `evaluated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`result_id`),
  KEY `idx_er_app` (`application_id`),
  CONSTRAINT `eligibility_results_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eligibility_results`
--

LOCK TABLES `eligibility_results` WRITE;
/*!40000 ALTER TABLE `eligibility_results` DISABLE KEYS */;
/*!40000 ALTER TABLE `eligibility_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `notification_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `recipient_user_id` int(10) unsigned NOT NULL,
  `trigger_event` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `notification_type` enum('application_submitted','document_requested','document_received','eligibility_passed','eligibility_denied','case_assigned','case_approved','case_denied','appeal_submitted','appeal_upheld','appeal_denied','sla_warning','sla_breach','general') NOT NULL DEFAULT 'general',
  PRIMARY KEY (`notification_id`),
  KEY `recipient_user_id` (`recipient_user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,6,'Application Submitted','Your application APP-2026-000001 has been successfully submitted. Track status on your dashboard.',1,'2026-01-10 09:01:00','general'),(2,6,'Case Status Changed','Your application APP-2026-000001 status has changed to: In Review.',1,'2026-01-12 10:01:00','general'),(3,6,'Case Approved','Congratulations! Your application APP-2026-000001 has been Approved. A case worker will contact you shortly.',1,'2026-01-20 10:01:00','general'),(4,6,'Application Submitted','Your application APP-2026-000002 has been successfully submitted.',1,'2026-01-15 10:31:00','general'),(5,6,'Case Denied','Your application APP-2026-000002 has been Denied. Reason: Insufficient documentation. You may appeal within 30 days.',1,'2026-01-25 14:01:00','general'),(6,7,'Application Submitted','Your application APP-2026-000004 has been successfully submitted.',0,'2026-02-10 11:01:00','general'),(7,7,'Application Submitted','Your application APP-2026-000005 has been successfully submitted.',0,'2026-02-15 09:31:00','general'),(8,2,'Case Assigned','A new case (APP-2026-000003) has been assigned to you for review.',1,'2026-02-05 09:01:00','general'),(9,2,'Case Returned for Review','Case APP-2026-000008 has been returned to you for additional review. Please check supervisor comments.',1,'2026-03-12 11:01:00','general'),(10,3,'Case Assigned','Case for application APP-2026-000001 has been assigned to you.',0,'2026-06-01 23:58:24','general'),(11,6,'Case Status Changed','Your application APP-2026-000010 status changed to: In Review.',1,'2026-06-02 00:00:27','general'),(12,6,'Case Status Changed','Your application APP-2026-000010 status changed to: Recommended for Approval.',1,'2026-06-02 00:00:36','general'),(13,6,'Status Changed','Your application APP-2026-000010 is now: Denied. Your application has been denied. You may appeal within 30 days.',1,'2026-06-02 11:21:15','general'),(14,6,'Status Changed','Your application APP-2026-000008 is now: Quality Review. Your case is undergoing a quality check.',1,'2026-06-02 11:21:27','general'),(15,6,'Status Changed','Your application APP-2026-000008 is now: Officer Review. A case officer is reviewing your application.',1,'2026-06-02 11:21:32','general'),(16,6,'Status Changed','Your application APP-2026-000008 is now: Quality Review. Your case is undergoing a quality check.',1,'2026-06-02 11:21:35','general'),(17,6,'Status Changed','Your application APP-2026-000008 is now: Supervisor Review. Your case is awaiting a final decision from a supervisor.',1,'2026-06-02 12:38:57','general'),(18,6,'Status Changed','Your application APP-2026-000008 is now: Approved. Congratulations — your application has been approved!',1,'2026-06-02 12:38:59','general'),(19,6,'Status Changed','Your application APP-2026-000006 is now: Officer Review. A case officer is reviewing your application.',1,'2026-06-02 16:32:39','general'),(20,6,'Status Changed','Your application APP-2026-000006 is now: Quality Review. Your case is undergoing a quality check.',1,'2026-06-02 16:32:43','general'),(21,6,'Status Changed','Your application APP-2026-000006 is now: Officer Review. A case officer is reviewing your application.',1,'2026-06-02 16:32:50','general'),(22,6,'Status Changed','Your application APP-2026-000006 is now: Quality Review. Your case is undergoing a quality check.',0,'2026-06-05 23:09:46','general'),(23,8,'New QR Case','Case APP-2026-000006 is ready for quality review.',0,'2026-06-05 23:09:46','case_assigned'),(24,6,'Status Changed','Your application APP-2026-000006 is now: Supervisor Review. Your case is awaiting a final decision from a supervisor.',0,'2026-06-05 23:09:53','general'),(25,6,'Status Changed','Your application APP-2026-000006 is now: Approved. Congratulations — your application has been approved!',0,'2026-06-05 23:10:00','general'),(26,7,'Case Approved','Congratulations! Your application APP-2026-000005 has been Approved. A representative will contact you with next steps.',0,'2026-06-05 23:10:51','case_approved');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `token_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`token_id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `programs`
--

DROP TABLE IF EXISTS `programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `programs` (
  `program_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `program_name` varchar(100) NOT NULL,
  `program_code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `eligibility_questions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`eligibility_questions`)),
  PRIMARY KEY (`program_id`),
  UNIQUE KEY `program_code` (`program_code`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `programs`
--

LOCK TABLES `programs` WRITE;
/*!40000 ALTER TABLE `programs` DISABLE KEYS */;
INSERT INTO `programs` VALUES (1,'Food Assistance','FOOD','Nutritional support programs',1,NULL),(2,'Housing Support','HOUS','Rental and housing assistance',1,NULL),(3,'Medical Aid','MEDI','Medical coverage and assistance',1,NULL),(4,'Employment Services','EMPL','Job training and placement services',1,NULL),(5,'Child Care Subsidy','CHLD','Subsidized child care for working parents',1,NULL),(6,'Energy Assistance','ENRG','Utility and heating cost assistance',1,NULL),(7,'Transportation Aid','TRAN','Transportation support services',1,NULL);
/*!40000 ALTER TABLE `programs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quality_review_actions`
--

DROP TABLE IF EXISTS `quality_review_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quality_review_actions` (
  `action_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `case_id` int(10) unsigned NOT NULL,
  `reviewer_user_id` int(10) unsigned NOT NULL,
  `action_type` enum('passed_to_supervisor','returned_to_officer','flagged_with_issues','doc_marked_complete','doc_marked_incomplete') NOT NULL,
  `notes` text DEFAULT NULL,
  `action_timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`action_id`),
  KEY `reviewer_user_id` (`reviewer_user_id`),
  KEY `idx_qra_case` (`case_id`),
  CONSTRAINT `quality_review_actions_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `cases` (`case_id`),
  CONSTRAINT `quality_review_actions_ibfk_2` FOREIGN KEY (`reviewer_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quality_review_actions`
--

LOCK TABLES `quality_review_actions` WRITE;
/*!40000 ALTER TABLE `quality_review_actions` DISABLE KEYS */;
/*!40000 ALTER TABLE `quality_review_actions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `risk_scores`
--

DROP TABLE IF EXISTS `risk_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `risk_scores` (
  `score_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` int(10) unsigned NOT NULL,
  `total_score` int(11) NOT NULL DEFAULT 0,
  `risk_level` enum('low','medium','high','critical') NOT NULL DEFAULT 'low',
  `score_breakdown_json` longtext DEFAULT NULL,
  `computed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `recomputed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`score_id`),
  UNIQUE KEY `application_id` (`application_id`),
  CONSTRAINT `risk_scores_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `risk_scores`
--

LOCK TABLES `risk_scores` WRITE;
/*!40000 ALTER TABLE `risk_scores` DISABLE KEYS */;
INSERT INTO `risk_scores` VALUES (1,10,130,'critical','{\"missing_documents\":{\"points\":75,\"detail\":\"3 required doc(s) missing\"},\"duplicate_detected\":{\"points\":30,\"detail\":\"Matching SSN+DOB+email found in another application\"},\"multiple_active_2\":{\"points\":25,\"detail\":\"2 other active applications\"}}','2026-06-02 11:11:34',NULL);
/*!40000 ALTER TABLE `risk_scores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `role_id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `role_name` varchar(20) NOT NULL,
  `description` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'admin','Full system access'),(2,'officer','Assigned case management'),(3,'supervisor','Final case decisions'),(4,'auditor','Read-only audit access'),(5,'citizen','Own applications only'),(6,'quality_reviewer','Reviews officer decisions before escalation to supervisor');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sla_rules`
--

DROP TABLE IF EXISTS `sla_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sla_rules` (
  `sla_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `status_name` varchar(60) NOT NULL,
  `business_days_allowed` tinyint(4) NOT NULL,
  `warning_threshold_days` tinyint(4) NOT NULL,
  `role_responsible` varchar(50) NOT NULL,
  PRIMARY KEY (`sla_id`),
  UNIQUE KEY `status_name` (`status_name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sla_rules`
--

LOCK TABLES `sla_rules` WRITE;
/*!40000 ALTER TABLE `sla_rules` DISABLE KEYS */;
INSERT INTO `sla_rules` VALUES (1,'Eligibility Review',5,3,'officer'),(2,'Pending Verification',3,2,'officer'),(3,'Document Requested',10,7,'citizen'),(4,'Officer Review',10,7,'officer'),(5,'Quality Review',5,3,'quality_reviewer'),(6,'Supervisor Review',5,3,'supervisor'),(7,'Appeal Review',15,10,'supervisor');
/*!40000 ALTER TABLE `sla_rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sla_tracking`
--

DROP TABLE IF EXISTS `sla_tracking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sla_tracking` (
  `tracking_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `case_id` int(10) unsigned NOT NULL,
  `status_name` varchar(60) NOT NULL,
  `entered_at` datetime NOT NULL,
  `sla_deadline` datetime NOT NULL,
  `warning_date` datetime NOT NULL,
  `is_breached` tinyint(1) NOT NULL DEFAULT 0,
  `breached_at` datetime DEFAULT NULL,
  PRIMARY KEY (`tracking_id`),
  KEY `idx_sla_case` (`case_id`),
  KEY `idx_sla_breach` (`is_breached`,`sla_deadline`),
  CONSTRAINT `sla_tracking_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `cases` (`case_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sla_tracking`
--

LOCK TABLES `sla_tracking` WRITE;
/*!40000 ALTER TABLE `sla_tracking` DISABLE KEYS */;
INSERT INTO `sla_tracking` VALUES (1,8,'Quality Review','2026-06-02 17:21:27','2026-06-09 17:21:27','2026-06-05 17:21:27',1,'2026-06-02 11:21:35'),(2,8,'Officer Review','2026-06-02 17:21:32','2026-06-16 17:21:32','2026-06-11 17:21:32',0,NULL),(3,8,'Quality Review','2026-06-02 17:21:35','2026-06-09 17:21:35','2026-06-05 17:21:35',0,NULL),(4,8,'Supervisor Review','2026-06-02 18:38:57','2026-06-09 18:38:57','2026-06-05 18:38:57',0,NULL),(5,6,'Officer Review','2026-06-02 22:32:39','2026-06-16 22:32:39','2026-06-11 22:32:39',1,'2026-06-02 16:32:50'),(6,6,'Quality Review','2026-06-02 22:32:43','2026-06-09 22:32:43','2026-06-05 22:32:43',1,'2026-06-05 23:09:46'),(7,6,'Officer Review','2026-06-02 22:32:50','2026-06-16 22:32:50','2026-06-11 22:32:50',0,NULL),(8,6,'Quality Review','2026-06-06 05:09:46','2026-06-12 05:09:46','2026-06-10 05:09:46',0,NULL),(9,6,'Supervisor Review','2026-06-06 05:09:53','2026-06-12 05:09:53','2026-06-10 05:09:53',0,NULL);
/*!40000 ALTER TABLE `sla_tracking` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL DEFAULT '',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES ('assignment_method','least_loaded','2026-06-02 00:36:30'),('rr_last_officer_id','0','2026-06-02 00:36:30'),('sla_last_check','2026-06-02 22:33:16','2026-06-02 16:33:16');
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `training_defects`
--

DROP TABLE IF EXISTS `training_defects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_defects` (
  `defect_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `defect_key` varchar(100) NOT NULL,
  `defect_name` varchar(200) NOT NULL,
  `defect_description` text DEFAULT NULL,
  `defect_category` enum('validation','business_rule','status_transition','api_authorization','accessibility','database_integrity') NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `activated_at` datetime DEFAULT NULL,
  `activated_by_user_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`defect_id`),
  UNIQUE KEY `defect_key` (`defect_key`),
  KEY `activated_by_user_id` (`activated_by_user_id`),
  CONSTRAINT `training_defects_ibfk_1` FOREIGN KEY (`activated_by_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `training_defects`
--

LOCK TABLES `training_defects` WRITE;
/*!40000 ALTER TABLE `training_defects` DISABLE KEYS */;
INSERT INTO `training_defects` VALUES (1,'missing_phone_validation','Phone field accepts any text','Phone field accepts any text without format validation (no (NNN) NNN-NNNN enforcement)','validation',0,NULL,NULL),(2,'duplicate_app_limit_bypass','Active application limit not enforced','Citizens can submit a 4th active application (3-application limit bypassed)','business_rule',0,NULL,NULL),(3,'invalid_status_transition_allowed','Officer can set case directly to Approved','Officer can transition a case directly to Approved, skipping Quality Review and Supervisor','status_transition',0,NULL,NULL),(4,'api_citizen_sees_all_apps','Citizen API returns all citizens\' applications','GET /api/v1/applications returns every application in the system, not just the authenticated citizen\'s','api_authorization',0,NULL,NULL),(5,'missing_csrf_token','State-changing forms skip CSRF validation','Forms that change server state do not validate the CSRF token on submission','validation',0,NULL,NULL),(6,'missing_form_labels','Submit Application form uses placeholders only','Submit Application form renders placeholder text but no visible <label> elements','accessibility',0,NULL,NULL),(7,'audit_log_deletable','DELETE /api/v1/audit-logs/{id} deletes records','API endpoint returns 200 and removes the audit log entry, violating immutability requirement','database_integrity',0,NULL,NULL),(8,'password_plaintext_logged','Failed logins log attempted password in plaintext','Failed login audit log entries include the raw attempted password in new_value','database_integrity',0,NULL,NULL),(9,'sla_calculation_ignores_weekends','SLA counts calendar days instead of business days','SLA deadline calculation uses ADDDATE with calendar days rather than skipping weekends','business_rule',0,NULL,NULL),(10,'risk_score_not_recomputed','Risk score not updated after document upload','Uploading new documents does not trigger risk score recomputation, leaving stale scores','business_rule',0,NULL,NULL),(11,'supervisor_no_comment_required','Supervisor can decide without decision comments','Supervisor approval/denial form allows submission without entering decision comments','business_rule',0,NULL,NULL),(12,'file_type_extension_only','Document upload checks extension only','File type validation relies on extension (.jpg, .pdf) rather than MIME type — allows disguised files','validation',0,NULL,NULL);
/*!40000 ALTER TABLE `training_defects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` tinyint(3) unsigned NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `status` enum('active','inactive','locked') NOT NULL DEFAULT 'active',
  `failed_logins` tinyint(4) NOT NULL DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `program_specialization` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,1,'Admin','User','admin@fccsms.gov','$2y$12$XdLkGWueoq34UdQYHCanQOAl6Oe1YEcYwgLdGTHHKX.fDcgJtLWXi','active',0,'2026-06-08 18:40:05','2026-06-01 23:43:07','2026-06-08 18:40:05',NULL),(2,2,'Officer','One','officer1@fccsms.gov','$2y$12$XdLkGWueoq34UdQYHCanQOAl6Oe1YEcYwgLdGTHHKX.fDcgJtLWXi','active',0,'2026-07-07 14:55:02','2026-06-01 23:43:07','2026-07-07 14:55:02',NULL),(3,2,'Officer','Two','officer2@fccsms.gov','$2y$12$XdLkGWueoq34UdQYHCanQOAl6Oe1YEcYwgLdGTHHKX.fDcgJtLWXi','active',0,NULL,'2026-06-01 23:43:07','2026-06-01 23:54:00',NULL),(4,3,'Supervisor','One','supervisor@fccsms.gov','$2y$12$XdLkGWueoq34UdQYHCanQOAl6Oe1YEcYwgLdGTHHKX.fDcgJtLWXi','active',0,'2026-06-08 18:37:46','2026-06-01 23:43:07','2026-06-08 18:37:46',NULL),(5,4,'Auditor','One','auditor@fccsms.gov','$2y$12$XdLkGWueoq34UdQYHCanQOAl6Oe1YEcYwgLdGTHHKX.fDcgJtLWXi','active',0,'2026-07-07 14:54:02','2026-06-01 23:43:07','2026-07-07 14:54:02',NULL),(6,5,'John','Citizen','john@example.com','$2y$12$XdLkGWueoq34UdQYHCanQOAl6Oe1YEcYwgLdGTHHKX.fDcgJtLWXi','active',0,'2026-07-07 14:53:44','2026-06-01 23:43:07','2026-07-07 14:53:44',NULL),(7,5,'Jane','Doe','jane@example.com','$2y$12$XdLkGWueoq34UdQYHCanQOAl6Oe1YEcYwgLdGTHHKX.fDcgJtLWXi','active',0,NULL,'2026-06-01 23:43:07','2026-06-01 23:54:00',NULL),(8,6,'Quality','Reviewer','qreviewer@fccsms.gov','$2y$12$4J0oSkBNGhNMFkz/OTWq9eHpuRZ1exfKMKgqGX1RO1AJOQi4YZ1I.','active',0,NULL,'2026-06-02 16:37:11','2026-06-02 16:37:24',NULL),(9,5,'Jane','Smith','jane.smith@email.com','$2y$12$bngEUsnnUrnhYL3CwKxCi.IasCTDxRMHvoUrg/OXMT7vJOEMXTm6G','active',0,NULL,'2026-06-08 18:00:36','2026-06-08 18:00:36',NULL),(10,5,'Jane','Smith','jane.smh@email.com','$2y$12$LewbZlGojbGKOIHDfBAepugLuAhApNu1U1f6PQmUKKZBY5OfgKNeC','active',0,NULL,'2026-06-08 18:40:26','2026-06-08 18:40:26',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'fccsms'
--

--
-- Dumping routines for database 'fccsms'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-12  3:16:37
