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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activities`
--

LOCK TABLES `activities` WRITE;
/*!40000 ALTER TABLE `activities` DISABLE KEYS */;
INSERT INTO `activities` VALUES (1,5,'Call','2026-10-20','Need to call again','Call','2026-10-21',1,'2026-10-19 15:40:19','2026-10-19 15:40:19'),(2,5,'Call','2026-10-21','Call done','Call','2026-10-22',1,'2026-10-19 15:41:19','2026-10-19 15:41:19'),(3,6,'Call','2026-10-19','Done','Call','2026-10-21',1,'2026-10-19 18:25:14','2026-10-19 18:25:14'),(4,6,'Call','2026-10-22','dONE 22','wHATS app','2026-10-24',1,'2026-10-19 18:26:05','2026-10-19 18:26:05');
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
  `changes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=124 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9444','2026-10-08 00:18:06'),(2,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9444','2026-10-08 00:18:18'),(3,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9444','2026-10-08 00:18:48'),(4,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 00:50:07'),(5,1,'Administrator','user.created','user',2,'Satish (satishworking2026@gmail.com)','Created user Satish','{\"fields\":{\"Name\":\"Satish\",\"Login email\":\"satishworking2026@gmail.com\",\"Mobile\":\"+91 91234 56780\",\"Status\":\"Active\",\"Units allowed\":\"Unit 1 \\u00b7 Chakan\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 01:01:12'),(6,1,'Administrator','user.role_assigned','user',2,'Satish (satishworking2026@gmail.com)','Assigned role Accounts to Satish','{\"role\":{\"old\":null,\"new\":\"Accounts\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 01:01:12'),(7,1,'Administrator','user.permissions_updated','user',2,'Satish (satishworking2026@gmail.com)','Gave Satish 32 extra permission(s)','{\"added\":[\"Quotation \\u00b7 View\",\"Quotation \\u00b7 Add\",\"Quotation \\u00b7 Edit\",\"Quotation \\u00b7 Delete\",\"Prospect \\u00b7 View\",\"Prospect \\u00b7 Add\",\"Prospect \\u00b7 Edit\",\"Prospect \\u00b7 Delete\",\"Tax invoice \\u00b7 View\",\"Tax invoice \\u00b7 Add\",\"Tax invoice \\u00b7 Edit\",\"Tax invoice \\u00b7 Delete\",\"Sales order \\u00b7 View\",\"Sales order \\u00b7 Add\",\"Sales order \\u00b7 Edit\",\"Sales order \\u00b7 Delete\",\"GRN entry \\u00b7 View\",\"GRN entry \\u00b7 Add\",\"GRN entry \\u00b7 Edit\",\"GRN entry \\u00b7 Delete\",\"Purchase order \\u00b7 View\",\"Purchase order \\u00b7 Add\",\"Purchase order \\u00b7 Edit\",\"Purchase order \\u00b7 Delete\",\"Stock transfer \\u00b7 View\",\"Stock transfer \\u00b7 Add\",\"Stock transfer \\u00b7 Edit\",\"Stock transfer \\u00b7 Delete\",\"Users list \\u00b7 View\",\"CRM reports \\u00b7 View\",\"Sales reports \\u00b7 View\",\"Purchase reports \\u00b7 View\"]}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 01:01:12'),(8,1,'Administrator','role.created','role',5,'tEMP','Created role tEMP',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 01:01:49'),(9,1,'Administrator','role.permissions_updated','role',5,'tEMP','Gave role tEMP 8 permission(s)','{\"added\":[\"Quotation \\u00b7 View\",\"Quotation \\u00b7 Add\",\"Quotation \\u00b7 Edit\",\"Quotation \\u00b7 Delete\",\"Prospect \\u00b7 View\",\"Prospect \\u00b7 Add\",\"Prospect \\u00b7 Edit\",\"Prospect \\u00b7 Delete\"]}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 01:01:49'),(10,1,'Administrator','user.permissions_updated','user',2,'Satish (satishworking2026@gmail.com)','Changed extra permissions of Satish','{\"removed\":[\"Tax invoice \\u00b7 View\",\"Tax invoice \\u00b7 Add\",\"Tax invoice \\u00b7 Edit\",\"Tax invoice \\u00b7 Delete\",\"Sales order \\u00b7 View\",\"Sales order \\u00b7 Add\",\"Sales order \\u00b7 Edit\",\"Sales order \\u00b7 Delete\",\"GRN entry \\u00b7 View\",\"GRN entry \\u00b7 Add\",\"GRN entry \\u00b7 Edit\",\"GRN entry \\u00b7 Delete\",\"Purchase order \\u00b7 View\",\"Purchase order \\u00b7 Add\",\"Purchase order \\u00b7 Edit\",\"Purchase order \\u00b7 Delete\",\"Stock transfer \\u00b7 View\",\"Stock transfer \\u00b7 Add\",\"Stock transfer \\u00b7 Edit\",\"Stock transfer \\u00b7 Delete\",\"Users list \\u00b7 View\"]}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 01:11:39'),(11,2,'Satish','auth.login','user',2,'Satish (satishworking2026@gmail.com)','Satish logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 01:12:02'),(12,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9444','2026-10-08 01:48:38'),(13,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/2.19675.0 Chrome/152.0.7977.130 Safari/537.36 MSIX','2026-10-08 02:24:21'),(14,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','','2026-10-08 03:57:08'),(15,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:19:42'),(16,2,'Satish','auth.login','user',2,'Satish (satishworking2026@gmail.com)','Satish logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:21:05'),(17,2,'Satish','auth.logout','user',2,'Satish (satishworking2026@gmail.com)','Satish logged out',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:21:27'),(18,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:21:34'),(19,1,'Administrator','user.created','user',3,'test@gmail.com (test@gmail.com)','Created user test@gmail.com','{\"fields\":{\"Name\":\"test@gmail.com\",\"Login email\":\"test@gmail.com\",\"Mobile\":\"+91 98765 43210\",\"Status\":\"Active\",\"Units allowed\":\"Unit 1 \\u00b7 Chakan\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:28:36'),(20,1,'Administrator','user.role_assigned','user',3,'test@gmail.com (test@gmail.com)','Assigned role Accounts to test@gmail.com','{\"role\":{\"old\":null,\"new\":\"Accounts\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:28:36'),(21,1,'Administrator','auth.logout','user',1,'Administrator (admin@nursery.local)','Administrator logged out',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:40:25'),(22,3,'test@gmail.com','auth.login','user',3,'test@gmail.com (test@gmail.com)','test@gmail.com logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:40:40'),(23,1,'Administrator','auth.logout','user',1,'Administrator (admin@nursery.local)','Administrator logged out',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:49:52'),(24,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:50:11'),(25,1,'Administrator','role.created','role',6,'TempRole','Created role TempRole',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:51:48'),(26,1,'Administrator','role.permissions_updated','role',6,'TempRole','Gave role TempRole 3 permission(s)','{\"added\":[\"Dashboard \\u00b7 View\",\"Quotation \\u00b7 View\",\"Tax invoice \\u00b7 View\"]}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:51:48'),(27,3,'test@gmail.com','auth.logout','user',3,'test@gmail.com (test@gmail.com)','test@gmail.com logged out',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:57:31'),(28,1,'Administrator','user.created','user',4,'Temp Temp (test1@gmail.com)','Created user Temp Temp','{\"fields\":{\"Name\":\"Temp Temp\",\"Login email\":\"test1@gmail.com\",\"Mobile\":\"+91 98798 76543\",\"Status\":\"Active\",\"Units allowed\":\"Unit 1 \\u00b7 Chakan, Unit 2 \\u00b7 Bhosari\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:57:37'),(29,1,'Administrator','user.role_assigned','user',4,'Temp Temp (test1@gmail.com)','Assigned role TempRole to Temp Temp','{\"role\":{\"old\":null,\"new\":\"TempRole\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:57:37'),(30,4,'Temp Temp','auth.login','user',4,'Temp Temp (test1@gmail.com)','Temp Temp logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-08 16:58:27'),(31,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9444','2026-10-13 16:02:46'),(32,NULL,NULL,'auth.failed',NULL,NULL,'admin@arom.com','Login failed for admin@arom.com (unknown email)',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-13 16:04:47'),(33,NULL,NULL,'auth.failed',NULL,NULL,'admin@arom.com','Login failed for admin@arom.com (unknown email)',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-13 16:04:54'),(34,NULL,NULL,'auth.failed',NULL,NULL,'admin@arom.com','Login failed for admin@arom.com (unknown email)',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-13 16:05:00'),(35,NULL,NULL,'auth.failed',NULL,NULL,'admin@arom.com','Login failed for admin@arom.com (unknown email)',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-13 16:05:16'),(36,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-13 16:41:44'),(37,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9444','2026-10-13 16:53:41'),(38,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9444','2026-10-13 16:54:50'),(39,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 18:16:51'),(40,1,'Administrator','prospect.created','prospect',1,'Satish','Added prospect Satish','{\"fields\":{\"Prospect name\":\"Satish\",\"Domestic \\/ Export\":\"Domestic\",\"Contact person\":\"Satish\",\"Position\":null,\"Contact no 1\":\"9852854774\",\"Contact no 2\":null,\"Email\":\"satish@gmail.com\",\"GSTIN\":null,\"Industry type\":null,\"Address\":null}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 18:19:18'),(41,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-16 00:59:06'),(42,1,'Administrator','prospect.created','prospect',2,'Ganesh Patil','Added prospect Ganesh Patil','{\"fields\":{\"Prospect name\":\"Ganesh Patil\",\"Domestic \\/ Export\":\"Domestic\",\"Contact person\":\"9874563214\",\"Position\":null,\"Contact no 1\":\"9874563214\",\"Contact no 2\":null,\"Email\":null,\"GSTIN\":null,\"Industry type\":null,\"Address\":null}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-16 01:00:36'),(43,1,'Administrator','contact.created','contact',1,'contact CT00001 GP New','Added contact CT00001 GP New for Ganesh Patil','{\"fields\":{\"Contact ID\":\"CT00001\",\"Company \\/ Client\":\"Ganesh Patil\",\"Name\":\"GP New\",\"Designation\":\"Design\",\"Department\":\"Department\",\"Mobile\":\"9874563214\",\"WhatsApp\":null,\"Email\":null,\"Alternate number\":null,\"Preferred communication\":null,\"Roles\":\"Decision maker\",\"Notes\":null}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-16 01:01:48'),(44,1,'Administrator','employee.created','employee',1,'Temp (FWRW)','Added employee Temp (FWRW)','{\"fields\":{\"Employee code\":\"FWRW\",\"Name\":\"Temp\",\"Designation\":\"Designation\",\"Department\":\"Department\",\"Mobile\":\"9874563214\",\"Email\":\"test@gmail.com\",\"Date of joining\":\"10\\/10\\/2222\",\"Address\":null,\"Login user\":\"Administrator\",\"Status\":\"Active\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-16 01:11:20'),(45,1,'Administrator','enquiry.created','enquiry',1,'ENQ/26-27/0001 · Ganesh Patil','Added enquiry ENQ/26-27/0001 for Ganesh Patil','{\"fields\":{\"Enquiry date\":\"16\\/10\\/2026\",\"Source\":\"Website\",\"Sub-source \\/ Reference\":null,\"Competitor details\":null,\"Prospect type\":\"Existing Client\",\"Company \\/ Client name\":\"Ganesh Patil\",\"Industry\":null,\"Industry type\":\"Corporate\",\"City\":\"Nashik\",\"Address\":null,\"Website\":null,\"Contact name\":\"GP\",\"Contact no\":\"7894561230\",\"Service type\":\"Corporate Plantation\",\"Approx. requirement\":null,\"Estimated value\":null,\"Expected closing date\":null,\"Lead rating\":\"Hot\",\"Lead assigned to\":\"Temp\",\"Next action\":\"test\",\"Next action date\":\"13\\/04\\/1982\",\"Remarks\":null}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-16 01:11:33'),(46,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-18 04:47:53'),(47,1,'Administrator','enquiry.created','enquiry',2,'ENQ/26-27/0002 · Satish','Added enquiry ENQ/26-27/0002 for Satish','{\"fields\":{\"Enquiry date\":\"18\\/10\\/2026\",\"Source\":\"Website\",\"Sub-source \\/ Reference\":\"Test\",\"Competitor details\":null,\"Prospect type\":\"Existing Client\",\"Company \\/ Client name\":\"Satish\",\"Industry\":null,\"Industry type\":\"Corporate\",\"City\":\"Ahmedabad\",\"Address\":null,\"Website\":null,\"Contact name\":\"tEST\",\"Contact no\":\"7894561230\",\"Service type\":\"Landscape Design\",\"Approx. requirement\":null,\"Estimated value\":null,\"Expected closing date\":null,\"Lead rating\":\"Cold\",\"Lead assigned to\":\"Temp\",\"Next action\":\"test\",\"Next action date\":\"13\\/04\\/1982\",\"Remarks\":null}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-18 04:54:24'),(48,1,'Administrator','site_visit.created','site_visit',1,'Site visit 19/10/2026 11:00 · ENQ/26-27/0002','Added a site visit for ENQ/26-27/0002','{\"fields\":{\"Enquiry\":\"ENQ\\/26-27\\/0002\",\"Visit date and time\":\"19\\/10\\/2026 11:00\",\"POC\":\"tEST, 7894561230\",\"Technical person\":null,\"Site address\":null,\"Area \\/ measurements\":null,\"Floors\":null,\"Indoor \\/ outdoor area\":null,\"Existing plants\":null,\"Existing planters\":null,\"Lighting condition\":null,\"Water availability\":null,\"Access restrictions\":null,\"Customer requirement\":null,\"Remarks\":null,\"Next action\":null,\"Status\":\"Done\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-18 04:59:34'),(49,1,'Administrator','prospect.created','prospect',3,'Suma Soft','Added prospect Suma Soft','{\"fields\":{\"Prospect name\":\"Suma Soft\",\"Domestic \\/ Export\":\"Domestic\",\"Contact person\":\"Rajesh\",\"Position\":\"Devp\",\"Contact no 1\":\"9876543210\",\"Contact no 2\":null,\"Email\":null,\"GSTIN\":null,\"Industry type\":null,\"Address\":null}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-18 05:20:32'),(50,1,'Administrator','contact.created','contact',2,'contact CT00002 Rajesh Contact','Added contact CT00002 Rajesh Contact for Suma Soft','{\"fields\":{\"Contact ID\":\"CT00002\",\"Company \\/ Client\":\"Suma Soft\",\"Name\":\"Rajesh Contact\",\"Designation\":null,\"Department\":null,\"Mobile\":\"9874563214\",\"WhatsApp\":null,\"Email\":null,\"Alternate number\":null,\"Preferred communication\":null,\"Roles\":\"Decision maker\",\"Notes\":null}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-18 05:21:33'),(51,1,'Administrator','enquiry.created','enquiry',3,'ENQ/26-27/0003 · Suma Soft','Added enquiry ENQ/26-27/0003 for Suma Soft','{\"fields\":{\"Enquiry date\":\"18\\/10\\/2026\",\"Source\":\"Architect\",\"Sub-source \\/ Reference\":\"New Paper\",\"Competitor details\":null,\"Prospect type\":\"New\",\"Company \\/ Client name\":\"Suma Soft\",\"Industry\":null,\"Industry type\":\"Corporate\",\"City\":\"Nashik\",\"Address\":null,\"Website\":\"https:\\/\\/sumasoft.com\",\"Contact name\":\"Rajesh\",\"Contact no\":\"9876543211\",\"Service type\":\"GD DECO\",\"Approx. requirement\":null,\"Estimated value\":null,\"Expected closing date\":null,\"Lead rating\":\"Hot\",\"Lead assigned to\":\"Temp\",\"Next action\":\"test\",\"Next action date\":\"13\\/04\\/2027\",\"Remarks\":null}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-18 05:26:31'),(52,1,'Administrator','site_visit.created','site_visit',2,'Site visit 19/10/2026 11:00 · ENQ/26-27/0003','Added a site visit for ENQ/26-27/0003','{\"fields\":{\"Enquiry\":\"ENQ\\/26-27\\/0003\",\"Visit date and time\":\"19\\/10\\/2026 11:00\",\"POC\":\"Rajesh, 9876543211\",\"Technical person\":\"Temp\",\"Site address\":null,\"Area \\/ measurements\":null,\"Floors\":null,\"Indoor \\/ outdoor area\":null,\"Existing plants\":null,\"Existing planters\":null,\"Lighting condition\":null,\"Water availability\":null,\"Access restrictions\":null,\"Customer requirement\":null,\"Remarks\":null,\"Next action\":null,\"Status\":\"Done\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-18 05:27:47'),(53,1,'Administrator','auth.logout','user',1,'Administrator (admin@nursery.local)','Administrator logged out',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-18 05:46:33'),(54,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-18 05:47:02'),(55,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-18 10:04:41'),(56,1,'Administrator','user.created','user',5,'Sales Persona (sales@gmail.com)','Created user Sales Persona','{\"fields\":{\"Name\":\"Sales Persona\",\"Login email\":\"sales@gmail.com\",\"Mobile\":\"+91 98765 43211\",\"Status\":\"Active\",\"Units allowed\":\"Unit 1 \\u00b7 Chakan\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-18 11:05:57'),(57,1,'Administrator','user.role_assigned','user',5,'Sales Persona (sales@gmail.com)','Assigned role Sales to Sales Persona','{\"role\":{\"old\":null,\"new\":\"Sales\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-18 11:05:57'),(58,1,'Administrator','quotation.created','quotation',1,'QT/26-27/0001 Rev 0','Created quotation QT/26-27/0001 (enquiry ENQ/26-27/0003) for Suma Soft','{\"fields\":{\"Enquiry no\":\"ENQ\\/26-27\\/0003\",\"Client\":\"Suma Soft\",\"Contact\":\"Rajesh, 9876543211\",\"Site\":\"Test Site\",\"Quotation date\":\"18\\/10\\/2026\",\"Valid until\":\"17\\/11\\/2026\",\"Sales person\":\"Sales Persona\",\"Currency\":\"INR\",\"Payment terms\":\"50 advance\",\"Delivery \\/ service terms\":\"NO Termsn\",\"GST \\/ tax\":\"18\",\"Discount\":\"0.00\",\"Notes\":null,\"Total\":\"106.20\",\"Cost: Purchase \\/ material cost\":\"200.00\",\"Cost: Plant production cost\":\"100.00\",\"Cost: Planter cost\":\"121.00\",\"Cost: Labour cost\":\"23.00\",\"Cost: Transportation cost\":\"123.00\",\"Cost: Installation cost\":\"23.00\",\"Cost: Maintenance cost\":\"34.00\",\"Cost: Other cost\":\"34.00\",\"Cost: Total cost\":\"658.00\",\"Cost: Margin %\":\"10.00\",\"Cost: Margin amount\":\"65.80\",\"Cost: Proposed selling price\":\"723.80\"},\"items\":[\"1 \\u00b7 Test \\u00b7 qty 1 Nos \\u00b7 rate 100 \\u00b7 discount 10% \\u00b7 tax 18% \\u00b7 total 106.20\"]}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-18 11:06:17'),(59,1,'Administrator','document.uploaded','quotation',1,'QT/26-27/0001 Rev 0','Uploaded BOQ \"lead-ageing-report-18880401-20270331.csv\"','{\"Document\":{\"old\":null,\"new\":\"BOQ: lead-ageing-report-18880401-20270331.csv (1 KB)\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-18 11:07:02'),(60,1,'Administrator','quotation.updated','quotation',1,'QT/26-27/0001 Rev 0','Updated quotation QT/26-27/0001 Rev 0','{\"Total\":{\"old\":\"106.20\",\"new\":\"1062001.06\"},\"items\":{\"added\":[\"1 \\u00b7 Test \\u00b7 qty 1 Nos \\u00b7 rate 1000001 \\u00b7 discount 10% \\u00b7 tax 18% \\u00b7 total 1062001.06\"],\"removed\":[\"1 \\u00b7 Test \\u00b7 qty 1 Nos \\u00b7 rate 100 \\u00b7 discount 10% \\u00b7 tax 18% \\u00b7 total 106.20\"]}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-18 11:08:24'),(61,1,'Administrator','quotation.status_changed','quotation',1,'QT/26-27/0001 Rev 0','Submitted QT/26-27/0001 Rev 0 for approval','{\"Status\":{\"old\":\"Draft\",\"new\":\"Internal Approval\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-18 11:08:38'),(62,1,'Administrator','quotation.status_changed','quotation',1,'QT/26-27/0001 Rev 0','Approved QT/26-27/0001 Rev 0','{\"Status\":{\"old\":\"Internal Approval\",\"new\":\"Approved\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-18 11:08:50'),(63,1,'Administrator','quotation.status_changed','quotation',1,'QT/26-27/0001 Rev 0','Sent QT/26-27/0001 Rev 0 by hand','{\"Status\":{\"old\":\"Approved\",\"new\":\"Sent\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-18 11:10:50'),(64,1,'Administrator','quotation.status_changed','quotation',1,'QT/26-27/0001 Rev 0','QT/26-27/0001 Rev 0 is now Accepted (PO 21312312 received)','{\"Status\":{\"old\":\"Sent\",\"new\":\"Accepted\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-18 11:11:28'),(65,1,'Administrator','enquiry.status_changed','enquiry',3,'ENQ/26-27/0003 · Suma Soft','ENQ/26-27/0003 is now PO received (PO 21312312 received)','{\"Status\":{\"old\":\"Open\",\"new\":\"PO received\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-18 11:11:28'),(66,1,'Administrator','customer_po.created','customer_po',1,'PO 21312312 · ENQ/26-27/0003','Entered PO / agreement 21312312 against QT/26-27/0001 Rev 0','{\"fields\":{\"PO \\/ Agreement no\":\"21312312\",\"PO date\":\"18\\/10\\/2026\",\"Quotation\":\"QT\\/26-27\\/0001 Rev 0\",\"Enquiry\":\"ENQ\\/26-27\\/0003\",\"Client\":\"Suma Soft\",\"Site\":\"Test Site\",\"PO value\":\"1062001.06\",\"PO quantity\":\"10.000\",\"Valid from\":\"18\\/10\\/2026\",\"Valid to\":\"23\\/10\\/2026\",\"Payment terms\":\"50 advance\",\"GST \\/ tax\":\"18\",\"Customer contact\":\"Rajesh, 9876543211\",\"Received by\":\"Temp\",\"Received date\":\"18\\/10\\/2026\",\"Remarks\":null}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-18 11:11:28'),(67,1,'Administrator','document.uploaded','site_visit',2,'Site visit 19/10/2026 11:00 · ENQ/26-27/0003','Uploaded Site photo \"QT-26-27-0001-rev0 (2).pdf\"','{\"Document\":{\"old\":null,\"new\":\"Site photo: QT-26-27-0001-rev0 (2).pdf (23 KB)\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-18 11:12:31'),(68,1,'Administrator','document.uploaded','site_visit',2,'Site visit 19/10/2026 11:00 · ENQ/26-27/0003','Uploaded Site photo \"QT-26-27-0001-rev0 (1).pdf\"','{\"Document\":{\"old\":null,\"new\":\"Site photo: QT-26-27-0001-rev0 (1).pdf (23 KB)\"}}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-18 11:12:39'),(69,NULL,NULL,'auth.failed',NULL,NULL,'admin@nursery.com','Login failed for admin@nursery.com (unknown email)',NULL,'152.59.60.69','Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/155.0.8059.37 Mobile/15E148 Safari/604.1','2026-10-18 17:31:25'),(70,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'152.59.60.69','Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/155.0.8059.37 Mobile/15E148 Safari/604.1','2026-10-18 17:31:46'),(71,1,'Administrator','enquiry.updated','enquiry',3,'ENQ/26-27/0003 · Suma Soft','Updated enquiry ENQ/26-27/0003','{\"Contact no\":{\"old\":\"9876543211\",\"new\":\"9876543210\"}}','152.59.60.135','Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/155.0.8059.37 Mobile/15E148 Safari/604.1','2026-10-18 17:35:47'),(72,1,'Administrator','document.uploaded','enquiry',3,'ENQ/26-27/0003 · Suma Soft','Uploaded Enquiry document \"image.jpg\"','{\"Document\":{\"old\":null,\"new\":\"Enquiry document: image.jpg (2.3 MB)\"}}','152.59.60.135','Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/155.0.8059.37 Mobile/15E148 Safari/604.1','2026-10-18 17:37:15'),(73,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'223.228.39.137','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Mobile Safari/537.36','2026-10-18 18:44:30'),(74,1,'Administrator','auth.logout','user',1,'Administrator (admin@nursery.local)','Administrator logged out',NULL,'223.228.39.137','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Mobile Safari/537.36','2026-10-18 18:44:48'),(75,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'122.167.114.193','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36','2026-10-19 01:51:42'),(76,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'152.59.59.80','Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/155.0.8059.37 Mobile/15E148 Safari/604.1','2026-10-19 02:05:42'),(77,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'103.133.158.204','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-19 05:42:56'),(78,NULL,NULL,'auth.failed',NULL,NULL,'admin@gmail.com','Login failed for admin@gmail.com (unknown email)',NULL,'103.133.158.204','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-19 11:06:40'),(79,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'103.133.158.204','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-19 11:46:15'),(80,1,'Administrator','prospect.created','prospect',4,'Digonal Mall','Added prospect Digonal Mall','{\"fields\":{\"Prospect name\":\"Digonal Mall\",\"Domestic \\/ Export\":\"Domestic\",\"Contact person\":\"Rajdarshan\",\"Position\":\"Manager\",\"Contact no 1\":\"98765643\",\"Contact no 2\":null,\"Email\":null,\"GSTIN\":null,\"Industry type\":null,\"Address\":null}}','103.133.158.204','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-19 11:48:39'),(81,1,'Administrator','prospect.updated','prospect',4,'Digonal Mall','Updated prospect Digonal Mall','{\"Industry type\":{\"old\":null,\"new\":\"Mall\"}}','103.133.158.204','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-19 11:50:39'),(82,1,'Administrator','enquiry.created','enquiry',4,'ENQ/26-27/0004 · Digonal Mall','Added enquiry ENQ/26-27/0004 for Digonal Mall','{\"fields\":{\"Enquiry date\":\"19\\/10\\/2026\",\"Source\":\"Phone Call\",\"Sub-source \\/ Reference\":null,\"Competitor details\":null,\"Prospect type\":\"New\",\"Company \\/ Client name\":\"Digonal Mall\",\"Industry\":null,\"Industry type\":\"Mall\",\"City\":\"Pune\",\"Address\":null,\"Website\":null,\"Contact name\":\"Rajdarshan\",\"Contact no\":\"98765643\",\"Service type\":\"Garden Maintenance\",\"Approx. requirement\":null,\"Estimated value\":null,\"Expected closing date\":null,\"Lead rating\":\"Hot\",\"Lead assigned to\":\"Temp\",\"Next action\":\"visit site\",\"Next action date\":\"20\\/10\\/2026\",\"Remarks\":null}}','103.133.158.204','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-19 11:54:52'),(83,1,'Administrator','site_visit.created','site_visit',3,'Site visit 20/10/2026 11:00 · ENQ/26-27/0004','Added a site visit for ENQ/26-27/0004','{\"fields\":{\"Enquiry\":\"ENQ\\/26-27\\/0004\",\"Visit date and time\":\"20\\/10\\/2026 11:00\",\"POC\":\"Rajdarshan\",\"Technical person\":\"Temp\",\"Site address\":null,\"Area \\/ measurements\":null,\"Floors\":null,\"Indoor \\/ outdoor area\":null,\"Existing plants\":null,\"Existing planters\":null,\"Lighting condition\":null,\"Water availability\":null,\"Access restrictions\":null,\"Customer requirement\":null,\"Remarks\":null,\"Next action\":null,\"Status\":\"Planned\"}}','103.133.158.204','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-19 12:02:01'),(84,1,'Administrator','prospect.created','prospect',5,'Gera','Added prospect Gera','{\"fields\":{\"Prospect name\":\"Gera\",\"Domestic \\/ Export\":\"Domestic\",\"Contact person\":\"Raghunath\",\"Position\":\"Owner\",\"Contact no 1\":\"9881211338\",\"Contact no 2\":null,\"Email\":null,\"GSTIN\":null,\"Industry type\":null,\"Address\":null}}','103.133.158.204','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-19 12:03:57'),(85,1,'Administrator','prospect.imported',NULL,NULL,NULL,'Imported 1 prospect(s) from a CSV file','{\"added\":[\"Orion Agro Equipment\"]}','103.133.158.204','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-19 12:32:39'),(86,1,'Administrator','auth.logout','user',1,'Administrator (admin@nursery.local)','Administrator logged out',NULL,'103.133.158.204','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-19 12:50:39'),(87,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'152.59.61.187','Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/155.0.8059.37 Mobile/15E148 Safari/604.1','2026-10-19 15:28:05'),(88,1,'Administrator','enquiry.created','enquiry',5,'ENQ/26-27/0005 · Suma Soft','Added enquiry ENQ/26-27/0005 for Suma Soft','{\"fields\":{\"Enquiry date\":\"19\\/10\\/2026\",\"Source\":\"Google\",\"Sub-source \\/ Reference\":\"Google add from youtube\",\"Competitor details\":\"Facebook\",\"Prospect type\":\"Existing Client\",\"Company \\/ Client name\":\"Suma Soft\",\"Industry\":\"Soft devp\",\"Industry type\":\"Hotel\",\"City\":\"Nashik\",\"Address\":\"Nsk\",\"Website\":\"https:\\/\\/sumasoft.com\",\"Contact name\":\"Rajesh\",\"Contact no\":\"9876543210\",\"Service type\":\"Plant Rental\",\"Approx. requirement\":\"2 floor\",\"Estimated value\":\"200000.00\",\"Expected closing date\":\"08\\/11\\/2026\",\"Lead rating\":\"Warm\",\"Lead assigned to\":\"Temp\",\"Next action\":\"Call to him\",\"Next action date\":\"25\\/10\\/2026\",\"Remarks\":null}}','152.59.61.187','Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/155.0.8059.37 Mobile/15E148 Safari/604.1','2026-10-19 15:39:02'),(89,1,'Administrator','enquiry.followup_added','enquiry',5,'ENQ/26-27/0005 · Suma Soft','Recorded a Call follow-up on ENQ/26-27/0005','{\"Follow-up\":{\"old\":null,\"new\":\"Call on 20\\/10\\/2026: Need to call again\"},\"Next action\":{\"old\":\"Call to him\",\"new\":\"Call\"},\"Next action date\":{\"old\":\"25\\/10\\/2026\",\"new\":\"21\\/10\\/2026\"}}','152.59.61.187','Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/155.0.8059.37 Mobile/15E148 Safari/604.1','2026-10-19 15:40:19'),(90,1,'Administrator','enquiry.followup_added','enquiry',5,'ENQ/26-27/0005 · Suma Soft','Recorded a Call follow-up on ENQ/26-27/0005','{\"Follow-up\":{\"old\":null,\"new\":\"Call on 21\\/10\\/2026: Call done\"},\"Next action\":{\"old\":\"Call\",\"new\":\"Call\"},\"Next action date\":{\"old\":\"21\\/10\\/2026\",\"new\":\"22\\/10\\/2026\"}}','152.59.61.187','Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/155.0.8059.37 Mobile/15E148 Safari/604.1','2026-10-19 15:41:19'),(91,1,'Administrator','document.uploaded','enquiry',5,'ENQ/26-27/0005 · Suma Soft','Uploaded Enquiry document \"image.jpg\"','{\"Document\":{\"old\":null,\"new\":\"Enquiry document: image.jpg (2 MB)\"}}','152.59.61.252','Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/155.0.8059.37 Mobile/15E148 Safari/604.1','2026-10-19 15:42:55'),(92,1,'Administrator','site_visit.created','site_visit',4,'Site visit 20/10/2026 11:00 · ENQ/26-27/0005','Added a site visit for ENQ/26-27/0005','{\"fields\":{\"Enquiry\":\"ENQ\\/26-27\\/0005\",\"Visit date and time\":\"20\\/10\\/2026 11:00\",\"POC\":\"Rajesh\",\"Technical person\":\"Temp\",\"Site address\":\"Nsk\",\"Area \\/ measurements\":\"100\",\"Floors\":\"200\",\"Indoor \\/ outdoor area\":\"50\",\"Existing plants\":null,\"Existing planters\":null,\"Lighting condition\":\"Ok\",\"Water availability\":\"Ok\",\"Access restrictions\":\"Ok\",\"Customer requirement\":null,\"Remarks\":null,\"Next action\":null,\"Status\":\"Planned\"}}','152.59.61.252','Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/155.0.8059.37 Mobile/15E148 Safari/604.1','2026-10-19 15:43:51'),(93,1,'Administrator','quotation.created','quotation',2,'QT/26-27/0002 Rev 0','Created quotation QT/26-27/0002 (enquiry ENQ/26-27/0005) for Suma Soft','{\"fields\":{\"Enquiry no\":\"ENQ\\/26-27\\/0005\",\"Client\":\"Suma Soft\",\"Contact\":\"Rajesh\",\"Site\":\"Nsk\",\"Quotation date\":\"19\\/10\\/2026\",\"Valid until\":\"18\\/11\\/2026\",\"Sales person\":\"Sales Persona\",\"Currency\":\"INR\",\"Payment terms\":\"50 % advance\",\"Delivery \\/ service terms\":null,\"GST \\/ tax\":\"18\",\"Discount\":\"62.00\",\"Notes\":null,\"Total\":\"106138.00\",\"Cost: Purchase \\/ material cost\":\"0.00\",\"Cost: Plant production cost\":\"0.00\",\"Cost: Planter cost\":\"0.00\",\"Cost: Labour cost\":\"0.00\",\"Cost: Transportation cost\":\"0.00\",\"Cost: Installation cost\":\"0.00\",\"Cost: Maintenance cost\":\"0.00\",\"Cost: Other cost\":\"0.00\",\"Cost: Total cost\":\"0.00\",\"Cost: Margin %\":\"0.00\",\"Cost: Margin amount\":\"0.00\",\"Cost: Proposed selling price\":\"0.00\"},\"items\":[\"1212 \\u00b7 Test \\u00b7 qty 10 1 \\u00b7 rate 10000 \\u00b7 discount 10% \\u00b7 tax 18% \\u00b7 total 106200.00\"]}','152.59.61.252','Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/155.0.8059.37 Mobile/15E148 Safari/604.1','2026-10-19 15:47:58'),(94,1,'Administrator','quotation.updated','quotation',2,'QT/26-27/0002 Rev 0','Updated quotation QT/26-27/0002 Rev 0','{\"Cost: Purchase \\/ material cost\":{\"old\":\"0.00\",\"new\":\"2000.00\"},\"Cost: Plant production cost\":{\"old\":\"0.00\",\"new\":\"20.00\"},\"Cost: Planter cost\":{\"old\":\"0.00\",\"new\":\"2000.00\"},\"Cost: Labour cost\":{\"old\":\"0.00\",\"new\":\"100.00\"},\"Cost: Transportation cost\":{\"old\":\"0.00\",\"new\":\"200.00\"},\"Cost: Installation cost\":{\"old\":\"0.00\",\"new\":\"200.00\"},\"Cost: Maintenance cost\":{\"old\":\"0.00\",\"new\":\"10.00\"},\"Cost: Other cost\":{\"old\":\"0.00\",\"new\":\"200.00\"},\"Cost: Total cost\":{\"old\":\"0.00\",\"new\":\"4730.00\"},\"Cost: Margin %\":{\"old\":\"0.00\",\"new\":\"20.00\"},\"Cost: Margin amount\":{\"old\":\"0.00\",\"new\":\"946.00\"},\"Cost: Proposed selling price\":{\"old\":\"0.00\",\"new\":\"5676.00\"}}','152.59.61.17','Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/155.0.8059.37 Mobile/15E148 Safari/604.1','2026-10-19 15:51:51'),(95,1,'Administrator','quotation.status_changed','quotation',2,'QT/26-27/0002 Rev 0','Submitted QT/26-27/0002 Rev 0 for approval','{\"Status\":{\"old\":\"Draft\",\"new\":\"Internal Approval\"}}','152.59.61.17','Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/155.0.8059.37 Mobile/15E148 Safari/604.1','2026-10-19 15:51:55'),(96,1,'Administrator','quotation.status_changed','quotation',2,'QT/26-27/0002 Rev 0','Rejected QT/26-27/0002 Rev 0 and returned it to Draft','{\"Status\":{\"old\":\"Internal Approval\",\"new\":\"Draft\"},\"Reason\":{\"old\":null,\"new\":\"Returned by Administrator: Need proper calculations\"}}','152.59.61.171','Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/155.0.8059.37 Mobile/15E148 Safari/604.1','2026-10-19 15:52:24'),(97,1,'Administrator','quotation.updated','quotation',2,'QT/26-27/0002 Rev 0','Updated quotation QT/26-27/0002 Rev 0','{\"Cost: Margin %\":{\"old\":\"20.00\",\"new\":\"50.00\"},\"Cost: Margin amount\":{\"old\":\"946.00\",\"new\":\"2365.00\"},\"Cost: Proposed selling price\":{\"old\":\"5676.00\",\"new\":\"7095.00\"}}','152.59.61.171','Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/155.0.8059.37 Mobile/15E148 Safari/604.1','2026-10-19 15:52:55'),(98,1,'Administrator','quotation.status_changed','quotation',2,'QT/26-27/0002 Rev 0','Submitted QT/26-27/0002 Rev 0 for approval','{\"Status\":{\"old\":\"Draft\",\"new\":\"Internal Approval\"}}','152.59.61.245','Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/155.0.8059.37 Mobile/15E148 Safari/604.1','2026-10-19 15:53:07'),(99,1,'Administrator','quotation.status_changed','quotation',2,'QT/26-27/0002 Rev 0','Approved QT/26-27/0002 Rev 0','{\"Status\":{\"old\":\"Internal Approval\",\"new\":\"Approved\"}}','152.59.61.245','Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/155.0.8059.37 Mobile/15E148 Safari/604.1','2026-10-19 15:53:12'),(100,1,'Administrator','quotation.status_changed','quotation',2,'QT/26-27/0002 Rev 0','Sent QT/26-27/0002 Rev 0 by hand','{\"Status\":{\"old\":\"Approved\",\"new\":\"Sent\"}}','152.59.61.162','Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/155.0.8059.37 Mobile/15E148 Safari/604.1','2026-10-19 15:56:08'),(101,1,'Administrator','customer_po.updated','customer_po',1,'PO 21312312 · ENQ/26-27/0003','Updated PO 21312312 · ENQ/26-27/0003','{\"Remarks\":{\"old\":null,\"new\":\"Ok\"}}','152.59.61.145','Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/155.0.8059.37 Mobile/15E148 Safari/604.1','2026-10-19 15:57:16'),(102,1,'Administrator','document.uploaded','customer_po',1,'PO 21312312 · ENQ/26-27/0003','Uploaded Customer PO \"image.jpg\"','{\"Document\":{\"old\":null,\"new\":\"Customer PO: image.jpg (1.3 MB)\"}}','152.59.61.56','Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/155.0.8059.37 Mobile/15E148 Safari/604.1','2026-10-19 15:59:10'),(103,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'122.167.118.206','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-19 17:41:01'),(104,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'223.233.82.9','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-19 17:50:15'),(105,1,'Administrator','prospect.created','prospect',7,'New Nursery Setup','Added prospect New Nursery Setup','{\"fields\":{\"Prospect name\":\"New Nursery Setup\",\"Domestic \\/ Export\":\"Domestic\",\"Contact person\":\"Eshwar\",\"Position\":\"Owner\",\"Contact no 1\":\"9876543210\",\"Contact no 2\":null,\"Email\":null,\"GSTIN\":null,\"Industry type\":null,\"Address\":null}}','223.233.82.9','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-19 17:55:39'),(106,1,'Administrator','prospect.updated','prospect',7,'New Nursery Setup','Updated prospect New Nursery Setup','{\"Contact person\":{\"old\":\"Eshwar\",\"new\":\"Eshwar New\"}}','223.233.82.9','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-19 17:56:27'),(107,1,'Administrator','prospect.imported',NULL,NULL,NULL,'Imported 1 prospect(s) from a CSV file','{\"added\":[\"Test\"]}','223.233.82.9','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-19 18:02:04'),(108,1,'Administrator','prospect.imported',NULL,NULL,NULL,'Imported 1 prospect(s) from a CSV file','{\"added\":[\"Test\"]}','223.233.82.9','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-19 18:03:22'),(109,1,'Administrator','prospect.imported',NULL,NULL,NULL,'Imported 1 prospect(s) from a CSV file','{\"added\":[\"Test\"]}','223.233.82.9','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-19 18:05:43'),(110,1,'Administrator','prospect.imported',NULL,NULL,NULL,'Imported 3 prospect(s) from a CSV file','{\"added\":[\"432\",\"123412345\",\"Test Test Final12\"]}','223.233.82.9','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-19 18:07:59'),(111,1,'Administrator','prospect.imported',NULL,NULL,NULL,'Imported 3 prospect(s) from a CSV file','{\"added\":[\"432\",\"123412345\",\"Test Test Final12\"]}','223.233.82.9','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-19 18:09:45'),(112,1,'Administrator','enquiry.created','enquiry',6,'ENQ/26-27/0006 · 123412345','Added enquiry ENQ/26-27/0006 for 123412345','{\"fields\":{\"Enquiry date\":\"19\\/10\\/2026\",\"Source\":\"Website\",\"Sub-source \\/ Reference\":null,\"Competitor details\":null,\"Prospect type\":\"Referral\",\"Company \\/ Client name\":\"123412345\",\"Industry\":null,\"Industry type\":\"Corporate\",\"City\":\"Nashik\",\"Address\":\"MIDC Chakan, Pune\",\"Website\":null,\"Contact name\":\"Test Test Final1\",\"Contact no\":\"+91 98220 1222\",\"Service type\":\"Plant AMC\",\"Approx. requirement\":\"40\",\"Estimated value\":\"200000.00\",\"Expected closing date\":\"21\\/11\\/2026\",\"Lead rating\":\"Hot\",\"Lead assigned to\":\"Temp\",\"Next action\":\"Call\",\"Next action date\":\"20\\/10\\/2026\",\"Remarks\":null}}','223.233.82.9','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-19 18:22:15'),(113,1,'Administrator','enquiry.followup_added','enquiry',6,'ENQ/26-27/0006 · 123412345','Recorded a Call follow-up on ENQ/26-27/0006','{\"Follow-up\":{\"old\":null,\"new\":\"Call on 19\\/10\\/2026: Done\"},\"Next action\":{\"old\":\"Call\",\"new\":\"Call\"},\"Next action date\":{\"old\":\"20\\/10\\/2026\",\"new\":\"21\\/10\\/2026\"}}','223.233.82.9','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-19 18:25:14'),(114,1,'Administrator','enquiry.followup_added','enquiry',6,'ENQ/26-27/0006 · 123412345','Recorded a Call follow-up on ENQ/26-27/0006','{\"Follow-up\":{\"old\":null,\"new\":\"Call on 22\\/10\\/2026: dONE 22\"},\"Next action\":{\"old\":\"Call\",\"new\":\"wHATS app\"},\"Next action date\":{\"old\":\"21\\/10\\/2026\",\"new\":\"24\\/10\\/2026\"}}','223.233.82.9','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-19 18:26:05'),(115,1,'Administrator','master.created','service_category',13,'admin','Added admin to the Category master','{\"fields\":{\"Name\":\"admin\",\"Order\":100,\"Active\":\"Yes\"}}','223.233.82.9','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-19 18:31:14'),(116,1,'Administrator','master.created','service_category',14,'admin new','Added admin new to the Category master','{\"fields\":{\"Name\":\"admin new\",\"Order\":100,\"Active\":\"Yes\"}}','223.233.82.9','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-19 18:31:34'),(117,1,'Administrator','quotation.created','quotation',3,'QT/26-27/0003 Rev 0','Created quotation QT/26-27/0003 (enquiry ENQ/26-27/0006) for 123412345','{\"fields\":{\"Enquiry no\":\"ENQ\\/26-27\\/0006\",\"Client\":\"123412345\",\"Contact\":\"Test Test Final1\",\"Site\":\"MIDC Chakan, Pune\",\"Quotation date\":\"20\\/10\\/2026\",\"Valid until\":\"19\\/11\\/2026\",\"Sales person\":\"Sales Persona\",\"Currency\":\"INR\",\"Payment terms\":null,\"Delivery \\/ service terms\":null,\"GST \\/ tax\":null,\"Discount\":\"50000.00\",\"Notes\":null,\"Total\":\"44400.00\",\"Cost: Purchase \\/ material cost\":\"200.00\",\"Cost: Plant production cost\":\"100.00\",\"Cost: Planter cost\":\"121.00\",\"Cost: Labour cost\":\"23.00\",\"Cost: Transportation cost\":\"123.00\",\"Cost: Installation cost\":\"23.00\",\"Cost: Maintenance cost\":\"34.00\",\"Cost: Other cost\":\"34.00\",\"Cost: Total cost\":\"658.00\",\"Cost: Margin %\":\"10.00\",\"Cost: Margin amount\":\"65.80\",\"Cost: Proposed selling price\":\"723.80\"},\"items\":[\"1 \\u00b7 Test \\u00b7 qty 100 Nos \\u00b7 rate 1000 \\u00b7 discount 20% \\u00b7 tax 18% \\u00b7 total 94400.00\"]}','223.233.82.9','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-19 18:47:04'),(118,1,'Administrator','quotation.status_changed','quotation',3,'QT/26-27/0003 Rev 0','Approved QT/26-27/0003 Rev 0 automatically (total at or below 1,00,000.00)','{\"Status\":{\"old\":\"Draft\",\"new\":\"Approved\"}}','223.233.82.9','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-19 18:52:57'),(119,1,'Administrator','quotation.status_changed','quotation',3,'QT/26-27/0003 Rev 0','Sent QT/26-27/0003 Rev 0 by hand','{\"Status\":{\"old\":\"Approved\",\"new\":\"Sent\"}}','223.233.82.9','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-19 18:53:52'),(120,1,'Administrator','quotation.status_changed','quotation',3,'QT/26-27/0003 Rev 0','QT/26-27/0003 Rev 0 is now Sent','{\"Status\":{\"old\":\"Sent\",\"new\":\"Sent\"}}','223.233.82.9','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-19 18:54:35'),(121,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'122.167.119.60','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-20 17:00:58'),(122,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'103.133.158.204','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-21 06:01:20'),(123,1,'Administrator','auth.login','user',1,'Administrator (admin@nursery.local)','Administrator logged in',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-21 18:19:23');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `app_settings`
--

DROP TABLE IF EXISTS `app_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `app_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(60) NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `app_settings_key_unique` (`key`),
  KEY `app_settings_updated_by_foreign` (`updated_by`),
  CONSTRAINT `app_settings_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `app_settings`
--

LOCK TABLES `app_settings` WRITE;
/*!40000 ALTER TABLE `app_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `app_settings` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `approvals`
--

LOCK TABLES `approvals` WRITE;
/*!40000 ALTER TABLE `approvals` DISABLE KEYS */;
INSERT INTO `approvals` VALUES (1,'quotation',1,'Submitted',1062001.06,NULL,1,'2026-10-18 11:08:38'),(2,'quotation',1,'Approved',1062001.06,NULL,1,'2026-10-18 11:08:50'),(3,'quotation',2,'Submitted',106138.00,NULL,1,'2026-10-19 15:51:55'),(4,'quotation',2,'Rejected',106138.00,'Need proper calculations',1,'2026-10-19 15:52:24'),(5,'quotation',2,'Submitted',106138.00,NULL,1,'2026-10-19 15:53:07'),(6,'quotation',2,'Approved',106138.00,NULL,1,'2026-10-19 15:53:12'),(7,'quotation',3,'Approved automatically',44400.00,'Total at or below 1,00,000.00',1,'2026-10-19 18:52:57');
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
INSERT INTO `cache` VALUES ('nursery-cache-4bc25146bbe4b00ec401ec9680c4c6ca','i:1;',1792609126),('nursery-cache-4bc25146bbe4b00ec401ec9680c4c6ca:timer','i:1792609126;',1792609126),('nursery-cache-admin@gmail.com|103.133.158.204','i:1;',1792408060),('nursery-cache-admin@gmail.com|103.133.158.204:timer','i:1792408060;',1792408060),('nursery-cache-admin@nursery.com|152.59.60.69','i:1;',1792344745),('nursery-cache-admin@nursery.com|152.59.60.69:timer','i:1792344745;',1792344745),('nursery-cache-app-settings','a:0:{}',1792612261),('nursery-cache-crm.alerts.unit.1.20261016','a:3:{s:6:\"visits\";i:0;s:8:\"approval\";i:0;s:2:\"po\";i:0;}',1792113382),('nursery-cache-crm.alerts.unit.1.20261018','a:3:{s:6:\"visits\";i:0;s:8:\"approval\";i:0;s:2:\"po\";i:0;}',1792345550),('nursery-cache-crm.alerts.unit.1.20261019','a:3:{s:6:\"visits\";i:0;s:8:\"approval\";i:0;s:2:\"po\";i:0;}',1792434899),('nursery-cache-crm.alerts.unit.1.20261020','a:3:{s:6:\"visits\";i:2;s:8:\"approval\";i:0;s:2:\"po\";i:0;}',1792515958),('nursery-cache-crm.alerts.unit.1.20261021','a:3:{s:6:\"visits\";i:2;s:8:\"approval\";i:0;s:2:\"po\";i:0;}',1792607639),('nursery-cache-crm.alerts.unit.1.20261022','a:3:{s:6:\"visits\";i:2;s:8:\"approval\";i:0;s:2:\"po\";i:0;}',1792609411),('nursery-cache-crm.alerts.unit.2.20261018','a:3:{s:6:\"visits\";i:0;s:8:\"approval\";i:0;s:2:\"po\";i:0;}',1792345844),('nursery-cache-crm.dashboard.1.1.20261019','a:11:{s:4:\"kpis\";a:9:{s:9:\"enquiries\";i:5;s:3:\"pos\";i:1;s:8:\"po_value\";d:1062001.0600000000558793544769287109375;s:10:\"conversion\";d:20;s:4:\"open\";i:4;s:3:\"hot\";i:2;s:10:\"po_pending\";i:0;s:7:\"closing\";i:0;s:13:\"closing_value\";d:0;}s:7:\"monthly\";a:8:{s:5:\"width\";i:600;s:6:\"height\";i:220;s:4:\"left\";i:32;s:4:\"base\";i:192;s:4:\"bars\";a:24:{i:0;a:6:{s:1:\"x\";d:37.7000000000000028421709430404007434844970703125;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Apr: Enquiries received 0\";}i:1;a:6:{s:1:\"x\";d:85;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"May: Enquiries received 0\";}i:2;a:6:{s:1:\"x\";d:132.30000000000001136868377216160297393798828125;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Jun: Enquiries received 0\";}i:3;a:6:{s:1:\"x\";d:179.69999999999998863131622783839702606201171875;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Jul: Enquiries received 0\";}i:4;a:6:{s:1:\"x\";d:227;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Aug: Enquiries received 0\";}i:5;a:6:{s:1:\"x\";d:274.30000000000001136868377216160297393798828125;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Sep: Enquiries received 0\";}i:6;a:6:{s:1:\"x\";d:321.69999999999998863131622783839702606201171875;s:1:\"y\";d:10;s:1:\"w\";d:16;s:1:\"h\";d:182;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Oct: Enquiries received 5\";}i:7;a:6:{s:1:\"x\";d:369;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Nov: Enquiries received 0\";}i:8;a:6:{s:1:\"x\";d:416.30000000000001136868377216160297393798828125;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Dec: Enquiries received 0\";}i:9;a:6:{s:1:\"x\";d:463.69999999999998863131622783839702606201171875;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Jan: Enquiries received 0\";}i:10;a:6:{s:1:\"x\";d:511;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Feb: Enquiries received 0\";}i:11;a:6:{s:1:\"x\";d:558.299999999999954525264911353588104248046875;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Mar: Enquiries received 0\";}i:12;a:6:{s:1:\"x\";d:55.7000000000000028421709430404007434844970703125;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Apr: PO received 0\";}i:13;a:6:{s:1:\"x\";d:103;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"May: PO received 0\";}i:14;a:6:{s:1:\"x\";d:150.30000000000001136868377216160297393798828125;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Jun: PO received 0\";}i:15;a:6:{s:1:\"x\";d:197.69999999999998863131622783839702606201171875;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Jul: PO received 0\";}i:16;a:6:{s:1:\"x\";d:245;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Aug: PO received 0\";}i:17;a:6:{s:1:\"x\";d:292.30000000000001136868377216160297393798828125;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Sep: PO received 0\";}i:18;a:6:{s:1:\"x\";d:339.69999999999998863131622783839702606201171875;s:1:\"y\";d:155.599999999999994315658113919198513031005859375;s:1:\"w\";d:16;s:1:\"h\";d:36.39999999999999857891452847979962825775146484375;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Oct: PO received 1\";}i:19;a:6:{s:1:\"x\";d:387;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Nov: PO received 0\";}i:20;a:6:{s:1:\"x\";d:434.30000000000001136868377216160297393798828125;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Dec: PO received 0\";}i:21;a:6:{s:1:\"x\";d:481.69999999999998863131622783839702606201171875;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Jan: PO received 0\";}i:22;a:6:{s:1:\"x\";d:529;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Feb: PO received 0\";}i:23;a:6:{s:1:\"x\";d:576.299999999999954525264911353588104248046875;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Mar: PO received 0\";}}s:5:\"ticks\";a:6:{i:0;a:2:{s:1:\"y\";d:192;s:5:\"label\";s:1:\"0\";}i:1;a:2:{s:1:\"y\";d:155.599999999999994315658113919198513031005859375;s:5:\"label\";s:1:\"1\";}i:2;a:2:{s:1:\"y\";d:119.2000000000000028421709430404007434844970703125;s:5:\"label\";s:1:\"2\";}i:3;a:2:{s:1:\"y\";d:82.7999999999999971578290569595992565155029296875;s:5:\"label\";s:1:\"3\";}i:4;a:2:{s:1:\"y\";d:46.39999999999999857891452847979962825775146484375;s:5:\"label\";s:1:\"4\";}i:5;a:2:{s:1:\"y\";d:10;s:5:\"label\";s:1:\"5\";}}s:6:\"labels\";a:12:{i:0;a:2:{s:1:\"x\";d:55.7000000000000028421709430404007434844970703125;s:5:\"label\";s:3:\"Apr\";}i:1;a:2:{s:1:\"x\";d:103;s:5:\"label\";s:3:\"May\";}i:2;a:2:{s:1:\"x\";d:150.30000000000001136868377216160297393798828125;s:5:\"label\";s:3:\"Jun\";}i:3;a:2:{s:1:\"x\";d:197.69999999999998863131622783839702606201171875;s:5:\"label\";s:3:\"Jul\";}i:4;a:2:{s:1:\"x\";d:245;s:5:\"label\";s:3:\"Aug\";}i:5;a:2:{s:1:\"x\";d:292.30000000000001136868377216160297393798828125;s:5:\"label\";s:3:\"Sep\";}i:6;a:2:{s:1:\"x\";d:339.69999999999998863131622783839702606201171875;s:5:\"label\";s:3:\"Oct\";}i:7;a:2:{s:1:\"x\";d:387;s:5:\"label\";s:3:\"Nov\";}i:8;a:2:{s:1:\"x\";d:434.30000000000001136868377216160297393798828125;s:5:\"label\";s:3:\"Dec\";}i:9;a:2:{s:1:\"x\";d:481.69999999999998863131622783839702606201171875;s:5:\"label\";s:3:\"Jan\";}i:10;a:2:{s:1:\"x\";d:529;s:5:\"label\";s:3:\"Feb\";}i:11;a:2:{s:1:\"x\";d:576.299999999999954525264911353588104248046875;s:5:\"label\";s:3:\"Mar\";}}s:6:\"legend\";a:2:{i:0;a:2:{s:5:\"label\";s:18:\"Enquiries received\";s:5:\"class\";s:3:\"cs1\";}i:1;a:2:{s:5:\"label\";s:11:\"PO received\";s:5:\"class\";s:3:\"cs2\";}}}s:12:\"monthlyTable\";a:12:{i:0;a:3:{s:5:\"month\";s:3:\"Apr\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:1;a:3:{s:5:\"month\";s:3:\"May\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:2;a:3:{s:5:\"month\";s:3:\"Jun\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:3;a:3:{s:5:\"month\";s:3:\"Jul\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:4;a:3:{s:5:\"month\";s:3:\"Aug\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:5;a:3:{s:5:\"month\";s:3:\"Sep\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:6;a:3:{s:5:\"month\";s:3:\"Oct\";s:9:\"enquiries\";i:5;s:3:\"pos\";i:1;}i:7;a:3:{s:5:\"month\";s:3:\"Nov\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:8;a:3:{s:5:\"month\";s:3:\"Dec\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:9;a:3:{s:5:\"month\";s:3:\"Jan\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:10;a:3:{s:5:\"month\";s:3:\"Feb\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:11;a:3:{s:5:\"month\";s:3:\"Mar\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}}s:7:\"sources\";a:2:{s:5:\"total\";i:5;s:6:\"slices\";a:4:{i:0;a:5:{s:5:\"label\";s:7:\"Website\";s:5:\"value\";i:2;s:7:\"percent\";d:40;s:5:\"class\";s:3:\"cs1\";s:4:\"path\";s:45:\"M50 50 L50.00 4.00 A46 46 0 0 1 77.04 87.21 Z\";}i:1;a:5:{s:5:\"label\";s:9:\"Architect\";s:5:\"value\";i:1;s:7:\"percent\";d:20;s:5:\"class\";s:3:\"cs2\";s:4:\"path\";s:46:\"M50 50 L77.04 87.21 A46 46 0 0 1 22.96 87.21 Z\";}i:2;a:5:{s:5:\"label\";s:10:\"Phone Call\";s:5:\"value\";i:1;s:7:\"percent\";d:20;s:5:\"class\";s:3:\"cs3\";s:4:\"path\";s:45:\"M50 50 L22.96 87.21 A46 46 0 0 1 6.25 35.79 Z\";}i:3;a:5:{s:5:\"label\";s:6:\"Google\";s:5:\"value\";i:1;s:7:\"percent\";d:20;s:5:\"class\";s:3:\"cs4\";s:4:\"path\";s:44:\"M50 50 L6.25 35.79 A46 46 0 0 1 50.00 4.00 Z\";}}}s:10:\"categories\";a:2:{s:5:\"total\";i:5;s:6:\"slices\";a:5:{i:0;a:5:{s:5:\"label\";s:20:\"Corporate Plantation\";s:5:\"value\";i:1;s:7:\"percent\";d:20;s:5:\"class\";s:3:\"cs1\";s:4:\"path\";s:45:\"M50 50 L50.00 4.00 A46 46 0 0 1 93.75 35.79 Z\";}i:1;a:5:{s:5:\"label\";s:16:\"Landscape Design\";s:5:\"value\";i:1;s:7:\"percent\";d:20;s:5:\"class\";s:3:\"cs2\";s:4:\"path\";s:46:\"M50 50 L93.75 35.79 A46 46 0 0 1 77.04 87.21 Z\";}i:2;a:5:{s:5:\"label\";s:7:\"GD DECO\";s:5:\"value\";i:1;s:7:\"percent\";d:20;s:5:\"class\";s:3:\"cs3\";s:4:\"path\";s:46:\"M50 50 L77.04 87.21 A46 46 0 0 1 22.96 87.21 Z\";}i:3;a:5:{s:5:\"label\";s:18:\"Garden Maintenance\";s:5:\"value\";i:1;s:7:\"percent\";d:20;s:5:\"class\";s:3:\"cs4\";s:4:\"path\";s:45:\"M50 50 L22.96 87.21 A46 46 0 0 1 6.25 35.79 Z\";}i:4;a:5:{s:5:\"label\";s:12:\"Plant Rental\";s:5:\"value\";i:1;s:7:\"percent\";d:20;s:5:\"class\";s:3:\"cs5\";s:4:\"path\";s:44:\"M50 50 L6.25 35.79 A46 46 0 0 1 50.00 4.00 Z\";}}}s:6:\"ageing\";a:5:{s:11:\"0 to 7 days\";i:4;s:12:\"8 to 15 days\";i:0;s:13:\"16 to 30 days\";i:0;s:13:\"31 to 60 days\";i:0;s:12:\"Over 60 days\";i:0;}s:9:\"ageingMax\";i:4;s:3:\"hot\";O:39:\"Illuminate\\Database\\Eloquent\\Collection\":2:{s:8:\"\0*\0items\";a:2:{i:0;O:18:\"App\\Models\\Enquiry\":34:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:9:\"enquiries\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:34:{s:2:\"id\";i:1;s:7:\"unit_id\";i:1;s:17:\"financial_year_id\";i:1;s:10:\"enquiry_no\";s:14:\"ENQ/26-27/0001\";s:12:\"enquiry_date\";s:10:\"2026-10-16\";s:14:\"lead_source_id\";i:1;s:10:\"sub_source\";N;s:18:\"competitor_details\";N;s:13:\"prospect_type\";s:15:\"Existing Client\";s:8:\"party_id\";N;s:11:\"prospect_id\";i:2;s:8:\"industry\";N;s:16:\"industry_type_id\";i:1;s:7:\"city_id\";i:5;s:7:\"address\";N;s:7:\"website\";N;s:12:\"contact_name\";s:2:\"GP\";s:10:\"contact_no\";s:10:\"7894561230\";s:17:\"contact_no_digits\";s:10:\"7894561230\";s:19:\"service_category_id\";i:11;s:18:\"approx_requirement\";N;s:15:\"estimated_value\";N;s:21:\"expected_closing_date\";N;s:11:\"lead_rating\";s:3:\"Hot\";s:11:\"assigned_to\";i:1;s:11:\"next_action\";s:4:\"test\";s:16:\"next_action_date\";s:10:\"1982-04-13\";s:7:\"remarks\";N;s:6:\"status\";s:4:\"Open\";s:10:\"created_by\";i:1;s:10:\"updated_by\";i:1;s:10:\"created_at\";s:19:\"2026-10-16 06:41:33\";s:10:\"updated_at\";s:19:\"2026-10-16 06:41:33\";s:10:\"deleted_at\";N;}s:11:\"\0*\0original\";a:34:{s:2:\"id\";i:1;s:7:\"unit_id\";i:1;s:17:\"financial_year_id\";i:1;s:10:\"enquiry_no\";s:14:\"ENQ/26-27/0001\";s:12:\"enquiry_date\";s:10:\"2026-10-16\";s:14:\"lead_source_id\";i:1;s:10:\"sub_source\";N;s:18:\"competitor_details\";N;s:13:\"prospect_type\";s:15:\"Existing Client\";s:8:\"party_id\";N;s:11:\"prospect_id\";i:2;s:8:\"industry\";N;s:16:\"industry_type_id\";i:1;s:7:\"city_id\";i:5;s:7:\"address\";N;s:7:\"website\";N;s:12:\"contact_name\";s:2:\"GP\";s:10:\"contact_no\";s:10:\"7894561230\";s:17:\"contact_no_digits\";s:10:\"7894561230\";s:19:\"service_category_id\";i:11;s:18:\"approx_requirement\";N;s:15:\"estimated_value\";N;s:21:\"expected_closing_date\";N;s:11:\"lead_rating\";s:3:\"Hot\";s:11:\"assigned_to\";i:1;s:11:\"next_action\";s:4:\"test\";s:16:\"next_action_date\";s:10:\"1982-04-13\";s:7:\"remarks\";N;s:6:\"status\";s:4:\"Open\";s:10:\"created_by\";i:1;s:10:\"updated_by\";i:1;s:10:\"created_at\";s:19:\"2026-10-16 06:41:33\";s:10:\"updated_at\";s:19:\"2026-10-16 06:41:33\";s:10:\"deleted_at\";N;}s:10:\"\0*\0changes\";a:0:{}s:11:\"\0*\0previous\";a:0:{}s:8:\"\0*\0casts\";a:8:{s:12:\"enquiry_date\";s:4:\"date\";s:21:\"expected_closing_date\";s:4:\"date\";s:16:\"next_action_date\";s:4:\"date\";s:15:\"estimated_value\";s:9:\"decimal:2\";s:13:\"prospect_type\";s:22:\"App\\Enums\\ProspectType\";s:11:\"lead_rating\";s:20:\"App\\Enums\\LeadRating\";s:6:\"status\";s:23:\"App\\Enums\\EnquiryStatus\";s:10:\"deleted_at\";s:8:\"datetime\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:3:{s:5:\"party\";N;s:8:\"prospect\";O:19:\"App\\Models\\Prospect\":34:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:9:\"prospects\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:2:{s:2:\"id\";i:2;s:4:\"name\";s:12:\"Ganesh Patil\";}s:11:\"\0*\0original\";a:2:{s:2:\"id\";i:2;s:4:\"name\";s:12:\"Ganesh Patil\";}s:10:\"\0*\0changes\";a:0:{}s:11:\"\0*\0previous\";a:0:{}s:8:\"\0*\0casts\";a:2:{s:6:\"market\";s:16:\"App\\Enums\\Market\";s:10:\"deleted_at\";s:8:\"datetime\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:27:\"\0*\0relationAutoloadCallback\";N;s:26:\"\0*\0relationAutoloadContext\";N;s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:10:{i:0;s:4:\"name\";i:1;s:6:\"market\";i:2;s:14:\"contact_person\";i:3;s:8:\"position\";i:4;s:7:\"phone_1\";i:5;s:7:\"phone_2\";i:6;s:5:\"email\";i:7;s:5:\"gstin\";i:8;s:13:\"industry_type\";i:9;s:7:\"address\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}s:16:\"\0*\0forceDeleting\";b:0;}s:8:\"assignee\";O:19:\"App\\Models\\Employee\":34:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:9:\"employees\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:2:{s:2:\"id\";i:1;s:4:\"name\";s:4:\"Temp\";}s:11:\"\0*\0original\";a:2:{s:2:\"id\";i:1;s:4:\"name\";s:4:\"Temp\";}s:10:\"\0*\0changes\";a:0:{}s:11:\"\0*\0previous\";a:0:{}s:8:\"\0*\0casts\";a:2:{s:12:\"joining_date\";s:4:\"date\";s:10:\"deleted_at\";s:8:\"datetime\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:27:\"\0*\0relationAutoloadCallback\";N;s:26:\"\0*\0relationAutoloadContext\";N;s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:10:{i:0;s:4:\"code\";i:1;s:4:\"name\";i:2;s:11:\"designation\";i:3;s:10:\"department\";i:4;s:6:\"mobile\";i:5;s:5:\"email\";i:6;s:12:\"joining_date\";i:7;s:7:\"address\";i:8;s:7:\"user_id\";i:9;s:6:\"status\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}s:16:\"\0*\0forceDeleting\";b:0;}}s:10:\"\0*\0touches\";a:0:{}s:27:\"\0*\0relationAutoloadCallback\";N;s:26:\"\0*\0relationAutoloadContext\";N;s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:23:{i:0;s:12:\"enquiry_date\";i:1;s:14:\"lead_source_id\";i:2;s:10:\"sub_source\";i:3;s:18:\"competitor_details\";i:4;s:13:\"prospect_type\";i:5;s:8:\"party_id\";i:6;s:11:\"prospect_id\";i:7;s:8:\"industry\";i:8;s:16:\"industry_type_id\";i:9;s:7:\"city_id\";i:10;s:7:\"address\";i:11;s:7:\"website\";i:12;s:12:\"contact_name\";i:13;s:10:\"contact_no\";i:14;s:19:\"service_category_id\";i:15;s:18:\"approx_requirement\";i:16;s:15:\"estimated_value\";i:17;s:21:\"expected_closing_date\";i:18;s:11:\"lead_rating\";i:19;s:11:\"assigned_to\";i:20;s:11:\"next_action\";i:21;s:16:\"next_action_date\";i:22;s:7:\"remarks\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}s:16:\"\0*\0forceDeleting\";b:0;}i:1;O:18:\"App\\Models\\Enquiry\":34:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:9:\"enquiries\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:34:{s:2:\"id\";i:4;s:7:\"unit_id\";i:1;s:17:\"financial_year_id\";i:1;s:10:\"enquiry_no\";s:14:\"ENQ/26-27/0004\";s:12:\"enquiry_date\";s:10:\"2026-10-19\";s:14:\"lead_source_id\";i:7;s:10:\"sub_source\";N;s:18:\"competitor_details\";N;s:13:\"prospect_type\";s:3:\"New\";s:8:\"party_id\";N;s:11:\"prospect_id\";i:4;s:8:\"industry\";N;s:16:\"industry_type_id\";i:2;s:7:\"city_id\";i:1;s:7:\"address\";N;s:7:\"website\";N;s:12:\"contact_name\";s:10:\"Rajdarshan\";s:10:\"contact_no\";s:8:\"98765643\";s:17:\"contact_no_digits\";s:8:\"98765643\";s:19:\"service_category_id\";i:5;s:18:\"approx_requirement\";N;s:15:\"estimated_value\";N;s:21:\"expected_closing_date\";N;s:11:\"lead_rating\";s:3:\"Hot\";s:11:\"assigned_to\";i:1;s:11:\"next_action\";s:10:\"visit site\";s:16:\"next_action_date\";s:10:\"2026-10-20\";s:7:\"remarks\";N;s:6:\"status\";s:4:\"Open\";s:10:\"created_by\";i:1;s:10:\"updated_by\";i:1;s:10:\"created_at\";s:19:\"2026-10-19 17:24:52\";s:10:\"updated_at\";s:19:\"2026-10-19 17:24:52\";s:10:\"deleted_at\";N;}s:11:\"\0*\0original\";a:34:{s:2:\"id\";i:4;s:7:\"unit_id\";i:1;s:17:\"financial_year_id\";i:1;s:10:\"enquiry_no\";s:14:\"ENQ/26-27/0004\";s:12:\"enquiry_date\";s:10:\"2026-10-19\";s:14:\"lead_source_id\";i:7;s:10:\"sub_source\";N;s:18:\"competitor_details\";N;s:13:\"prospect_type\";s:3:\"New\";s:8:\"party_id\";N;s:11:\"prospect_id\";i:4;s:8:\"industry\";N;s:16:\"industry_type_id\";i:2;s:7:\"city_id\";i:1;s:7:\"address\";N;s:7:\"website\";N;s:12:\"contact_name\";s:10:\"Rajdarshan\";s:10:\"contact_no\";s:8:\"98765643\";s:17:\"contact_no_digits\";s:8:\"98765643\";s:19:\"service_category_id\";i:5;s:18:\"approx_requirement\";N;s:15:\"estimated_value\";N;s:21:\"expected_closing_date\";N;s:11:\"lead_rating\";s:3:\"Hot\";s:11:\"assigned_to\";i:1;s:11:\"next_action\";s:10:\"visit site\";s:16:\"next_action_date\";s:10:\"2026-10-20\";s:7:\"remarks\";N;s:6:\"status\";s:4:\"Open\";s:10:\"created_by\";i:1;s:10:\"updated_by\";i:1;s:10:\"created_at\";s:19:\"2026-10-19 17:24:52\";s:10:\"updated_at\";s:19:\"2026-10-19 17:24:52\";s:10:\"deleted_at\";N;}s:10:\"\0*\0changes\";a:0:{}s:11:\"\0*\0previous\";a:0:{}s:8:\"\0*\0casts\";a:8:{s:12:\"enquiry_date\";s:4:\"date\";s:21:\"expected_closing_date\";s:4:\"date\";s:16:\"next_action_date\";s:4:\"date\";s:15:\"estimated_value\";s:9:\"decimal:2\";s:13:\"prospect_type\";s:22:\"App\\Enums\\ProspectType\";s:11:\"lead_rating\";s:20:\"App\\Enums\\LeadRating\";s:6:\"status\";s:23:\"App\\Enums\\EnquiryStatus\";s:10:\"deleted_at\";s:8:\"datetime\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:3:{s:5:\"party\";N;s:8:\"prospect\";O:19:\"App\\Models\\Prospect\":34:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:9:\"prospects\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:2:{s:2:\"id\";i:4;s:4:\"name\";s:12:\"Digonal Mall\";}s:11:\"\0*\0original\";a:2:{s:2:\"id\";i:4;s:4:\"name\";s:12:\"Digonal Mall\";}s:10:\"\0*\0changes\";a:0:{}s:11:\"\0*\0previous\";a:0:{}s:8:\"\0*\0casts\";a:2:{s:6:\"market\";s:16:\"App\\Enums\\Market\";s:10:\"deleted_at\";s:8:\"datetime\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:27:\"\0*\0relationAutoloadCallback\";N;s:26:\"\0*\0relationAutoloadContext\";N;s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:10:{i:0;s:4:\"name\";i:1;s:6:\"market\";i:2;s:14:\"contact_person\";i:3;s:8:\"position\";i:4;s:7:\"phone_1\";i:5;s:7:\"phone_2\";i:6;s:5:\"email\";i:7;s:5:\"gstin\";i:8;s:13:\"industry_type\";i:9;s:7:\"address\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}s:16:\"\0*\0forceDeleting\";b:0;}s:8:\"assignee\";r:521;}s:10:\"\0*\0touches\";a:0:{}s:27:\"\0*\0relationAutoloadCallback\";N;s:26:\"\0*\0relationAutoloadContext\";N;s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:23:{i:0;s:12:\"enquiry_date\";i:1;s:14:\"lead_source_id\";i:2;s:10:\"sub_source\";i:3;s:18:\"competitor_details\";i:4;s:13:\"prospect_type\";i:5;s:8:\"party_id\";i:6;s:11:\"prospect_id\";i:7;s:8:\"industry\";i:8;s:16:\"industry_type_id\";i:9;s:7:\"city_id\";i:10;s:7:\"address\";i:11;s:7:\"website\";i:12;s:12:\"contact_name\";i:13;s:10:\"contact_no\";i:14;s:19:\"service_category_id\";i:15;s:18:\"approx_requirement\";i:16;s:15:\"estimated_value\";i:17;s:21:\"expected_closing_date\";i:18;s:11:\"lead_rating\";i:19;s:11:\"assigned_to\";i:20;s:11:\"next_action\";i:21;s:16:\"next_action_date\";i:22;s:7:\"remarks\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}s:16:\"\0*\0forceDeleting\";b:0;}}s:28:\"\0*\0escapeWhenCastingToString\";b:0;}s:7:\"closing\";O:39:\"Illuminate\\Database\\Eloquent\\Collection\":2:{s:8:\"\0*\0items\";a:0:{}s:28:\"\0*\0escapeWhenCastingToString\";b:0;}s:9:\"poPending\";O:39:\"Illuminate\\Database\\Eloquent\\Collection\":2:{s:8:\"\0*\0items\";a:0:{}s:28:\"\0*\0escapeWhenCastingToString\";b:0;}s:4:\"year\";s:7:\"2026-27\";}',1792433880),('nursery-cache-crm.dashboard.1.1.20261020','a:11:{s:4:\"kpis\";a:9:{s:9:\"enquiries\";i:6;s:3:\"pos\";i:1;s:8:\"po_value\";d:1062001.0600000000558793544769287109375;s:10:\"conversion\";d:16.699999999999999289457264239899814128875732421875;s:4:\"open\";i:5;s:3:\"hot\";i:3;s:10:\"po_pending\";i:0;s:7:\"closing\";i:0;s:13:\"closing_value\";d:0;}s:7:\"monthly\";a:8:{s:5:\"width\";i:600;s:6:\"height\";i:220;s:4:\"left\";i:32;s:4:\"base\";i:192;s:4:\"bars\";a:24:{i:0;a:6:{s:1:\"x\";d:37.7000000000000028421709430404007434844970703125;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Apr: Enquiries received 0\";}i:1;a:6:{s:1:\"x\";d:85;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"May: Enquiries received 0\";}i:2;a:6:{s:1:\"x\";d:132.30000000000001136868377216160297393798828125;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Jun: Enquiries received 0\";}i:3;a:6:{s:1:\"x\";d:179.69999999999998863131622783839702606201171875;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Jul: Enquiries received 0\";}i:4;a:6:{s:1:\"x\";d:227;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Aug: Enquiries received 0\";}i:5;a:6:{s:1:\"x\";d:274.30000000000001136868377216160297393798828125;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Sep: Enquiries received 0\";}i:6;a:6:{s:1:\"x\";d:321.69999999999998863131622783839702606201171875;s:1:\"y\";d:10;s:1:\"w\";d:16;s:1:\"h\";d:182;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Oct: Enquiries received 6\";}i:7;a:6:{s:1:\"x\";d:369;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Nov: Enquiries received 0\";}i:8;a:6:{s:1:\"x\";d:416.30000000000001136868377216160297393798828125;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Dec: Enquiries received 0\";}i:9;a:6:{s:1:\"x\";d:463.69999999999998863131622783839702606201171875;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Jan: Enquiries received 0\";}i:10;a:6:{s:1:\"x\";d:511;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Feb: Enquiries received 0\";}i:11;a:6:{s:1:\"x\";d:558.299999999999954525264911353588104248046875;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Mar: Enquiries received 0\";}i:12;a:6:{s:1:\"x\";d:55.7000000000000028421709430404007434844970703125;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Apr: PO received 0\";}i:13;a:6:{s:1:\"x\";d:103;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"May: PO received 0\";}i:14;a:6:{s:1:\"x\";d:150.30000000000001136868377216160297393798828125;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Jun: PO received 0\";}i:15;a:6:{s:1:\"x\";d:197.69999999999998863131622783839702606201171875;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Jul: PO received 0\";}i:16;a:6:{s:1:\"x\";d:245;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Aug: PO received 0\";}i:17;a:6:{s:1:\"x\";d:292.30000000000001136868377216160297393798828125;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Sep: PO received 0\";}i:18;a:6:{s:1:\"x\";d:339.69999999999998863131622783839702606201171875;s:1:\"y\";d:161.69999999999998863131622783839702606201171875;s:1:\"w\";d:16;s:1:\"h\";d:30.300000000000000710542735760100185871124267578125;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Oct: PO received 1\";}i:19;a:6:{s:1:\"x\";d:387;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Nov: PO received 0\";}i:20;a:6:{s:1:\"x\";d:434.30000000000001136868377216160297393798828125;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Dec: PO received 0\";}i:21;a:6:{s:1:\"x\";d:481.69999999999998863131622783839702606201171875;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Jan: PO received 0\";}i:22;a:6:{s:1:\"x\";d:529;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Feb: PO received 0\";}i:23;a:6:{s:1:\"x\";d:576.299999999999954525264911353588104248046875;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Mar: PO received 0\";}}s:5:\"ticks\";a:4:{i:0;a:2:{s:1:\"y\";d:192;s:5:\"label\";s:1:\"0\";}i:1;a:2:{s:1:\"y\";d:131.30000000000001136868377216160297393798828125;s:5:\"label\";s:1:\"2\";}i:2;a:2:{s:1:\"y\";d:70.7000000000000028421709430404007434844970703125;s:5:\"label\";s:1:\"4\";}i:3;a:2:{s:1:\"y\";d:10;s:5:\"label\";s:1:\"6\";}}s:6:\"labels\";a:12:{i:0;a:2:{s:1:\"x\";d:55.7000000000000028421709430404007434844970703125;s:5:\"label\";s:3:\"Apr\";}i:1;a:2:{s:1:\"x\";d:103;s:5:\"label\";s:3:\"May\";}i:2;a:2:{s:1:\"x\";d:150.30000000000001136868377216160297393798828125;s:5:\"label\";s:3:\"Jun\";}i:3;a:2:{s:1:\"x\";d:197.69999999999998863131622783839702606201171875;s:5:\"label\";s:3:\"Jul\";}i:4;a:2:{s:1:\"x\";d:245;s:5:\"label\";s:3:\"Aug\";}i:5;a:2:{s:1:\"x\";d:292.30000000000001136868377216160297393798828125;s:5:\"label\";s:3:\"Sep\";}i:6;a:2:{s:1:\"x\";d:339.69999999999998863131622783839702606201171875;s:5:\"label\";s:3:\"Oct\";}i:7;a:2:{s:1:\"x\";d:387;s:5:\"label\";s:3:\"Nov\";}i:8;a:2:{s:1:\"x\";d:434.30000000000001136868377216160297393798828125;s:5:\"label\";s:3:\"Dec\";}i:9;a:2:{s:1:\"x\";d:481.69999999999998863131622783839702606201171875;s:5:\"label\";s:3:\"Jan\";}i:10;a:2:{s:1:\"x\";d:529;s:5:\"label\";s:3:\"Feb\";}i:11;a:2:{s:1:\"x\";d:576.299999999999954525264911353588104248046875;s:5:\"label\";s:3:\"Mar\";}}s:6:\"legend\";a:2:{i:0;a:2:{s:5:\"label\";s:18:\"Enquiries received\";s:5:\"class\";s:3:\"cs1\";}i:1;a:2:{s:5:\"label\";s:11:\"PO received\";s:5:\"class\";s:3:\"cs2\";}}}s:12:\"monthlyTable\";a:12:{i:0;a:3:{s:5:\"month\";s:3:\"Apr\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:1;a:3:{s:5:\"month\";s:3:\"May\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:2;a:3:{s:5:\"month\";s:3:\"Jun\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:3;a:3:{s:5:\"month\";s:3:\"Jul\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:4;a:3:{s:5:\"month\";s:3:\"Aug\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:5;a:3:{s:5:\"month\";s:3:\"Sep\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:6;a:3:{s:5:\"month\";s:3:\"Oct\";s:9:\"enquiries\";i:6;s:3:\"pos\";i:1;}i:7;a:3:{s:5:\"month\";s:3:\"Nov\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:8;a:3:{s:5:\"month\";s:3:\"Dec\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:9;a:3:{s:5:\"month\";s:3:\"Jan\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:10;a:3:{s:5:\"month\";s:3:\"Feb\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:11;a:3:{s:5:\"month\";s:3:\"Mar\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}}s:7:\"sources\";a:2:{s:5:\"total\";i:6;s:6:\"slices\";a:4:{i:0;a:5:{s:5:\"label\";s:7:\"Website\";s:5:\"value\";i:3;s:7:\"percent\";d:50;s:5:\"class\";s:3:\"cs1\";s:4:\"path\";s:45:\"M50 50 L50.00 4.00 A46 46 0 0 1 50.00 96.00 Z\";}i:1;a:5:{s:5:\"label\";s:9:\"Architect\";s:5:\"value\";i:1;s:7:\"percent\";d:16.699999999999999289457264239899814128875732421875;s:5:\"class\";s:3:\"cs2\";s:4:\"path\";s:46:\"M50 50 L50.00 96.00 A46 46 0 0 1 10.16 73.00 Z\";}i:2;a:5:{s:5:\"label\";s:10:\"Phone Call\";s:5:\"value\";i:1;s:7:\"percent\";d:16.699999999999999289457264239899814128875732421875;s:5:\"class\";s:3:\"cs3\";s:4:\"path\";s:46:\"M50 50 L10.16 73.00 A46 46 0 0 1 10.16 27.00 Z\";}i:3;a:5:{s:5:\"label\";s:6:\"Google\";s:5:\"value\";i:1;s:7:\"percent\";d:16.699999999999999289457264239899814128875732421875;s:5:\"class\";s:3:\"cs4\";s:4:\"path\";s:45:\"M50 50 L10.16 27.00 A46 46 0 0 1 50.00 4.00 Z\";}}}s:10:\"categories\";a:2:{s:5:\"total\";i:6;s:6:\"slices\";a:6:{i:0;a:5:{s:5:\"label\";s:20:\"Corporate Plantation\";s:5:\"value\";i:1;s:7:\"percent\";d:16.699999999999999289457264239899814128875732421875;s:5:\"class\";s:3:\"cs1\";s:4:\"path\";s:45:\"M50 50 L50.00 4.00 A46 46 0 0 1 89.84 27.00 Z\";}i:1;a:5:{s:5:\"label\";s:16:\"Landscape Design\";s:5:\"value\";i:1;s:7:\"percent\";d:16.699999999999999289457264239899814128875732421875;s:5:\"class\";s:3:\"cs2\";s:4:\"path\";s:46:\"M50 50 L89.84 27.00 A46 46 0 0 1 89.84 73.00 Z\";}i:2;a:5:{s:5:\"label\";s:7:\"GD DECO\";s:5:\"value\";i:1;s:7:\"percent\";d:16.699999999999999289457264239899814128875732421875;s:5:\"class\";s:3:\"cs3\";s:4:\"path\";s:46:\"M50 50 L89.84 73.00 A46 46 0 0 1 50.00 96.00 Z\";}i:3;a:5:{s:5:\"label\";s:18:\"Garden Maintenance\";s:5:\"value\";i:1;s:7:\"percent\";d:16.699999999999999289457264239899814128875732421875;s:5:\"class\";s:3:\"cs4\";s:4:\"path\";s:46:\"M50 50 L50.00 96.00 A46 46 0 0 1 10.16 73.00 Z\";}i:4;a:5:{s:5:\"label\";s:12:\"Plant Rental\";s:5:\"value\";i:1;s:7:\"percent\";d:16.699999999999999289457264239899814128875732421875;s:5:\"class\";s:3:\"cs5\";s:4:\"path\";s:46:\"M50 50 L10.16 73.00 A46 46 0 0 1 10.16 27.00 Z\";}i:5;a:5:{s:5:\"label\";s:9:\"Plant AMC\";s:5:\"value\";i:1;s:7:\"percent\";d:16.699999999999999289457264239899814128875732421875;s:5:\"class\";s:3:\"cs6\";s:4:\"path\";s:45:\"M50 50 L10.16 27.00 A46 46 0 0 1 50.00 4.00 Z\";}}}s:6:\"ageing\";a:5:{s:11:\"0 to 7 days\";i:5;s:12:\"8 to 15 days\";i:0;s:13:\"16 to 30 days\";i:0;s:13:\"31 to 60 days\";i:0;s:12:\"Over 60 days\";i:0;}s:9:\"ageingMax\";i:5;s:3:\"hot\";O:39:\"Illuminate\\Database\\Eloquent\\Collection\":2:{s:8:\"\0*\0items\";a:3:{i:0;O:18:\"App\\Models\\Enquiry\":34:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:9:\"enquiries\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:34:{s:2:\"id\";i:1;s:7:\"unit_id\";i:1;s:17:\"financial_year_id\";i:1;s:10:\"enquiry_no\";s:14:\"ENQ/26-27/0001\";s:12:\"enquiry_date\";s:10:\"2026-10-16\";s:14:\"lead_source_id\";i:1;s:10:\"sub_source\";N;s:18:\"competitor_details\";N;s:13:\"prospect_type\";s:15:\"Existing Client\";s:8:\"party_id\";N;s:11:\"prospect_id\";i:2;s:8:\"industry\";N;s:16:\"industry_type_id\";i:1;s:7:\"city_id\";i:5;s:7:\"address\";N;s:7:\"website\";N;s:12:\"contact_name\";s:2:\"GP\";s:10:\"contact_no\";s:10:\"7894561230\";s:17:\"contact_no_digits\";s:10:\"7894561230\";s:19:\"service_category_id\";i:11;s:18:\"approx_requirement\";N;s:15:\"estimated_value\";N;s:21:\"expected_closing_date\";N;s:11:\"lead_rating\";s:3:\"Hot\";s:11:\"assigned_to\";i:1;s:11:\"next_action\";s:4:\"test\";s:16:\"next_action_date\";s:10:\"1982-04-13\";s:7:\"remarks\";N;s:6:\"status\";s:4:\"Open\";s:10:\"created_by\";i:1;s:10:\"updated_by\";i:1;s:10:\"created_at\";s:19:\"2026-10-16 06:41:33\";s:10:\"updated_at\";s:19:\"2026-10-16 06:41:33\";s:10:\"deleted_at\";N;}s:11:\"\0*\0original\";a:34:{s:2:\"id\";i:1;s:7:\"unit_id\";i:1;s:17:\"financial_year_id\";i:1;s:10:\"enquiry_no\";s:14:\"ENQ/26-27/0001\";s:12:\"enquiry_date\";s:10:\"2026-10-16\";s:14:\"lead_source_id\";i:1;s:10:\"sub_source\";N;s:18:\"competitor_details\";N;s:13:\"prospect_type\";s:15:\"Existing Client\";s:8:\"party_id\";N;s:11:\"prospect_id\";i:2;s:8:\"industry\";N;s:16:\"industry_type_id\";i:1;s:7:\"city_id\";i:5;s:7:\"address\";N;s:7:\"website\";N;s:12:\"contact_name\";s:2:\"GP\";s:10:\"contact_no\";s:10:\"7894561230\";s:17:\"contact_no_digits\";s:10:\"7894561230\";s:19:\"service_category_id\";i:11;s:18:\"approx_requirement\";N;s:15:\"estimated_value\";N;s:21:\"expected_closing_date\";N;s:11:\"lead_rating\";s:3:\"Hot\";s:11:\"assigned_to\";i:1;s:11:\"next_action\";s:4:\"test\";s:16:\"next_action_date\";s:10:\"1982-04-13\";s:7:\"remarks\";N;s:6:\"status\";s:4:\"Open\";s:10:\"created_by\";i:1;s:10:\"updated_by\";i:1;s:10:\"created_at\";s:19:\"2026-10-16 06:41:33\";s:10:\"updated_at\";s:19:\"2026-10-16 06:41:33\";s:10:\"deleted_at\";N;}s:10:\"\0*\0changes\";a:0:{}s:11:\"\0*\0previous\";a:0:{}s:8:\"\0*\0casts\";a:8:{s:12:\"enquiry_date\";s:4:\"date\";s:21:\"expected_closing_date\";s:4:\"date\";s:16:\"next_action_date\";s:4:\"date\";s:15:\"estimated_value\";s:9:\"decimal:2\";s:13:\"prospect_type\";s:22:\"App\\Enums\\ProspectType\";s:11:\"lead_rating\";s:20:\"App\\Enums\\LeadRating\";s:6:\"status\";s:23:\"App\\Enums\\EnquiryStatus\";s:10:\"deleted_at\";s:8:\"datetime\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:3:{s:5:\"party\";N;s:8:\"prospect\";O:19:\"App\\Models\\Prospect\":34:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:9:\"prospects\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:2:{s:2:\"id\";i:2;s:4:\"name\";s:12:\"Ganesh Patil\";}s:11:\"\0*\0original\";a:2:{s:2:\"id\";i:2;s:4:\"name\";s:12:\"Ganesh Patil\";}s:10:\"\0*\0changes\";a:0:{}s:11:\"\0*\0previous\";a:0:{}s:8:\"\0*\0casts\";a:2:{s:6:\"market\";s:16:\"App\\Enums\\Market\";s:10:\"deleted_at\";s:8:\"datetime\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:27:\"\0*\0relationAutoloadCallback\";N;s:26:\"\0*\0relationAutoloadContext\";N;s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:10:{i:0;s:4:\"name\";i:1;s:6:\"market\";i:2;s:14:\"contact_person\";i:3;s:8:\"position\";i:4;s:7:\"phone_1\";i:5;s:7:\"phone_2\";i:6;s:5:\"email\";i:7;s:5:\"gstin\";i:8;s:13:\"industry_type\";i:9;s:7:\"address\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}s:16:\"\0*\0forceDeleting\";b:0;}s:8:\"assignee\";O:19:\"App\\Models\\Employee\":34:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:9:\"employees\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:2:{s:2:\"id\";i:1;s:4:\"name\";s:4:\"Temp\";}s:11:\"\0*\0original\";a:2:{s:2:\"id\";i:1;s:4:\"name\";s:4:\"Temp\";}s:10:\"\0*\0changes\";a:0:{}s:11:\"\0*\0previous\";a:0:{}s:8:\"\0*\0casts\";a:2:{s:12:\"joining_date\";s:4:\"date\";s:10:\"deleted_at\";s:8:\"datetime\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:27:\"\0*\0relationAutoloadCallback\";N;s:26:\"\0*\0relationAutoloadContext\";N;s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:10:{i:0;s:4:\"code\";i:1;s:4:\"name\";i:2;s:11:\"designation\";i:3;s:10:\"department\";i:4;s:6:\"mobile\";i:5;s:5:\"email\";i:6;s:12:\"joining_date\";i:7;s:7:\"address\";i:8;s:7:\"user_id\";i:9;s:6:\"status\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}s:16:\"\0*\0forceDeleting\";b:0;}}s:10:\"\0*\0touches\";a:0:{}s:27:\"\0*\0relationAutoloadCallback\";N;s:26:\"\0*\0relationAutoloadContext\";N;s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:23:{i:0;s:12:\"enquiry_date\";i:1;s:14:\"lead_source_id\";i:2;s:10:\"sub_source\";i:3;s:18:\"competitor_details\";i:4;s:13:\"prospect_type\";i:5;s:8:\"party_id\";i:6;s:11:\"prospect_id\";i:7;s:8:\"industry\";i:8;s:16:\"industry_type_id\";i:9;s:7:\"city_id\";i:10;s:7:\"address\";i:11;s:7:\"website\";i:12;s:12:\"contact_name\";i:13;s:10:\"contact_no\";i:14;s:19:\"service_category_id\";i:15;s:18:\"approx_requirement\";i:16;s:15:\"estimated_value\";i:17;s:21:\"expected_closing_date\";i:18;s:11:\"lead_rating\";i:19;s:11:\"assigned_to\";i:20;s:11:\"next_action\";i:21;s:16:\"next_action_date\";i:22;s:7:\"remarks\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}s:16:\"\0*\0forceDeleting\";b:0;}i:1;O:18:\"App\\Models\\Enquiry\":34:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:9:\"enquiries\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:34:{s:2:\"id\";i:4;s:7:\"unit_id\";i:1;s:17:\"financial_year_id\";i:1;s:10:\"enquiry_no\";s:14:\"ENQ/26-27/0004\";s:12:\"enquiry_date\";s:10:\"2026-10-19\";s:14:\"lead_source_id\";i:7;s:10:\"sub_source\";N;s:18:\"competitor_details\";N;s:13:\"prospect_type\";s:3:\"New\";s:8:\"party_id\";N;s:11:\"prospect_id\";i:4;s:8:\"industry\";N;s:16:\"industry_type_id\";i:2;s:7:\"city_id\";i:1;s:7:\"address\";N;s:7:\"website\";N;s:12:\"contact_name\";s:10:\"Rajdarshan\";s:10:\"contact_no\";s:8:\"98765643\";s:17:\"contact_no_digits\";s:8:\"98765643\";s:19:\"service_category_id\";i:5;s:18:\"approx_requirement\";N;s:15:\"estimated_value\";N;s:21:\"expected_closing_date\";N;s:11:\"lead_rating\";s:3:\"Hot\";s:11:\"assigned_to\";i:1;s:11:\"next_action\";s:10:\"visit site\";s:16:\"next_action_date\";s:10:\"2026-10-20\";s:7:\"remarks\";N;s:6:\"status\";s:4:\"Open\";s:10:\"created_by\";i:1;s:10:\"updated_by\";i:1;s:10:\"created_at\";s:19:\"2026-10-19 17:24:52\";s:10:\"updated_at\";s:19:\"2026-10-19 17:24:52\";s:10:\"deleted_at\";N;}s:11:\"\0*\0original\";a:34:{s:2:\"id\";i:4;s:7:\"unit_id\";i:1;s:17:\"financial_year_id\";i:1;s:10:\"enquiry_no\";s:14:\"ENQ/26-27/0004\";s:12:\"enquiry_date\";s:10:\"2026-10-19\";s:14:\"lead_source_id\";i:7;s:10:\"sub_source\";N;s:18:\"competitor_details\";N;s:13:\"prospect_type\";s:3:\"New\";s:8:\"party_id\";N;s:11:\"prospect_id\";i:4;s:8:\"industry\";N;s:16:\"industry_type_id\";i:2;s:7:\"city_id\";i:1;s:7:\"address\";N;s:7:\"website\";N;s:12:\"contact_name\";s:10:\"Rajdarshan\";s:10:\"contact_no\";s:8:\"98765643\";s:17:\"contact_no_digits\";s:8:\"98765643\";s:19:\"service_category_id\";i:5;s:18:\"approx_requirement\";N;s:15:\"estimated_value\";N;s:21:\"expected_closing_date\";N;s:11:\"lead_rating\";s:3:\"Hot\";s:11:\"assigned_to\";i:1;s:11:\"next_action\";s:10:\"visit site\";s:16:\"next_action_date\";s:10:\"2026-10-20\";s:7:\"remarks\";N;s:6:\"status\";s:4:\"Open\";s:10:\"created_by\";i:1;s:10:\"updated_by\";i:1;s:10:\"created_at\";s:19:\"2026-10-19 17:24:52\";s:10:\"updated_at\";s:19:\"2026-10-19 17:24:52\";s:10:\"deleted_at\";N;}s:10:\"\0*\0changes\";a:0:{}s:11:\"\0*\0previous\";a:0:{}s:8:\"\0*\0casts\";a:8:{s:12:\"enquiry_date\";s:4:\"date\";s:21:\"expected_closing_date\";s:4:\"date\";s:16:\"next_action_date\";s:4:\"date\";s:15:\"estimated_value\";s:9:\"decimal:2\";s:13:\"prospect_type\";s:22:\"App\\Enums\\ProspectType\";s:11:\"lead_rating\";s:20:\"App\\Enums\\LeadRating\";s:6:\"status\";s:23:\"App\\Enums\\EnquiryStatus\";s:10:\"deleted_at\";s:8:\"datetime\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:3:{s:5:\"party\";N;s:8:\"prospect\";O:19:\"App\\Models\\Prospect\":34:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:9:\"prospects\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:2:{s:2:\"id\";i:4;s:4:\"name\";s:12:\"Digonal Mall\";}s:11:\"\0*\0original\";a:2:{s:2:\"id\";i:4;s:4:\"name\";s:12:\"Digonal Mall\";}s:10:\"\0*\0changes\";a:0:{}s:11:\"\0*\0previous\";a:0:{}s:8:\"\0*\0casts\";a:2:{s:6:\"market\";s:16:\"App\\Enums\\Market\";s:10:\"deleted_at\";s:8:\"datetime\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:27:\"\0*\0relationAutoloadCallback\";N;s:26:\"\0*\0relationAutoloadContext\";N;s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:10:{i:0;s:4:\"name\";i:1;s:6:\"market\";i:2;s:14:\"contact_person\";i:3;s:8:\"position\";i:4;s:7:\"phone_1\";i:5;s:7:\"phone_2\";i:6;s:5:\"email\";i:7;s:5:\"gstin\";i:8;s:13:\"industry_type\";i:9;s:7:\"address\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}s:16:\"\0*\0forceDeleting\";b:0;}s:8:\"assignee\";r:521;}s:10:\"\0*\0touches\";a:0:{}s:27:\"\0*\0relationAutoloadCallback\";N;s:26:\"\0*\0relationAutoloadContext\";N;s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:23:{i:0;s:12:\"enquiry_date\";i:1;s:14:\"lead_source_id\";i:2;s:10:\"sub_source\";i:3;s:18:\"competitor_details\";i:4;s:13:\"prospect_type\";i:5;s:8:\"party_id\";i:6;s:11:\"prospect_id\";i:7;s:8:\"industry\";i:8;s:16:\"industry_type_id\";i:9;s:7:\"city_id\";i:10;s:7:\"address\";i:11;s:7:\"website\";i:12;s:12:\"contact_name\";i:13;s:10:\"contact_no\";i:14;s:19:\"service_category_id\";i:15;s:18:\"approx_requirement\";i:16;s:15:\"estimated_value\";i:17;s:21:\"expected_closing_date\";i:18;s:11:\"lead_rating\";i:19;s:11:\"assigned_to\";i:20;s:11:\"next_action\";i:21;s:16:\"next_action_date\";i:22;s:7:\"remarks\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}s:16:\"\0*\0forceDeleting\";b:0;}i:2;O:18:\"App\\Models\\Enquiry\":34:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:9:\"enquiries\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:34:{s:2:\"id\";i:6;s:7:\"unit_id\";i:1;s:17:\"financial_year_id\";i:1;s:10:\"enquiry_no\";s:14:\"ENQ/26-27/0006\";s:12:\"enquiry_date\";s:10:\"2026-10-19\";s:14:\"lead_source_id\";i:1;s:10:\"sub_source\";N;s:18:\"competitor_details\";N;s:13:\"prospect_type\";s:8:\"Referral\";s:8:\"party_id\";N;s:11:\"prospect_id\";i:12;s:8:\"industry\";N;s:16:\"industry_type_id\";i:1;s:7:\"city_id\";i:5;s:7:\"address\";s:17:\"MIDC Chakan, Pune\";s:7:\"website\";N;s:12:\"contact_name\";s:16:\"Test Test Final1\";s:10:\"contact_no\";s:14:\"+91 98220 1222\";s:17:\"contact_no_digits\";s:9:\"982201222\";s:19:\"service_category_id\";i:2;s:18:\"approx_requirement\";s:2:\"40\";s:15:\"estimated_value\";s:9:\"200000.00\";s:21:\"expected_closing_date\";s:10:\"2026-11-21\";s:11:\"lead_rating\";s:3:\"Hot\";s:11:\"assigned_to\";i:1;s:11:\"next_action\";s:9:\"wHATS app\";s:16:\"next_action_date\";s:10:\"2026-10-24\";s:7:\"remarks\";N;s:6:\"status\";s:4:\"Open\";s:10:\"created_by\";i:1;s:10:\"updated_by\";i:1;s:10:\"created_at\";s:19:\"2026-10-19 23:52:15\";s:10:\"updated_at\";s:19:\"2026-10-19 23:56:05\";s:10:\"deleted_at\";N;}s:11:\"\0*\0original\";a:34:{s:2:\"id\";i:6;s:7:\"unit_id\";i:1;s:17:\"financial_year_id\";i:1;s:10:\"enquiry_no\";s:14:\"ENQ/26-27/0006\";s:12:\"enquiry_date\";s:10:\"2026-10-19\";s:14:\"lead_source_id\";i:1;s:10:\"sub_source\";N;s:18:\"competitor_details\";N;s:13:\"prospect_type\";s:8:\"Referral\";s:8:\"party_id\";N;s:11:\"prospect_id\";i:12;s:8:\"industry\";N;s:16:\"industry_type_id\";i:1;s:7:\"city_id\";i:5;s:7:\"address\";s:17:\"MIDC Chakan, Pune\";s:7:\"website\";N;s:12:\"contact_name\";s:16:\"Test Test Final1\";s:10:\"contact_no\";s:14:\"+91 98220 1222\";s:17:\"contact_no_digits\";s:9:\"982201222\";s:19:\"service_category_id\";i:2;s:18:\"approx_requirement\";s:2:\"40\";s:15:\"estimated_value\";s:9:\"200000.00\";s:21:\"expected_closing_date\";s:10:\"2026-11-21\";s:11:\"lead_rating\";s:3:\"Hot\";s:11:\"assigned_to\";i:1;s:11:\"next_action\";s:9:\"wHATS app\";s:16:\"next_action_date\";s:10:\"2026-10-24\";s:7:\"remarks\";N;s:6:\"status\";s:4:\"Open\";s:10:\"created_by\";i:1;s:10:\"updated_by\";i:1;s:10:\"created_at\";s:19:\"2026-10-19 23:52:15\";s:10:\"updated_at\";s:19:\"2026-10-19 23:56:05\";s:10:\"deleted_at\";N;}s:10:\"\0*\0changes\";a:0:{}s:11:\"\0*\0previous\";a:0:{}s:8:\"\0*\0casts\";a:8:{s:12:\"enquiry_date\";s:4:\"date\";s:21:\"expected_closing_date\";s:4:\"date\";s:16:\"next_action_date\";s:4:\"date\";s:15:\"estimated_value\";s:9:\"decimal:2\";s:13:\"prospect_type\";s:22:\"App\\Enums\\ProspectType\";s:11:\"lead_rating\";s:20:\"App\\Enums\\LeadRating\";s:6:\"status\";s:23:\"App\\Enums\\EnquiryStatus\";s:10:\"deleted_at\";s:8:\"datetime\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:3:{s:5:\"party\";N;s:8:\"prospect\";O:19:\"App\\Models\\Prospect\":34:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:9:\"prospects\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:2:{s:2:\"id\";i:12;s:4:\"name\";s:9:\"123412345\";}s:11:\"\0*\0original\";a:2:{s:2:\"id\";i:12;s:4:\"name\";s:9:\"123412345\";}s:10:\"\0*\0changes\";a:0:{}s:11:\"\0*\0previous\";a:0:{}s:8:\"\0*\0casts\";a:2:{s:6:\"market\";s:16:\"App\\Enums\\Market\";s:10:\"deleted_at\";s:8:\"datetime\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:27:\"\0*\0relationAutoloadCallback\";N;s:26:\"\0*\0relationAutoloadContext\";N;s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:10:{i:0;s:4:\"name\";i:1;s:6:\"market\";i:2;s:14:\"contact_person\";i:3;s:8:\"position\";i:4;s:7:\"phone_1\";i:5;s:7:\"phone_2\";i:6;s:5:\"email\";i:7;s:5:\"gstin\";i:8;s:13:\"industry_type\";i:9;s:7:\"address\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}s:16:\"\0*\0forceDeleting\";b:0;}s:8:\"assignee\";r:521;}s:10:\"\0*\0touches\";a:0:{}s:27:\"\0*\0relationAutoloadCallback\";N;s:26:\"\0*\0relationAutoloadContext\";N;s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:23:{i:0;s:12:\"enquiry_date\";i:1;s:14:\"lead_source_id\";i:2;s:10:\"sub_source\";i:3;s:18:\"competitor_details\";i:4;s:13:\"prospect_type\";i:5;s:8:\"party_id\";i:6;s:11:\"prospect_id\";i:7;s:8:\"industry\";i:8;s:16:\"industry_type_id\";i:9;s:7:\"city_id\";i:10;s:7:\"address\";i:11;s:7:\"website\";i:12;s:12:\"contact_name\";i:13;s:10:\"contact_no\";i:14;s:19:\"service_category_id\";i:15;s:18:\"approx_requirement\";i:16;s:15:\"estimated_value\";i:17;s:21:\"expected_closing_date\";i:18;s:11:\"lead_rating\";i:19;s:11:\"assigned_to\";i:20;s:11:\"next_action\";i:21;s:16:\"next_action_date\";i:22;s:7:\"remarks\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}s:16:\"\0*\0forceDeleting\";b:0;}}s:28:\"\0*\0escapeWhenCastingToString\";b:0;}s:7:\"closing\";O:39:\"Illuminate\\Database\\Eloquent\\Collection\":2:{s:8:\"\0*\0items\";a:0:{}s:28:\"\0*\0escapeWhenCastingToString\";b:0;}s:9:\"poPending\";O:39:\"Illuminate\\Database\\Eloquent\\Collection\":2:{s:8:\"\0*\0items\";a:0:{}s:28:\"\0*\0escapeWhenCastingToString\";b:0;}s:4:\"year\";s:7:\"2026-27\";}',1792515958),('nursery-cache-crm.dashboard.1.1.20261021','a:11:{s:4:\"kpis\";a:9:{s:9:\"enquiries\";i:6;s:3:\"pos\";i:1;s:8:\"po_value\";d:1062001.0600000000558793544769287109375;s:10:\"conversion\";d:16.699999999999999289457264239899814128875732421875;s:4:\"open\";i:5;s:3:\"hot\";i:3;s:10:\"po_pending\";i:0;s:7:\"closing\";i:0;s:13:\"closing_value\";d:0;}s:7:\"monthly\";a:8:{s:5:\"width\";i:600;s:6:\"height\";i:220;s:4:\"left\";i:32;s:4:\"base\";i:192;s:4:\"bars\";a:24:{i:0;a:6:{s:1:\"x\";d:37.7000000000000028421709430404007434844970703125;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Apr: Enquiries received 0\";}i:1;a:6:{s:1:\"x\";d:85;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"May: Enquiries received 0\";}i:2;a:6:{s:1:\"x\";d:132.30000000000001136868377216160297393798828125;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Jun: Enquiries received 0\";}i:3;a:6:{s:1:\"x\";d:179.69999999999998863131622783839702606201171875;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Jul: Enquiries received 0\";}i:4;a:6:{s:1:\"x\";d:227;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Aug: Enquiries received 0\";}i:5;a:6:{s:1:\"x\";d:274.30000000000001136868377216160297393798828125;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Sep: Enquiries received 0\";}i:6;a:6:{s:1:\"x\";d:321.69999999999998863131622783839702606201171875;s:1:\"y\";d:10;s:1:\"w\";d:16;s:1:\"h\";d:182;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Oct: Enquiries received 6\";}i:7;a:6:{s:1:\"x\";d:369;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Nov: Enquiries received 0\";}i:8;a:6:{s:1:\"x\";d:416.30000000000001136868377216160297393798828125;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Dec: Enquiries received 0\";}i:9;a:6:{s:1:\"x\";d:463.69999999999998863131622783839702606201171875;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Jan: Enquiries received 0\";}i:10;a:6:{s:1:\"x\";d:511;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Feb: Enquiries received 0\";}i:11;a:6:{s:1:\"x\";d:558.299999999999954525264911353588104248046875;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs1\";s:5:\"title\";s:25:\"Mar: Enquiries received 0\";}i:12;a:6:{s:1:\"x\";d:55.7000000000000028421709430404007434844970703125;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Apr: PO received 0\";}i:13;a:6:{s:1:\"x\";d:103;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"May: PO received 0\";}i:14;a:6:{s:1:\"x\";d:150.30000000000001136868377216160297393798828125;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Jun: PO received 0\";}i:15;a:6:{s:1:\"x\";d:197.69999999999998863131622783839702606201171875;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Jul: PO received 0\";}i:16;a:6:{s:1:\"x\";d:245;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Aug: PO received 0\";}i:17;a:6:{s:1:\"x\";d:292.30000000000001136868377216160297393798828125;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Sep: PO received 0\";}i:18;a:6:{s:1:\"x\";d:339.69999999999998863131622783839702606201171875;s:1:\"y\";d:161.69999999999998863131622783839702606201171875;s:1:\"w\";d:16;s:1:\"h\";d:30.300000000000000710542735760100185871124267578125;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Oct: PO received 1\";}i:19;a:6:{s:1:\"x\";d:387;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Nov: PO received 0\";}i:20;a:6:{s:1:\"x\";d:434.30000000000001136868377216160297393798828125;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Dec: PO received 0\";}i:21;a:6:{s:1:\"x\";d:481.69999999999998863131622783839702606201171875;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Jan: PO received 0\";}i:22;a:6:{s:1:\"x\";d:529;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Feb: PO received 0\";}i:23;a:6:{s:1:\"x\";d:576.299999999999954525264911353588104248046875;s:1:\"y\";d:192;s:1:\"w\";d:16;s:1:\"h\";d:0;s:5:\"class\";s:3:\"cs2\";s:5:\"title\";s:18:\"Mar: PO received 0\";}}s:5:\"ticks\";a:4:{i:0;a:2:{s:1:\"y\";d:192;s:5:\"label\";s:1:\"0\";}i:1;a:2:{s:1:\"y\";d:131.30000000000001136868377216160297393798828125;s:5:\"label\";s:1:\"2\";}i:2;a:2:{s:1:\"y\";d:70.7000000000000028421709430404007434844970703125;s:5:\"label\";s:1:\"4\";}i:3;a:2:{s:1:\"y\";d:10;s:5:\"label\";s:1:\"6\";}}s:6:\"labels\";a:12:{i:0;a:2:{s:1:\"x\";d:55.7000000000000028421709430404007434844970703125;s:5:\"label\";s:3:\"Apr\";}i:1;a:2:{s:1:\"x\";d:103;s:5:\"label\";s:3:\"May\";}i:2;a:2:{s:1:\"x\";d:150.30000000000001136868377216160297393798828125;s:5:\"label\";s:3:\"Jun\";}i:3;a:2:{s:1:\"x\";d:197.69999999999998863131622783839702606201171875;s:5:\"label\";s:3:\"Jul\";}i:4;a:2:{s:1:\"x\";d:245;s:5:\"label\";s:3:\"Aug\";}i:5;a:2:{s:1:\"x\";d:292.30000000000001136868377216160297393798828125;s:5:\"label\";s:3:\"Sep\";}i:6;a:2:{s:1:\"x\";d:339.69999999999998863131622783839702606201171875;s:5:\"label\";s:3:\"Oct\";}i:7;a:2:{s:1:\"x\";d:387;s:5:\"label\";s:3:\"Nov\";}i:8;a:2:{s:1:\"x\";d:434.30000000000001136868377216160297393798828125;s:5:\"label\";s:3:\"Dec\";}i:9;a:2:{s:1:\"x\";d:481.69999999999998863131622783839702606201171875;s:5:\"label\";s:3:\"Jan\";}i:10;a:2:{s:1:\"x\";d:529;s:5:\"label\";s:3:\"Feb\";}i:11;a:2:{s:1:\"x\";d:576.299999999999954525264911353588104248046875;s:5:\"label\";s:3:\"Mar\";}}s:6:\"legend\";a:2:{i:0;a:2:{s:5:\"label\";s:18:\"Enquiries received\";s:5:\"class\";s:3:\"cs1\";}i:1;a:2:{s:5:\"label\";s:11:\"PO received\";s:5:\"class\";s:3:\"cs2\";}}}s:12:\"monthlyTable\";a:12:{i:0;a:3:{s:5:\"month\";s:3:\"Apr\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:1;a:3:{s:5:\"month\";s:3:\"May\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:2;a:3:{s:5:\"month\";s:3:\"Jun\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:3;a:3:{s:5:\"month\";s:3:\"Jul\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:4;a:3:{s:5:\"month\";s:3:\"Aug\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:5;a:3:{s:5:\"month\";s:3:\"Sep\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:6;a:3:{s:5:\"month\";s:3:\"Oct\";s:9:\"enquiries\";i:6;s:3:\"pos\";i:1;}i:7;a:3:{s:5:\"month\";s:3:\"Nov\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:8;a:3:{s:5:\"month\";s:3:\"Dec\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:9;a:3:{s:5:\"month\";s:3:\"Jan\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:10;a:3:{s:5:\"month\";s:3:\"Feb\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}i:11;a:3:{s:5:\"month\";s:3:\"Mar\";s:9:\"enquiries\";i:0;s:3:\"pos\";i:0;}}s:7:\"sources\";a:2:{s:5:\"total\";i:6;s:6:\"slices\";a:4:{i:0;a:5:{s:5:\"label\";s:7:\"Website\";s:5:\"value\";i:3;s:7:\"percent\";d:50;s:5:\"class\";s:3:\"cs1\";s:4:\"path\";s:45:\"M50 50 L50.00 4.00 A46 46 0 0 1 50.00 96.00 Z\";}i:1;a:5:{s:5:\"label\";s:9:\"Architect\";s:5:\"value\";i:1;s:7:\"percent\";d:16.699999999999999289457264239899814128875732421875;s:5:\"class\";s:3:\"cs2\";s:4:\"path\";s:46:\"M50 50 L50.00 96.00 A46 46 0 0 1 10.16 73.00 Z\";}i:2;a:5:{s:5:\"label\";s:10:\"Phone Call\";s:5:\"value\";i:1;s:7:\"percent\";d:16.699999999999999289457264239899814128875732421875;s:5:\"class\";s:3:\"cs3\";s:4:\"path\";s:46:\"M50 50 L10.16 73.00 A46 46 0 0 1 10.16 27.00 Z\";}i:3;a:5:{s:5:\"label\";s:6:\"Google\";s:5:\"value\";i:1;s:7:\"percent\";d:16.699999999999999289457264239899814128875732421875;s:5:\"class\";s:3:\"cs4\";s:4:\"path\";s:45:\"M50 50 L10.16 27.00 A46 46 0 0 1 50.00 4.00 Z\";}}}s:10:\"categories\";a:2:{s:5:\"total\";i:6;s:6:\"slices\";a:6:{i:0;a:5:{s:5:\"label\";s:20:\"Corporate Plantation\";s:5:\"value\";i:1;s:7:\"percent\";d:16.699999999999999289457264239899814128875732421875;s:5:\"class\";s:3:\"cs1\";s:4:\"path\";s:45:\"M50 50 L50.00 4.00 A46 46 0 0 1 89.84 27.00 Z\";}i:1;a:5:{s:5:\"label\";s:16:\"Landscape Design\";s:5:\"value\";i:1;s:7:\"percent\";d:16.699999999999999289457264239899814128875732421875;s:5:\"class\";s:3:\"cs2\";s:4:\"path\";s:46:\"M50 50 L89.84 27.00 A46 46 0 0 1 89.84 73.00 Z\";}i:2;a:5:{s:5:\"label\";s:7:\"GD DECO\";s:5:\"value\";i:1;s:7:\"percent\";d:16.699999999999999289457264239899814128875732421875;s:5:\"class\";s:3:\"cs3\";s:4:\"path\";s:46:\"M50 50 L89.84 73.00 A46 46 0 0 1 50.00 96.00 Z\";}i:3;a:5:{s:5:\"label\";s:18:\"Garden Maintenance\";s:5:\"value\";i:1;s:7:\"percent\";d:16.699999999999999289457264239899814128875732421875;s:5:\"class\";s:3:\"cs4\";s:4:\"path\";s:46:\"M50 50 L50.00 96.00 A46 46 0 0 1 10.16 73.00 Z\";}i:4;a:5:{s:5:\"label\";s:12:\"Plant Rental\";s:5:\"value\";i:1;s:7:\"percent\";d:16.699999999999999289457264239899814128875732421875;s:5:\"class\";s:3:\"cs5\";s:4:\"path\";s:46:\"M50 50 L10.16 73.00 A46 46 0 0 1 10.16 27.00 Z\";}i:5;a:5:{s:5:\"label\";s:9:\"Plant AMC\";s:5:\"value\";i:1;s:7:\"percent\";d:16.699999999999999289457264239899814128875732421875;s:5:\"class\";s:3:\"cs6\";s:4:\"path\";s:45:\"M50 50 L10.16 27.00 A46 46 0 0 1 50.00 4.00 Z\";}}}s:6:\"ageing\";a:5:{s:11:\"0 to 7 days\";i:5;s:12:\"8 to 15 days\";i:0;s:13:\"16 to 30 days\";i:0;s:13:\"31 to 60 days\";i:0;s:12:\"Over 60 days\";i:0;}s:9:\"ageingMax\";i:5;s:3:\"hot\";O:39:\"Illuminate\\Database\\Eloquent\\Collection\":2:{s:8:\"\0*\0items\";a:3:{i:0;O:18:\"App\\Models\\Enquiry\":34:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:9:\"enquiries\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:34:{s:2:\"id\";i:1;s:7:\"unit_id\";i:1;s:17:\"financial_year_id\";i:1;s:10:\"enquiry_no\";s:14:\"ENQ/26-27/0001\";s:12:\"enquiry_date\";s:10:\"2026-10-16\";s:14:\"lead_source_id\";i:1;s:10:\"sub_source\";N;s:18:\"competitor_details\";N;s:13:\"prospect_type\";s:15:\"Existing Client\";s:8:\"party_id\";N;s:11:\"prospect_id\";i:2;s:8:\"industry\";N;s:16:\"industry_type_id\";i:1;s:7:\"city_id\";i:5;s:7:\"address\";N;s:7:\"website\";N;s:12:\"contact_name\";s:2:\"GP\";s:10:\"contact_no\";s:10:\"7894561230\";s:17:\"contact_no_digits\";s:10:\"7894561230\";s:19:\"service_category_id\";i:11;s:18:\"approx_requirement\";N;s:15:\"estimated_value\";N;s:21:\"expected_closing_date\";N;s:11:\"lead_rating\";s:3:\"Hot\";s:11:\"assigned_to\";i:1;s:11:\"next_action\";s:4:\"test\";s:16:\"next_action_date\";s:10:\"1982-04-13\";s:7:\"remarks\";N;s:6:\"status\";s:4:\"Open\";s:10:\"created_by\";i:1;s:10:\"updated_by\";i:1;s:10:\"created_at\";s:19:\"2026-10-16 06:41:33\";s:10:\"updated_at\";s:19:\"2026-10-16 06:41:33\";s:10:\"deleted_at\";N;}s:11:\"\0*\0original\";a:34:{s:2:\"id\";i:1;s:7:\"unit_id\";i:1;s:17:\"financial_year_id\";i:1;s:10:\"enquiry_no\";s:14:\"ENQ/26-27/0001\";s:12:\"enquiry_date\";s:10:\"2026-10-16\";s:14:\"lead_source_id\";i:1;s:10:\"sub_source\";N;s:18:\"competitor_details\";N;s:13:\"prospect_type\";s:15:\"Existing Client\";s:8:\"party_id\";N;s:11:\"prospect_id\";i:2;s:8:\"industry\";N;s:16:\"industry_type_id\";i:1;s:7:\"city_id\";i:5;s:7:\"address\";N;s:7:\"website\";N;s:12:\"contact_name\";s:2:\"GP\";s:10:\"contact_no\";s:10:\"7894561230\";s:17:\"contact_no_digits\";s:10:\"7894561230\";s:19:\"service_category_id\";i:11;s:18:\"approx_requirement\";N;s:15:\"estimated_value\";N;s:21:\"expected_closing_date\";N;s:11:\"lead_rating\";s:3:\"Hot\";s:11:\"assigned_to\";i:1;s:11:\"next_action\";s:4:\"test\";s:16:\"next_action_date\";s:10:\"1982-04-13\";s:7:\"remarks\";N;s:6:\"status\";s:4:\"Open\";s:10:\"created_by\";i:1;s:10:\"updated_by\";i:1;s:10:\"created_at\";s:19:\"2026-10-16 06:41:33\";s:10:\"updated_at\";s:19:\"2026-10-16 06:41:33\";s:10:\"deleted_at\";N;}s:10:\"\0*\0changes\";a:0:{}s:11:\"\0*\0previous\";a:0:{}s:8:\"\0*\0casts\";a:8:{s:12:\"enquiry_date\";s:4:\"date\";s:21:\"expected_closing_date\";s:4:\"date\";s:16:\"next_action_date\";s:4:\"date\";s:15:\"estimated_value\";s:9:\"decimal:2\";s:13:\"prospect_type\";s:22:\"App\\Enums\\ProspectType\";s:11:\"lead_rating\";s:20:\"App\\Enums\\LeadRating\";s:6:\"status\";s:23:\"App\\Enums\\EnquiryStatus\";s:10:\"deleted_at\";s:8:\"datetime\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:3:{s:5:\"party\";N;s:8:\"prospect\";O:19:\"App\\Models\\Prospect\":34:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:9:\"prospects\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:2:{s:2:\"id\";i:2;s:4:\"name\";s:12:\"Ganesh Patil\";}s:11:\"\0*\0original\";a:2:{s:2:\"id\";i:2;s:4:\"name\";s:12:\"Ganesh Patil\";}s:10:\"\0*\0changes\";a:0:{}s:11:\"\0*\0previous\";a:0:{}s:8:\"\0*\0casts\";a:2:{s:6:\"market\";s:16:\"App\\Enums\\Market\";s:10:\"deleted_at\";s:8:\"datetime\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:27:\"\0*\0relationAutoloadCallback\";N;s:26:\"\0*\0relationAutoloadContext\";N;s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:10:{i:0;s:4:\"name\";i:1;s:6:\"market\";i:2;s:14:\"contact_person\";i:3;s:8:\"position\";i:4;s:7:\"phone_1\";i:5;s:7:\"phone_2\";i:6;s:5:\"email\";i:7;s:5:\"gstin\";i:8;s:13:\"industry_type\";i:9;s:7:\"address\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}s:16:\"\0*\0forceDeleting\";b:0;}s:8:\"assignee\";O:19:\"App\\Models\\Employee\":34:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:9:\"employees\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:2:{s:2:\"id\";i:1;s:4:\"name\";s:4:\"Temp\";}s:11:\"\0*\0original\";a:2:{s:2:\"id\";i:1;s:4:\"name\";s:4:\"Temp\";}s:10:\"\0*\0changes\";a:0:{}s:11:\"\0*\0previous\";a:0:{}s:8:\"\0*\0casts\";a:2:{s:12:\"joining_date\";s:4:\"date\";s:10:\"deleted_at\";s:8:\"datetime\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:27:\"\0*\0relationAutoloadCallback\";N;s:26:\"\0*\0relationAutoloadContext\";N;s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:10:{i:0;s:4:\"code\";i:1;s:4:\"name\";i:2;s:11:\"designation\";i:3;s:10:\"department\";i:4;s:6:\"mobile\";i:5;s:5:\"email\";i:6;s:12:\"joining_date\";i:7;s:7:\"address\";i:8;s:7:\"user_id\";i:9;s:6:\"status\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}s:16:\"\0*\0forceDeleting\";b:0;}}s:10:\"\0*\0touches\";a:0:{}s:27:\"\0*\0relationAutoloadCallback\";N;s:26:\"\0*\0relationAutoloadContext\";N;s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:23:{i:0;s:12:\"enquiry_date\";i:1;s:14:\"lead_source_id\";i:2;s:10:\"sub_source\";i:3;s:18:\"competitor_details\";i:4;s:13:\"prospect_type\";i:5;s:8:\"party_id\";i:6;s:11:\"prospect_id\";i:7;s:8:\"industry\";i:8;s:16:\"industry_type_id\";i:9;s:7:\"city_id\";i:10;s:7:\"address\";i:11;s:7:\"website\";i:12;s:12:\"contact_name\";i:13;s:10:\"contact_no\";i:14;s:19:\"service_category_id\";i:15;s:18:\"approx_requirement\";i:16;s:15:\"estimated_value\";i:17;s:21:\"expected_closing_date\";i:18;s:11:\"lead_rating\";i:19;s:11:\"assigned_to\";i:20;s:11:\"next_action\";i:21;s:16:\"next_action_date\";i:22;s:7:\"remarks\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}s:16:\"\0*\0forceDeleting\";b:0;}i:1;O:18:\"App\\Models\\Enquiry\":34:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:9:\"enquiries\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:34:{s:2:\"id\";i:4;s:7:\"unit_id\";i:1;s:17:\"financial_year_id\";i:1;s:10:\"enquiry_no\";s:14:\"ENQ/26-27/0004\";s:12:\"enquiry_date\";s:10:\"2026-10-19\";s:14:\"lead_source_id\";i:7;s:10:\"sub_source\";N;s:18:\"competitor_details\";N;s:13:\"prospect_type\";s:3:\"New\";s:8:\"party_id\";N;s:11:\"prospect_id\";i:4;s:8:\"industry\";N;s:16:\"industry_type_id\";i:2;s:7:\"city_id\";i:1;s:7:\"address\";N;s:7:\"website\";N;s:12:\"contact_name\";s:10:\"Rajdarshan\";s:10:\"contact_no\";s:8:\"98765643\";s:17:\"contact_no_digits\";s:8:\"98765643\";s:19:\"service_category_id\";i:5;s:18:\"approx_requirement\";N;s:15:\"estimated_value\";N;s:21:\"expected_closing_date\";N;s:11:\"lead_rating\";s:3:\"Hot\";s:11:\"assigned_to\";i:1;s:11:\"next_action\";s:10:\"visit site\";s:16:\"next_action_date\";s:10:\"2026-10-20\";s:7:\"remarks\";N;s:6:\"status\";s:4:\"Open\";s:10:\"created_by\";i:1;s:10:\"updated_by\";i:1;s:10:\"created_at\";s:19:\"2026-10-19 17:24:52\";s:10:\"updated_at\";s:19:\"2026-10-19 17:24:52\";s:10:\"deleted_at\";N;}s:11:\"\0*\0original\";a:34:{s:2:\"id\";i:4;s:7:\"unit_id\";i:1;s:17:\"financial_year_id\";i:1;s:10:\"enquiry_no\";s:14:\"ENQ/26-27/0004\";s:12:\"enquiry_date\";s:10:\"2026-10-19\";s:14:\"lead_source_id\";i:7;s:10:\"sub_source\";N;s:18:\"competitor_details\";N;s:13:\"prospect_type\";s:3:\"New\";s:8:\"party_id\";N;s:11:\"prospect_id\";i:4;s:8:\"industry\";N;s:16:\"industry_type_id\";i:2;s:7:\"city_id\";i:1;s:7:\"address\";N;s:7:\"website\";N;s:12:\"contact_name\";s:10:\"Rajdarshan\";s:10:\"contact_no\";s:8:\"98765643\";s:17:\"contact_no_digits\";s:8:\"98765643\";s:19:\"service_category_id\";i:5;s:18:\"approx_requirement\";N;s:15:\"estimated_value\";N;s:21:\"expected_closing_date\";N;s:11:\"lead_rating\";s:3:\"Hot\";s:11:\"assigned_to\";i:1;s:11:\"next_action\";s:10:\"visit site\";s:16:\"next_action_date\";s:10:\"2026-10-20\";s:7:\"remarks\";N;s:6:\"status\";s:4:\"Open\";s:10:\"created_by\";i:1;s:10:\"updated_by\";i:1;s:10:\"created_at\";s:19:\"2026-10-19 17:24:52\";s:10:\"updated_at\";s:19:\"2026-10-19 17:24:52\";s:10:\"deleted_at\";N;}s:10:\"\0*\0changes\";a:0:{}s:11:\"\0*\0previous\";a:0:{}s:8:\"\0*\0casts\";a:8:{s:12:\"enquiry_date\";s:4:\"date\";s:21:\"expected_closing_date\";s:4:\"date\";s:16:\"next_action_date\";s:4:\"date\";s:15:\"estimated_value\";s:9:\"decimal:2\";s:13:\"prospect_type\";s:22:\"App\\Enums\\ProspectType\";s:11:\"lead_rating\";s:20:\"App\\Enums\\LeadRating\";s:6:\"status\";s:23:\"App\\Enums\\EnquiryStatus\";s:10:\"deleted_at\";s:8:\"datetime\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:3:{s:5:\"party\";N;s:8:\"prospect\";O:19:\"App\\Models\\Prospect\":34:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:9:\"prospects\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:2:{s:2:\"id\";i:4;s:4:\"name\";s:12:\"Digonal Mall\";}s:11:\"\0*\0original\";a:2:{s:2:\"id\";i:4;s:4:\"name\";s:12:\"Digonal Mall\";}s:10:\"\0*\0changes\";a:0:{}s:11:\"\0*\0previous\";a:0:{}s:8:\"\0*\0casts\";a:2:{s:6:\"market\";s:16:\"App\\Enums\\Market\";s:10:\"deleted_at\";s:8:\"datetime\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:27:\"\0*\0relationAutoloadCallback\";N;s:26:\"\0*\0relationAutoloadContext\";N;s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:10:{i:0;s:4:\"name\";i:1;s:6:\"market\";i:2;s:14:\"contact_person\";i:3;s:8:\"position\";i:4;s:7:\"phone_1\";i:5;s:7:\"phone_2\";i:6;s:5:\"email\";i:7;s:5:\"gstin\";i:8;s:13:\"industry_type\";i:9;s:7:\"address\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}s:16:\"\0*\0forceDeleting\";b:0;}s:8:\"assignee\";r:521;}s:10:\"\0*\0touches\";a:0:{}s:27:\"\0*\0relationAutoloadCallback\";N;s:26:\"\0*\0relationAutoloadContext\";N;s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:23:{i:0;s:12:\"enquiry_date\";i:1;s:14:\"lead_source_id\";i:2;s:10:\"sub_source\";i:3;s:18:\"competitor_details\";i:4;s:13:\"prospect_type\";i:5;s:8:\"party_id\";i:6;s:11:\"prospect_id\";i:7;s:8:\"industry\";i:8;s:16:\"industry_type_id\";i:9;s:7:\"city_id\";i:10;s:7:\"address\";i:11;s:7:\"website\";i:12;s:12:\"contact_name\";i:13;s:10:\"contact_no\";i:14;s:19:\"service_category_id\";i:15;s:18:\"approx_requirement\";i:16;s:15:\"estimated_value\";i:17;s:21:\"expected_closing_date\";i:18;s:11:\"lead_rating\";i:19;s:11:\"assigned_to\";i:20;s:11:\"next_action\";i:21;s:16:\"next_action_date\";i:22;s:7:\"remarks\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}s:16:\"\0*\0forceDeleting\";b:0;}i:2;O:18:\"App\\Models\\Enquiry\":34:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:9:\"enquiries\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:34:{s:2:\"id\";i:6;s:7:\"unit_id\";i:1;s:17:\"financial_year_id\";i:1;s:10:\"enquiry_no\";s:14:\"ENQ/26-27/0006\";s:12:\"enquiry_date\";s:10:\"2026-10-19\";s:14:\"lead_source_id\";i:1;s:10:\"sub_source\";N;s:18:\"competitor_details\";N;s:13:\"prospect_type\";s:8:\"Referral\";s:8:\"party_id\";N;s:11:\"prospect_id\";i:12;s:8:\"industry\";N;s:16:\"industry_type_id\";i:1;s:7:\"city_id\";i:5;s:7:\"address\";s:17:\"MIDC Chakan, Pune\";s:7:\"website\";N;s:12:\"contact_name\";s:16:\"Test Test Final1\";s:10:\"contact_no\";s:14:\"+91 98220 1222\";s:17:\"contact_no_digits\";s:9:\"982201222\";s:19:\"service_category_id\";i:2;s:18:\"approx_requirement\";s:2:\"40\";s:15:\"estimated_value\";s:9:\"200000.00\";s:21:\"expected_closing_date\";s:10:\"2026-11-21\";s:11:\"lead_rating\";s:3:\"Hot\";s:11:\"assigned_to\";i:1;s:11:\"next_action\";s:9:\"wHATS app\";s:16:\"next_action_date\";s:10:\"2026-10-24\";s:7:\"remarks\";N;s:6:\"status\";s:4:\"Open\";s:10:\"created_by\";i:1;s:10:\"updated_by\";i:1;s:10:\"created_at\";s:19:\"2026-10-19 23:52:15\";s:10:\"updated_at\";s:19:\"2026-10-19 23:56:05\";s:10:\"deleted_at\";N;}s:11:\"\0*\0original\";a:34:{s:2:\"id\";i:6;s:7:\"unit_id\";i:1;s:17:\"financial_year_id\";i:1;s:10:\"enquiry_no\";s:14:\"ENQ/26-27/0006\";s:12:\"enquiry_date\";s:10:\"2026-10-19\";s:14:\"lead_source_id\";i:1;s:10:\"sub_source\";N;s:18:\"competitor_details\";N;s:13:\"prospect_type\";s:8:\"Referral\";s:8:\"party_id\";N;s:11:\"prospect_id\";i:12;s:8:\"industry\";N;s:16:\"industry_type_id\";i:1;s:7:\"city_id\";i:5;s:7:\"address\";s:17:\"MIDC Chakan, Pune\";s:7:\"website\";N;s:12:\"contact_name\";s:16:\"Test Test Final1\";s:10:\"contact_no\";s:14:\"+91 98220 1222\";s:17:\"contact_no_digits\";s:9:\"982201222\";s:19:\"service_category_id\";i:2;s:18:\"approx_requirement\";s:2:\"40\";s:15:\"estimated_value\";s:9:\"200000.00\";s:21:\"expected_closing_date\";s:10:\"2026-11-21\";s:11:\"lead_rating\";s:3:\"Hot\";s:11:\"assigned_to\";i:1;s:11:\"next_action\";s:9:\"wHATS app\";s:16:\"next_action_date\";s:10:\"2026-10-24\";s:7:\"remarks\";N;s:6:\"status\";s:4:\"Open\";s:10:\"created_by\";i:1;s:10:\"updated_by\";i:1;s:10:\"created_at\";s:19:\"2026-10-19 23:52:15\";s:10:\"updated_at\";s:19:\"2026-10-19 23:56:05\";s:10:\"deleted_at\";N;}s:10:\"\0*\0changes\";a:0:{}s:11:\"\0*\0previous\";a:0:{}s:8:\"\0*\0casts\";a:8:{s:12:\"enquiry_date\";s:4:\"date\";s:21:\"expected_closing_date\";s:4:\"date\";s:16:\"next_action_date\";s:4:\"date\";s:15:\"estimated_value\";s:9:\"decimal:2\";s:13:\"prospect_type\";s:22:\"App\\Enums\\ProspectType\";s:11:\"lead_rating\";s:20:\"App\\Enums\\LeadRating\";s:6:\"status\";s:23:\"App\\Enums\\EnquiryStatus\";s:10:\"deleted_at\";s:8:\"datetime\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:3:{s:5:\"party\";N;s:8:\"prospect\";O:19:\"App\\Models\\Prospect\":34:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:9:\"prospects\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:2:{s:2:\"id\";i:12;s:4:\"name\";s:9:\"123412345\";}s:11:\"\0*\0original\";a:2:{s:2:\"id\";i:12;s:4:\"name\";s:9:\"123412345\";}s:10:\"\0*\0changes\";a:0:{}s:11:\"\0*\0previous\";a:0:{}s:8:\"\0*\0casts\";a:2:{s:6:\"market\";s:16:\"App\\Enums\\Market\";s:10:\"deleted_at\";s:8:\"datetime\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:27:\"\0*\0relationAutoloadCallback\";N;s:26:\"\0*\0relationAutoloadContext\";N;s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:10:{i:0;s:4:\"name\";i:1;s:6:\"market\";i:2;s:14:\"contact_person\";i:3;s:8:\"position\";i:4;s:7:\"phone_1\";i:5;s:7:\"phone_2\";i:6;s:5:\"email\";i:7;s:5:\"gstin\";i:8;s:13:\"industry_type\";i:9;s:7:\"address\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}s:16:\"\0*\0forceDeleting\";b:0;}s:8:\"assignee\";r:521;}s:10:\"\0*\0touches\";a:0:{}s:27:\"\0*\0relationAutoloadCallback\";N;s:26:\"\0*\0relationAutoloadContext\";N;s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:23:{i:0;s:12:\"enquiry_date\";i:1;s:14:\"lead_source_id\";i:2;s:10:\"sub_source\";i:3;s:18:\"competitor_details\";i:4;s:13:\"prospect_type\";i:5;s:8:\"party_id\";i:6;s:11:\"prospect_id\";i:7;s:8:\"industry\";i:8;s:16:\"industry_type_id\";i:9;s:7:\"city_id\";i:10;s:7:\"address\";i:11;s:7:\"website\";i:12;s:12:\"contact_name\";i:13;s:10:\"contact_no\";i:14;s:19:\"service_category_id\";i:15;s:18:\"approx_requirement\";i:16;s:15:\"estimated_value\";i:17;s:21:\"expected_closing_date\";i:18;s:11:\"lead_rating\";i:19;s:11:\"assigned_to\";i:20;s:11:\"next_action\";i:21;s:16:\"next_action_date\";i:22;s:7:\"remarks\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}s:16:\"\0*\0forceDeleting\";b:0;}}s:28:\"\0*\0escapeWhenCastingToString\";b:0;}s:7:\"closing\";O:39:\"Illuminate\\Database\\Eloquent\\Collection\":2:{s:8:\"\0*\0items\";a:0:{}s:28:\"\0*\0escapeWhenCastingToString\";b:0;}s:9:\"poPending\";O:39:\"Illuminate\\Database\\Eloquent\\Collection\":2:{s:8:\"\0*\0items\";a:0:{}s:28:\"\0*\0escapeWhenCastingToString\";b:0;}s:4:\"year\";s:7:\"2026-27\";}',1792562780),('nursery-cache-crm.due.1.all.20261016','a:3:{s:5:\"count\";i:1;s:7:\"overdue\";i:1;s:4:\"list\";a:1:{i:0;a:7:{s:2:\"id\";i:1;s:2:\"no\";s:14:\"ENQ/26-27/0001\";s:6:\"client\";s:12:\"Ganesh Patil\";s:6:\"action\";s:4:\"test\";s:4:\"date\";s:10:\"13/04/1982\";s:4:\"late\";i:16257;s:3:\"url\";s:44:\"http://localhost/nursery-app/crm/enquiries/1\";}}}',1792113396),('nursery-cache-crm.due.1.all.20261018','a:3:{s:5:\"count\";i:2;s:7:\"overdue\";i:2;s:4:\"list\";a:2:{i:0;a:7:{s:2:\"id\";i:1;s:2:\"no\";s:14:\"ENQ/26-27/0001\";s:6:\"client\";s:12:\"Ganesh Patil\";s:6:\"action\";s:4:\"test\";s:4:\"date\";s:10:\"13/04/1982\";s:4:\"late\";i:16259;s:3:\"url\";s:40:\"https://arominfotech.net/crm/enquiries/1\";}i:1;a:7:{s:2:\"id\";i:2;s:2:\"no\";s:14:\"ENQ/26-27/0002\";s:6:\"client\";s:6:\"Satish\";s:6:\"action\";s:4:\"test\";s:4:\"date\";s:10:\"13/04/1982\";s:4:\"late\";i:16259;s:3:\"url\";s:40:\"https://arominfotech.net/crm/enquiries/2\";}}}',1792345550),('nursery-cache-crm.due.1.all.20261019','a:3:{s:5:\"count\";i:2;s:7:\"overdue\";i:2;s:4:\"list\";a:2:{i:0;a:7:{s:2:\"id\";i:1;s:2:\"no\";s:14:\"ENQ/26-27/0001\";s:6:\"client\";s:12:\"Ganesh Patil\";s:6:\"action\";s:4:\"test\";s:4:\"date\";s:10:\"13/04/1982\";s:4:\"late\";i:16260;s:3:\"url\";s:40:\"https://arominfotech.net/crm/enquiries/1\";}i:1;a:7:{s:2:\"id\";i:2;s:2:\"no\";s:14:\"ENQ/26-27/0002\";s:6:\"client\";s:6:\"Satish\";s:6:\"action\";s:4:\"test\";s:4:\"date\";s:10:\"13/04/1982\";s:4:\"late\";i:16260;s:3:\"url\";s:40:\"https://arominfotech.net/crm/enquiries/2\";}}}',1792434899),('nursery-cache-crm.due.1.all.20261020','a:3:{s:5:\"count\";i:3;s:7:\"overdue\";i:2;s:4:\"list\";a:3:{i:0;a:7:{s:2:\"id\";i:1;s:2:\"no\";s:14:\"ENQ/26-27/0001\";s:6:\"client\";s:12:\"Ganesh Patil\";s:6:\"action\";s:4:\"test\";s:4:\"date\";s:10:\"13/04/1982\";s:4:\"late\";i:16261;s:3:\"url\";s:40:\"https://arominfotech.net/crm/enquiries/1\";}i:1;a:7:{s:2:\"id\";i:2;s:2:\"no\";s:14:\"ENQ/26-27/0002\";s:6:\"client\";s:6:\"Satish\";s:6:\"action\";s:4:\"test\";s:4:\"date\";s:10:\"13/04/1982\";s:4:\"late\";i:16261;s:3:\"url\";s:40:\"https://arominfotech.net/crm/enquiries/2\";}i:2;a:7:{s:2:\"id\";i:4;s:2:\"no\";s:14:\"ENQ/26-27/0004\";s:6:\"client\";s:12:\"Digonal Mall\";s:6:\"action\";s:10:\"visit site\";s:4:\"date\";s:10:\"20/10/2026\";s:4:\"late\";i:0;s:3:\"url\";s:40:\"https://arominfotech.net/crm/enquiries/4\";}}}',1792515958),('nursery-cache-crm.due.1.all.20261021','a:3:{s:5:\"count\";i:3;s:7:\"overdue\";i:3;s:4:\"list\";a:3:{i:0;a:7:{s:2:\"id\";i:1;s:2:\"no\";s:14:\"ENQ/26-27/0001\";s:6:\"client\";s:12:\"Ganesh Patil\";s:6:\"action\";s:4:\"test\";s:4:\"date\";s:10:\"13/04/1982\";s:4:\"late\";i:16262;s:3:\"url\";s:44:\"http://localhost/nursery-app/crm/enquiries/1\";}i:1;a:7:{s:2:\"id\";i:2;s:2:\"no\";s:14:\"ENQ/26-27/0002\";s:6:\"client\";s:6:\"Satish\";s:6:\"action\";s:4:\"test\";s:4:\"date\";s:10:\"13/04/1982\";s:4:\"late\";i:16262;s:3:\"url\";s:44:\"http://localhost/nursery-app/crm/enquiries/2\";}i:2;a:7:{s:2:\"id\";i:4;s:2:\"no\";s:14:\"ENQ/26-27/0004\";s:6:\"client\";s:12:\"Digonal Mall\";s:6:\"action\";s:10:\"visit site\";s:4:\"date\";s:10:\"20/10/2026\";s:4:\"late\";i:1;s:3:\"url\";s:44:\"http://localhost/nursery-app/crm/enquiries/4\";}}}',1792607639),('nursery-cache-crm.due.1.all.20261022','a:3:{s:5:\"count\";i:4;s:7:\"overdue\";i:3;s:4:\"list\";a:4:{i:0;a:7:{s:2:\"id\";i:1;s:2:\"no\";s:14:\"ENQ/26-27/0001\";s:6:\"client\";s:12:\"Ganesh Patil\";s:6:\"action\";s:4:\"test\";s:4:\"date\";s:10:\"13/04/1982\";s:4:\"late\";i:16263;s:3:\"url\";s:44:\"http://localhost/nursery-app/crm/enquiries/1\";}i:1;a:7:{s:2:\"id\";i:2;s:2:\"no\";s:14:\"ENQ/26-27/0002\";s:6:\"client\";s:6:\"Satish\";s:6:\"action\";s:4:\"test\";s:4:\"date\";s:10:\"13/04/1982\";s:4:\"late\";i:16263;s:3:\"url\";s:44:\"http://localhost/nursery-app/crm/enquiries/2\";}i:2;a:7:{s:2:\"id\";i:4;s:2:\"no\";s:14:\"ENQ/26-27/0004\";s:6:\"client\";s:12:\"Digonal Mall\";s:6:\"action\";s:10:\"visit site\";s:4:\"date\";s:10:\"20/10/2026\";s:4:\"late\";i:2;s:3:\"url\";s:44:\"http://localhost/nursery-app/crm/enquiries/4\";}i:3;a:7:{s:2:\"id\";i:5;s:2:\"no\";s:14:\"ENQ/26-27/0005\";s:6:\"client\";s:9:\"Suma Soft\";s:6:\"action\";s:4:\"Call\";s:4:\"date\";s:10:\"22/10/2026\";s:4:\"late\";i:0;s:3:\"url\";s:44:\"http://localhost/nursery-app/crm/enquiries/5\";}}}',1792609411),('nursery-cache-crm.due.2.all.20261018','a:3:{s:5:\"count\";i:0;s:7:\"overdue\";i:0;s:4:\"list\";a:0:{}}',1792345844),('nursery-cache-crm.prospects.count','i:16;',1792609082),('nursery-cache-ec76914d4ea579eff60fc3d6c112242e','i:1;',1792425610),('nursery-cache-ec76914d4ea579eff60fc3d6c112242e:timer','i:1792425610;',1792425610),('nursery-cache-illuminate:cache:flexible:created:crm.alerts.unit.1.20261016','i:1792113082;',1792113382),('nursery-cache-illuminate:cache:flexible:created:crm.alerts.unit.1.20261018','i:1792345250;',1792345550),('nursery-cache-illuminate:cache:flexible:created:crm.alerts.unit.1.20261019','i:1792434599;',1792434899),('nursery-cache-illuminate:cache:flexible:created:crm.alerts.unit.1.20261020','i:1792515658;',1792515958),('nursery-cache-illuminate:cache:flexible:created:crm.alerts.unit.1.20261021','i:1792607339;',1792607639),('nursery-cache-illuminate:cache:flexible:created:crm.alerts.unit.1.20261022','i:1792609111;',1792609411),('nursery-cache-illuminate:cache:flexible:created:crm.alerts.unit.2.20261018','i:1792345544;',1792345844),('nursery-cache-illuminate:cache:flexible:created:crm.dashboard.1.1.20261019','i:1792433580;',1792433880),('nursery-cache-illuminate:cache:flexible:created:crm.dashboard.1.1.20261020','i:1792515658;',1792515958),('nursery-cache-illuminate:cache:flexible:created:crm.dashboard.1.1.20261021','i:1792562480;',1792562780),('nursery-cache-illuminate:cache:flexible:created:crm.due.1.all.20261016','i:1792113096;',1792113396),('nursery-cache-illuminate:cache:flexible:created:crm.due.1.all.20261018','i:1792345250;',1792345550),('nursery-cache-illuminate:cache:flexible:created:crm.due.1.all.20261019','i:1792434599;',1792434899),('nursery-cache-illuminate:cache:flexible:created:crm.due.1.all.20261020','i:1792515658;',1792515958),('nursery-cache-illuminate:cache:flexible:created:crm.due.1.all.20261021','i:1792607339;',1792607639),('nursery-cache-illuminate:cache:flexible:created:crm.due.1.all.20261022','i:1792609110;',1792609411),('nursery-cache-illuminate:cache:flexible:created:crm.due.2.all.20261018','i:1792345544;',1792345844),('nursery-cache-module-settings','a:0:{}',1792610364),('nursery-cache-page-setup.company','a:0:{}',1792611738),('nursery-cache-page-setup.contact','a:0:{}',1792610364),('nursery-cache-page-setup.customer-po','a:0:{}',1792611738),('nursery-cache-page-setup.employee','a:0:{}',1792611738),('nursery-cache-page-setup.enquiry','a:0:{}',1792610384),('nursery-cache-page-setup.enquiry-followup','a:0:{}',1792612561),('nursery-cache-page-setup.item','a:0:{}',1792611738),('nursery-cache-page-setup.master-categories','a:0:{}',1792611738),('nursery-cache-page-setup.master-cities','a:0:{}',1792611738),('nursery-cache-page-setup.master-hsn-sac','a:0:{}',1792611738),('nursery-cache-page-setup.master-industry-types','a:0:{}',1792611738),('nursery-cache-page-setup.master-item-categories','a:0:{}',1792611738),('nursery-cache-page-setup.master-sources','a:0:{}',1792611738),('nursery-cache-page-setup.master-tax-structures','a:0:{}',1792611738),('nursery-cache-page-setup.master-transporters','a:0:{}',1792611738),('nursery-cache-page-setup.master-uoms','a:0:{}',1792611738),('nursery-cache-page-setup.party','a:0:{}',1792611738),('nursery-cache-page-setup.prospect','a:0:{}',1792610364),('nursery-cache-page-setup.quotation','a:0:{}',1792611738),('nursery-cache-page-setup.sales-order','a:0:{}',1792611738),('nursery-cache-page-setup.site-visit','a:0:{}',1792610939),('nursery-cache-page-setup.tax-invoice','a:0:{}',1792611739);
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
  KEY `cities_active_name_index` (`is_active`,`name`),
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contacts`
--

LOCK TABLES `contacts` WRITE;
/*!40000 ALTER TABLE `contacts` DISABLE KEYS */;
INSERT INTO `contacts` VALUES (1,'CT00001',NULL,2,'GP New','Design','Department','9874563214','9874563214',NULL,NULL,NULL,NULL,1,0,0,0,0,NULL,1,1,'2026-10-16 01:01:48','2026-10-16 01:01:48',NULL),(2,'CT00002',NULL,3,'Rajesh Contact',NULL,NULL,'9874563214','9874563214',NULL,NULL,NULL,NULL,1,0,0,0,0,NULL,1,1,'2026-10-18 05:21:33','2026-10-18 05:21:33',NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_pos`
--

LOCK TABLES `customer_pos` WRITE;
/*!40000 ALTER TABLE `customer_pos` DISABLE KEYS */;
INSERT INTO `customer_pos` VALUES (1,1,1,3,1,'21312312','2026-10-18',NULL,3,'Test Site',1062001.06,10.000,'2026-10-18','2026-10-23','50 advance','18','Rajesh, 9876543211',1,'2026-10-18','Ok',1,1,'2026-10-18 11:11:28','2026-10-19 15:57:16',NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
INSERT INTO `documents` VALUES (1,'quotation',1,'BOQ','lead-ageing-report-18880401-20270331.csv','documents/quotation/1/6hHNL8bIIVTizGhLgde60IdRO94pCSIjApJUPr1D.csv','text/csv',441,1,'2026-10-18 11:07:02'),(2,'site_visit',2,'Site photo','QT-26-27-0001-rev0 (2).pdf','documents/site_visit/2/ARqcv1mplZqmE88vL1Wy5zgcCGIi1mZUsXfscn0l.pdf','application/pdf',23641,1,'2026-10-18 11:12:31'),(3,'site_visit',2,'Site photo','QT-26-27-0001-rev0 (1).pdf','documents/site_visit/2/N4D7a5g0ZkltIe6v7MzZf34kJjWFtL4r4BisdVLp.pdf','application/pdf',23566,1,'2026-10-18 11:12:39'),(4,'enquiry',3,'Enquiry document','image.jpg','documents/enquiry/3/sAsDxyq4Kwxj1PqZvWTUh7odx3uNCItZkac0UbxR.jpg','image/jpeg',2423869,1,'2026-10-18 17:37:15'),(5,'enquiry',5,'Enquiry document','image.jpg','documents/enquiry/5/XzNAA1BSs4grnDiv4DJcbSvcfZimqwwvsell4DmW.jpg','image/jpeg',2100325,1,'2026-10-19 15:42:55'),(6,'customer_po',1,'Customer PO','image.jpg','documents/customer_po/1/XkSGKybQBTXXoouO18MO6D2WBHXOYQXM65hJKBOj.jpg','image/jpeg',1400851,1,'2026-10-19 15:59:10');
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
  KEY `employees_status_deleted_name_index` (`status`,`deleted_at`,`name`),
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enquiries`
--

LOCK TABLES `enquiries` WRITE;
/*!40000 ALTER TABLE `enquiries` DISABLE KEYS */;
INSERT INTO `enquiries` VALUES (1,1,1,'ENQ/26-27/0001','2026-10-16',1,NULL,NULL,'Existing Client',NULL,2,NULL,1,5,NULL,NULL,'GP','7894561230','7894561230',11,NULL,NULL,NULL,'Hot',1,'test','1982-04-13',NULL,'Open',1,1,'2026-10-16 01:11:33','2026-10-16 01:11:33',NULL),(2,1,1,'ENQ/26-27/0002','2026-10-18',1,'Test',NULL,'Existing Client',NULL,1,NULL,1,15,NULL,NULL,'tEST','7894561230','7894561230',3,NULL,NULL,NULL,'Cold',1,'test','1982-04-13',NULL,'Open',1,1,'2026-10-18 04:54:24','2026-10-18 04:54:24',NULL),(3,1,1,'ENQ/26-27/0003','2026-10-18',13,'New Paper',NULL,'New',NULL,3,NULL,1,5,NULL,'https://sumasoft.com','Rajesh','9876543210','9876543210',8,NULL,NULL,NULL,'Hot',1,'test','2027-04-13',NULL,'PO received',1,1,'2026-10-18 05:26:31','2026-10-18 17:35:47',NULL),(4,1,1,'ENQ/26-27/0004','2026-10-19',7,NULL,NULL,'New',NULL,4,NULL,2,1,NULL,NULL,'Rajdarshan','98765643','98765643',5,NULL,NULL,NULL,'Hot',1,'visit site','2026-10-20',NULL,'Open',1,1,'2026-10-19 11:54:52','2026-10-19 11:54:52',NULL),(5,1,1,'ENQ/26-27/0005','2026-10-19',2,'Google add from youtube','Facebook','Existing Client',NULL,3,'Soft devp',3,5,'Nsk','https://sumasoft.com','Rajesh','9876543210','9876543210',1,'2 floor',200000.00,'2026-11-08','Warm',1,'Call','2026-10-22',NULL,'Open',1,1,'2026-10-19 15:39:02','2026-10-19 15:41:19',NULL),(6,1,1,'ENQ/26-27/0006','2026-10-19',1,NULL,NULL,'Referral',NULL,12,NULL,1,5,'MIDC Chakan, Pune',NULL,'Test Test Final1','+91 98220 1222','982201222',2,'40',200000.00,'2026-11-21','Hot',1,'wHATS app','2026-10-24',NULL,'Open',1,1,'2026-10-19 18:22:15','2026-10-19 18:26:05',NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_10_07_000001_add_admin_fields_to_users_table',1),(5,'2026_10_07_000002_create_units_tables',1),(6,'2026_10_07_000003_create_activity_logs_table',1),(7,'2026_10_07_220903_create_permission_tables',1),(8,'2026_10_08_000001_create_financial_years_and_number_series_tables',2),(9,'2026_10_08_000002_create_currencies_and_parties_tables',2),(10,'2026_10_08_000003_create_prospects_table',2),(11,'2026_10_08_000004_create_quotations_tables',3),(12,'2026_10_08_000005_add_list_and_report_indexes',4),(13,'2026_10_13_000001_create_crm_masters_tables',5),(14,'2026_10_13_000002_create_enquiries_site_visits_activities_tables',5),(15,'2026_10_13_000003_add_crm_quotation_fields',5),(16,'2026_10_13_000004_create_customer_pos_and_documents_tables',5),(17,'2026_10_14_000001_add_crm_list_and_dashboard_indexes',6),(18,'2026_10_14_000002_add_contact_no_digits_to_enquiries',7),(19,'2026_10_14_000003_create_module_settings_and_contacts_tables',8),(20,'2026_10_15_000001_create_sales_tables',9),(21,'2026_10_18_000001_add_picker_indexes',10),(22,'2026_10_18_000002_sales_review_fixes',11),(23,'2026_10_22_000001_create_app_settings_table',12);
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
INSERT INTO `model_has_roles` VALUES (1,'user',1),(2,'user',5),(4,'user',2),(4,'user',3),(6,'user',4);
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `number_series`
--

LOCK TABLES `number_series` WRITE;
/*!40000 ALTER TABLE `number_series` DISABLE KEYS */;
INSERT INTO `number_series` VALUES (1,'enquiry','ENQ',7,1,1,'2026-10-16 01:11:33','2026-10-19 18:22:15'),(2,'quotation','QT',4,1,1,'2026-10-18 11:06:17','2026-10-19 18:47:04');
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
  KEY `parties_status_name_index` (`status`,`deleted_at`,`name`),
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
  KEY `prospects_deleted_name_index` (`deleted_at`,`name`),
  CONSTRAINT `prospects_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prospects_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prospects_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prospects`
--

LOCK TABLES `prospects` WRITE;
/*!40000 ALTER TABLE `prospects` DISABLE KEYS */;
INSERT INTO `prospects` VALUES (1,'Satish','Domestic','Satish',NULL,'9852854774',NULL,'satish@gmail.com',NULL,NULL,NULL,1,1,1,'2026-10-14 18:19:18','2026-10-14 18:19:18',NULL),(2,'Ganesh Patil','Domestic','9874563214',NULL,'9874563214',NULL,NULL,NULL,NULL,NULL,1,1,1,'2026-10-16 01:00:36','2026-10-16 01:00:36',NULL),(3,'Suma Soft','Domestic','Rajesh','Devp','9876543210',NULL,NULL,NULL,NULL,NULL,1,1,1,'2026-10-18 05:20:32','2026-10-18 05:20:32',NULL),(4,'Digonal Mall','Domestic','Rajdarshan','Manager','98765643',NULL,NULL,NULL,'Mall',NULL,1,1,1,'2026-10-19 11:48:39','2026-10-19 11:50:39',NULL),(5,'Gera','Domestic','Raghunath','Owner','9881211338',NULL,NULL,NULL,NULL,NULL,1,1,1,'2026-10-19 12:03:57','2026-10-19 12:03:57',NULL),(6,'Orion Agro Equipment','Domestic','Sanjay Patil','Purchase manager','+91 98220 11111',NULL,'sanjay@orion.example','27AAACO1234F1Z5','Agriculture machinery','MIDC Chakan, Pune',1,1,1,'2026-10-19 12:32:39','2026-10-19 12:32:39',NULL),(7,'New Nursery Setup','Domestic','Eshwar New','Owner','9876543210',NULL,NULL,NULL,NULL,NULL,1,1,1,'2026-10-19 17:55:39','2026-10-19 17:56:27',NULL),(8,'Test','Domestic','Test Test Final','Owner','+91 98220 11111',NULL,'sanjay@gmail.example','27AAACO1234F1Z5','Agriculture machinery','MIDC Chakan, Pune',1,1,1,'2026-10-19 18:02:04','2026-10-19 18:02:04',NULL),(9,'Test','Domestic','Test Test Final','Owner','+91 98220 11111',NULL,'sanjay@gmail.example','27AAACO1234F1Z5','Agriculture machinery','MIDC Chakan, Pune',1,1,1,'2026-10-19 18:03:22','2026-10-19 18:03:22',NULL),(10,'Test','Domestic','Test Test Final','Owner','+91 98220 11111',NULL,'sanjay@gmail.example','27AAACO1234F1Z5','Agriculture machinery','MIDC Chakan, Pune',1,1,1,'2026-10-19 18:05:43','2026-10-19 18:05:43',NULL),(11,'432','Domestic','Test Test Final','Owner','+91 98220 11111',NULL,'sanjay@gmail.example','27AAACO1234F1Z5','Agriculture machinery','MIDC Chakan, Pune',1,1,1,'2026-10-19 18:07:59','2026-10-19 18:07:59',NULL),(12,'123412345','Domestic','Test Test Final1','Owner','+91 98220 1222',NULL,'sanjay@gmail.example','27AAACO1234F1Z5','Agriculture machinery','MIDC Chakan, Pune',1,1,1,'2026-10-19 18:07:59','2026-10-19 18:07:59',NULL),(13,'Test Test Final12','Domestic','Test Test Final12','Owner','+91 98220 1133',NULL,'sanjay@gmail.example','27AAACO1234F1Z5','Agriculture machinery','MIDC Chakan, Pune',1,1,1,'2026-10-19 18:07:59','2026-10-19 18:07:59',NULL),(14,'432','Domestic','Test Test Final','Owner','+91 98220 11111',NULL,'sanjay@gmail.example','27AAACO1234F1Z5','Agriculture machinery','MIDC Chakan, Pune',1,1,1,'2026-10-19 18:09:45','2026-10-19 18:09:45',NULL),(15,'123412345','Domestic','Test Test Final1','Owner','+91 98220 1222',NULL,'sanjay@gmail.example','27AAACO1234F1Z5','Agriculture machinery','MIDC Chakan, Pune',1,1,1,'2026-10-19 18:09:45','2026-10-19 18:09:45',NULL),(16,'Test Test Final12','Domestic','Test Test Final12','Owner','+91 98220 1133',NULL,'sanjay@gmail.example','27AAACO1234F1Z5','Agriculture machinery','MIDC Chakan, Pune',1,1,1,'2026-10-19 18:09:45','2026-10-19 18:09:45',NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotation_costs`
--

LOCK TABLES `quotation_costs` WRITE;
/*!40000 ALTER TABLE `quotation_costs` DISABLE KEYS */;
INSERT INTO `quotation_costs` VALUES (1,1,200.00,100.00,121.00,23.00,123.00,23.00,34.00,34.00,658.00,10.00,65.80,723.80,'2026-10-18 11:06:17','2026-10-18 11:06:17'),(2,2,2000.00,20.00,2000.00,100.00,200.00,200.00,10.00,200.00,4730.00,50.00,2365.00,7095.00,'2026-10-19 15:47:58','2026-10-19 15:52:55'),(3,3,200.00,100.00,121.00,23.00,123.00,23.00,34.00,34.00,658.00,10.00,65.80,723.80,'2026-10-19 18:47:04','2026-10-19 18:47:04');
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotation_lines`
--

LOCK TABLES `quotation_lines` WRITE;
/*!40000 ALTER TABLE `quotation_lines` DISABLE KEYS */;
INSERT INTO `quotation_lines` VALUES (3,1,1,'1','Test',1.000,'Nos',NULL,1000001.0000,10.00,18.00,1062001.06,NULL,NULL,NULL,'2026-10-18 11:08:24','2026-10-18 11:08:24'),(6,2,1,'1212','Test',10.000,'1',NULL,10000.0000,10.00,18.00,106200.00,NULL,NULL,NULL,'2026-10-19 15:52:55','2026-10-19 15:52:55'),(7,3,1,'1','Test',100.000,'Nos',NULL,1000.0000,20.00,18.00,94400.00,NULL,NULL,NULL,'2026-10-19 18:47:04','2026-10-19 18:47:04');
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotations`
--

LOCK TABLES `quotations` WRITE;
/*!40000 ALTER TABLE `quotations` DISABLE KEYS */;
INSERT INTO `quotations` VALUES (1,1,1,3,'ENQ/26-27/0003','QT/26-27/0001',0,'2026-10-18','2026-11-17',NULL,1,NULL,3,'Rajesh, 9876543211','Test Site','Architect',NULL,NULL,5,1,'2026-10-18',1,'New',1,1,'2026-10-18 11:06:17','2026-10-18 11:11:28',NULL,'50 advance','NO Termsn','18',0.00,NULL,1062001.06,1062001.06,'Accepted',NULL,'2026-10-18 11:10:50'),(2,1,1,5,'ENQ/26-27/0005','QT/26-27/0002',0,'2026-10-19','2026-11-18',NULL,1,NULL,3,'Rajesh','Nsk','Google',NULL,NULL,5,1,'2026-10-19',1,'New',1,1,'2026-10-19 15:47:58','2026-10-19 15:56:08',NULL,'50 % advance',NULL,'18',62.00,NULL,106200.00,106138.00,'Sent',NULL,'2026-10-19 15:56:08'),(3,1,1,6,'ENQ/26-27/0006','QT/26-27/0003',0,'2026-10-20','2026-11-19',NULL,1,NULL,12,'Test Test Final1','MIDC Chakan, Pune','Website',NULL,NULL,5,1,'2026-10-19',1,'New',1,1,'2026-10-19 18:47:04','2026-10-19 18:53:52',NULL,NULL,NULL,NULL,50000.00,NULL,94400.00,44400.00,'Sent',NULL,'2026-10-19 18:53:52');
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
  KEY `sales_invoices_unit_type_date_index` (`unit_id`,`invoice_type`,`invoice_date`),
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
  `credited_quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
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
  KEY `sales_orders_party_po_index` (`party_id`,`po_number`),
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
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_categories`
--

LOCK TABLES `service_categories` WRITE;
/*!40000 ALTER TABLE `service_categories` DISABLE KEYS */;
INSERT INTO `service_categories` VALUES (1,'Plant Rental',1,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(2,'Plant AMC',2,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(3,'Landscape Design',3,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(4,'Landscape Development',4,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(5,'Garden Maintenance',5,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(6,'Vertical Garden / Green Wall',6,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(7,'Terrace Garden',7,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(8,'GD DECO',8,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(9,'Plant Sale',9,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(10,'Planters',10,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(11,'Corporate Plantation',11,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(12,'Other',12,1,NULL,NULL,'2026-10-13 17:42:40','2026-10-13 17:42:40'),(13,'admin',100,1,1,1,'2026-10-19 18:31:14','2026-10-19 18:31:14'),(14,'admin new',100,1,1,1,'2026-10-19 18:31:34','2026-10-19 18:31:34');
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
INSERT INTO `sessions` VALUES ('0o84eUZrAF0uvSoZc8rgFexddtadritFq9T6SBdj',1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','YTo2OntzOjY6Il90b2tlbiI7czo0MDoieVdSQUlEblMzUnVPMG9HQVdDV0VCeDJqdXg0SzNLeE1qMTZKdlpsbCI7czozOiJ1cmwiO2E6MDp7fXM6OToiX3ByZXZpb3VzIjthOjI6e3M6MzoidXJsIjtzOjU1OiJodHRwOi8vbG9jYWxob3N0L251cnNlcnktYXBwL2xvb2t1cC9wcm9zcGVjdHM/cGFnZT0xJnE9IjtzOjU6InJvdXRlIjtzOjE2OiJsb29rdXAucHJvc3BlY3RzIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjE3OiJwYXNzd29yZF9oYXNoX3dlYiI7czo2NDoiMGYyNDFmNTJiODczNzVlMzlhOTNlY2EzYmQzYmYwM2VkNTUyMDg4ZDRiNWQ3OTk1ZThiZThkMmU3NTZhNDRiNCI7fQ==',1792609118),('4LSdVEpde3qBDQifpZzDfAtAiTvshcYm9cC1XvQT',NULL,'147.90.209.111','Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1','YTozOntzOjY6Il90b2tlbiI7czo0MDoic3dkNzBaRDJwYXFVcXNMQ055bjZwZTMxdjZVT1NobVhONXhJb3lXTyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjk6Imh0dHA6Ly9hcm9taW5mb3RlY2gubmV0L2xvZ2luIjtzOjU6InJvdXRlIjtzOjU6ImxvZ2luIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1792601409),('mLNlNzeOq8Lge5xKpxu5Br53dkAmP4xCwfDowOZ6',NULL,'45.137.203.60','Mozilla/5.0 CMS detector','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiQzZBOW1QTjdtTTdKcHlWdkwyU1JxanZMT0dZWmFScTl3TzZjTmNlcSI7czozOiJ1cmwiO2E6MTp7czo4OiJpbnRlbmRlZCI7czoyNDoiaHR0cHM6Ly9hcm9taW5mb3RlY2gubmV0Ijt9czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzA6Imh0dHBzOi8vYXJvbWluZm90ZWNoLm5ldC9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1792604158),('PBqzRK8Vry3z9enluastLfE6GrWuZuiOaO1soW1M',NULL,'147.90.209.111','Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiTjNPT0tONVR6aHM0ZG1vM2p6OU1lUEtUVVVKWEplZjRiWkQ1c3BMMyI7czozOiJ1cmwiO2E6MTp7czo4OiJpbnRlbmRlZCI7czoyMzoiaHR0cDovL2Fyb21pbmZvdGVjaC5uZXQiO31zOjk6Il9wcmV2aW91cyI7YToyOntzOjM6InVybCI7czoyMzoiaHR0cDovL2Fyb21pbmZvdGVjaC5uZXQiO3M6NToicm91dGUiO3M6NDoiaG9tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1792601408);
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_visits`
--

LOCK TABLES `site_visits` WRITE;
/*!40000 ALTER TABLE `site_visits` DISABLE KEYS */;
INSERT INTO `site_visits` VALUES (1,1,2,'2026-10-19 11:00:00','tEST, 7894561230',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Done',1,1,'2026-10-18 04:59:34','2026-10-18 04:59:34',NULL),(2,1,3,'2026-10-19 11:00:00','Rajesh, 9876543211',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Done',1,1,'2026-10-18 05:27:46','2026-10-18 05:27:46',NULL),(3,1,4,'2026-10-20 11:00:00','Rajdarshan',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Planned',1,1,'2026-10-19 12:02:01','2026-10-19 12:02:01',NULL),(4,1,5,'2026-10-20 11:00:00','Rajesh',1,'Nsk','100','200','50',NULL,NULL,'Ok','Ok','Ok',NULL,NULL,NULL,'Planned',1,1,'2026-10-19 15:43:51','2026-10-19 15:43:51',NULL);
/*!40000 ALTER TABLE `site_visits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_balances`
--

DROP TABLE IF EXISTS `stock_balances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_balances` (
  `unit_id` bigint(20) unsigned NOT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`unit_id`,`item_id`),
  KEY `stock_balances_item_index` (`item_id`),
  CONSTRAINT `stock_balances_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  CONSTRAINT `stock_balances_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_balances`
--

LOCK TABLES `stock_balances` WRITE;
/*!40000 ALTER TABLE `stock_balances` DISABLE KEYS */;
/*!40000 ALTER TABLE `stock_balances` ENABLE KEYS */;
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
INSERT INTO `unit_user` VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(2,1),(2,4);
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Administrator','admin@nursery.local','9800000000',NULL,'$2y$12$egkFraYLoVeDOeiKFBhBM.rdZRAlzHcjSDLPQWMYGbzRaw4MGkX4i','Active','2026-10-21 18:19:23','28hSyZVzhpQAA6pK4RZtZlxw57OsOUX02jyh4szOqbE8vR6DWEU35OZP9JTf',NULL,NULL,'2026-10-08 00:15:48','2026-10-21 18:19:23',NULL),(2,'Satish','satishworking2026@gmail.com','9123456780',NULL,'$2y$12$8NXB.9Rg.IUfysljTlCVMuCTOd/SnBQ1La69TAGIJnD.BFggFy3jO','Active','2026-10-08 16:21:05',NULL,1,1,'2026-10-08 01:01:12','2026-10-08 16:21:05',NULL),(3,'test@gmail.com','test@gmail.com','9876543210',NULL,'$2y$12$Wf3xohHuDXXZkNpGebHdteJUoxgDKIx9sAYUGU4Bce.v71GU39tgi','Active','2026-10-08 16:40:40',NULL,1,1,'2026-10-08 16:28:36','2026-10-08 16:40:40',NULL),(4,'Temp Temp','test1@gmail.com','9879876543',NULL,'$2y$12$WlOAI3RXwSEIB17fs.oHy.jNMNOIzJT4uUtZyw.4Zh39a1tgMS9aO','Active','2026-10-08 16:58:27',NULL,1,1,'2026-10-08 16:57:36','2026-10-08 16:58:27',NULL),(5,'Sales Persona','sales@gmail.com','9876543211',NULL,'$2y$12$Q.qcu6tHhi.iG1Q5bG4k4OS6UkpUOUEm5L1qDGV9bAc7K1O/S7/WO','Active',NULL,NULL,1,1,'2026-10-18 11:05:57','2026-10-18 11:05:57',NULL);
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

-- Dump completed on 2026-10-22  9:10:36
