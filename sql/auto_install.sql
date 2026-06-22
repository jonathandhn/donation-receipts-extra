CREATE TABLE IF NOT EXISTS `civicrm_donrecextra_receipt_audit` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `donrec_receipt_id` int unsigned NOT NULL,
  `receipt_number` varchar(128) NOT NULL,
  `contact_id` int unsigned NOT NULL,
  `beneficiary_type` varchar(32) NOT NULL DEFAULT 'Unknown',
  `receipt_type` varchar(16) NOT NULL,
  `current_status` varchar(24) NOT NULL,
  `issued_at` datetime NOT NULL,
  `period_from` datetime DEFAULT NULL,
  `period_to` datetime DEFAULT NULL,
  `total_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `non_deductible_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `currency` char(3) NOT NULL DEFAULT 'EUR',
  `original_file_id` int unsigned DEFAULT NULL,
  `pdf_sha256` char(64) DEFAULT NULL,
  `first_seen_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UI_donrecextra_donrec_receipt` (`donrec_receipt_id`),
  KEY `IX_donrecextra_receipt_number` (`receipt_number`),
  KEY `IX_donrecextra_receipt_contact` (`contact_id`),
  KEY `IX_donrecextra_receipt_issued` (`issued_at`),
  KEY `IX_donrecextra_receipt_status` (`current_status`),
  KEY `IX_donrecextra_receipt_beneficiary` (`beneficiary_type`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `civicrm_donrecextra_receipt_item_audit` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `receipt_audit_id` int unsigned NOT NULL,
  `donrec_receipt_item_id` int unsigned NOT NULL,
  `contribution_id` int unsigned NOT NULL,
  `receive_date` datetime DEFAULT NULL,
  `total_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `non_deductible_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `currency` char(3) NOT NULL DEFAULT 'EUR',
  `financial_type_id` int unsigned DEFAULT NULL,
  `contribution_hash` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UI_donrecextra_receipt_item` (`receipt_audit_id`, `donrec_receipt_item_id`),
  KEY `IX_donrecextra_item_contribution` (`contribution_id`),
  CONSTRAINT `FK_donrecextra_item_receipt` FOREIGN KEY (`receipt_audit_id`)
    REFERENCES `civicrm_donrecextra_receipt_audit` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `civicrm_donrecextra_receipt_event` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `receipt_audit_id` int unsigned NOT NULL,
  `event_type` varchar(24) NOT NULL,
  `occurred_at` datetime NOT NULL,
  `recorded_at` datetime NOT NULL,
  `time_precision` varchar(16) NOT NULL DEFAULT 'exact',
  `actor_contact_id` int unsigned DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `source` varchar(64) NOT NULL,
  `metadata` longtext DEFAULT NULL,
  `event_hash` char(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UI_donrecextra_receipt_event` (`receipt_audit_id`, `event_type`),
  KEY `IX_donrecextra_event_occurred` (`occurred_at`),
  KEY `IX_donrecextra_event_type` (`event_type`),
  CONSTRAINT `FK_donrecextra_event_receipt` FOREIGN KEY (`receipt_audit_id`)
    REFERENCES `civicrm_donrecextra_receipt_audit` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `civicrm_donrecextra_audit_report` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `as_of` datetime NOT NULL,
  `granularity` varchar(16) NOT NULL,
  `metrics_json` longtext NOT NULL,
  `selection_hash` char(64) NOT NULL,
  `created_at` datetime NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IX_donrecextra_report_period` (`period_from`, `period_to`),
  KEY `IX_donrecextra_report_asof` (`as_of`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
