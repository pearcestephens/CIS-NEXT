/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.13-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: 127.0.0.1    Database: jcepnzzkmj
-- ------------------------------------------------------
-- Server version	10.11.13-MariaDB-0ubuntu0.24.04.1-log

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
-- Table structure for table `ai_detection_types`
--

DROP TABLE IF EXISTS `ai_detection_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_detection_types` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `detection_code` varchar(30) NOT NULL,
  `detection_name` varchar(100) NOT NULL,
  `category` enum('PERSON','VEHICLE','OBJECT','BEHAVIOR','BIOMETRIC','ANOMALY','SAFETY','SECURITY') NOT NULL,
  `description` text DEFAULT NULL,
  `confidence_threshold_default` decimal(5,4) DEFAULT 0.7500,
  `risk_level` enum('LOW','MEDIUM','HIGH','CRITICAL') DEFAULT 'MEDIUM',
  `response_required` tinyint(1) DEFAULT 0,
  `alert_enabled` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `detection_code` (`detection_code`),
  KEY `idx_detection_code` (`detection_code`),
  KEY `idx_category` (`category`),
  KEY `idx_risk_level` (`risk_level`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ai_detection_zones`
--

DROP TABLE IF EXISTS `ai_detection_zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_detection_zones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `feed_id` int(10) unsigned NOT NULL,
  `zone_name` varchar(100) NOT NULL,
  `zone_type` enum('INCLUDE','EXCLUDE','TRIGGER','PRIVACY','COUNTING','INTRUSION') DEFAULT 'INCLUDE',
  `polygon_coordinates` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`polygon_coordinates`)),
  `zone_area_pixels` int(10) unsigned DEFAULT NULL,
  `detection_types` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`detection_types`)),
  `confidence_threshold` decimal(5,4) DEFAULT 0.7500,
  `sensitivity_level` enum('LOW','MEDIUM','HIGH','ULTRA') DEFAULT 'MEDIUM',
  `time_restrictions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`time_restrictions`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_feed` (`feed_id`),
  KEY `idx_zone_type` (`zone_type`),
  KEY `idx_sensitivity` (`sensitivity_level`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `ai_detection_zones_ibfk_1` FOREIGN KEY (`feed_id`) REFERENCES `cam_feeds` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ai_detections`
--

DROP TABLE IF EXISTS `ai_detections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_detections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `model_id` int(10) unsigned NOT NULL,
  `detection_type_id` smallint(5) unsigned NOT NULL,
  `detection_zone_id` int(10) unsigned DEFAULT NULL,
  `object_class` varchar(100) DEFAULT NULL,
  `object_id` varchar(100) DEFAULT NULL,
  `confidence_score` decimal(5,4) NOT NULL,
  `bounding_box_x` decimal(8,4) DEFAULT NULL,
  `bounding_box_y` decimal(8,4) DEFAULT NULL,
  `bounding_box_width` decimal(8,4) DEFAULT NULL,
  `bounding_box_height` decimal(8,4) DEFAULT NULL,
  `center_point_x` decimal(8,4) DEFAULT NULL,
  `center_point_y` decimal(8,4) DEFAULT NULL,
  `object_area_pixels` int(10) unsigned DEFAULT NULL,
  `tracking_id` varchar(100) DEFAULT NULL,
  `tracking_age_frames` smallint(5) unsigned DEFAULT 1,
  `velocity_x` decimal(8,4) DEFAULT NULL,
  `velocity_y` decimal(8,4) DEFAULT NULL,
  `speed_pixels_per_second` decimal(8,2) DEFAULT NULL,
  `direction_degrees` decimal(5,2) DEFAULT NULL,
  `attributes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attributes`)),
  `keypoints` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`keypoints`)),
  `mask_polygon` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`mask_polygon`)),
  `processing_time_ms` int(10) unsigned DEFAULT NULL,
  `model_version` varchar(50) DEFAULT NULL,
  `detected_at` timestamp(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `detection_zone_id` (`detection_zone_id`),
  KEY `idx_event` (`event_id`),
  KEY `idx_model` (`model_id`),
  KEY `idx_detection_type` (`detection_type_id`),
  KEY `idx_confidence` (`confidence_score`),
  KEY `idx_detected_at` (`detected_at`),
  KEY `idx_tracking` (`tracking_id`),
  KEY `idx_object_class` (`object_class`),
  KEY `idx_processing_time` (`processing_time_ms`),
  CONSTRAINT `ai_detections_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events_master` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ai_detections_ibfk_2` FOREIGN KEY (`model_id`) REFERENCES `ai_models` (`id`),
  CONSTRAINT `ai_detections_ibfk_3` FOREIGN KEY (`detection_type_id`) REFERENCES `ai_detection_types` (`id`),
  CONSTRAINT `ai_detections_ibfk_4` FOREIGN KEY (`detection_zone_id`) REFERENCES `ai_detection_zones` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ai_model_assignments`
--

DROP TABLE IF EXISTS `ai_model_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_model_assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `feed_id` int(10) unsigned NOT NULL,
  `model_id` int(10) unsigned NOT NULL,
  `detection_type_id` smallint(5) unsigned NOT NULL,
  `confidence_threshold` decimal(5,4) DEFAULT 0.7500,
  `processing_interval_ms` int(10) unsigned DEFAULT 1000,
  `max_detections_per_frame` tinyint(3) unsigned DEFAULT 10,
  `nms_threshold` decimal(5,4) DEFAULT 0.4500,
  `tracking_enabled` tinyint(1) DEFAULT 1,
  `tracking_max_age_frames` smallint(5) unsigned DEFAULT 30,
  `is_enabled` tinyint(1) DEFAULT 1,
  `assigned_at` timestamp NULL DEFAULT current_timestamp(),
  `assigned_by_user_id` int(10) unsigned DEFAULT NULL,
  `performance_metrics` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`performance_metrics`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_feed_model_detection` (`feed_id`,`model_id`,`detection_type_id`),
  KEY `detection_type_id` (`detection_type_id`),
  KEY `assigned_by_user_id` (`assigned_by_user_id`),
  KEY `idx_feed` (`feed_id`),
  KEY `idx_model` (`model_id`),
  KEY `idx_enabled` (`is_enabled`),
  KEY `idx_tracking` (`tracking_enabled`),
  CONSTRAINT `ai_model_assignments_ibfk_1` FOREIGN KEY (`feed_id`) REFERENCES `cam_feeds` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ai_model_assignments_ibfk_2` FOREIGN KEY (`model_id`) REFERENCES `ai_models` (`id`),
  CONSTRAINT `ai_model_assignments_ibfk_3` FOREIGN KEY (`detection_type_id`) REFERENCES `ai_detection_types` (`id`),
  CONSTRAINT `ai_model_assignments_ibfk_4` FOREIGN KEY (`assigned_by_user_id`) REFERENCES `auth_users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ai_model_training`
--

DROP TABLE IF EXISTS `ai_model_training`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_model_training` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `model_id` int(11) NOT NULL,
  `dataset_path` varchar(500) DEFAULT NULL,
  `training_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`training_config`)),
  `training_status` enum('queued','training','validating','completed','failed','stopped') DEFAULT 'queued',
  `training_progress` decimal(5,2) DEFAULT 0.00,
  `dataset_size` int(11) DEFAULT 0,
  `epochs_completed` int(11) DEFAULT 0,
  `total_epochs` int(11) DEFAULT 100,
  `current_loss` decimal(10,6) DEFAULT NULL,
  `best_accuracy` decimal(5,2) DEFAULT NULL,
  `training_time_minutes` int(11) DEFAULT 0,
  `gpu_device` int(11) DEFAULT 0,
  `gpu_utilization` decimal(5,2) DEFAULT 0.00,
  `memory_utilization` decimal(5,2) DEFAULT 0.00,
  `estimated_completion` timestamp NULL DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_model_training` (`model_id`),
  KEY `idx_status` (`training_status`),
  KEY `idx_started` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ai_model_validation`
--

DROP TABLE IF EXISTS `ai_model_validation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_model_validation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `model_id` int(11) NOT NULL,
  `validation_dataset` varchar(500) DEFAULT NULL,
  `accuracy_score` decimal(5,2) DEFAULT NULL,
  `precision_score` decimal(5,2) DEFAULT NULL,
  `recall_score` decimal(5,2) DEFAULT NULL,
  `f1_score` decimal(5,2) DEFAULT NULL,
  `validation_loss` decimal(10,6) DEFAULT NULL,
  `confusion_matrix` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`confusion_matrix`)),
  `validation_time_minutes` int(11) DEFAULT 0,
  `validated_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_model_validation` (`model_id`),
  KEY `idx_accuracy` (`accuracy_score`),
  KEY `idx_validated` (`validated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ai_models`
--

DROP TABLE IF EXISTS `ai_models`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_models` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `model_uuid` char(36) NOT NULL,
  `model_name` varchar(100) NOT NULL,
  `model_version` varchar(50) NOT NULL,
  `model_type` enum('DETECTION','CLASSIFICATION','TRACKING','RECOGNITION','ANALYSIS','PREDICTION') NOT NULL,
  `framework` enum('TENSORFLOW','PYTORCH','ONNX','OPENVINO','TENSORRT','DARKNET') NOT NULL,
  `input_resolution_width` smallint(5) unsigned DEFAULT NULL,
  `input_resolution_height` smallint(5) unsigned DEFAULT NULL,
  `processing_fps` tinyint(3) unsigned DEFAULT 25,
  `accuracy_percentage` decimal(5,2) DEFAULT NULL,
  `precision_percentage` decimal(5,2) DEFAULT NULL,
  `recall_percentage` decimal(5,2) DEFAULT NULL,
  `f1_score` decimal(5,4) DEFAULT NULL,
  `model_file_path` varchar(500) DEFAULT NULL,
  `config_file_path` varchar(500) DEFAULT NULL,
  `weights_file_path` varchar(500) DEFAULT NULL,
  `labels_file_path` varchar(500) DEFAULT NULL,
  `preprocessing_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preprocessing_config`)),
  `postprocessing_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`postprocessing_config`)),
  `gpu_memory_mb` int(10) unsigned DEFAULT NULL,
  `cpu_cores_required` tinyint(3) unsigned DEFAULT NULL,
  `inference_time_ms` decimal(6,2) DEFAULT NULL,
  `batch_size_optimal` tinyint(3) unsigned DEFAULT 1,
  `training_dataset` varchar(200) DEFAULT NULL,
  `validation_dataset` varchar(200) DEFAULT NULL,
  `training_date` date DEFAULT NULL,
  `last_updated` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `model_uuid` (`model_uuid`),
  KEY `idx_model_uuid` (`model_uuid`),
  KEY `idx_model_type` (`model_type`),
  KEY `idx_framework` (`framework`),
  KEY `idx_accuracy` (`accuracy_percentage`),
  KEY `idx_inference_time` (`inference_time_ms`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alert_rules`
--

DROP TABLE IF EXISTS `alert_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `alert_rules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rule_name` varchar(100) NOT NULL,
  `rule_description` text DEFAULT NULL,
  `event_conditions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`event_conditions`)),
  `severity_id` tinyint(3) unsigned NOT NULL,
  `notification_channels` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`notification_channels`)),
  `escalation_rules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`escalation_rules`)),
  `cooldown_minutes` smallint(5) unsigned DEFAULT 5,
  `max_alerts_per_hour` tinyint(3) unsigned DEFAULT 10,
  `time_restrictions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`time_restrictions`)),
  `outlet_restrictions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`outlet_restrictions`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rule_name` (`rule_name`),
  KEY `idx_severity` (`severity_id`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `alert_rules_ibfk_1` FOREIGN KEY (`severity_id`) REFERENCES `event_severity_levels` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `analytics_daily_summaries`
--

DROP TABLE IF EXISTS `analytics_daily_summaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `analytics_daily_summaries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `feed_id` int(10) unsigned NOT NULL,
  `summary_date` date NOT NULL,
  `total_events` int(10) unsigned DEFAULT 0,
  `motion_events` int(10) unsigned DEFAULT 0,
  `ai_detections` int(10) unsigned DEFAULT 0,
  `person_detections` int(10) unsigned DEFAULT 0,
  `vehicle_detections` int(10) unsigned DEFAULT 0,
  `security_events` int(10) unsigned DEFAULT 0,
  `false_positives` int(10) unsigned DEFAULT 0,
  `avg_confidence` decimal(5,4) DEFAULT NULL,
  `peak_confidence` decimal(5,4) DEFAULT NULL,
  `total_recording_minutes` int(10) unsigned DEFAULT 0,
  `total_clips_generated` int(10) unsigned DEFAULT 0,
  `total_thumbnails_generated` int(10) unsigned DEFAULT 0,
  `avg_fps` decimal(5,2) DEFAULT NULL,
  `avg_bitrate_kbps` int(10) unsigned DEFAULT NULL,
  `peak_bitrate_kbps` int(10) unsigned DEFAULT NULL,
  `data_storage_gb` decimal(10,3) DEFAULT NULL,
  `uptime_minutes` int(10) unsigned DEFAULT 0,
  `downtime_minutes` int(10) unsigned DEFAULT 0,
  `uptime_percentage` decimal(5,2) GENERATED ALWAYS AS (case when `uptime_minutes` + `downtime_minutes` > 0 then `uptime_minutes` / (`uptime_minutes` + `downtime_minutes`) * 100 else 100 end) VIRTUAL,
  `avg_response_time_ms` decimal(8,3) DEFAULT NULL,
  `error_count` int(10) unsigned DEFAULT 0,
  `performance_score` decimal(5,2) DEFAULT NULL,
  `ai_processing_hours` decimal(6,2) DEFAULT 0.00,
  `total_objects_tracked` int(10) unsigned DEFAULT 0,
  `unique_tracking_ids` int(10) unsigned DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_feed_date` (`feed_id`,`summary_date`),
  KEY `idx_summary_date` (`summary_date`),
  KEY `idx_performance_score` (`performance_score`),
  KEY `idx_uptime_percentage` (`uptime_percentage`),
  KEY `idx_total_events` (`total_events`),
  CONSTRAINT `analytics_daily_summaries_ibfk_1` FOREIGN KEY (`feed_id`) REFERENCES `cam_feeds` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `session_id` char(128) DEFAULT NULL,
  `action_type` enum('CREATE','READ','UPDATE','DELETE','LOGIN','LOGOUT','CONFIG_CHANGE','EXPORT','IMPORT','BACKUP','RESTORE') NOT NULL,
  `resource_type` varchar(50) NOT NULL,
  `resource_id` varchar(100) DEFAULT NULL,
  `resource_name` varchar(200) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `change_summary` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `request_method` varchar(10) DEFAULT NULL,
  `request_url` varchar(500) DEFAULT NULL,
  `response_code` smallint(5) unsigned DEFAULT NULL,
  `success` tinyint(1) DEFAULT 1,
  `error_message` text DEFAULT NULL,
  `processing_time_ms` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `session_id` (`session_id`),
  KEY `idx_user_action` (`user_id`,`action_type`),
  KEY `idx_resource` (`resource_type`,`resource_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_success` (`success`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_ip_address` (`ip_address`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`),
  CONSTRAINT `audit_logs_ibfk_2` FOREIGN KEY (`session_id`) REFERENCES `auth_sessions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auth_login_attempts`
--

DROP TABLE IF EXISTS `auth_login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `success` tinyint(1) NOT NULL,
  `failure_reason` enum('INVALID_CREDENTIALS','ACCOUNT_LOCKED','ACCOUNT_DISABLED','MFA_REQUIRED','MFA_FAILED','OTHER') DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `session_id` char(128) DEFAULT NULL,
  `attempted_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `session_id` (`session_id`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_success` (`success`),
  KEY `idx_attempted_at` (`attempted_at`),
  KEY `idx_failure_reason` (`failure_reason`),
  CONSTRAINT `auth_login_attempts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`),
  CONSTRAINT `auth_login_attempts_ibfk_2` FOREIGN KEY (`session_id`) REFERENCES `auth_sessions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auth_password_history`
--

DROP TABLE IF EXISTS `auth_password_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_password_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `password_algorithm` enum('BCRYPT','ARGON2','PBKDF2') DEFAULT 'BCRYPT',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `auth_password_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auth_permissions`
--

DROP TABLE IF EXISTS `auth_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_permissions` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `permission_code` varchar(50) NOT NULL,
  `permission_name` varchar(100) NOT NULL,
  `resource_type` varchar(50) DEFAULT NULL,
  `action_type` enum('CREATE','READ','UPDATE','DELETE','EXECUTE','ADMIN') NOT NULL,
  `description` text DEFAULT NULL,
  `is_system_permission` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `permission_code` (`permission_code`),
  KEY `idx_permission_code` (`permission_code`),
  KEY `idx_resource_action` (`resource_type`,`action_type`),
  KEY `idx_system_permission` (`is_system_permission`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auth_role_permissions`
--

DROP TABLE IF EXISTS `auth_role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_role_permissions` (
  `role_id` smallint(5) unsigned NOT NULL,
  `permission_id` smallint(5) unsigned NOT NULL,
  `granted_at` timestamp NULL DEFAULT current_timestamp(),
  `granted_by_user_id` int(10) unsigned DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  KEY `idx_granted_at` (`granted_at`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `auth_role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `auth_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `auth_role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `auth_permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auth_roles`
--

DROP TABLE IF EXISTS `auth_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_roles` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `role_code` varchar(50) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `role_description` text DEFAULT NULL,
  `role_level` tinyint(3) unsigned DEFAULT 1,
  `is_system_role` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_code` (`role_code`),
  KEY `idx_role_code` (`role_code`),
  KEY `idx_role_level` (`role_level`),
  KEY `idx_system_role` (`is_system_role`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auth_sessions`
--

DROP TABLE IF EXISTS `auth_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_sessions` (
  `id` char(128) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `session_token` char(64) NOT NULL,
  `refresh_token` char(64) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `device_fingerprint` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL,
  `last_activity_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_token` (`session_token`),
  KEY `idx_user` (`user_id`),
  KEY `idx_session_token` (`session_token`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_active` (`is_active`),
  KEY `idx_last_activity` (`last_activity_at`),
  CONSTRAINT `auth_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auth_user_roles`
--

DROP TABLE IF EXISTS `auth_user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_user_roles` (
  `user_id` int(10) unsigned NOT NULL,
  `role_id` smallint(5) unsigned NOT NULL,
  `assigned_at` timestamp NULL DEFAULT current_timestamp(),
  `assigned_by_user_id` int(10) unsigned DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `role_id` (`role_id`),
  KEY `assigned_by_user_id` (`assigned_by_user_id`),
  KEY `idx_assigned_at` (`assigned_at`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `auth_user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `auth_user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `auth_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `auth_user_roles_ibfk_3` FOREIGN KEY (`assigned_by_user_id`) REFERENCES `auth_users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auth_users`
--

DROP TABLE IF EXISTS `auth_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int(10) unsigned NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `password_salt` varchar(100) DEFAULT NULL,
  `password_algorithm` enum('BCRYPT','ARGON2','PBKDF2') DEFAULT 'BCRYPT',
  `mfa_enabled` tinyint(1) DEFAULT 0,
  `mfa_secret` varchar(100) DEFAULT NULL,
  `mfa_backup_codes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`mfa_backup_codes`)),
  `is_active` tinyint(1) DEFAULT 1,
  `is_locked` tinyint(1) DEFAULT 0,
  `is_verified` tinyint(1) DEFAULT 0,
  `failed_login_attempts` tinyint(3) unsigned DEFAULT 0,
  `max_failed_attempts` tinyint(3) unsigned DEFAULT 5,
  `lockout_until` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `password_changed_at` timestamp NULL DEFAULT current_timestamp(),
  `must_change_password` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `person_id` (`person_id`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_active` (`is_active`),
  KEY `idx_locked` (`is_locked`),
  KEY `idx_verified` (`is_verified`),
  KEY `idx_last_login` (`last_login_at`),
  KEY `idx_last_activity` (`last_activity_at`),
  KEY `idx_lockout` (`lockout_until`),
  CONSTRAINT `auth_users_ibfk_1` FOREIGN KEY (`person_id`) REFERENCES `people_persons` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cam_feeds`
--

DROP TABLE IF EXISTS `cam_feeds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cam_feeds` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `system_id` int(10) unsigned NOT NULL,
  `feed_name` varchar(100) NOT NULL,
  `feed_type` enum('MAIN','SUB','THIRD','AUDIO','SNAPSHOT','MJPEG','THUMBNAIL') DEFAULT 'MAIN',
  `stream_url` varchar(500) DEFAULT NULL,
  `rtsp_url` varchar(500) DEFAULT NULL,
  `rtmp_url` varchar(500) DEFAULT NULL,
  `hls_url` varchar(500) DEFAULT NULL,
  `webrtc_url` varchar(500) DEFAULT NULL,
  `onvif_profile_token` varchar(100) DEFAULT NULL,
  `resolution_width` smallint(5) unsigned DEFAULT 1920,
  `resolution_height` smallint(5) unsigned DEFAULT 1080,
  `fps` tinyint(3) unsigned DEFAULT 25,
  `bitrate_kbps` int(10) unsigned DEFAULT 2048,
  `codec` enum('H264','H265','MJPEG','MPEG4','VP8','VP9','AV1') DEFAULT 'H264',
  `profile` enum('BASELINE','MAIN','HIGH','HIGH10','HIGH422','HIGH444') DEFAULT 'HIGH',
  `quality_level` enum('LOW','MEDIUM','HIGH','ULTRA') DEFAULT 'HIGH',
  `audio_enabled` tinyint(1) DEFAULT 0,
  `audio_codec` enum('AAC','G711A','G711U','MP3','PCM') DEFAULT 'AAC',
  `audio_bitrate_kbps` smallint(5) unsigned DEFAULT 64,
  `ptz_capable` tinyint(1) DEFAULT 0,
  `zoom_capable` tinyint(1) DEFAULT 0,
  `focus_capable` tinyint(1) DEFAULT 0,
  `iris_capable` tinyint(1) DEFAULT 0,
  `night_vision` tinyint(1) DEFAULT 1,
  `infrared_enabled` tinyint(1) DEFAULT 0,
  `white_balance_auto` tinyint(1) DEFAULT 1,
  `motion_detection` tinyint(1) DEFAULT 1,
  `privacy_masking` tinyint(1) DEFAULT 0,
  `tampering_detection` tinyint(1) DEFAULT 0,
  `ai_analytics_enabled` tinyint(1) DEFAULT 1,
  `is_recording` tinyint(1) DEFAULT 0,
  `recording_schedule` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`recording_schedule`)),
  `stream_status` enum('ONLINE','OFFLINE','ERROR','MAINTENANCE','UNAUTHORIZED') DEFAULT 'OFFLINE',
  `error_message` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `recording_enabled` tinyint(1) DEFAULT 1,
  `last_frame_at` timestamp NULL DEFAULT NULL,
  `frame_count` bigint(20) unsigned DEFAULT 0,
  `bytes_received` bigint(20) unsigned DEFAULT 0,
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `preview_image_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_system_feed` (`system_id`,`feed_type`),
  KEY `idx_feed_name` (`feed_name`),
  KEY `idx_stream_status` (`stream_status`),
  KEY `idx_recording` (`is_recording`),
  KEY `idx_resolution` (`resolution_width`,`resolution_height`),
  KEY `idx_last_frame` (`last_frame_at`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `cam_feeds_ibfk_1` FOREIGN KEY (`system_id`) REFERENCES `cam_systems` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cam_firmware_versions`
--

DROP TABLE IF EXISTS `cam_firmware_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cam_firmware_versions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `model_id` int(10) unsigned NOT NULL,
  `version_number` varchar(50) NOT NULL,
  `version_type` enum('STABLE','BETA','ALPHA','RC','HOTFIX') DEFAULT 'STABLE',
  `release_date` date DEFAULT NULL,
  `build_date` date DEFAULT NULL,
  `security_patch_level` varchar(20) DEFAULT NULL,
  `is_critical_update` tinyint(1) DEFAULT 0,
  `is_latest` tinyint(1) DEFAULT 0,
  `changelog` text DEFAULT NULL,
  `known_issues` text DEFAULT NULL,
  `download_url` varchar(500) DEFAULT NULL,
  `file_size_bytes` bigint(20) unsigned DEFAULT NULL,
  `checksum_md5` char(32) DEFAULT NULL,
  `checksum_sha256` char(64) DEFAULT NULL,
  `installation_notes` text DEFAULT NULL,
  `rollback_supported` tinyint(1) DEFAULT 1,
  `minimum_previous_version` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_model_version` (`model_id`,`version_number`),
  KEY `idx_version_number` (`version_number`),
  KEY `idx_version_type` (`version_type`),
  KEY `idx_release_date` (`release_date`),
  KEY `idx_latest` (`is_latest`),
  KEY `idx_critical` (`is_critical_update`),
  CONSTRAINT `cam_firmware_versions_ibfk_1` FOREIGN KEY (`model_id`) REFERENCES `cam_models` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cam_manufacturers`
--

DROP TABLE IF EXISTS `cam_manufacturers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cam_manufacturers` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `manufacturer_code` varchar(20) NOT NULL,
  `manufacturer_name` varchar(100) NOT NULL,
  `country_id` smallint(5) unsigned DEFAULT NULL,
  `website_url` varchar(255) DEFAULT NULL,
  `support_email` varchar(100) DEFAULT NULL,
  `support_phone` varchar(20) DEFAULT NULL,
  `founded_year` year(4) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `manufacturer_code` (`manufacturer_code`),
  KEY `idx_manufacturer_code` (`manufacturer_code`),
  KEY `idx_manufacturer_name` (`manufacturer_name`),
  KEY `idx_country` (`country_id`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `cam_manufacturers_ibfk_1` FOREIGN KEY (`country_id`) REFERENCES `sys_countries` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cam_model_capabilities`
--

DROP TABLE IF EXISTS `cam_model_capabilities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cam_model_capabilities` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `model_id` int(10) unsigned NOT NULL,
  `capability_type` enum('VIDEO_CODEC','AUDIO_CODEC','PROTOCOL','ANALYTICS','STORAGE','NETWORK','FEATURE') NOT NULL,
  `capability_name` varchar(100) NOT NULL,
  `capability_value` varchar(255) DEFAULT NULL,
  `is_standard` tinyint(1) DEFAULT 1,
  `is_optional` tinyint(1) DEFAULT 0,
  `requires_license` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_model_capability` (`model_id`,`capability_type`),
  KEY `idx_capability_type` (`capability_type`),
  KEY `idx_capability_name` (`capability_name`),
  CONSTRAINT `cam_model_capabilities_ibfk_1` FOREIGN KEY (`model_id`) REFERENCES `cam_models` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cam_model_series`
--

DROP TABLE IF EXISTS `cam_model_series`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cam_model_series` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `manufacturer_id` smallint(5) unsigned NOT NULL,
  `series_code` varchar(30) NOT NULL,
  `series_name` varchar(100) NOT NULL,
  `series_description` text DEFAULT NULL,
  `target_market` enum('CONSUMER','PROSUMER','PROFESSIONAL','ENTERPRISE','INDUSTRIAL') NOT NULL,
  `launch_year` year(4) DEFAULT NULL,
  `end_of_life_year` year(4) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_manufacturer_series_code` (`manufacturer_id`,`series_code`),
  KEY `idx_series_code` (`series_code`),
  KEY `idx_target_market` (`target_market`),
  KEY `idx_launch_year` (`launch_year`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `cam_model_series_ibfk_1` FOREIGN KEY (`manufacturer_id`) REFERENCES `cam_manufacturers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cam_models`
--

DROP TABLE IF EXISTS `cam_models`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cam_models` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `manufacturer_id` smallint(5) unsigned NOT NULL,
  `series_id` smallint(5) unsigned DEFAULT NULL,
  `model_code` varchar(50) NOT NULL,
  `model_name` varchar(100) NOT NULL,
  `model_type` enum('FIXED','PTZ','DOME','BULLET','THERMAL','PANORAMIC','FISHEYE','TURRET','BOX') NOT NULL,
  `form_factor` enum('INDOOR','OUTDOOR','VANDAL_RESISTANT','COVERT','MOBILE') NOT NULL,
  `max_resolution_width` smallint(5) unsigned DEFAULT NULL,
  `max_resolution_height` smallint(5) unsigned DEFAULT NULL,
  `max_fps` tinyint(3) unsigned DEFAULT 30,
  `min_illumination_lux` decimal(8,4) DEFAULT NULL,
  `ir_range_meters` decimal(6,2) DEFAULT NULL,
  `optical_zoom_max` decimal(4,1) DEFAULT NULL,
  `digital_zoom_max` decimal(4,1) DEFAULT NULL,
  `operating_temp_min` tinyint(4) DEFAULT NULL,
  `operating_temp_max` tinyint(4) DEFAULT NULL,
  `humidity_max` tinyint(3) unsigned DEFAULT NULL,
  `power_consumption_watts` decimal(6,2) DEFAULT NULL,
  `power_input_voltage` varchar(20) DEFAULT NULL,
  `ip_rating` varchar(10) DEFAULT NULL,
  `impact_rating` varchar(10) DEFAULT NULL,
  `weight_grams` smallint(5) unsigned DEFAULT NULL,
  `dimensions_length` decimal(6,2) DEFAULT NULL,
  `dimensions_width` decimal(6,2) DEFAULT NULL,
  `dimensions_height` decimal(6,2) DEFAULT NULL,
  `warranty_months` tinyint(3) unsigned DEFAULT 12,
  `msrp_usd` decimal(8,2) DEFAULT NULL,
  `launch_date` date DEFAULT NULL,
  `discontinue_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_manufacturer_model` (`manufacturer_id`,`model_code`),
  KEY `series_id` (`series_id`),
  KEY `idx_model_type` (`model_type`),
  KEY `idx_form_factor` (`form_factor`),
  KEY `idx_resolution` (`max_resolution_width`,`max_resolution_height`),
  KEY `idx_launch_date` (`launch_date`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `cam_models_ibfk_1` FOREIGN KEY (`manufacturer_id`) REFERENCES `cam_manufacturers` (`id`),
  CONSTRAINT `cam_models_ibfk_2` FOREIGN KEY (`series_id`) REFERENCES `cam_model_series` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cam_ptz_presets`
--

DROP TABLE IF EXISTS `cam_ptz_presets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cam_ptz_presets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `feed_id` int(10) unsigned NOT NULL,
  `preset_number` tinyint(3) unsigned NOT NULL,
  `preset_name` varchar(100) NOT NULL,
  `pan_position` decimal(8,4) DEFAULT NULL,
  `tilt_position` decimal(8,4) DEFAULT NULL,
  `zoom_position` decimal(8,4) DEFAULT NULL,
  `focus_position` decimal(8,4) DEFAULT NULL,
  `iris_position` decimal(8,4) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `is_home_position` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_feed_preset_number` (`feed_id`,`preset_number`),
  KEY `idx_preset_name` (`preset_name`),
  KEY `idx_home_position` (`is_home_position`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `cam_ptz_presets_ibfk_1` FOREIGN KEY (`feed_id`) REFERENCES `cam_feeds` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cam_systems`
--

DROP TABLE IF EXISTS `cam_systems`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cam_systems` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `system_uuid` char(36) NOT NULL,
  `outlet_id` int(10) unsigned NOT NULL,
  `zone_id` int(10) unsigned DEFAULT NULL,
  `model_id` int(10) unsigned NOT NULL,
  `firmware_version_id` int(10) unsigned DEFAULT NULL,
  `system_name` varchar(100) NOT NULL,
  `display_name` varchar(100) DEFAULT NULL,
  `asset_tag` varchar(50) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `mac_address` char(17) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `port_number` smallint(5) unsigned DEFAULT 80,
  `secondary_ip` varchar(45) DEFAULT NULL,
  `subnet_mask` varchar(15) DEFAULT NULL,
  `gateway_ip` varchar(45) DEFAULT NULL,
  `dns_primary` varchar(45) DEFAULT NULL,
  `dns_secondary` varchar(45) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `api_key` varchar(255) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_price` decimal(10,2) DEFAULT NULL,
  `supplier_name` varchar(200) DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `installation_date` date DEFAULT NULL,
  `installation_notes` text DEFAULT NULL,
  `last_maintenance_date` date DEFAULT NULL,
  `next_maintenance_due` date DEFAULT NULL,
  `maintenance_schedule_months` tinyint(3) unsigned DEFAULT 6,
  `is_active` tinyint(1) DEFAULT 1,
  `is_online` tinyint(1) DEFAULT 0,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `last_reboot_at` timestamp NULL DEFAULT NULL,
  `health_score` decimal(5,2) DEFAULT 100.00,
  `uptime_seconds` bigint(20) unsigned DEFAULT 0,
  `cpu_usage_percent` decimal(5,2) DEFAULT NULL,
  `memory_usage_percent` decimal(5,2) DEFAULT NULL,
  `temperature_celsius` decimal(5,2) DEFAULT NULL,
  `storage_used_percent` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `system_uuid` (`system_uuid`),
  UNIQUE KEY `unique_outlet_system_name` (`outlet_id`,`system_name`),
  UNIQUE KEY `unique_mac_address` (`mac_address`),
  UNIQUE KEY `unique_ip_port` (`ip_address`,`port_number`),
  KEY `firmware_version_id` (`firmware_version_id`),
  KEY `idx_outlet` (`outlet_id`),
  KEY `idx_zone` (`zone_id`),
  KEY `idx_model` (`model_id`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_online_status` (`is_online`),
  KEY `idx_health_score` (`health_score`),
  KEY `idx_last_seen` (`last_seen_at`),
  KEY `idx_maintenance` (`next_maintenance_due`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `cam_systems_ibfk_1` FOREIGN KEY (`outlet_id`) REFERENCES `org_outlets` (`id`),
  CONSTRAINT `cam_systems_ibfk_2` FOREIGN KEY (`zone_id`) REFERENCES `org_zones` (`id`),
  CONSTRAINT `cam_systems_ibfk_3` FOREIGN KEY (`model_id`) REFERENCES `cam_models` (`id`),
  CONSTRAINT `cam_systems_ibfk_4` FOREIGN KEY (`firmware_version_id`) REFERENCES `cam_firmware_versions` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `camera_alerts`
--

DROP TABLE IF EXISTS `camera_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `camera_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feed_id` int(11) NOT NULL,
  `alert_type` enum('OFFLINE','LOW_QUALITY','STORAGE_FULL','MOTION_FLOOD','TAMPER','HARDWARE_ERROR') DEFAULT 'OFFLINE',
  `alert_level` enum('INFO','WARNING','ERROR','CRITICAL') DEFAULT 'WARNING',
  `message` text NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `is_resolved` tinyint(1) DEFAULT 0,
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `notification_sent` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_feed_alert_type` (`feed_id`,`alert_type`),
  KEY `idx_alert_level` (`alert_level`),
  KEY `idx_unresolved` (`is_resolved`,`created_at`),
  CONSTRAINT `camera_alerts_ibfk_1` FOREIGN KEY (`feed_id`) REFERENCES `camera_feeds` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `camera_analytics`
--

DROP TABLE IF EXISTS `camera_analytics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `camera_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feed_id` int(11) NOT NULL,
  `analytics_date` date NOT NULL,
  `total_events` int(11) DEFAULT 0,
  `motion_events` int(11) DEFAULT 0,
  `ai_detections` int(11) DEFAULT 0,
  `recording_hours` decimal(6,2) DEFAULT 0.00,
  `uptime_percentage` decimal(5,2) DEFAULT 0.00,
  `avg_fps` decimal(5,2) DEFAULT 0.00,
  `data_usage_gb` decimal(10,3) DEFAULT 0.000,
  `alert_count` int(11) DEFAULT 0,
  `performance_score` decimal(5,2) DEFAULT 0.00,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_feed_date` (`feed_id`,`analytics_date`),
  KEY `idx_analytics_date` (`analytics_date`),
  CONSTRAINT `camera_analytics_ibfk_1` FOREIGN KEY (`feed_id`) REFERENCES `camera_feeds` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `camera_configurations`
--

DROP TABLE IF EXISTS `camera_configurations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `camera_configurations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feed_id` int(11) NOT NULL,
  `config_type` enum('VIDEO','AUDIO','MOTION','AI','NETWORK','STORAGE','SYSTEM') DEFAULT 'VIDEO',
  `config_key` varchar(100) NOT NULL,
  `config_value` text DEFAULT NULL,
  `default_value` text DEFAULT NULL,
  `data_type` enum('STRING','INTEGER','DECIMAL','BOOLEAN','JSON','ARRAY') DEFAULT 'STRING',
  `description` text DEFAULT NULL,
  `is_advanced` tinyint(1) DEFAULT 0,
  `requires_restart` tinyint(1) DEFAULT 0,
  `last_changed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_feed_config_key` (`feed_id`,`config_type`,`config_key`),
  KEY `idx_feed_config_type` (`feed_id`,`config_type`),
  KEY `idx_config_key` (`config_key`),
  CONSTRAINT `camera_configurations_ibfk_1` FOREIGN KEY (`feed_id`) REFERENCES `camera_feeds` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `camera_events`
--

DROP TABLE IF EXISTS `camera_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `camera_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feed_id` int(11) NOT NULL,
  `event_type` enum('MOTION','ALARM','TAMPER','DISCONNECT','RECONNECT','ERROR','AI_DETECTION') DEFAULT 'MOTION',
  `event_severity` enum('LOW','MEDIUM','HIGH','CRITICAL') DEFAULT 'MEDIUM',
  `event_description` text DEFAULT NULL,
  `detection_area` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`detection_area`)),
  `confidence_score` decimal(5,2) DEFAULT NULL,
  `ai_detection_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ai_detection_data`)),
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `video_clip_path` varchar(255) DEFAULT NULL,
  `start_time` timestamp NOT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `is_acknowledged` tinyint(1) DEFAULT 0,
  `acknowledged_by` int(11) DEFAULT NULL,
  `acknowledged_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_feed_event_type` (`feed_id`,`event_type`),
  KEY `idx_severity_time` (`event_severity`,`start_time`),
  KEY `idx_acknowledged` (`is_acknowledged`),
  CONSTRAINT `camera_events_ibfk_1` FOREIGN KEY (`feed_id`) REFERENCES `camera_feeds` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `camera_feeds`
--

DROP TABLE IF EXISTS `camera_feeds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `camera_feeds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `system_id` int(11) NOT NULL,
  `feed_name` varchar(100) NOT NULL,
  `feed_type` enum('MAIN','SUB','THIRD','AUDIO') DEFAULT 'MAIN',
  `stream_url` varchar(500) DEFAULT NULL,
  `rtsp_url` varchar(500) DEFAULT NULL,
  `rtmp_url` varchar(500) DEFAULT NULL,
  `hls_url` varchar(500) DEFAULT NULL,
  `resolution_width` int(11) DEFAULT 1920,
  `resolution_height` int(11) DEFAULT 1080,
  `fps` int(11) DEFAULT 25,
  `bitrate_kbps` int(11) DEFAULT 2048,
  `codec` enum('H264','H265','MJPEG','MPEG4') DEFAULT 'H264',
  `audio_enabled` tinyint(1) DEFAULT 0,
  `audio_codec` enum('AAC','G711A','G711U','MP3') DEFAULT 'AAC',
  `ptz_capable` tinyint(1) DEFAULT 0,
  `zoom_capable` tinyint(1) DEFAULT 0,
  `night_vision` tinyint(1) DEFAULT 1,
  `motion_detection` tinyint(1) DEFAULT 1,
  `is_recording` tinyint(1) DEFAULT 0,
  `recording_schedule` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`recording_schedule`)),
  `stream_status` enum('ONLINE','OFFLINE','ERROR','MAINTENANCE') DEFAULT 'OFFLINE',
  `last_frame_at` timestamp NULL DEFAULT NULL,
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_system_feed` (`system_id`,`feed_type`),
  KEY `idx_stream_status` (`stream_status`),
  KEY `idx_recording` (`is_recording`),
  CONSTRAINT `camera_feeds_ibfk_1` FOREIGN KEY (`system_id`) REFERENCES `camera_systems` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=99 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `camera_groups`
--

DROP TABLE IF EXISTS `camera_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `camera_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_name` varchar(100) NOT NULL,
  `group_type` enum('STORE','FLOOR','ZONE','CUSTOM') DEFAULT 'CUSTOM',
  `outlet_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `layout_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`layout_config`)),
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_outlet_type` (`outlet_id`,`group_type`),
  KEY `idx_active_order` (`is_active`,`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `camera_health_monitoring`
--

DROP TABLE IF EXISTS `camera_health_monitoring`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `camera_health_monitoring` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feed_id` int(11) NOT NULL,
  `check_time` timestamp NULL DEFAULT current_timestamp(),
  `ping_response_ms` int(11) DEFAULT NULL,
  `stream_quality_score` decimal(5,2) DEFAULT NULL,
  `fps_actual` decimal(5,2) DEFAULT NULL,
  `bitrate_actual_kbps` int(11) DEFAULT NULL,
  `packet_loss_percentage` decimal(5,2) DEFAULT NULL,
  `connection_errors` int(11) DEFAULT 0,
  `cpu_usage_percentage` decimal(5,2) DEFAULT NULL,
  `memory_usage_percentage` decimal(5,2) DEFAULT NULL,
  `storage_usage_percentage` decimal(5,2) DEFAULT NULL,
  `temperature_celsius` decimal(5,2) DEFAULT NULL,
  `uptime_hours` decimal(10,2) DEFAULT NULL,
  `health_score` decimal(5,2) DEFAULT NULL,
  `status_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`status_details`)),
  PRIMARY KEY (`id`),
  KEY `idx_feed_check_time` (`feed_id`,`check_time`),
  KEY `idx_health_score` (`health_score`),
  CONSTRAINT `camera_health_monitoring_ibfk_1` FOREIGN KEY (`feed_id`) REFERENCES `camera_feeds` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `camera_health_scores`
--

DROP TABLE IF EXISTS `camera_health_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `camera_health_scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feed_id` int(10) unsigned NOT NULL,
  `health_score` int(11) DEFAULT 0,
  `uptime_percentage` decimal(5,2) DEFAULT 0.00,
  `frame_rate_score` int(11) DEFAULT 0,
  `quality_score` int(11) DEFAULT 0,
  `connectivity_score` int(11) DEFAULT 0,
  `calculated_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_feed_health` (`feed_id`),
  KEY `idx_health_score` (`health_score`),
  KEY `idx_calculated` (`calculated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `camera_presets`
--

DROP TABLE IF EXISTS `camera_presets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `camera_presets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feed_id` int(11) NOT NULL,
  `preset_name` varchar(100) NOT NULL,
  `preset_number` int(11) DEFAULT NULL,
  `pan_position` decimal(8,4) DEFAULT NULL,
  `tilt_position` decimal(8,4) DEFAULT NULL,
  `zoom_level` decimal(6,2) DEFAULT NULL,
  `focus_position` int(11) DEFAULT NULL,
  `iris_position` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_feed_preset_number` (`feed_id`,`preset_number`),
  KEY `idx_feed_preset` (`feed_id`,`preset_number`),
  CONSTRAINT `camera_presets_ibfk_1` FOREIGN KEY (`feed_id`) REFERENCES `camera_feeds` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `camera_recordings`
--

DROP TABLE IF EXISTS `camera_recordings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `camera_recordings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feed_id` int(11) NOT NULL,
  `recording_type` enum('CONTINUOUS','MOTION','ALARM','MANUAL','SCHEDULE') DEFAULT 'CONTINUOUS',
  `file_path` varchar(500) DEFAULT NULL,
  `file_size_bytes` bigint(20) DEFAULT 0,
  `duration_seconds` int(11) DEFAULT 0,
  `start_time` timestamp NOT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `resolution` varchar(20) DEFAULT NULL,
  `fps` int(11) DEFAULT NULL,
  `codec` varchar(20) DEFAULT NULL,
  `trigger_event` varchar(100) DEFAULT NULL,
  `is_locked` tinyint(1) DEFAULT 0,
  `is_exported` tinyint(1) DEFAULT 0,
  `export_path` varchar(500) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_feed_time` (`feed_id`,`start_time`),
  KEY `idx_recording_type` (`recording_type`),
  KEY `idx_time_range` (`start_time`,`end_time`),
  CONSTRAINT `camera_recordings_ibfk_1` FOREIGN KEY (`feed_id`) REFERENCES `camera_feeds` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `camera_schedules`
--

DROP TABLE IF EXISTS `camera_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `camera_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feed_id` int(11) NOT NULL,
  `schedule_type` enum('RECORDING','MOTION_DETECTION','AI_ANALYSIS','PRESET_TOUR') DEFAULT 'RECORDING',
  `schedule_name` varchar(100) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `monday_schedule` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`monday_schedule`)),
  `tuesday_schedule` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tuesday_schedule`)),
  `wednesday_schedule` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`wednesday_schedule`)),
  `thursday_schedule` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`thursday_schedule`)),
  `friday_schedule` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`friday_schedule`)),
  `saturday_schedule` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`saturday_schedule`)),
  `sunday_schedule` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sunday_schedule`)),
  `holiday_schedule` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`holiday_schedule`)),
  `exceptions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`exceptions`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_feed_schedule_type` (`feed_id`,`schedule_type`),
  KEY `idx_active_schedules` (`is_active`),
  CONSTRAINT `camera_schedules_ibfk_1` FOREIGN KEY (`feed_id`) REFERENCES `camera_feeds` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `camera_systems`
--

DROP TABLE IF EXISTS `camera_systems`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `camera_systems` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `system_name` varchar(100) NOT NULL,
  `system_type` enum('HIKVISION','DAHUA','AXIS','BOSCH','UNIVIEW','GENERIC') DEFAULT 'GENERIC',
  `ip_address` varchar(45) NOT NULL,
  `port` int(11) DEFAULT 80,
  `username` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `rtsp_port` int(11) DEFAULT 554,
  `http_port` int(11) DEFAULT 80,
  `https_port` int(11) DEFAULT 443,
  `onvif_port` int(11) DEFAULT 2020,
  `firmware_version` varchar(50) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `manufacturer` varchar(100) DEFAULT NULL,
  `location_description` text DEFAULT NULL,
  `outlet_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_online` tinyint(1) DEFAULT 0,
  `last_ping_at` timestamp NULL DEFAULT NULL,
  `capabilities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`capabilities`)),
  `configuration` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`configuration`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ip_port` (`ip_address`,`port`),
  KEY `idx_outlet_active` (`outlet_id`,`is_active`),
  KEY `idx_system_type` (`system_type`),
  KEY `idx_online_status` (`is_online`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `camera_users`
--

DROP TABLE IF EXISTS `camera_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `camera_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `role` enum('ADMIN','OPERATOR','VIEWER','GUARD') DEFAULT 'VIEWER',
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `allowed_cameras` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`allowed_cameras`)),
  `allowed_groups` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`allowed_groups`)),
  `max_concurrent_streams` int(11) DEFAULT 4,
  `session_timeout_minutes` int(11) DEFAULT 480,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_user_role` (`user_id`,`role`),
  KEY `idx_active_users` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cis_ai_detections`
--

DROP TABLE IF EXISTS `cis_ai_detections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cis_ai_detections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `detection_type` enum('AFTER_HOURS_ACTIVITY','STAFF_MOVEMENT','FRAUD_PATTERN','SECURITY_INCIDENT','UNUSUAL_BEHAVIOR','HIGH_VALUE_TRANSACTION','RAPID_TRANSACTIONS','VOID_PATTERN') NOT NULL,
  `outlet_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `camera_id` int(11) DEFAULT NULL,
  `detected_at` datetime NOT NULL,
  `confidence_score` decimal(3,2) DEFAULT 0.00,
  `details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details_json`)),
  `status` enum('NEW','REVIEWED','RESOLVED','FALSE_POSITIVE') DEFAULT 'NEW',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_detection_type` (`detection_type`),
  KEY `idx_outlet_date` (`outlet_id`,`detected_at`),
  KEY `idx_status` (`status`),
  KEY `idx_confidence` (`confidence_score`),
  KEY `idx_reviewed_by` (`reviewed_by`)
) ENGINE=InnoDB AUTO_INCREMENT=111 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AI detection results storage and tracking';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cis_camera_feeds`
--

DROP TABLE IF EXISTS `cis_camera_feeds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cis_camera_feeds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `camera_name` varchar(100) NOT NULL,
  `outlet_id` int(11) NOT NULL,
  `camera_ip` varchar(45) DEFAULT NULL,
  `camera_port` int(11) DEFAULT 554,
  `stream_url` varchar(500) DEFAULT NULL,
  `camera_type` varchar(50) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE','MAINTENANCE','ERROR') DEFAULT 'ACTIVE',
  `last_ping` datetime DEFAULT NULL,
  `settings_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings_json`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_outlet_id` (`outlet_id`),
  KEY `idx_status` (`status`),
  KEY `idx_camera_ip` (`camera_ip`),
  KEY `idx_last_ping` (`last_ping`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Camera feeds configuration and monitoring';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cis_incidents`
--

DROP TABLE IF EXISTS `cis_incidents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cis_incidents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `incident_type` enum('SECURITY_BREACH','THEFT','VANDALISM','STAFF_MISCONDUCT','SYSTEM_ALERT','OTHER') NOT NULL,
  `severity` enum('LOW','MEDIUM','HIGH','CRITICAL') DEFAULT 'MEDIUM',
  `outlet_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `incident_date` datetime NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details_json`)),
  `status` enum('NEW','INVESTIGATING','RESOLVED','CLOSED') DEFAULT 'NEW',
  `assigned_to` int(11) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_incident_type` (`incident_type`),
  KEY `idx_severity` (`severity`),
  KEY `idx_outlet_date` (`outlet_id`,`incident_date`),
  KEY `idx_status` (`status`),
  KEY `idx_assigned_to` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Security incidents tracking and management';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ciswatch_alert_config`
--

DROP TABLE IF EXISTS `ciswatch_alert_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ciswatch_alert_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alert_type` varchar(50) NOT NULL,
  `priority_level` enum('HIGH','MEDIUM','LOW') NOT NULL,
  `notification_channels` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`notification_channels`)),
  `response_sla_minutes` int(11) DEFAULT 30,
  `escalation_enabled` tinyint(1) DEFAULT 1,
  `escalation_delay_minutes` int(11) DEFAULT 15,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_alert_type` (`alert_type`),
  KEY `idx_priority_active` (`priority_level`,`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ciswatch_detection_config`
--

DROP TABLE IF EXISTS `ciswatch_detection_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ciswatch_detection_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module_name` varchar(100) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sensitivity_level` decimal(5,2) DEFAULT 70.00,
  `threshold_value` decimal(10,2) DEFAULT 200.00,
  `detection_algorithm` varchar(50) DEFAULT 'ml_standard',
  `false_positive_rate` decimal(5,2) DEFAULT 5.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `last_updated` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `module_name` (`module_name`),
  KEY `idx_module_active` (`module_name`,`is_active`),
  KEY `idx_sensitivity` (`sensitivity_level`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ciswatch_notification_config`
--

DROP TABLE IF EXISTS `ciswatch_notification_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ciswatch_notification_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `channel_type` enum('EMAIL','SMS','PUSH','PHONE') NOT NULL,
  `is_enabled` tinyint(1) DEFAULT 1,
  `configuration` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`configuration`)),
  `test_recipient` varchar(255) DEFAULT NULL,
  `last_test_at` timestamp NULL DEFAULT NULL,
  `test_status` enum('SUCCESS','FAILED','PENDING') DEFAULT 'PENDING',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_channel` (`channel_type`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ciswatch_system_config`
--

DROP TABLE IF EXISTS `ciswatch_system_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ciswatch_system_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL,
  `config_value` text DEFAULT NULL,
  `config_type` enum('STRING','INTEGER','DECIMAL','BOOLEAN','JSON') DEFAULT 'STRING',
  `description` text DEFAULT NULL,
  `is_system` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`),
  KEY `idx_key_type` (`config_key`,`config_type`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `configuration`
--

DROP TABLE IF EXISTS `configuration`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuration` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_name` varchar(255) DEFAULT NULL,
  `setting_value` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_setting_name` (`setting_name`),
  KEY `ix_configuration_setting` (`setting_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `deputy_shifts`
--

DROP TABLE IF EXISTS `deputy_shifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `deputy_shifts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `deputy_shift_id` varchar(50) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `outlet_id` int(11) DEFAULT NULL,
  `shift_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `break_duration` int(11) DEFAULT 0,
  `role` varchar(100) DEFAULT NULL,
  `status` enum('SCHEDULED','IN_PROGRESS','COMPLETED','CANCELLED') DEFAULT 'SCHEDULED',
  `actual_start` datetime DEFAULT NULL,
  `actual_end` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `deputy_shift_id` (`deputy_shift_id`),
  KEY `idx_user_date` (`user_id`,`shift_date`),
  KEY `idx_outlet_date` (`outlet_id`,`shift_date`),
  KEY `idx_status` (`status`),
  KEY `idx_shift_times` (`shift_date`,`start_time`,`end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Staff scheduling data from Deputy';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `deputy_timesheets`
--

DROP TABLE IF EXISTS `deputy_timesheets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `deputy_timesheets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `deputy_timesheet_id` varchar(50) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `outlet_id` int(11) DEFAULT NULL,
  `work_date` date NOT NULL,
  `clock_in` datetime DEFAULT NULL,
  `clock_out` datetime DEFAULT NULL,
  `break_start` datetime DEFAULT NULL,
  `break_end` datetime DEFAULT NULL,
  `total_hours` decimal(4,2) DEFAULT 0.00,
  `overtime_hours` decimal(4,2) DEFAULT 0.00,
  `status` enum('DRAFT','SUBMITTED','APPROVED','REJECTED') DEFAULT 'DRAFT',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `deputy_timesheet_id` (`deputy_timesheet_id`),
  KEY `idx_user_date` (`user_id`,`work_date`),
  KEY `idx_outlet_date` (`outlet_id`,`work_date`),
  KEY `idx_status` (`status`),
  KEY `idx_clock_times` (`clock_in`,`clock_out`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Timesheet data from Deputy for attendance tracking';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ep_api_quotas`
--

DROP TABLE IF EXISTS `ep_api_quotas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ep_api_quotas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` varchar(50) NOT NULL,
  `daily_limit` int(11) DEFAULT 10000,
  `monthly_limit` int(11) DEFAULT 300000,
  `daily_used` int(11) DEFAULT 0,
  `monthly_used` int(11) DEFAULT 0,
  `last_daily_reset` date DEFAULT curdate(),
  `last_monthly_reset` date DEFAULT date_format(current_timestamp(),'%Y-%m-01'),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_supplier_id` (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ep_api_rate_limits`
--

DROP TABLE IF EXISTS `ep_api_rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ep_api_rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identifier` varchar(100) NOT NULL,
  `endpoint` varchar(100) NOT NULL,
  `requests_count` int(11) DEFAULT 0,
  `window_start` timestamp NULL DEFAULT current_timestamp(),
  `blocked_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_rate_limit` (`identifier`,`endpoint`),
  KEY `idx_window_start` (`window_start`),
  KEY `idx_blocked_until` (`blocked_until`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ep_api_security_events`
--

DROP TABLE IF EXISTS `ep_api_security_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ep_api_security_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` enum('rate_limit_exceeded','suspicious_pattern','authentication_failure','sql_injection_attempt','xss_attempt') NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `supplier_id` varchar(50) DEFAULT NULL,
  `endpoint` varchar(100) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `severity` enum('low','medium','high','critical') DEFAULT 'medium',
  `resolved` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_event_type` (`event_type`,`created_at`),
  KEY `idx_ip_events` (`ip_address`,`created_at`),
  KEY `idx_severity` (`severity`,`resolved`),
  KEY `idx_unresolved` (`resolved`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ep_api_usage_logs`
--

DROP TABLE IF EXISTS `ep_api_usage_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ep_api_usage_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` varchar(50) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `endpoint` varchar(100) NOT NULL,
  `method` varchar(10) NOT NULL,
  `response_time_ms` int(11) DEFAULT NULL,
  `response_code` int(11) DEFAULT NULL,
  `request_size` int(11) DEFAULT 0,
  `response_size` int(11) DEFAULT 0,
  `blocked` tinyint(1) DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_supplier_usage` (`supplier_id`,`created_at`),
  KEY `idx_ip_usage` (`ip_address`,`created_at`),
  KEY `idx_endpoint_usage` (`endpoint`,`created_at`),
  KEY `idx_blocked_requests` (`blocked`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `event_categories`
--

DROP TABLE IF EXISTS `event_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_categories` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `category_code` varchar(30) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `parent_category_id` smallint(5) unsigned DEFAULT NULL,
  `category_level` tinyint(3) unsigned DEFAULT 1,
  `color_hex` char(7) DEFAULT '#3498db',
  `icon_class` varchar(50) DEFAULT NULL,
  `is_system_category` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_code` (`category_code`),
  KEY `idx_category_code` (`category_code`),
  KEY `idx_parent` (`parent_category_id`),
  KEY `idx_level` (`category_level`),
  KEY `idx_system` (`is_system_category`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `event_categories_ibfk_1` FOREIGN KEY (`parent_category_id`) REFERENCES `event_categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `event_severity_levels`
--

DROP TABLE IF EXISTS `event_severity_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_severity_levels` (
  `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `severity_code` varchar(20) NOT NULL,
  `severity_name` varchar(50) NOT NULL,
  `severity_level` tinyint(3) unsigned NOT NULL,
  `color_hex` char(7) DEFAULT NULL,
  `background_color_hex` char(7) DEFAULT NULL,
  `notification_required` tinyint(1) DEFAULT 0,
  `email_notification` tinyint(1) DEFAULT 0,
  `sms_notification` tinyint(1) DEFAULT 0,
  `push_notification` tinyint(1) DEFAULT 1,
  `escalation_minutes` int(10) unsigned DEFAULT NULL,
  `auto_acknowledge` tinyint(1) DEFAULT 0,
  `retention_days` smallint(5) unsigned DEFAULT 90,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `severity_code` (`severity_code`),
  KEY `idx_severity_code` (`severity_code`),
  KEY `idx_severity_level` (`severity_level`),
  KEY `idx_notification` (`notification_required`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `events_master`
--

DROP TABLE IF EXISTS `events_master`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `events_master` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_uuid` char(36) NOT NULL,
  `feed_id` int(10) unsigned NOT NULL,
  `category_id` smallint(5) unsigned NOT NULL,
  `severity_id` tinyint(3) unsigned NOT NULL,
  `event_type` enum('MOTION','AI_DETECTION','SYSTEM_ALERT','USER_ACTION','ALARM','MAINTENANCE','NETWORK','HARDWARE') NOT NULL,
  `event_title` varchar(200) NOT NULL,
  `event_description` text DEFAULT NULL,
  `detection_confidence` decimal(5,4) DEFAULT NULL,
  `object_count` tinyint(3) unsigned DEFAULT 1,
  `bounding_boxes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`bounding_boxes`)),
  `tracking_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tracking_ids`)),
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `video_clip_path` varchar(255) DEFAULT NULL,
  `video_duration_seconds` decimal(6,2) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `custom_attributes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_attributes`)),
  `location_coordinates` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`location_coordinates`)),
  `started_at` timestamp(3) NOT NULL,
  `ended_at` timestamp(3) NULL DEFAULT NULL,
  `duration_seconds` decimal(8,3) DEFAULT NULL,
  `peak_confidence` decimal(5,4) DEFAULT NULL,
  `min_confidence` decimal(5,4) DEFAULT NULL,
  `avg_confidence` decimal(5,4) DEFAULT NULL,
  `frame_count` int(10) unsigned DEFAULT 1,
  `is_acknowledged` tinyint(1) DEFAULT 0,
  `acknowledged_by_user_id` int(10) unsigned DEFAULT NULL,
  `acknowledged_at` timestamp NULL DEFAULT NULL,
  `acknowledgment_note` text DEFAULT NULL,
  `is_resolved` tinyint(1) DEFAULT 0,
  `resolved_by_user_id` int(10) unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_note` text DEFAULT NULL,
  `false_positive` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_uuid` (`event_uuid`),
  KEY `category_id` (`category_id`),
  KEY `acknowledged_by_user_id` (`acknowledged_by_user_id`),
  KEY `resolved_by_user_id` (`resolved_by_user_id`),
  KEY `idx_event_uuid` (`event_uuid`),
  KEY `idx_feed_time` (`feed_id`,`started_at`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_severity` (`severity_id`),
  KEY `idx_started_at` (`started_at`),
  KEY `idx_acknowledged` (`is_acknowledged`),
  KEY `idx_resolved` (`is_resolved`),
  KEY `idx_confidence` (`detection_confidence`),
  KEY `idx_duration` (`duration_seconds`),
  KEY `idx_false_positive` (`false_positive`),
  CONSTRAINT `events_master_ibfk_1` FOREIGN KEY (`feed_id`) REFERENCES `cam_feeds` (`id`),
  CONSTRAINT `events_master_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `event_categories` (`id`),
  CONSTRAINT `events_master_ibfk_3` FOREIGN KEY (`severity_id`) REFERENCES `event_severity_levels` (`id`),
  CONSTRAINT `events_master_ibfk_4` FOREIGN KEY (`acknowledged_by_user_id`) REFERENCES `auth_users` (`id`),
  CONSTRAINT `events_master_ibfk_5` FOREIGN KEY (`resolved_by_user_id`) REFERENCES `auth_users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `integration_endpoints`
--

DROP TABLE IF EXISTS `integration_endpoints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `integration_endpoints` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `endpoint_name` varchar(100) NOT NULL,
  `endpoint_type` enum('REST_API','GRAPHQL','SOAP','WEBHOOK','MQTT','WEBSOCKET','FTP','SFTP') NOT NULL,
  `base_url` varchar(500) NOT NULL,
  `authentication_type` enum('NONE','BASIC','BEARER','API_KEY','OAUTH2','CERTIFICATE') DEFAULT 'NONE',
  `authentication_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`authentication_config`)),
  `headers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`headers`)),
  `timeout_seconds` tinyint(3) unsigned DEFAULT 30,
  `retry_attempts` tinyint(3) unsigned DEFAULT 3,
  `retry_delay_seconds` tinyint(3) unsigned DEFAULT 5,
  `rate_limit_per_minute` smallint(5) unsigned DEFAULT 60,
  `last_successful_call` timestamp NULL DEFAULT NULL,
  `last_failed_call` timestamp NULL DEFAULT NULL,
  `total_calls` bigint(20) unsigned DEFAULT 0,
  `successful_calls` bigint(20) unsigned DEFAULT 0,
  `failed_calls` bigint(20) unsigned DEFAULT 0,
  `avg_response_time_ms` decimal(8,3) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_endpoint_name` (`endpoint_name`),
  KEY `idx_endpoint_type` (`endpoint_type`),
  KEY `idx_active` (`is_active`),
  KEY `idx_last_successful` (`last_successful_call`),
  KEY `idx_performance` (`avg_response_time_ms`,`successful_calls`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `network_connections`
--

DROP TABLE IF EXISTS `network_connections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `network_connections` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `system_id` int(10) unsigned NOT NULL,
  `switch_id` int(10) unsigned DEFAULT NULL,
  `segment_id` smallint(5) unsigned NOT NULL,
  `port_number` tinyint(3) unsigned DEFAULT NULL,
  `connection_type` enum('WIRED','WIRELESS','POWERLINE','FIBER') DEFAULT 'WIRED',
  `cable_type` enum('CAT5E','CAT6','CAT6A','FIBER_MM','FIBER_SM','COAX','WIRELESS') DEFAULT 'CAT6',
  `cable_length_meters` decimal(6,2) DEFAULT NULL,
  `bandwidth_mbps` smallint(5) unsigned DEFAULT 100,
  `duplex_mode` enum('HALF','FULL','AUTO') DEFAULT 'FULL',
  `power_over_ethernet` tinyint(1) DEFAULT 0,
  `power_consumption_watts` decimal(5,2) DEFAULT NULL,
  `link_status` enum('UP','DOWN','UNKNOWN') DEFAULT 'UNKNOWN',
  `last_up_at` timestamp NULL DEFAULT NULL,
  `last_down_at` timestamp NULL DEFAULT NULL,
  `uptime_seconds` bigint(20) unsigned DEFAULT 0,
  `rx_bytes` bigint(20) unsigned DEFAULT 0,
  `tx_bytes` bigint(20) unsigned DEFAULT 0,
  `rx_packets` bigint(20) unsigned DEFAULT 0,
  `tx_packets` bigint(20) unsigned DEFAULT 0,
  `rx_errors` bigint(20) unsigned DEFAULT 0,
  `tx_errors` bigint(20) unsigned DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_switch_port` (`switch_id`,`port_number`),
  KEY `system_id` (`system_id`),
  KEY `segment_id` (`segment_id`),
  KEY `idx_connection_type` (`connection_type`),
  KEY `idx_cable_type` (`cable_type`),
  KEY `idx_link_status` (`link_status`),
  KEY `idx_poe` (`power_over_ethernet`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `network_connections_ibfk_1` FOREIGN KEY (`system_id`) REFERENCES `cam_systems` (`id`) ON DELETE CASCADE,
  CONSTRAINT `network_connections_ibfk_2` FOREIGN KEY (`switch_id`) REFERENCES `network_switches` (`id`),
  CONSTRAINT `network_connections_ibfk_3` FOREIGN KEY (`segment_id`) REFERENCES `network_segments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `network_segments`
--

DROP TABLE IF EXISTS `network_segments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `network_segments` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `outlet_id` int(10) unsigned NOT NULL,
  `segment_name` varchar(100) NOT NULL,
  `network_address` varchar(18) NOT NULL,
  `subnet_mask` varchar(15) NOT NULL,
  `gateway_ip` varchar(45) DEFAULT NULL,
  `dns_primary` varchar(45) DEFAULT NULL,
  `dns_secondary` varchar(45) DEFAULT NULL,
  `vlan_id` smallint(5) unsigned DEFAULT NULL,
  `segment_type` enum('CAMERAS','MANAGEMENT','STORAGE','GUEST','IOT','SERVERS') NOT NULL,
  `security_level` enum('OPEN','RESTRICTED','SECURED','ISOLATED') DEFAULT 'SECURED',
  `max_devices` smallint(5) unsigned DEFAULT 254,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_outlet_segment` (`outlet_id`,`segment_name`),
  KEY `idx_segment_type` (`segment_type`),
  KEY `idx_security_level` (`security_level`),
  KEY `idx_vlan` (`vlan_id`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `network_segments_ibfk_1` FOREIGN KEY (`outlet_id`) REFERENCES `org_outlets` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `network_switches`
--

DROP TABLE IF EXISTS `network_switches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `network_switches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `outlet_id` int(10) unsigned NOT NULL,
  `segment_id` smallint(5) unsigned DEFAULT NULL,
  `switch_name` varchar(100) NOT NULL,
  `brand` varchar(50) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `mac_address` char(17) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `port_count` tinyint(3) unsigned NOT NULL,
  `poe_capable` tinyint(1) DEFAULT 0,
  `poe_budget_watts` smallint(5) unsigned DEFAULT NULL,
  `managed` tinyint(1) DEFAULT 1,
  `firmware_version` varchar(50) DEFAULT NULL,
  `location_description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_online` tinyint(1) DEFAULT 0,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_outlet_switch_name` (`outlet_id`,`switch_name`),
  KEY `segment_id` (`segment_id`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_poe_capable` (`poe_capable`),
  KEY `idx_online_status` (`is_online`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `network_switches_ibfk_1` FOREIGN KEY (`outlet_id`) REFERENCES `org_outlets` (`id`),
  CONSTRAINT `network_switches_ibfk_2` FOREIGN KEY (`segment_id`) REFERENCES `network_segments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notification_channels`
--

DROP TABLE IF EXISTS `notification_channels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_channels` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `channel_name` varchar(100) NOT NULL,
  `channel_type` enum('EMAIL','SMS','WEBHOOK','SLACK','TEAMS','DISCORD','PUSH','DESKTOP') NOT NULL,
  `configuration` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`configuration`)),
  `is_active` tinyint(1) DEFAULT 1,
  `test_successful` tinyint(1) DEFAULT 0,
  `last_test_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `channel_name` (`channel_name`),
  KEY `idx_channel_name` (`channel_name`),
  KEY `idx_channel_type` (`channel_type`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `org_companies`
--

DROP TABLE IF EXISTS `org_companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `org_companies` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `company_code` varchar(20) NOT NULL,
  `company_name` varchar(200) NOT NULL,
  `trading_name` varchar(200) DEFAULT NULL,
  `legal_name` varchar(200) DEFAULT NULL,
  `registration_number` varchar(50) DEFAULT NULL,
  `tax_number` varchar(50) DEFAULT NULL,
  `country_id` smallint(5) unsigned DEFAULT NULL,
  `timezone_id` smallint(5) unsigned DEFAULT NULL,
  `parent_company_id` smallint(5) unsigned DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_code` (`company_code`),
  KEY `timezone_id` (`timezone_id`),
  KEY `parent_company_id` (`parent_company_id`),
  KEY `idx_company_code` (`company_code`),
  KEY `idx_company_name` (`company_name`),
  KEY `idx_country` (`country_id`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `org_companies_ibfk_1` FOREIGN KEY (`country_id`) REFERENCES `sys_countries` (`id`),
  CONSTRAINT `org_companies_ibfk_2` FOREIGN KEY (`timezone_id`) REFERENCES `sys_timezones` (`id`),
  CONSTRAINT `org_companies_ibfk_3` FOREIGN KEY (`parent_company_id`) REFERENCES `org_companies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `org_locations`
--

DROP TABLE IF EXISTS `org_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `org_locations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` smallint(5) unsigned NOT NULL,
  `location_code` varchar(20) NOT NULL,
  `location_name` varchar(100) NOT NULL,
  `location_type` enum('HEADQUARTERS','BRANCH','WAREHOUSE','RETAIL','OFFICE') NOT NULL,
  `address_line_1` varchar(200) DEFAULT NULL,
  `address_line_2` varchar(200) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state_province` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country_id` smallint(5) unsigned DEFAULT NULL,
  `timezone_id` smallint(5) unsigned DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_company_location_code` (`company_id`,`location_code`),
  KEY `country_id` (`country_id`),
  KEY `timezone_id` (`timezone_id`),
  KEY `idx_location_type` (`location_type`),
  KEY `idx_coordinates` (`latitude`,`longitude`),
  KEY `idx_city` (`city`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `org_locations_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `org_companies` (`id`),
  CONSTRAINT `org_locations_ibfk_2` FOREIGN KEY (`country_id`) REFERENCES `sys_countries` (`id`),
  CONSTRAINT `org_locations_ibfk_3` FOREIGN KEY (`timezone_id`) REFERENCES `sys_timezones` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `org_outlets`
--

DROP TABLE IF EXISTS `org_outlets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `org_outlets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `location_id` int(10) unsigned NOT NULL,
  `outlet_code` varchar(20) NOT NULL,
  `outlet_name` varchar(100) NOT NULL,
  `outlet_type` enum('RETAIL_STORE','KIOSK','POPUP','ONLINE','WAREHOUSE') NOT NULL,
  `trading_hours` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`trading_hours`)),
  `contact_phone` varchar(20) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `total_floor_area` decimal(10,2) DEFAULT NULL,
  `retail_floor_area` decimal(10,2) DEFAULT NULL,
  `storage_area` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `opened_date` date DEFAULT NULL,
  `closed_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_location_outlet_code` (`location_id`,`outlet_code`),
  KEY `idx_outlet_type` (`outlet_type`),
  KEY `idx_dates` (`opened_date`,`closed_date`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `org_outlets_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `org_locations` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `org_zones`
--

DROP TABLE IF EXISTS `org_zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `org_zones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `outlet_id` int(10) unsigned NOT NULL,
  `zone_code` varchar(20) NOT NULL,
  `zone_name` varchar(100) NOT NULL,
  `zone_type` enum('ENTRANCE','SALES_FLOOR','CHECKOUT','STORAGE','OFFICE','SECURITY','EXTERIOR') NOT NULL,
  `floor_level` tinyint(4) DEFAULT 0,
  `area_square_meters` decimal(8,2) DEFAULT NULL,
  `x_coordinate` decimal(8,2) DEFAULT NULL,
  `y_coordinate` decimal(8,2) DEFAULT NULL,
  `width` decimal(8,2) DEFAULT NULL,
  `height` decimal(8,2) DEFAULT NULL,
  `is_secure_area` tinyint(1) DEFAULT 0,
  `is_customer_accessible` tinyint(1) DEFAULT 1,
  `is_monitored` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_outlet_zone_code` (`outlet_id`,`zone_code`),
  KEY `idx_zone_type` (`zone_type`),
  KEY `idx_coordinates` (`x_coordinate`,`y_coordinate`),
  KEY `idx_monitored` (`is_monitored`),
  KEY `idx_secure` (`is_secure_area`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `org_zones_ibfk_1` FOREIGN KEY (`outlet_id`) REFERENCES `org_outlets` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pending_transfer_config`
--

DROP TABLE IF EXISTS `pending_transfer_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pending_transfer_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` varchar(64) NOT NULL,
  `outlet_id` varchar(64) NOT NULL,
  `hold` tinyint(1) NOT NULL DEFAULT 0,
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_product_outlet` (`product_id`,`outlet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `people_contacts`
--

DROP TABLE IF EXISTS `people_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `people_contacts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int(10) unsigned NOT NULL,
  `contact_type` enum('EMAIL','PHONE_MOBILE','PHONE_HOME','PHONE_WORK','ADDRESS_HOME','ADDRESS_WORK') NOT NULL,
  `contact_value` varchar(500) NOT NULL,
  `country_id` smallint(5) unsigned DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `country_id` (`country_id`),
  KEY `idx_person_contact_type` (`person_id`,`contact_type`),
  KEY `idx_contact_value` (`contact_value`(100)),
  KEY `idx_primary` (`is_primary`),
  KEY `idx_verified` (`is_verified`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `people_contacts_ibfk_1` FOREIGN KEY (`person_id`) REFERENCES `people_persons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `people_contacts_ibfk_2` FOREIGN KEY (`country_id`) REFERENCES `sys_countries` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `people_identities`
--

DROP TABLE IF EXISTS `people_identities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `people_identities` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int(10) unsigned NOT NULL,
  `identity_type` enum('PASSPORT','DRIVERS_LICENSE','NATIONAL_ID','EMPLOYEE_ID','BIOMETRIC') NOT NULL,
  `identity_number` varchar(100) NOT NULL,
  `issuing_authority` varchar(200) DEFAULT NULL,
  `issuing_country_id` smallint(5) unsigned DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by_user_id` int(10) unsigned DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_person_identity_type` (`person_id`,`identity_type`),
  KEY `issuing_country_id` (`issuing_country_id`),
  KEY `idx_identity_number` (`identity_number`),
  KEY `idx_identity_type` (`identity_type`),
  KEY `idx_expiry_date` (`expiry_date`),
  KEY `idx_verified` (`is_verified`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `people_identities_ibfk_1` FOREIGN KEY (`person_id`) REFERENCES `people_persons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `people_identities_ibfk_2` FOREIGN KEY (`issuing_country_id`) REFERENCES `sys_countries` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `people_persons`
--

DROP TABLE IF EXISTS `people_persons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `people_persons` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `person_uuid` char(36) NOT NULL,
  `title` varchar(10) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_names` varchar(200) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `preferred_name` varchar(100) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('M','F','O','U') DEFAULT 'U',
  `nationality_country_id` smallint(5) unsigned DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `person_uuid` (`person_uuid`),
  KEY `idx_person_uuid` (`person_uuid`),
  KEY `idx_names` (`last_name`,`first_name`),
  KEY `idx_nationality` (`nationality_country_id`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `people_persons_ibfk_1` FOREIGN KEY (`nationality_country_id`) REFERENCES `sys_countries` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `performance_benchmarks`
--

DROP TABLE IF EXISTS `performance_benchmarks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `performance_benchmarks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `benchmark_name` varchar(100) NOT NULL,
  `benchmark_category` enum('DETECTION','TRACKING','ANALYTICS','STORAGE','NETWORK','SYSTEM') NOT NULL,
  `target_value` decimal(10,4) NOT NULL,
  `warning_threshold` decimal(10,4) DEFAULT NULL,
  `critical_threshold` decimal(10,4) DEFAULT NULL,
  `unit_of_measure` varchar(20) DEFAULT NULL,
  `measurement_interval_seconds` int(10) unsigned DEFAULT 300,
  `is_higher_better` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_benchmark_name` (`benchmark_name`),
  KEY `idx_category` (`benchmark_category`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stock_products_to_transfer`
--

DROP TABLE IF EXISTS `stock_products_to_transfer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_products_to_transfer` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_id` int(10) unsigned NOT NULL,
  `product_id` varchar(64) NOT NULL,
  `qty_to_transfer` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_transfer_id` (`transfer_id`),
  KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stock_transfers`
--

DROP TABLE IF EXISTS `stock_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_transfers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `date_created` datetime NOT NULL DEFAULT current_timestamp(),
  `transfer_created_by_user` int(10) unsigned DEFAULT NULL,
  `outlet_from` varchar(64) NOT NULL,
  `outlet_to` varchar(64) NOT NULL,
  `source_module` varchar(100) NOT NULL DEFAULT 'transfer_system_v4',
  PRIMARY KEY (`id`),
  KEY `idx_outlet_to` (`outlet_to`),
  KEY `ix_stock_transfers_outlet_from` (`outlet_from`),
  KEY `ix_stock_transfers_outlet_to` (`outlet_to`),
  KEY `ix_stock_transfers_date` (`date_created`),
  KEY `idx_stock_transfers_from` (`outlet_from`),
  KEY `idx_stock_transfers_to` (`outlet_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sys_countries`
--

DROP TABLE IF EXISTS `sys_countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sys_countries` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `iso2` char(2) NOT NULL,
  `iso3` char(3) NOT NULL,
  `name` varchar(100) NOT NULL,
  `calling_code` varchar(10) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `iso2` (`iso2`),
  UNIQUE KEY `iso3` (`iso3`),
  KEY `idx_iso2` (`iso2`),
  KEY `idx_iso3` (`iso3`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sys_data_types`
--

DROP TABLE IF EXISTS `sys_data_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sys_data_types` (
  `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `type_name` varchar(30) NOT NULL,
  `type_category` enum('PRIMITIVE','COMPOSITE','COLLECTION') NOT NULL,
  `validation_pattern` varchar(255) DEFAULT NULL,
  `min_length` smallint(5) unsigned DEFAULT NULL,
  `max_length` smallint(5) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `type_name` (`type_name`),
  KEY `idx_type_name` (`type_name`),
  KEY `idx_category` (`type_category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sys_timezones`
--

DROP TABLE IF EXISTS `sys_timezones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sys_timezones` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `timezone_name` varchar(50) NOT NULL,
  `utc_offset` varchar(10) NOT NULL,
  `description` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `timezone_name` (`timezone_name`),
  KEY `idx_timezone_name` (`timezone_name`),
  KEY `idx_utc_offset` (`utc_offset`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sys_units_measure`
--

DROP TABLE IF EXISTS `sys_units_measure`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sys_units_measure` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `unit_code` varchar(10) NOT NULL,
  `unit_name` varchar(50) NOT NULL,
  `unit_symbol` varchar(10) DEFAULT NULL,
  `category` enum('LENGTH','WEIGHT','VOLUME','TIME','FREQUENCY','DATA','ANGLE') NOT NULL,
  `base_unit_id` smallint(5) unsigned DEFAULT NULL,
  `conversion_factor` decimal(20,10) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unit_code` (`unit_code`),
  KEY `base_unit_id` (`base_unit_id`),
  KEY `idx_unit_code` (`unit_code`),
  KEY `idx_category` (`category`),
  CONSTRAINT `sys_units_measure_ibfk_1` FOREIGN KEY (`base_unit_id`) REFERENCES `sys_units_measure` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `system_event_log`
--

DROP TABLE IF EXISTS `system_event_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_event_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_type` varchar(100) NOT NULL,
  `summary` varchar(255) NOT NULL,
  `details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details_json`)),
  `severity` varchar(20) NOT NULL DEFAULT 'info',
  `source_module` varchar(100) NOT NULL DEFAULT 'transfer_system_v4',
  `actor_type` varchar(50) DEFAULT NULL,
  `actor_id` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `ix_system_event_log_ts` (`created_at`),
  KEY `ix_system_event_log_type` (`event_type`)
) ENGINE=InnoDB AUTO_INCREMENT=129 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `system_health_metrics`
--

DROP TABLE IF EXISTS `system_health_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_health_metrics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `system_id` int(10) unsigned NOT NULL,
  `metric_timestamp` timestamp(3) NOT NULL,
  `cpu_usage_percent` decimal(5,2) DEFAULT NULL,
  `memory_usage_percent` decimal(5,2) DEFAULT NULL,
  `memory_total_mb` int(10) unsigned DEFAULT NULL,
  `memory_available_mb` int(10) unsigned DEFAULT NULL,
  `disk_usage_percent` decimal(5,2) DEFAULT NULL,
  `disk_total_gb` decimal(10,2) DEFAULT NULL,
  `disk_available_gb` decimal(10,2) DEFAULT NULL,
  `network_rx_mbps` decimal(8,3) DEFAULT NULL,
  `network_tx_mbps` decimal(8,3) DEFAULT NULL,
  `network_rx_packets_per_sec` int(10) unsigned DEFAULT NULL,
  `network_tx_packets_per_sec` int(10) unsigned DEFAULT NULL,
  `temperature_celsius` decimal(5,2) DEFAULT NULL,
  `fan_speed_rpm` smallint(5) unsigned DEFAULT NULL,
  `power_consumption_watts` decimal(6,2) DEFAULT NULL,
  `uptime_seconds` bigint(20) unsigned DEFAULT NULL,
  `ping_response_ms` smallint(5) unsigned DEFAULT NULL,
  `packet_loss_percent` decimal(5,2) DEFAULT NULL,
  `stream_fps_actual` decimal(5,2) DEFAULT NULL,
  `stream_bitrate_kbps` int(10) unsigned DEFAULT NULL,
  `stream_frame_drops` int(10) unsigned DEFAULT 0,
  `ai_inference_fps` decimal(5,2) DEFAULT NULL,
  `ai_processing_latency_ms` decimal(8,3) DEFAULT NULL,
  `gpu_usage_percent` decimal(5,2) DEFAULT NULL,
  `gpu_memory_usage_percent` decimal(5,2) DEFAULT NULL,
  `error_count` smallint(5) unsigned DEFAULT 0,
  `warning_count` smallint(5) unsigned DEFAULT 0,
  `alert_count` smallint(5) unsigned DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_system_timestamp` (`system_id`,`metric_timestamp`),
  KEY `idx_timestamp` (`metric_timestamp`),
  KEY `idx_cpu_usage` (`cpu_usage_percent`),
  KEY `idx_memory_usage` (`memory_usage_percent`),
  KEY `idx_temperature` (`temperature_celsius`),
  KEY `idx_ping_response` (`ping_response_ms`),
  KEY `idx_ai_performance` (`ai_inference_fps`,`ai_processing_latency_ms`),
  CONSTRAINT `system_health_metrics_ibfk_1` FOREIGN KEY (`system_id`) REFERENCES `cam_systems` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transfer_events`
--

DROP TABLE IF EXISTS `transfer_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transfer_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ts` datetime NOT NULL DEFAULT current_timestamp(),
  `run_id` varchar(64) DEFAULT NULL,
  `event_type` varchar(64) NOT NULL,
  `product_id` varchar(64) DEFAULT NULL,
  `outlet_id` varchar(64) DEFAULT NULL,
  `message` varchar(255) DEFAULT NULL,
  `data_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data_json`)),
  PRIMARY KEY (`id`),
  KEY `idx_ts` (`ts`),
  KEY `ix_transfer_events_run` (`run_id`),
  KEY `ix_transfer_events_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `role` enum('STAFF','MANAGER','ADMIN') DEFAULT 'STAFF',
  `outlet_id` int(11) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_outlet_role` (`outlet_id`,`role`),
  KEY `idx_active` (`active`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Staff and user accounts for security tracking';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `v_effective_pack_rules`
--

DROP TABLE IF EXISTS `v_effective_pack_rules`;
/*!50001 DROP VIEW IF EXISTS `v_effective_pack_rules`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_effective_pack_rules` AS SELECT
 1 AS `product_id`,
  1 AS `effective_pack_size`,
  1 AS `effective_outer_multiple`,
  1 AS `effective_enforce_outer`,
  1 AS `effective_rounding_mode` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_recent_sales_12w`
--

DROP TABLE IF EXISTS `v_recent_sales_12w`;
/*!50001 DROP VIEW IF EXISTS `v_recent_sales_12w`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_recent_sales_12w` AS SELECT
 1 AS `year_num`,
  1 AS `week_num`,
  1 AS `product_id`,
  1 AS `outlet_id`,
  1 AS `total_quantity` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_transfer_eligible_products`
--

DROP TABLE IF EXISTS `v_transfer_eligible_products`;
/*!50001 DROP VIEW IF EXISTS `v_transfer_eligible_products`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_transfer_eligible_products` AS SELECT
 1 AS `product_id`,
  1 AS `outlet_id`,
  1 AS `name`,
  1 AS `quantity`,
  1 AS `inventory_level`,
  1 AS `score` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `vend_customers`
--

DROP TABLE IF EXISTS `vend_customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vend_customer_id` varchar(50) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `customer_group` varchar(50) DEFAULT NULL,
  `total_sales` decimal(10,2) DEFAULT 0.00,
  `total_visits` int(11) DEFAULT 0,
  `last_visit` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `vend_customer_id` (`vend_customer_id`),
  KEY `idx_email` (`email`),
  KEY `idx_phone` (`phone`),
  KEY `idx_customer_group` (`customer_group`),
  KEY `idx_last_visit` (`last_visit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Customer data for behavior analysis';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vend_inventory`
--

DROP TABLE IF EXISTS `vend_inventory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_inventory` (
  `product_id` varchar(64) NOT NULL,
  `outlet_id` varchar(64) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`product_id`,`outlet_id`),
  KEY `idx_outlet` (`outlet_id`),
  KEY `idx_vend_inventory_outlet_product` (`outlet_id`,`product_id`),
  KEY `idx_vend_inventory_product` (`product_id`),
  KEY `ix_vend_inventory_outlet_product` (`outlet_id`,`product_id`),
  KEY `ix_vend_inventory_product_outlet` (`product_id`,`outlet_id`),
  KEY `idx_vend_inventory_outlet_qty` (`outlet_id`,`quantity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vend_outlets`
--

DROP TABLE IF EXISTS `vend_outlets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_outlets` (
  `id` varchar(64) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vend_outlets_open_hours`
--

DROP TABLE IF EXISTS `vend_outlets_open_hours`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_outlets_open_hours` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `outlet_id` int(11) NOT NULL,
  `monday_open` time DEFAULT '10:00:00',
  `monday_close` time DEFAULT '19:00:00',
  `tuesday_open` time DEFAULT '10:00:00',
  `tuesday_close` time DEFAULT '19:00:00',
  `wednesday_open` time DEFAULT '10:00:00',
  `wednesday_close` time DEFAULT '19:00:00',
  `thursday_open` time DEFAULT '10:00:00',
  `thursday_close` time DEFAULT '19:00:00',
  `friday_open` time DEFAULT '10:00:00',
  `friday_close` time DEFAULT '19:00:00',
  `saturday_open` time DEFAULT '10:00:00',
  `saturday_close` time DEFAULT '19:00:00',
  `sunday_open` time DEFAULT '10:00:00',
  `sunday_close` time DEFAULT '19:00:00',
  `timezone` varchar(50) DEFAULT 'Pacific/Auckland',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `outlet_id` (`outlet_id`),
  KEY `idx_outlet_id` (`outlet_id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Store operating hours for after-hours detection';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vend_products`
--

DROP TABLE IF EXISTS `vend_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_products` (
  `id` varchar(64) NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vend_sales`
--

DROP TABLE IF EXISTS `vend_sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `increment_id` varchar(50) NOT NULL,
  `sale_date` datetime NOT NULL,
  `outlet_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `total_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('COMPLETED','VOIDED','CANCELLED','PENDING') DEFAULT 'COMPLETED',
  `payment_method` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `vend_id` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `increment_id` (`increment_id`),
  KEY `idx_sale_date` (`sale_date`),
  KEY `idx_outlet_user` (`outlet_id`,`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_outlet_date` (`outlet_id`,`sale_date`),
  KEY `idx_increment_id` (`increment_id`),
  KEY `idx_total_price` (`total_price`)
) ENGINE=InnoDB AUTO_INCREMENT=535 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Core sales transaction data for AI fraud detection';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vend_store_config`
--

DROP TABLE IF EXISTS `vend_store_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_store_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `outlet_id` int(11) NOT NULL,
  `high_value_threshold` decimal(10,2) DEFAULT 200.00,
  `alert_sensitivity` decimal(5,2) DEFAULT 70.00,
  `security_level` enum('STANDARD','HIGH','MAXIMUM') DEFAULT 'STANDARD',
  `camera_count` int(11) DEFAULT 3,
  `monitoring_enabled` tinyint(1) DEFAULT 1,
  `after_hours_alerts` tinyint(1) DEFAULT 1,
  `fraud_detection_enabled` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_outlet` (`outlet_id`),
  KEY `idx_security_level` (`security_level`),
  KEY `idx_monitoring_enabled` (`monitoring_enabled`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Final view structure for view `v_effective_pack_rules`
--

/*!50001 DROP VIEW IF EXISTS `v_effective_pack_rules`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`jcepnzzkmj`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_effective_pack_rules` AS select '' AS `product_id`,1 AS `effective_pack_size`,1 AS `effective_outer_multiple`,0 AS `effective_enforce_outer`,'floor' AS `effective_rounding_mode` from DUAL  where 1 = 0 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_recent_sales_12w`
--

/*!50001 DROP VIEW IF EXISTS `v_recent_sales_12w`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`jcepnzzkmj`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_recent_sales_12w` AS select 0 AS `year_num`,0 AS `week_num`,'' AS `product_id`,'' AS `outlet_id`,0 AS `total_quantity` from DUAL  where 1 = 0 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_transfer_eligible_products`
--

/*!50001 DROP VIEW IF EXISTS `v_transfer_eligible_products`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`jcepnzzkmj`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_transfer_eligible_products` AS select `p`.`id` AS `product_id`,`i`.`outlet_id` AS `outlet_id`,`p`.`name` AS `name`,`i`.`quantity` AS `quantity`,case when `i`.`quantity` > 50 then 'high' when `i`.`quantity` > 10 then 'normal' else 'low' end AS `inventory_level`,1.0 AS `score` from (`vend_products` `p` join `vend_inventory` `i` on(`p`.`id` = `i`.`product_id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-09-09 11:34:49
