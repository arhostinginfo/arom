-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: nursery
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
-- Table structure for table `activities`
--

DROP TABLE IF EXISTS `activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `enquiry_id` bigint(20) unsigned NOT NULL,
  `activity_type` enum('Call','Email','WhatsApp') NOT NULL,
  `activity_date` date NOT NULL,
  `details` text NOT NULL,
  `next_action` varchar(255) DEFAULT NULL,
  `target_date` date DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activities_created_by_foreign` (`created_by`),
  KEY `activities_enquiry_id_id_index` (`enquiry_id`,`id`),
  KEY `activities_enquiry_date_index` (`enquiry_id`,`activity_date`),
  CONSTRAINT `activities_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `activities_enquiry_id_foreign` FOREIGN KEY (`enquiry_id`) REFERENCES `enquiries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activities`
--

LOCK TABLES `activities` WRITE;
/*!40000 ALTER TABLE `activities` DISABLE KEYS */;
/*!40000 ALTER TABLE `activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `user_name` varchar(100) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `subject_type` varchar(255) DEFAULT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `subject_label` varchar(191) DEFAULT NULL,
  `description` varchar(500) NOT NULL,
  `changes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`changes`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `activity_logs_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  KEY `activity_logs_action_index` (`action`),
  KEY `activity_logs_created_at_index` (`created_at`),
  KEY `activity_logs_action_id_index` (`action`,`id`),
  KEY `activity_logs_user_created_index` (`user_id`,`created_at`),
  CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9444','2026-10-08 00:18:06'),(2,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9444','2026-10-08 00:18:18'),(3,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9444','2026-10-08 00:18:48'),(4,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 00:50:07'),(5,1,'Administrator','user.created','user',2,'Satish (satishworking2026@gmail.com)','Created user Satish','{\"fields\":{\"Name\":\"Satish\",\"Login email\":\"satishworking2026@gmail.com\",\"Mobile\":\"+91 91234 56780\",\"Status\":\"Active\",\"Units allowed\":\"Unit 1 \\u00b7 Chakan\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 01:01:12'),(6,1,'Administrator','user.role_assigned','user',2,'Satish (satishworking2026@gmail.com)','Assigned role Accounts to Satish','{\"role\":{\"old\":null,\"new\":\"Accounts\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 01:01:12'),(7,1,'Administrator','user.permissions_updated','user',2,'Satish (satishworking2026@gmail.com)','Gave Satish 32 extra permission(s)','{\"added\":[\"Quotation \\u00b7 View\",\"Quotation \\u00b7 Add\",\"Quotation \\u00b7 Edit\",\"Quotation \\u00b7 Delete\",\"Prospect \\u00b7 View\",\"Prospect \\u00b7 Add\",\"Prospect \\u00b7 Edit\",\"Prospect \\u00b7 Delete\",\"Tax invoice \\u00b7 View\",\"Tax invoice \\u00b7 Add\",\"Tax invoice \\u00b7 Edit\",\"Tax invoice \\u00b7 Delete\",\"Sales order \\u00b7 View\",\"Sales order \\u00b7 Add\",\"Sales order \\u00b7 Edit\",\"Sales order \\u00b7 Delete\",\"GRN entry \\u00b7 View\",\"GRN entry \\u00b7 Add\",\"GRN entry \\u00b7 Edit\",\"GRN entry \\u00b7 Delete\",\"Purchase order \\u00b7 View\",\"Purchase order \\u00b7 Add\",\"Purchase order \\u00b7 Edit\",\"Purchase order \\u00b7 Delete\",\"Stock transfer \\u00b7 View\",\"Stock transfer \\u00b7 Add\",\"Stock transfer \\u00b7 Edit\",\"Stock transfer \\u00b7 Delete\",\"Users list \\u00b7 View\",\"CRM reports \\u00b7 View\",\"Sales reports \\u00b7 View\",\"Purchase reports \\u00b7 View\"]}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 01:01:12'),(8,1,'Administrator','role.created','role',5,'tEMP','Created role tEMP',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 01:01:49'),(9,1,'Administrator','role.permissions_updated','role',5,'tEMP','Gave role tEMP 8 permission(s)','{\"added\":[\"Quotation \\u00b7 View\",\"Quotation \\u00b7 Add\",\"Quotation \\u00b7 Edit\",\"Quotation \\u00b7 Delete\",\"Prospect \\u00b7 View\",\"Prospect \\u00b7 Add\",\"Prospect \\u00b7 Edit\",\"Prospect \\u00b7 Delete\"]}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 01:01:49'),(10,1,'Administrator','user.permissions_updated','user',2,'Satish (satishworking2026@gmail.com)','Changed extra permissions of Satish','{\"removed\":[\"Tax invoice \\u00b7 View\",\"Tax invoice \\u00b7 Add\",\"Tax invoice \\u00b7 Edit\",\"Tax invoice \\u00b7 Delete\",\"Sales order \\u00b7 View\",\"Sales order \\u00b7 Add\",\"Sales order \\u00b7 Edit\",\"Sales order \\u00b7 Delete\",\"GRN entry \\u00b7 View\",\"GRN entry \\u00b7 Add\",\"GRN entry \\u00b7 Edit\",\"GRN entry \\u00b7 Delete\",\"Purchase order \\u00b7 View\",\"Purchase order \\u00b7 Add\",\"Purchase order \\u00b7 Edit\",\"Purchase order \\u00b7 Delete\",\"Stock transfer \\u00b7 View\",\"Stock transfer \\u00b7 Add\",\"Stock transfer \\u00b7 Edit\",\"Stock transfer \\u00b7 Delete\",\"Users list \\u00b7 View\"]}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 01:11:39'),(11,2,'Satish','auth.login','user',2,'Satish (satishworking2026@gmail.com)','Satish logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 01:12:02'),(12,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9444','2026-10-08 01:48:38'),(13,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/2.19675.0 Chrome/152.0.7977.130 Safari/537.36 MSIX','2026-10-08 02:24:21'),(14,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','','2026-10-08 03:57:08'),(15,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:19:42'),(16,2,'Satish','auth.login','user',2,'Satish (satishworking2026@gmail.com)','Satish logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:21:05'),(17,2,'Satish','auth.logout','user',2,'Satish (satishworking2026@gmail.com)','Satish logged out',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:21:27'),(18,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:21:34'),(19,1,'Administrator','user.created','user',3,'test@gmail.com (test@gmail.com)','Created user test@gmail.com','{\"fields\":{\"Name\":\"test@gmail.com\",\"Login email\":\"test@gmail.com\",\"Mobile\":\"+91 98765 43210\",\"Status\":\"Active\",\"Units allowed\":\"Unit 1 \\u00b7 Chakan\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:28:36'),(20,1,'Administrator','user.role_assigned','user',3,'test@gmail.com (test@gmail.com)','Assigned role Accounts to test@gmail.com','{\"role\":{\"old\":null,\"new\":\"Accounts\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:28:36'),(21,1,'Administrator','auth.logout','user',1,'Administrator (admin@nursery.local)','Administrator logged out',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:40:25'),(22,3,'test@gmail.com','auth.login','user',3,'test@gmail.com (test@gmail.com)','test@gmail.com logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:40:40'),(23,1,'Administrator','auth.logout','user',1,'Administrator (admin@nursery.local)','Administrator logged out',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:49:52'),(24,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:50:11'),(25,1,'Administrator','role.created','role',6,'TempRole','Created role TempRole',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:51:48'),(26,1,'Administrator','role.permissions_updated','role',6,'TempRole','Gave role TempRole 3 permission(s)','{\"added\":[\"Dashboard \\u00b7 View\",\"Quotation \\u00b7 View\",\"Tax invoice \\u00b7 View\"]}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:51:48'),(27,3,'test@gmail.com','auth.logout','user',3,'test@gmail.com (test@gmail.com)','test@gmail.com logged out',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:57:31'),(28,1,'Administrator','user.created','user',4,'Temp Temp (test1@gmail.com)','Created user Temp Temp','{\"fields\":{\"Name\":\"Temp Temp\",\"Login email\":\"test1@gmail.com\",\"Mobile\":\"+91 98798 76543\",\"Status\":\"Active\",\"Units allowed\":\"Unit 1 \\u00b7 Chakan, Unit 2 \\u00b7 Bhosari\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:57:37'),(29,1,'Administrator','user.role_assigned','user',4,'Temp Temp (test1@gmail.com)','Assigned role TempRole to Temp Temp','{\"role\":{\"old\":null,\"new\":\"TempRole\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:57:37'),(30,4,'Temp Temp','auth.login','user',4,'Temp Temp (test1@gmail.com)','Temp Temp logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:58:27'),(31,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9444','2026-10-13 16:02:46'),(32,NULL,NULL,'auth.failed',NULL,NULL,'admin@arom.com','Login failed for admin@arom.com (unknown email)',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-13 16:04:47'),(33,NULL,NULL,'auth.failed',NULL,NULL,'admin@arom.com','Login failed for admin@arom.com (unknown email)',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-13 16:04:54'),(34,NULL,NULL,'auth.failed',NULL,NULL,'admin@arom.com','Login failed for admin@arom.com (unknown email)',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-13 16:05:00'),(35,NULL,NULL,'auth.failed',NULL,NULL,'admin@arom.com','Login failed for admin@arom.com (unknown email)',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-13 16:05:16'),(36,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-13 16:41:44'),(37,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9444','2026-10-13 16:53:41'),(38,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9444','2026-10-13 16:54:50'),(39,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 18:16:51'),(40,1,'Administrator','prospect.created','prospect',1,'Satish','Added prospect Satish','{\"fields\":{\"Prospect name\":\"Satish\",\"Domestic \\/ Export\":\"Domestic\",\"Contact person\":\"Satish\",\"Position\":null,\"Contact no 1\":\"9852854774\",\"Contact no 2\":null,\"Email\":\"satish@gmail.com\",\"GSTIN\":null,\"Industry type\":null,\"Address\":null}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 18:19:18'),(41,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-16 00:59:06'),(42,1,'Administrator','prospect.created','prospect',2,'Ganesh Patil','Added prospect Ganesh Patil','{\"fields\":{\"Prospect name\":\"Ganesh Patil\",\"Domestic \\/ Export\":\"Domestic\",\"Contact person\":\"9874563214\",\"Position\":null,\"Contact no 1\":\"9874563214\",\"Contact no 2\":null,\"Email\":null,\"GSTIN\":null,\"Industry type\":null,\"Address\":null}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-16 01:00:36'),(43,1,'Administrator','contact.created','contact',1,'contact CT00001 GP New','Added contact CT00001 GP New for Ganesh Patil','{\"fields\":{\"Contact ID\":\"CT00001\",\"Company \\/ Client\":\"Ganesh Patil\",\"Name\":\"GP New\",\"Designation\":\"Design\",\"Department\":\"Department\",\"Mobile\":\"9874563214\",\"WhatsApp\":null,\"Email\":null,\"Alternate number\":null,\"Preferred communication\":null,\"Roles\":\"Decision maker\",\"Notes\":null}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-16 01:01:48'),(44,1,'Administrator','employee.created','employee',1,'Temp (FWRW)','Added employee Temp (FWRW)','{\"fields\":{\"Employee code\":\"FWRW\",\"Name\":\"Temp\",\"Designation\":\"Designation\",\"Department\":\"Department\",\"Mobile\":\"9874563214\",\"Email\":\"test@gmail.com\",\"Date of joining\":\"10\\/10\\/2222\",\"Address\":null,\"Login user\":\"Administrator\",\"Status\":\"Active\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-16 01:11:20'),(45,1,'Administrator','enquiry.created','enquiry',1,'ENQ/26-27/0001 · Ganesh Patil','Added enquiry ENQ/26-27/0001 for Ganesh Patil','{\"fields\":{\"Enquiry date\":\"16\\/10\\/2026\",\"Source\":\"Website\",\"Sub-source \\/ Reference\":null,\"Competitor details\":null,\"Prospect type\":\"Existing Client\",\"Company \\/ Client name\":\"Ganesh Patil\",\"Industry\":null,\"Industry type\":\"Corporate\",\"City\":\"Nashik\",\"Address\":null,\"Website\":null,\"Contact name\":\"GP\",\"Contact no\":\"7894561230\",\"Service type\":\"Corporate Plantation\",\"Approx. requirement\":null,\"Estimated value\":null,\"Expected closing date\":null,\"Lead rating\":\"Hot\",\"Lead assigned to\":\"Temp\",\"Next action\":\"test\",\"Next action date\":\"13\\/04\\/1982\",\"Remarks\":null}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-16 01:11:33'),(46,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-18 04:47:53'),(47,1,'Administrator','enquiry.created','enquiry',2,'ENQ/26-27/0002 · Satish','Added enquiry ENQ/26-27/0002 for Satish','{\"fields\":{\"Enquiry date\":\"18\\/10\\/2026\",\"Source\":\"Website\",\"Sub-source \\/ Reference\":\"Test\",\"Competitor details\":null,\"Prospect type\":\"Existing Client\",\"Company \\/ Client name\":\"Satish\",\"Industry\":null,\"Industry type\":\"Corporate\",\"City\":\"Ahmedabad\",\"Address\":null,\"Website\":null,\"Contact name\":\"tEST\",\"Contact no\":\"7894561230\",\"Service type\":\"Landscape Design\",\"Approx. requirement\":null,\"Estimated value\":null,\"Expected closing date\":null,\"Lead rating\":\"Cold\",\"Lead assigned to\":\"Temp\",\"Next action\":\"test\",\"Next action date\":\"13\\/04\\/1982\",\"Remarks\":null}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-18 04:54:24'),(48,1,'Administrator','site_visit.created','site_visit',1,'Site visit 19/10/2026 11:00 · ENQ/26-27/0002','Added a site visit for ENQ/26-27/0002','{\"fields\":{\"Enquiry\":\"ENQ\\/26-27\\/0002\",\"Visit date and time\":\"19\\/10\\/2026 11:00\",\"POC\":\"tEST, 7894561230\",\"Technical person\":null,\"Site address\":null,\"Area \\/ measurements\":null,\"Floors\":null,\"Indoor \\/ outdoor area\":null,\"Existing plants\":null,\"Existing planters\":null,\"Lighting condition\":null,\"Water availability\":null,\"Access restrictions\":null,\"Customer requirement\":null,\"Remarks\":null,\"Next action\":null,\"Status\":\"Done\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-18 04:59:34');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `approval_rules`
--

DROP TABLE IF EXISTS `approval_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `approval_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `document_type` varchar(30) NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `approve_above_amount` decimal(15,2) DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `approval_rules_document_type_unique` (`document_type`),
  KEY `approval_rules_updated_by_foreign` (`updated_by`),
  CONSTRAINT `approval_rules_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `approval_rules`
--

LOCK TABLES `approval_rules` WRITE;
/*!40000 ALTER TABLE `approval_rules` DISABLE KEYS */;
INSERT INTO `approval_rules` VALUES (1,'quotation',1,100000.00,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(2,'sales_order',1,NULL,NULL,'2026-10-15 03:13:41','2026-10-15 03:13:41');
/*!40000 ALTER TABLE `approval_rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `approvals`
--

DROP TABLE IF EXISTS `approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `approvals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `approvable_type` varchar(40) NOT NULL,
  `approvable_id` bigint(20) unsigned NOT NULL,
  `action` enum('Submitted','Approved','Rejected','Approved automatically') NOT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `acted_by` bigint(20) unsigned DEFAULT NULL,
  `acted_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `approvals_acted_by_foreign` (`acted_by`),
  KEY `approvals_subject_index` (`approvable_type`,`approvable_id`,`id`),
  CONSTRAINT `approvals_acted_by_foreign` FOREIGN KEY (`acted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `approvals`
--

LOCK TABLES `approvals` WRITE;
/*!40000 ALTER TABLE `approvals` DISABLE KEYS */;
/*!40000 ALTER TABLE `approvals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('nursery-cache-4bc25146bbe4b00ec401ec9680c4c6ca','i:1;',1792300400),('nursery-cache-4bc25146bbe4b00ec401ec9680c4c6ca:timer','i:1792300400;',1792300400),('nursery-cache-crm.alerts.unit.1.20261016','a:3:{s:6:\"visits\";i:0;s:8:\"approval\";i:0;s:2:\"po\";i:0;}',1792113382),('nursery-cache-crm.alerts.unit.1.20261018','a:3:{s:6:\"visits\";i:0;s:8:\"approval\";i:0;s:2:\"po\";i:0;}',1792300593),('nursery-cache-crm.due.1.all.20261016','a:3:{s:5:\"count\";i:1;s:7:\"overdue\";i:1;s:4:\"list\";a:1:{i:0;a:7:{s:2:\"id\";i:1;s:2:\"no\";s:14:\"ENQ/26-27/0001\";s:6:\"client\";s:12:\"Ganesh Patil\";s:6:\"action\";s:4:\"test\";s:4:\"date\";s:10:\"13/04/1982\";s:4:\"late\";i:16257;s:3:\"url\";s:44:\"http://localhost/nursery-app/crm/enquiries/1\";}}}',1792113396),('nursery-cache-crm.due.1.all.20261018','a:3:{s:5:\"count\";i:2;s:7:\"overdue\";i:2;s:4:\"list\";a:2:{i:0;a:7:{s:2:\"id\";i:1;s:2:\"no\";s:14:\"ENQ/26-27/0001\";s:6:\"client\";s:12:\"Ganesh Patil\";s:6:\"action\";s:4:\"test\";s:4:\"date\";s:10:\"13/04/1982\";s:4:\"late\";i:16259;s:3:\"url\";s:44:\"http://localhost/nursery-app/crm/enquiries/1\";}i:1;a:7:{s:2:\"id\";i:2;s:2:\"no\";s:14:\"ENQ/26-27/0002\";s:6:\"client\";s:6:\"Satish\";s:6:\"action\";s:4:\"test\";s:4:\"date\";s:10:\"13/04/1982\";s:4:\"late\";i:16259;s:3:\"url\";s:44:\"http://localhost/nursery-app/crm/enquiries/2\";}}}',1792300593),('nursery-cache-crm.prospects.count','i:2;',1792299474),('nursery-cache-illuminate:cache:flexible:created:crm.alerts.unit.1.20261016','i:1792113082;',1792113382),('nursery-cache-illuminate:cache:flexible:created:crm.alerts.unit.1.20261018','i:1792300293;',1792300593),('nursery-cache-illuminate:cache:flexible:created:crm.due.1.all.20261016','i:1792113096;',1792113396),('nursery-cache-illuminate:cache:flexible:created:crm.due.1.all.20261018','i:1792300293;',1792300593),('nursery-cache-module-settings','a:0:{}',1792302474),('nursery-cache-page-setup.contact','a:0:{}',1792302474),('nursery-cache-page-setup.customer-po','a:0:{}',1792303006),('nursery-cache-page-setup.employee','a:0:{}',1792116603),('nursery-cache-page-setup.enquiry','a:0:{}',1792302474),('nursery-cache-page-setup.master-sources','a:0:{}',1792116475),('nursery-cache-page-setup.prospect','a:0:{}',1792302474),('nursery-cache-page-setup.quotation','a:0:{}',1792302993),('nursery-cache-page-setup.site-visit','a:0:{}',1792302982);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cities`
--

DROP TABLE IF EXISTS `cities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cities_name_unique` (`name`),
  KEY `cities_created_by_foreign` (`created_by`),
  KEY `cities_updated_by_foreign` (`updated_by`),
  KEY `cities_is_active_sort_order_name_index` (`is_active`,`sort_order`,`name`),
  CONSTRAINT `cities_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cities_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cities`
--

LOCK TABLES `cities` WRITE;
/*!40000 ALTER TABLE `cities` DISABLE KEYS */;
INSERT INTO `cities` VALUES (1,'Pune',1,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(2,'Mumbai',2,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(3,'Thane',3,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(4,'Navi Mumbai',4,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(5,'Nashik',5,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(6,'Nagpur',6,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(7,'Aurangabad',7,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(8,'Kolhapur',8,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(9,'Bengaluru',9,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(10,'Hyderabad',10,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(11,'Chennai',11,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(12,'Delhi',12,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(13,'Gurugram',13,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(14,'Noida',14,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(15,'Ahmedabad',15,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(16,'Kolkata',16,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(17,'Goa',17,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40');
/*!40000 ALTER TABLE `cities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `companies`
--

DROP TABLE IF EXISTS `companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `companies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `pan` varchar(10) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `companies_updated_by_foreign` (`updated_by`),
  CONSTRAINT `companies_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `companies`
--

LOCK TABLES `companies` WRITE;
/*!40000 ALTER TABLE `companies` DISABLE KEYS */;
/*!40000 ALTER TABLE `companies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contacts`
--

DROP TABLE IF EXISTS `contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contacts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contact_no` varchar(20) DEFAULT NULL,
  `party_id` bigint(20) unsigned DEFAULT NULL,
  `prospect_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `mobile_digits` varchar(20) DEFAULT NULL,
  `whatsapp` varchar(20) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `alternate_no` varchar(20) DEFAULT NULL,
  `preferred_communication` varchar(20) DEFAULT NULL,
  `is_decision_maker` tinyint(1) NOT NULL DEFAULT 0,
  `is_influencer` tinyint(1) NOT NULL DEFAULT 0,
  `is_purchase_contact` tinyint(1) NOT NULL DEFAULT 0,
  `is_facility_contact` tinyint(1) NOT NULL DEFAULT 0,
  `is_billing_contact` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contacts_contact_no_unique` (`contact_no`),
  KEY `contacts_created_by_foreign` (`created_by`),
  KEY `contacts_updated_by_foreign` (`updated_by`),
  KEY `contacts_prospect_name_index` (`prospect_id`,`deleted_at`,`name`),
  KEY `contacts_party_name_index` (`party_id`,`deleted_at`,`name`),
  KEY `contacts_name_index` (`deleted_at`,`name`),
  KEY `contacts_mobile_digits_index` (`mobile_digits`),
  CONSTRAINT `contacts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contacts_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`),
  CONSTRAINT `contacts_prospect_id_foreign` FOREIGN KEY (`prospect_id`) REFERENCES `prospects` (`id`),
  CONSTRAINT `contacts_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contacts`
--

LOCK TABLES `contacts` WRITE;
/*!40000 ALTER TABLE `contacts` DISABLE KEYS */;
INSERT INTO `contacts` VALUES (1,'CT00001',NULL,2,'GP New','Design','Department','9874563214','9874563214',NULL,NULL,NULL,NULL,1,0,0,0,0,NULL,1,1,'2026-10-16 01:01:48','2026-10-16 01:01:48',NULL);
/*!40000 ALTER TABLE `contacts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `currencies`
--

DROP TABLE IF EXISTS `currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `currencies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `country` varchar(100) NOT NULL,
  `code` varchar(3) NOT NULL,
  `symbol` varchar(5) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `currencies_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `currencies`
--

LOCK TABLES `currencies` WRITE;
/*!40000 ALTER TABLE `currencies` DISABLE KEYS */;
INSERT INTO `currencies` VALUES (1,'India','INR','₹','2026-10-08 02:23:16','2026-10-08 02:23:16'),(2,'United States','USD','$','2026-10-08 02:23:16','2026-10-08 02:23:16'),(3,'Euro area','EUR','€','2026-10-08 02:23:16','2026-10-08 02:23:16');
/*!40000 ALTER TABLE `currencies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_pos`
--

DROP TABLE IF EXISTS `customer_pos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_pos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `unit_id` bigint(20) unsigned NOT NULL,
  `financial_year_id` bigint(20) unsigned NOT NULL,
  `enquiry_id` bigint(20) unsigned NOT NULL,
  `quotation_id` bigint(20) unsigned DEFAULT NULL,
  `po_no` varchar(60) NOT NULL,
  `po_date` date NOT NULL,
  `party_id` bigint(20) unsigned DEFAULT NULL,
  `prospect_id` bigint(20) unsigned DEFAULT NULL,
  `site` varchar(255) DEFAULT NULL,
  `po_value` decimal(15,2) NOT NULL,
  `po_quantity` decimal(15,3) DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `payment_terms` varchar(255) DEFAULT NULL,
  `tax_terms` varchar(150) DEFAULT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `received_by` bigint(20) unsigned DEFAULT NULL,
  `received_date` date NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_pos_financial_year_id_foreign` (`financial_year_id`),
  KEY `customer_pos_quotation_id_foreign` (`quotation_id`),
  KEY `customer_pos_party_id_foreign` (`party_id`),
  KEY `customer_pos_prospect_id_foreign` (`prospect_id`),
  KEY `customer_pos_received_by_foreign` (`received_by`),
  KEY `customer_pos_created_by_foreign` (`created_by`),
  KEY `customer_pos_updated_by_foreign` (`updated_by`),
  KEY `customer_pos_unit_date_index` (`unit_id`,`po_date`),
  KEY `customer_pos_enquiry_deleted_index` (`enquiry_id`,`deleted_at`),
  KEY `customer_pos_unit_number_index` (`unit_id`,`po_no`),
  CONSTRAINT `customer_pos_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_pos_enquiry_id_foreign` FOREIGN KEY (`enquiry_id`) REFERENCES `enquiries` (`id`),
  CONSTRAINT `customer_pos_financial_year_id_foreign` FOREIGN KEY (`financial_year_id`) REFERENCES `financial_years` (`id`),
  CONSTRAINT `customer_pos_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`),
  CONSTRAINT `customer_pos_prospect_id_foreign` FOREIGN KEY (`prospect_id`) REFERENCES `prospects` (`id`),
  CONSTRAINT `customer_pos_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`),
  CONSTRAINT `customer_pos_received_by_foreign` FOREIGN KEY (`received_by`) REFERENCES `employees` (`id`),
  CONSTRAINT `customer_pos_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`),
  CONSTRAINT `customer_pos_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_pos`
--

LOCK TABLES `customer_pos` WRITE;
/*!40000 ALTER TABLE `customer_pos` DISABLE KEYS */;
/*!40000 ALTER TABLE `customer_pos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `documentable_type` varchar(40) NOT NULL,
  `documentable_id` bigint(20) unsigned NOT NULL,
  `category` varchar(40) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `path` varchar(255) NOT NULL,
  `mime` varchar(120) DEFAULT NULL,
  `size` int(10) unsigned NOT NULL,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `documents_uploaded_by_foreign` (`uploaded_by`),
  KEY `documents_subject_index` (`documentable_type`,`documentable_id`,`id`),
  CONSTRAINT `documents_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employees` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employees_code_unique` (`code`),
  UNIQUE KEY `employees_user_id_unique` (`user_id`),
  KEY `employees_created_by_foreign` (`created_by`),
  KEY `employees_updated_by_foreign` (`updated_by`),
  KEY `employees_status_name_index` (`status`,`name`),
  KEY `employees_name_index` (`name`),
  CONSTRAINT `employees_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
INSERT INTO `employees` VALUES (1,'FWRW','Temp','Designation','Department','9874563214','test@gmail.com','2222-10-10',NULL,1,'Active',1,1,'2026-10-16 01:11:20','2026-10-16 01:11:20',NULL);
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enquiries`
--

DROP TABLE IF EXISTS `enquiries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `enquiries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `unit_id` bigint(20) unsigned NOT NULL,
  `financial_year_id` bigint(20) unsigned NOT NULL,
  `enquiry_no` varchar(30) NOT NULL,
  `enquiry_date` date NOT NULL,
  `lead_source_id` bigint(20) unsigned DEFAULT NULL,
  `sub_source` varchar(150) DEFAULT NULL,
  `competitor_details` varchar(255) DEFAULT NULL,
  `prospect_type` enum('New','Existing Client','Referral') DEFAULT NULL,
  `party_id` bigint(20) unsigned DEFAULT NULL,
  `prospect_id` bigint(20) unsigned DEFAULT NULL,
  `industry` varchar(100) DEFAULT NULL,
  `industry_type_id` bigint(20) unsigned DEFAULT NULL,
  `city_id` bigint(20) unsigned DEFAULT NULL,
  `address` text DEFAULT NULL,
  `website` varchar(191) DEFAULT NULL,
  `contact_name` varchar(100) DEFAULT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `contact_no_digits` varchar(20) DEFAULT NULL,
  `service_category_id` bigint(20) unsigned DEFAULT NULL,
  `approx_requirement` varchar(255) DEFAULT NULL,
  `estimated_value` decimal(15,2) DEFAULT NULL,
  `expected_closing_date` date DEFAULT NULL,
  `lead_rating` enum('Hot','Warm','Cold') DEFAULT NULL,
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `next_action` varchar(255) DEFAULT NULL,
  `next_action_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `status` enum('Open','PO received','Lost') NOT NULL DEFAULT 'Open',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `enquiries_number_unique` (`unit_id`,`financial_year_id`,`enquiry_no`),
  KEY `enquiries_financial_year_id_foreign` (`financial_year_id`),
  KEY `enquiries_lead_source_id_foreign` (`lead_source_id`),
  KEY `enquiries_party_id_foreign` (`party_id`),
  KEY `enquiries_prospect_id_foreign` (`prospect_id`),
  KEY `enquiries_industry_type_id_foreign` (`industry_type_id`),
  KEY `enquiries_city_id_foreign` (`city_id`),
  KEY `enquiries_service_category_id_foreign` (`service_category_id`),
  KEY `enquiries_created_by_foreign` (`created_by`),
  KEY `enquiries_updated_by_foreign` (`updated_by`),
  KEY `enquiries_unit_date_index` (`unit_id`,`enquiry_date`),
  KEY `enquiries_unit_due_index` (`unit_id`,`status`,`next_action_date`),
  KEY `enquiries_unit_rating_index` (`unit_id`,`status`,`lead_rating`),
  KEY `enquiries_unit_closing_index` (`unit_id`,`status`,`expected_closing_date`),
  KEY `enquiries_assignee_due_index` (`assigned_to`,`status`,`next_action_date`),
  KEY `enquiries_unit_open_date_index` (`unit_id`,`deleted_at`,`status`,`enquiry_date`),
  KEY `enquiries_unit_figures_index` (`unit_id`,`deleted_at`,`enquiry_date`,`lead_source_id`,`service_category_id`),
  KEY `enquiries_unit_next_index` (`unit_id`,`next_action_date`),
  KEY `enquiries_unit_expected_index` (`unit_id`,`expected_closing_date`),
  KEY `enquiries_unit_contact_index` (`unit_id`,`contact_no`),
  KEY `enquiries_unit_number_index` (`unit_id`,`enquiry_no`),
  KEY `enquiries_unit_contact_digits_index` (`unit_id`,`contact_no_digits`),
  CONSTRAINT `enquiries_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `employees` (`id`),
  CONSTRAINT `enquiries_city_id_foreign` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`),
  CONSTRAINT `enquiries_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `enquiries_financial_year_id_foreign` FOREIGN KEY (`financial_year_id`) REFERENCES `financial_years` (`id`),
  CONSTRAINT `enquiries_industry_type_id_foreign` FOREIGN KEY (`industry_type_id`) REFERENCES `industry_types` (`id`),
  CONSTRAINT `enquiries_lead_source_id_foreign` FOREIGN KEY (`lead_source_id`) REFERENCES `lead_sources` (`id`),
  CONSTRAINT `enquiries_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`),
  CONSTRAINT `enquiries_prospect_id_foreign` FOREIGN KEY (`prospect_id`) REFERENCES `prospects` (`id`),
  CONSTRAINT `enquiries_service_category_id_foreign` FOREIGN KEY (`service_category_id`) REFERENCES `service_categories` (`id`),
  CONSTRAINT `enquiries_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`),
  CONSTRAINT `enquiries_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enquiries`
--

LOCK TABLES `enquiries` WRITE;
/*!40000 ALTER TABLE `enquiries` DISABLE KEYS */;
INSERT INTO `enquiries` VALUES (1,1,1,'ENQ/26-27/0001','2026-10-16',1,NULL,NULL,'Existing Client',NULL,2,NULL,1,5,NULL,NULL,'GP','7894561230','7894561230',11,NULL,NULL,NULL,'Hot',1,'test','1982-04-13',NULL,'Open',1,1,'2026-10-16 01:11:33','2026-10-16 01:11:33',NULL),(2,1,1,'ENQ/26-27/0002','2026-10-18',1,'Test',NULL,'Existing Client',NULL,1,NULL,1,15,NULL,NULL,'tEST','7894561230','7894561230',3,NULL,NULL,NULL,'Cold',1,'test','1982-04-13',NULL,'Open',1,1,'2026-10-18 04:54:24','2026-10-18 04:54:24',NULL);
/*!40000 ALTER TABLE `enquiries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `financial_years`
--

DROP TABLE IF EXISTS `financial_years`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `financial_years` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(5) NOT NULL,
  `starts_on` date NOT NULL,
  `ends_on` date NOT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `financial_years_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `financial_years`
--

LOCK TABLES `financial_years` WRITE;
/*!40000 ALTER TABLE `financial_years` DISABLE KEYS */;
INSERT INTO `financial_years` VALUES (1,'26-27','2026-04-01','2027-03-31',1,'2026-10-08 02:23:17','2026-10-08 02:23:17');
/*!40000 ALTER TABLE `financial_years` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hsn_sac_codes`
--

DROP TABLE IF EXISTS `hsn_sac_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hsn_sac_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(10) NOT NULL,
  `type` enum('HSN','SAC') NOT NULL DEFAULT 'HSN',
  `description` varchar(255) DEFAULT NULL,
  `tax_structure_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hsn_sac_codes_code_type_unique` (`code`,`type`),
  KEY `hsn_sac_codes_tax_structure_id_foreign` (`tax_structure_id`),
  KEY `hsn_sac_codes_created_by_foreign` (`created_by`),
  KEY `hsn_sac_codes_updated_by_foreign` (`updated_by`),
  CONSTRAINT `hsn_sac_codes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hsn_sac_codes_tax_structure_id_foreign` FOREIGN KEY (`tax_structure_id`) REFERENCES `tax_structures` (`id`),
  CONSTRAINT `hsn_sac_codes_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hsn_sac_codes`
--

LOCK TABLES `hsn_sac_codes` WRITE;
/*!40000 ALTER TABLE `hsn_sac_codes` DISABLE KEYS */;
/*!40000 ALTER TABLE `hsn_sac_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `industry_types`
--

DROP TABLE IF EXISTS `industry_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `industry_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `industry_types_name_unique` (`name`),
  KEY `industry_types_created_by_foreign` (`created_by`),
  KEY `industry_types_updated_by_foreign` (`updated_by`),
  KEY `industry_types_is_active_sort_order_name_index` (`is_active`,`sort_order`,`name`),
  CONSTRAINT `industry_types_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `industry_types_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `industry_types`
--

LOCK TABLES `industry_types` WRITE;
/*!40000 ALTER TABLE `industry_types` DISABLE KEYS */;
INSERT INTO `industry_types` VALUES (1,'Corporate',1,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(2,'Mall',2,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(3,'Hotel',3,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(4,'Hospital',4,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(5,'Builder',5,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(6,'Other',6,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40');
/*!40000 ALTER TABLE `industry_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `item_categories`
--

DROP TABLE IF EXISTS `item_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `item_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` enum('Sales','Purchase','Both') NOT NULL DEFAULT 'Both',
  `item_no_prefix` varchar(10) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_categories_name_unique` (`name`),
  KEY `item_categories_created_by_foreign` (`created_by`),
  KEY `item_categories_updated_by_foreign` (`updated_by`),
  CONSTRAINT `item_categories_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `item_categories_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item_categories`
--

LOCK TABLES `item_categories` WRITE;
/*!40000 ALTER TABLE `item_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `item_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `items`
--

DROP TABLE IF EXISTS `items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `party_id` bigint(20) unsigned DEFAULT NULL,
  `item_category_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `item_no` varchar(50) NOT NULL,
  `internal_item_no` varchar(50) DEFAULT NULL,
  `type` enum('Sales','Purchase','Both') NOT NULL,
  `hsn_sac_code_id` bigint(20) unsigned NOT NULL,
  `uom_id` bigint(20) unsigned NOT NULL,
  `tax_structure_id` bigint(20) unsigned NOT NULL,
  `price` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `currency_id` bigint(20) unsigned DEFAULT NULL,
  `min_stock` decimal(15,3) DEFAULT NULL,
  `max_stock` decimal(15,3) DEFAULT NULL,
  `weight_per_piece` decimal(15,3) DEFAULT NULL,
  `packaging_qty` decimal(15,3) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `items_item_no_unique` (`item_no`),
  KEY `items_item_category_id_foreign` (`item_category_id`),
  KEY `items_hsn_sac_code_id_foreign` (`hsn_sac_code_id`),
  KEY `items_uom_id_foreign` (`uom_id`),
  KEY `items_tax_structure_id_foreign` (`tax_structure_id`),
  KEY `items_currency_id_foreign` (`currency_id`),
  KEY `items_created_by_foreign` (`created_by`),
  KEY `items_updated_by_foreign` (`updated_by`),
  KEY `items_party_status_index` (`party_id`,`status`,`type`,`item_no`),
  KEY `items_description_index` (`deleted_at`,`description`),
  KEY `items_internal_no_index` (`internal_item_no`),
  CONSTRAINT `items_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `items_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `items_hsn_sac_code_id_foreign` FOREIGN KEY (`hsn_sac_code_id`) REFERENCES `hsn_sac_codes` (`id`),
  CONSTRAINT `items_item_category_id_foreign` FOREIGN KEY (`item_category_id`) REFERENCES `item_categories` (`id`),
  CONSTRAINT `items_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`),
  CONSTRAINT `items_tax_structure_id_foreign` FOREIGN KEY (`tax_structure_id`) REFERENCES `tax_structures` (`id`),
  CONSTRAINT `items_uom_id_foreign` FOREIGN KEY (`uom_id`) REFERENCES `uoms` (`id`),
  CONSTRAINT `items_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `items`
--

LOCK TABLES `items` WRITE;
/*!40000 ALTER TABLE `items` DISABLE KEYS */;
/*!40000 ALTER TABLE `items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lead_sources`
--

DROP TABLE IF EXISTS `lead_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lead_sources` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lead_sources_name_unique` (`name`),
  KEY `lead_sources_created_by_foreign` (`created_by`),
  KEY `lead_sources_updated_by_foreign` (`updated_by`),
  KEY `lead_sources_is_active_sort_order_name_index` (`is_active`,`sort_order`,`name`),
  CONSTRAINT `lead_sources_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lead_sources_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lead_sources`
--

LOCK TABLES `lead_sources` WRITE;
/*!40000 ALTER TABLE `lead_sources` DISABLE KEYS */;
INSERT INTO `lead_sources` VALUES (1,'Website',1,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(2,'Google',2,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(3,'Instagram',3,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(4,'Facebook',4,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(5,'LinkedIn',5,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(6,'WhatsApp',6,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(7,'Phone Call',7,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(8,'Email',8,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(9,'Existing Client',9,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(10,'Referral',10,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(11,'BNI',11,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(12,'Event/Exhibition',12,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(13,'Architect',13,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(14,'Interior Designer',14,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(15,'Facility Management Company',15,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(16,'Builder',16,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(17,'Direct Visit',17,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(18,'Other',18,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40');
/*!40000 ALTER TABLE `lead_sources` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_10_07_000001_add_admin_fields_to_users_table',1),(5,'2026_10_07_000002_create_units_tables',1),(6,'2026_10_07_000003_create_activity_logs_table',1),(7,'2026_10_07_220903_create_permission_tables',1),(8,'2026_10_08_000001_create_financial_years_and_number_series_tables',2),(9,'2026_10_08_000002_create_currencies_and_parties_tables',2),(10,'2026_10_08_000003_create_prospects_table',2),(11,'2026_10_08_000004_create_quotations_tables',3),(12,'2026_10_08_000005_add_list_and_report_indexes',4),(13,'2026_10_13_000001_create_crm_masters_tables',5),(14,'2026_10_13_000002_create_enquiries_site_visits_activities_tables',5),(15,'2026_10_13_000003_add_crm_quotation_fields',5),(16,'2026_10_13_000004_create_customer_pos_and_documents_tables',5),(17,'2026_10_14_000001_add_crm_list_and_dashboard_indexes',6),(18,'2026_10_14_000002_add_contact_no_digits_to_enquiries',7),(19,'2026_10_14_000003_create_module_settings_and_contacts_tables',8),(20,'2026_10_15_000001_create_sales_tables',9);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_permissions`
--

LOCK TABLES `model_has_permissions` WRITE;
/*!40000 ALTER TABLE `model_has_permissions` DISABLE KEYS */;
INSERT INTO `model_has_permissions` VALUES (2,'user',2),(3,'user',2),(4,'user',2),(5,'user',2),(6,'user',2),(7,'user',2),(8,'user',2),(9,'user',2),(56,'user',2),(57,'user',2),(58,'user',2);
/*!40000 ALTER TABLE `model_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_roles`
--

DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_roles`
--

LOCK TABLES `model_has_roles` WRITE;
/*!40000 ALTER TABLE `model_has_roles` DISABLE KEYS */;
INSERT INTO `model_has_roles` VALUES (1,'user',1),(4,'user',2),(4,'user',3),(6,'user',4);
/*!40000 ALTER TABLE `model_has_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `module_settings`
--

DROP TABLE IF EXISTS `module_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `module_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `module` varchar(40) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `module_settings_module_unique` (`module`),
  KEY `module_settings_updated_by_foreign` (`updated_by`),
  CONSTRAINT `module_settings_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `module_settings`
--

LOCK TABLES `module_settings` WRITE;
/*!40000 ALTER TABLE `module_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `module_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `number_series`
--

DROP TABLE IF EXISTS `number_series`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `number_series` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `document_type` varchar(30) NOT NULL,
  `prefix` varchar(10) NOT NULL,
  `next_number` int(10) unsigned NOT NULL DEFAULT 1,
  `unit_id` bigint(20) unsigned NOT NULL,
  `financial_year_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `number_series_document_type_unit_id_financial_year_id_unique` (`document_type`,`unit_id`,`financial_year_id`),
  KEY `number_series_unit_id_foreign` (`unit_id`),
  KEY `number_series_financial_year_id_foreign` (`financial_year_id`),
  CONSTRAINT `number_series_financial_year_id_foreign` FOREIGN KEY (`financial_year_id`) REFERENCES `financial_years` (`id`),
  CONSTRAINT `number_series_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `number_series`
--

LOCK TABLES `number_series` WRITE;
/*!40000 ALTER TABLE `number_series` DISABLE KEYS */;
INSERT INTO `number_series` VALUES (1,'enquiry','ENQ',3,1,1,'2026-10-16 01:11:33','2026-10-18 04:54:24');
/*!40000 ALTER TABLE `number_series` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `page_settings`
--

DROP TABLE IF EXISTS `page_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `page_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `page` varchar(40) NOT NULL,
  `field` varchar(60) NOT NULL,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_settings_page_field_unique` (`page`,`field`),
  KEY `page_settings_updated_by_foreign` (`updated_by`),
  CONSTRAINT `page_settings_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `page_settings`
--

LOCK TABLES `page_settings` WRITE;
/*!40000 ALTER TABLE `page_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `page_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `parties`
--

DROP TABLE IF EXISTS `parties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `parties` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `type` enum('Customer','Supplier','Both') NOT NULL,
  `billing_address` text DEFAULT NULL,
  `shipping_address` text DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `opening_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `opening_balance_type` enum('DR','CR') NOT NULL DEFAULT 'DR',
  `currency_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `parties_gstin_unique` (`gstin`),
  KEY `parties_currency_id_foreign` (`currency_id`),
  KEY `parties_created_by_foreign` (`created_by`),
  KEY `parties_updated_by_foreign` (`updated_by`),
  KEY `parties_type_status_index` (`type`,`status`),
  KEY `parties_name_index` (`name`),
  CONSTRAINT `parties_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `parties_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `parties_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parties`
--

LOCK TABLES `parties` WRITE;
/*!40000 ALTER TABLE `parties` DISABLE KEYS */;
/*!40000 ALTER TABLE `parties` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
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
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'dashboard.view','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(2,'quotation.view','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(3,'quotation.add','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(4,'quotation.edit','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(5,'quotation.delete','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(6,'prospect.view','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(7,'prospect.add','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(8,'prospect.edit','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(9,'prospect.delete','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(10,'tax-invoice.view','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(11,'tax-invoice.add','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(12,'tax-invoice.edit','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(13,'tax-invoice.delete','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(14,'sales-order.view','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(15,'sales-order.add','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(16,'sales-order.edit','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(17,'sales-order.delete','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(18,'grn.view','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(19,'grn.add','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(20,'grn.edit','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(21,'grn.delete','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(22,'purchase-order.view','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(23,'purchase-order.add','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(24,'purchase-order.edit','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(25,'purchase-order.delete','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(26,'stock-transfer.view','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(27,'stock-transfer.add','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(28,'stock-transfer.edit','web','2026-10-08 00:15:46','2026-10-08 00:15:46'),(29,'stock-transfer.delete','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(30,'party-ledger.view','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(31,'accounts-entry.view','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(32,'accounts-entry.add','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(33,'accounts-entry.edit','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(34,'accounts-entry.delete','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(35,'users.view','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(36,'users.add','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(37,'users.edit','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(38,'users.delete','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(39,'access-rights.view','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(40,'access-rights.add','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(41,'access-rights.edit','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(42,'access-rights.delete','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(43,'approval.view','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(44,'approval.edit','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(45,'dashboard-config.view','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(46,'dashboard-config.edit','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(47,'config.view','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(48,'config.edit','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(49,'page-setup.view','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(50,'page-setup.edit','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(51,'masters.view','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(52,'masters.add','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(53,'masters.edit','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(54,'masters.delete','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(55,'activity-log.view','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(56,'reports-crm.view','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(57,'reports-sales.view','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(58,'reports-purchase.view','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(59,'reports-accounts.view','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(60,'enquiry.view','web','2026-10-13 17:42:42','2026-10-13 17:42:42'),(61,'enquiry.add','web','2026-10-13 17:42:42','2026-10-13 17:42:42'),(62,'enquiry.edit','web','2026-10-13 17:42:42','2026-10-13 17:42:42'),(63,'enquiry.delete','web','2026-10-13 17:42:43','2026-10-13 17:42:43'),(64,'site-visit.view','web','2026-10-13 17:42:43','2026-10-13 17:42:43'),(65,'site-visit.add','web','2026-10-13 17:42:43','2026-10-13 17:42:43'),(66,'site-visit.edit','web','2026-10-13 17:42:43','2026-10-13 17:42:43'),(67,'site-visit.delete','web','2026-10-13 17:42:43','2026-10-13 17:42:43'),(68,'quotation-approval.view','web','2026-10-13 17:42:43','2026-10-13 17:42:43'),(69,'quotation-approval.edit','web','2026-10-13 17:42:43','2026-10-13 17:42:43'),(70,'customer-po.view','web','2026-10-13 17:42:43','2026-10-13 17:42:43'),(71,'customer-po.add','web','2026-10-13 17:42:43','2026-10-13 17:42:43'),(72,'customer-po.edit','web','2026-10-13 17:42:43','2026-10-13 17:42:43'),(73,'customer-po.delete','web','2026-10-13 17:42:43','2026-10-13 17:42:43'),(74,'contact.view','web','2026-10-14 16:53:37','2026-10-14 16:53:37'),(75,'contact.add','web','2026-10-14 16:53:37','2026-10-14 16:53:37'),(76,'contact.edit','web','2026-10-14 16:53:37','2026-10-14 16:53:37'),(77,'contact.delete','web','2026-10-14 16:53:37','2026-10-14 16:53:37'),(78,'sales-order-approval.view','web','2026-10-15 03:13:41','2026-10-15 03:13:41'),(79,'sales-order-approval.edit','web','2026-10-15 03:13:41','2026-10-15 03:13:41');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prospects`
--

DROP TABLE IF EXISTS `prospects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `prospects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `market` enum('Domestic','Export') NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `phone_1` varchar(20) NOT NULL,
  `phone_2` varchar(20) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `industry_type` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `unit_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prospects_created_by_foreign` (`created_by`),
  KEY `prospects_updated_by_foreign` (`updated_by`),
  KEY `prospects_created_at_index` (`created_at`),
  KEY `prospects_name_index` (`name`),
  KEY `prospects_market_index` (`market`),
  KEY `prospects_unit_created_index` (`unit_id`,`created_at`),
  KEY `prospects_phone_1_index` (`phone_1`),
  CONSTRAINT `prospects_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prospects_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prospects_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prospects`
--

LOCK TABLES `prospects` WRITE;
/*!40000 ALTER TABLE `prospects` DISABLE KEYS */;
INSERT INTO `prospects` VALUES (1,'Satish','Domestic','Satish',NULL,'9852854774',NULL,'satish@gmail.com',NULL,NULL,NULL,1,1,1,'2026-10-14 18:19:18','2026-10-14 18:19:18',NULL),(2,'Ganesh Patil','Domestic','9874563214',NULL,'9874563214',NULL,NULL,NULL,NULL,NULL,1,1,1,'2026-10-16 01:00:36','2026-10-16 01:00:36',NULL);
/*!40000 ALTER TABLE `prospects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotation_costs`
--

DROP TABLE IF EXISTS `quotation_costs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quotation_costs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `quotation_id` bigint(20) unsigned NOT NULL,
  `material_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `plant_production_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `planter_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `labour_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `transportation_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `installation_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `maintenance_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `other_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `margin_percent` decimal(7,2) NOT NULL DEFAULT 0.00,
  `margin_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `proposed_selling_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quotation_costs_quotation_id_unique` (`quotation_id`),
  CONSTRAINT `quotation_costs_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotation_costs`
--

LOCK TABLES `quotation_costs` WRITE;
/*!40000 ALTER TABLE `quotation_costs` DISABLE KEYS */;
/*!40000 ALTER TABLE `quotation_costs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotation_lines`
--

DROP TABLE IF EXISTS `quotation_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quotation_lines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `quotation_id` bigint(20) unsigned NOT NULL,
  `line_no` smallint(5) unsigned NOT NULL,
  `item_no` varchar(50) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `qty` decimal(15,3) DEFAULT NULL,
  `uom` varchar(20) DEFAULT NULL,
  `annual_volume` decimal(15,3) DEFAULT NULL,
  `rate` decimal(15,4) DEFAULT NULL,
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `tax_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sample_target_date` date DEFAULT NULL,
  `production_target_date` date DEFAULT NULL,
  `technical_details` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quotation_lines_quotation_id_line_no_unique` (`quotation_id`,`line_no`),
  CONSTRAINT `quotation_lines_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotation_lines`
--

LOCK TABLES `quotation_lines` WRITE;
/*!40000 ALTER TABLE `quotation_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `quotation_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotations`
--

DROP TABLE IF EXISTS `quotations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quotations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `unit_id` bigint(20) unsigned NOT NULL,
  `financial_year_id` bigint(20) unsigned NOT NULL,
  `enquiry_id` bigint(20) unsigned DEFAULT NULL,
  `enquiry_no` varchar(30) NOT NULL,
  `quotation_no` varchar(30) NOT NULL,
  `revision_no` smallint(5) unsigned NOT NULL DEFAULT 0,
  `quotation_date` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `previous_revision_id` bigint(20) unsigned DEFAULT NULL,
  `is_latest` tinyint(1) NOT NULL DEFAULT 1,
  `party_id` bigint(20) unsigned DEFAULT NULL,
  `prospect_id` bigint(20) unsigned DEFAULT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `site` varchar(255) DEFAULT NULL,
  `source_of_enquiry` varchar(100) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `project_name` varchar(150) DEFAULT NULL,
  `sales_person_id` bigint(20) unsigned NOT NULL,
  `prepared_by` bigint(20) unsigned DEFAULT NULL,
  `enquiry_receipt_date` date NOT NULL,
  `currency_id` bigint(20) unsigned NOT NULL,
  `followup_status` enum('New','Contacted','Quotation sent','Negotiation','Won','Lost') NOT NULL DEFAULT 'New',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `payment_terms` varchar(255) DEFAULT NULL,
  `delivery_terms` varchar(255) DEFAULT NULL,
  `tax_terms` varchar(150) DEFAULT NULL,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('Draft','Internal Approval','Approved','Sent','Customer Viewed/Feedback','Negotiation','Accepted','Rejected','Expired','Hold') NOT NULL DEFAULT 'Draft',
  `status_reason` varchar(255) DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quotations_number_revision_unique` (`unit_id`,`financial_year_id`,`quotation_no`,`revision_no`),
  KEY `quotations_financial_year_id_foreign` (`financial_year_id`),
  KEY `quotations_previous_revision_id_foreign` (`previous_revision_id`),
  KEY `quotations_party_id_foreign` (`party_id`),
  KEY `quotations_prospect_id_foreign` (`prospect_id`),
  KEY `quotations_sales_person_id_foreign` (`sales_person_id`),
  KEY `quotations_currency_id_foreign` (`currency_id`),
  KEY `quotations_created_by_foreign` (`created_by`),
  KEY `quotations_updated_by_foreign` (`updated_by`),
  KEY `quotations_unit_year_latest_index` (`unit_id`,`financial_year_id`,`is_latest`),
  KEY `quotations_enquiry_receipt_date_index` (`enquiry_receipt_date`),
  KEY `quotations_followup_status_index` (`followup_status`),
  KEY `quotations_latest_unit_date_index` (`is_latest`,`unit_id`,`enquiry_receipt_date`),
  KEY `quotations_enquiry_id_foreign` (`enquiry_id`),
  KEY `quotations_prepared_by_foreign` (`prepared_by`),
  KEY `quotations_unit_latest_status_index` (`unit_id`,`is_latest`,`status`),
  KEY `quotations_unit_latest_date_index` (`unit_id`,`is_latest`,`quotation_date`),
  CONSTRAINT `quotations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quotations_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `quotations_enquiry_id_foreign` FOREIGN KEY (`enquiry_id`) REFERENCES `enquiries` (`id`),
  CONSTRAINT `quotations_financial_year_id_foreign` FOREIGN KEY (`financial_year_id`) REFERENCES `financial_years` (`id`),
  CONSTRAINT `quotations_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`),
  CONSTRAINT `quotations_prepared_by_foreign` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quotations_previous_revision_id_foreign` FOREIGN KEY (`previous_revision_id`) REFERENCES `quotations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quotations_prospect_id_foreign` FOREIGN KEY (`prospect_id`) REFERENCES `prospects` (`id`),
  CONSTRAINT `quotations_sales_person_id_foreign` FOREIGN KEY (`sales_person_id`) REFERENCES `users` (`id`),
  CONSTRAINT `quotations_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`),
  CONSTRAINT `quotations_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotations`
--

LOCK TABLES `quotations` WRITE;
/*!40000 ALTER TABLE `quotations` DISABLE KEYS */;
/*!40000 ALTER TABLE `quotations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_has_permissions`
--

DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_has_permissions`
--

LOCK TABLES `role_has_permissions` WRITE;
/*!40000 ALTER TABLE `role_has_permissions` DISABLE KEYS */;
INSERT INTO `role_has_permissions` VALUES (1,1),(1,2),(1,3),(1,4),(1,6),(2,1),(2,2),(2,5),(2,6),(3,1),(3,2),(3,5),(4,1),(4,2),(4,5),(5,1),(5,2),(5,5),(6,1),(6,2),(6,5),(7,1),(7,2),(7,5),(8,1),(8,2),(8,5),(9,1),(9,2),(9,5),(10,1),(10,2),(10,6),(11,1),(11,2),(12,1),(12,2),(13,1),(13,2),(14,1),(14,2),(15,1),(15,2),(16,1),(16,2),(17,1),(17,2),(18,1),(18,3),(19,1),(19,3),(20,1),(20,3),(21,1),(21,3),(22,1),(22,3),(23,1),(23,3),(24,1),(24,3),(25,1),(25,3),(26,1),(26,3),(27,1),(27,3),(28,1),(28,3),(29,1),(29,3),(30,1),(30,4),(31,1),(31,4),(32,1),(32,4),(33,1),(33,4),(34,1),(34,4),(35,1),(36,1),(37,1),(38,1),(39,1),(40,1),(41,1),(42,1),(43,1),(44,1),(45,1),(46,1),(47,1),(48,1),(49,1),(50,1),(51,1),(52,1),(53,1),(54,1),(55,1),(56,1),(56,2),(57,1),(57,2),(58,1),(58,3),(59,1),(59,4),(60,2),(61,2),(62,2),(63,2),(64,2),(65,2),(66,2),(67,2),(70,2),(71,2),(72,2),(73,2),(74,2),(75,2),(76,2),(77,2);
/*!40000 ALTER TABLE `role_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Admin','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(2,'Sales','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(3,'Purchase','web','2026-10-08 00:15:47','2026-10-08 00:15:47'),(4,'Accounts','web','2026-10-08 00:15:48','2026-10-08 00:15:48'),(5,'tEMP','web','2026-10-08 01:01:49','2026-10-08 01:01:49'),(6,'TempRole','web','2026-10-08 16:51:48','2026-10-08 16:51:48');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_invoice_lines`
--

DROP TABLE IF EXISTS `sales_invoice_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_invoice_lines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sales_invoice_id` bigint(20) unsigned NOT NULL,
  `line_no` smallint(5) unsigned NOT NULL,
  `sales_order_line_id` bigint(20) unsigned DEFAULT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `hsn_code` varchar(10) DEFAULT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `rate` decimal(15,4) NOT NULL,
  `taxable_value` decimal(15,2) NOT NULL,
  `cgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `sgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `igst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `cgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_invoice_lines_sales_invoice_id_line_no_index` (`sales_invoice_id`,`line_no`),
  KEY `sales_invoice_lines_sales_order_line_id_index` (`sales_order_line_id`),
  KEY `sales_invoice_lines_item_id_index` (`item_id`),
  CONSTRAINT `sales_invoice_lines_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  CONSTRAINT `sales_invoice_lines_sales_invoice_id_foreign` FOREIGN KEY (`sales_invoice_id`) REFERENCES `sales_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_invoice_lines_sales_order_line_id_foreign` FOREIGN KEY (`sales_order_line_id`) REFERENCES `sales_order_lines` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_invoice_lines`
--

LOCK TABLES `sales_invoice_lines` WRITE;
/*!40000 ALTER TABLE `sales_invoice_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_invoice_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_invoices`
--

DROP TABLE IF EXISTS `sales_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `unit_id` bigint(20) unsigned NOT NULL,
  `financial_year_id` bigint(20) unsigned NOT NULL,
  `invoice_type` enum('Sales','Credit','Debit') NOT NULL,
  `invoice_no` varchar(30) NOT NULL,
  `invoice_date` date NOT NULL,
  `party_id` bigint(20) unsigned NOT NULL,
  `sales_order_id` bigint(20) unsigned DEFAULT NULL,
  `currency_id` bigint(20) unsigned DEFAULT NULL,
  `shipping_address` text NOT NULL,
  `mode_of_transport` enum('Road','Rail','Air','Ship') DEFAULT NULL,
  `transporter_id` bigint(20) unsigned DEFAULT NULL,
  `vehicle_no` varchar(20) DEFAULT NULL,
  `distance_km` decimal(8,1) DEFAULT NULL,
  `pf_charges` decimal(15,2) NOT NULL DEFAULT 0.00,
  `supply_type` enum('Intra-state','Inter-state','Export') NOT NULL,
  `taxable_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('Active','Cancelled') NOT NULL DEFAULT 'Active',
  `cancel_reason` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_invoices_number_unique` (`unit_id`,`financial_year_id`,`invoice_type`,`invoice_no`),
  KEY `sales_invoices_financial_year_id_foreign` (`financial_year_id`),
  KEY `sales_invoices_currency_id_foreign` (`currency_id`),
  KEY `sales_invoices_transporter_id_foreign` (`transporter_id`),
  KEY `sales_invoices_created_by_foreign` (`created_by`),
  KEY `sales_invoices_updated_by_foreign` (`updated_by`),
  KEY `sales_invoices_unit_date_index` (`unit_id`,`financial_year_id`,`invoice_date`),
  KEY `sales_invoices_party_date_index` (`party_id`,`invoice_date`),
  KEY `sales_invoices_order_index` (`sales_order_id`,`status`),
  CONSTRAINT `sales_invoices_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_invoices_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `sales_invoices_financial_year_id_foreign` FOREIGN KEY (`financial_year_id`) REFERENCES `financial_years` (`id`),
  CONSTRAINT `sales_invoices_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`),
  CONSTRAINT `sales_invoices_sales_order_id_foreign` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`),
  CONSTRAINT `sales_invoices_transporter_id_foreign` FOREIGN KEY (`transporter_id`) REFERENCES `transporters` (`id`),
  CONSTRAINT `sales_invoices_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`),
  CONSTRAINT `sales_invoices_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_invoices`
--

LOCK TABLES `sales_invoices` WRITE;
/*!40000 ALTER TABLE `sales_invoices` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_order_lines`
--

DROP TABLE IF EXISTS `sales_order_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_order_lines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sales_order_id` bigint(20) unsigned NOT NULL,
  `line_no` smallint(5) unsigned NOT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `rate` decimal(15,4) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `delivery_date` date NOT NULL,
  `invoiced_quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_order_lines_item_id_foreign` (`item_id`),
  KEY `sales_order_lines_sales_order_id_line_no_index` (`sales_order_id`,`line_no`),
  CONSTRAINT `sales_order_lines_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  CONSTRAINT `sales_order_lines_sales_order_id_foreign` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_order_lines`
--

LOCK TABLES `sales_order_lines` WRITE;
/*!40000 ALTER TABLE `sales_order_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_order_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_orders`
--

DROP TABLE IF EXISTS `sales_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `unit_id` bigint(20) unsigned NOT NULL,
  `financial_year_id` bigint(20) unsigned NOT NULL,
  `so_no` varchar(30) NOT NULL,
  `party_id` bigint(20) unsigned NOT NULL,
  `po_number` varchar(60) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `revision_no` varchar(10) DEFAULT NULL,
  `revision_date` date DEFAULT NULL,
  `status` enum('Draft','Pending approval','Approved','Rejected') NOT NULL DEFAULT 'Draft',
  `status_reason` varchar(500) DEFAULT NULL,
  `total_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_orders_unit_id_financial_year_id_so_no_unique` (`unit_id`,`financial_year_id`,`so_no`),
  KEY `sales_orders_financial_year_id_foreign` (`financial_year_id`),
  KEY `sales_orders_created_by_foreign` (`created_by`),
  KEY `sales_orders_updated_by_foreign` (`updated_by`),
  KEY `sales_orders_unit_date_index` (`unit_id`,`financial_year_id`,`start_date`),
  KEY `sales_orders_party_status_index` (`party_id`,`status`,`deleted_at`),
  KEY `sales_orders_unit_status_index` (`unit_id`,`status`),
  KEY `sales_orders_unit_po_index` (`unit_id`,`po_number`),
  CONSTRAINT `sales_orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_orders_financial_year_id_foreign` FOREIGN KEY (`financial_year_id`) REFERENCES `financial_years` (`id`),
  CONSTRAINT `sales_orders_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`),
  CONSTRAINT `sales_orders_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`),
  CONSTRAINT `sales_orders_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_orders`
--

LOCK TABLES `sales_orders` WRITE;
/*!40000 ALTER TABLE `sales_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_categories`
--

DROP TABLE IF EXISTS `service_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_categories_name_unique` (`name`),
  KEY `service_categories_created_by_foreign` (`created_by`),
  KEY `service_categories_updated_by_foreign` (`updated_by`),
  KEY `service_categories_is_active_sort_order_name_index` (`is_active`,`sort_order`,`name`),
  CONSTRAINT `service_categories_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `service_categories_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_categories`
--

LOCK TABLES `service_categories` WRITE;
/*!40000 ALTER TABLE `service_categories` DISABLE KEYS */;
INSERT INTO `service_categories` VALUES (1,'Plant Rental',1,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(2,'Plant AMC',2,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(3,'Landscape Design',3,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(4,'Landscape Development',4,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(5,'Garden Maintenance',5,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(6,'Vertical Garden / Green Wall',6,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(7,'Terrace Garden',7,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(8,'GD DECO',8,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(9,'Plant Sale',9,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(10,'Planters',10,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(11,'Corporate Plantation',11,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(12,'Other',12,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40');
/*!40000 ALTER TABLE `service_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('WXP7J1F3AQUD0zKvQsj8uecopGrg091ZBXeq1lLK',1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','YTo2OntzOjY6Il90b2tlbiI7czo0MDoiSmptMTdzNjNIaThpOUk5MTBPU1Q3RG1SRXhLUlRuWEFGckUyS0dwaSI7czozOiJ1cmwiO2E6MDp7fXM6OToiX3ByZXZpb3VzIjthOjI6e3M6MzoidXJsIjtzOjEyNDoiaHR0cDovL2xvY2FsaG9zdC9udXJzZXJ5LWFwcC9yZXBvcnRzL2NybS9sZWFkLWFnZWluZy9kb3dubG9hZD9mb3JtYXQ9Y3N2JmZyb209MDElMkYwNCUyRjE4ODgmcmF0aW5nPSZ0bz0zMSUyRjAzJTJGMjAyNyZ1bml0PSI7czo1OiJyb3V0ZSI7czoyMDoicmVwb3J0cy5jcm0uZG93bmxvYWQiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxO3M6MTc6InBhc3N3b3JkX2hhc2hfd2ViIjtzOjY0OiIwZjI0MWY1MmI4NzM3NWUzOWE5M2VjYTNiZDNiZjAzZWQ1NTIwODhkNGI1ZDc5OTVlOGJlOGQyZTc1NmE0NGI0Ijt9',1792300340);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `site_visits`
--

DROP TABLE IF EXISTS `site_visits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `site_visits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `unit_id` bigint(20) unsigned NOT NULL,
  `enquiry_id` bigint(20) unsigned NOT NULL,
  `visit_at` datetime NOT NULL,
  `poc` varchar(150) DEFAULT NULL,
  `technical_employee_id` bigint(20) unsigned DEFAULT NULL,
  `site_address` text DEFAULT NULL,
  `area_measurements` varchar(255) DEFAULT NULL,
  `floors` varchar(100) DEFAULT NULL,
  `indoor_outdoor_area` varchar(255) DEFAULT NULL,
  `existing_plants` text DEFAULT NULL,
  `existing_planters` text DEFAULT NULL,
  `lighting_condition` varchar(150) DEFAULT NULL,
  `water_availability` varchar(150) DEFAULT NULL,
  `access_restrictions` varchar(255) DEFAULT NULL,
  `customer_requirement` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `next_action` varchar(255) DEFAULT NULL,
  `status` enum('Planned','Done') NOT NULL DEFAULT 'Planned',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `site_visits_technical_employee_id_foreign` (`technical_employee_id`),
  KEY `site_visits_created_by_foreign` (`created_by`),
  KEY `site_visits_updated_by_foreign` (`updated_by`),
  KEY `site_visits_unit_due_index` (`unit_id`,`status`,`visit_at`),
  KEY `site_visits_unit_visit_index` (`unit_id`,`visit_at`),
  KEY `site_visits_enquiry_visit_index` (`enquiry_id`,`visit_at`),
  CONSTRAINT `site_visits_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `site_visits_enquiry_id_foreign` FOREIGN KEY (`enquiry_id`) REFERENCES `enquiries` (`id`),
  CONSTRAINT `site_visits_technical_employee_id_foreign` FOREIGN KEY (`technical_employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `site_visits_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`),
  CONSTRAINT `site_visits_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_visits`
--

LOCK TABLES `site_visits` WRITE;
/*!40000 ALTER TABLE `site_visits` DISABLE KEYS */;
INSERT INTO `site_visits` VALUES (1,1,2,'2026-10-19 11:00:00','tEST, 7894561230',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Done',1,1,'2026-10-18 04:59:34','2026-10-18 04:59:34',NULL);
/*!40000 ALTER TABLE `site_visits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_movements`
--

DROP TABLE IF EXISTS `stock_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_movements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `unit_id` bigint(20) unsigned NOT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `movement_date` date NOT NULL,
  `quantity_in` decimal(15,3) NOT NULL DEFAULT 0.000,
  `quantity_out` decimal(15,3) NOT NULL DEFAULT 0.000,
  `source_type` varchar(40) NOT NULL,
  `source_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_movements_item_id_foreign` (`item_id`),
  KEY `stock_movements_unit_item_date_index` (`unit_id`,`item_id`,`movement_date`),
  KEY `stock_movements_source_index` (`source_type`,`source_id`),
  CONSTRAINT `stock_movements_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  CONSTRAINT `stock_movements_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_movements`
--

LOCK TABLES `stock_movements` WRITE;
/*!40000 ALTER TABLE `stock_movements` DISABLE KEYS */;
/*!40000 ALTER TABLE `stock_movements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tax_structures`
--

DROP TABLE IF EXISTS `tax_structures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_structures` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `cgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `sgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `igst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tax_structures_name_unique` (`name`),
  KEY `tax_structures_created_by_foreign` (`created_by`),
  KEY `tax_structures_updated_by_foreign` (`updated_by`),
  CONSTRAINT `tax_structures_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_structures_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tax_structures`
--

LOCK TABLES `tax_structures` WRITE;
/*!40000 ALTER TABLE `tax_structures` DISABLE KEYS */;
INSERT INTO `tax_structures` VALUES (1,'GST 0%',0.00,0.00,0.00,1,NULL,NULL,'2026-10-15 03:13:41','2026-10-15 03:13:41'),(2,'GST 5%',2.50,2.50,5.00,1,NULL,NULL,'2026-10-15 03:13:41','2026-10-15 03:13:41'),(3,'GST 12%',6.00,6.00,12.00,1,NULL,NULL,'2026-10-15 03:13:41','2026-10-15 03:13:41'),(4,'GST 18%',9.00,9.00,18.00,1,NULL,NULL,'2026-10-15 03:13:41','2026-10-15 03:13:41'),(5,'GST 28%',14.00,14.00,28.00,1,NULL,NULL,'2026-10-15 03:13:41','2026-10-15 03:13:41');
/*!40000 ALTER TABLE `tax_structures` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transporters`
--

DROP TABLE IF EXISTS `transporters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transporters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transporters_name_unique` (`name`),
  KEY `transporters_created_by_foreign` (`created_by`),
  KEY `transporters_updated_by_foreign` (`updated_by`),
  CONSTRAINT `transporters_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transporters_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transporters`
--

LOCK TABLES `transporters` WRITE;
/*!40000 ALTER TABLE `transporters` DISABLE KEYS */;
/*!40000 ALTER TABLE `transporters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `unit_user`
--

DROP TABLE IF EXISTS `unit_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `unit_user` (
  `unit_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`unit_id`,`user_id`),
  KEY `unit_user_user_id_index` (`user_id`),
  CONSTRAINT `unit_user_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE,
  CONSTRAINT `unit_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `unit_user`
--

LOCK TABLES `unit_user` WRITE;
/*!40000 ALTER TABLE `unit_user` DISABLE KEYS */;
INSERT INTO `unit_user` VALUES (1,1),(1,2),(1,3),(1,4),(2,1),(2,4);
/*!40000 ALTER TABLE `unit_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `units`
--

DROP TABLE IF EXISTS `units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `units` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `units_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `units`
--

LOCK TABLES `units` WRITE;
/*!40000 ALTER TABLE `units` DISABLE KEYS */;
INSERT INTO `units` VALUES (1,'U1','Unit 1 · Chakan','2026-10-08 00:15:46','2026-10-08 00:15:46'),(2,'U2','Unit 2 · Bhosari','2026-10-08 00:15:46','2026-10-08 00:15:46');
/*!40000 ALTER TABLE `units` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `uoms`
--

DROP TABLE IF EXISTS `uoms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `uoms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(10) NOT NULL,
  `description` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uoms_code_unique` (`code`),
  KEY `uoms_created_by_foreign` (`created_by`),
  KEY `uoms_updated_by_foreign` (`updated_by`),
  CONSTRAINT `uoms_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `uoms_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `uoms`
--

LOCK TABLES `uoms` WRITE;
/*!40000 ALTER TABLE `uoms` DISABLE KEYS */;
INSERT INTO `uoms` VALUES (1,'NOS','Numbers',1,NULL,NULL,'2026-10-15 03:13:41','2026-10-15 03:13:41'),(2,'KG','Kilogram',1,NULL,NULL,'2026-10-15 03:13:41','2026-10-15 03:13:41'),(3,'MTR','Metre',1,NULL,NULL,'2026-10-15 03:13:41','2026-10-15 03:13:41'),(4,'SET','Set',1,NULL,NULL,'2026-10-15 03:13:41','2026-10-15 03:13:41'),(5,'LTR','Litre',1,NULL,NULL,'2026-10-15 03:13:41','2026-10-15 03:13:41'),(6,'SQM','Square metre',1,NULL,NULL,'2026-10-15 03:13:41','2026-10-15 03:13:41');
/*!40000 ALTER TABLE `uoms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `mobile` varchar(10) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_mobile_unique` (`mobile`),
  KEY `users_created_by_foreign` (`created_by`),
  KEY `users_updated_by_foreign` (`updated_by`),
  KEY `users_status_index` (`status`),
  KEY `users_name_index` (`name`),
  KEY `users_last_login_at_index` (`last_login_at`),
  CONSTRAINT `users_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Administrator','admin@nursery.local','9800000000',NULL,'$2y$12$egkFraYLoVeDOeiKFBhBM.rdZRAlzHcjSDLPQWMYGbzRaw4MGkX4i','Active','2026-10-18 04:47:53',NULL,NULL,NULL,'2026-10-08 00:15:48','2026-10-18 04:47:53',NULL),(2,'Satish','satishworking2026@gmail.com','9123456780',NULL,'$2y$12$8NXB.9Rg.IUfysljTlCVMuCTOd/SnBQ1La69TAGIJnD.BFggFy3jO','Active','2026-10-08 16:21:05',NULL,1,1,'2026-10-08 01:01:12','2026-10-08 16:21:05',NULL),(3,'test@gmail.com','test@gmail.com','9876543210',NULL,'$2y$12$Wf3xohHuDXXZkNpGebHdteJUoxgDKIx9sAYUGU4Bce.v71GU39tgi','Active','2026-10-08 16:40:40',NULL,1,1,'2026-10-08 16:28:36','2026-10-08 16:40:40',NULL),(4,'Temp Temp','test1@gmail.com','9879876543',NULL,'$2y$12$WlOAI3RXwSEIB17fs.oHy.jNMNOIzJT4uUtZyw.4Zh39a1tgMS9aO','Active','2026-10-08 16:58:27',NULL,1,1,'2026-10-08 16:57:36','2026-10-08 16:58:27',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'nursery'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-18 10:43:00
