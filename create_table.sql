CREATE TABLE IF NOT EXISTS `senior_caregiver_assignments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `senior_id` int NOT NULL,
  `caregiver_id` int NOT NULL,
  `assigned_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `senior_id` (`senior_id`),
  KEY `caregiver_id` (`caregiver_id`),
  CONSTRAINT `fk_assignment_senior` FOREIGN KEY (`senior_id`) REFERENCES `seniorcitizens` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignment_caregiver` FOREIGN KEY (`caregiver_id`) REFERENCES `userdetails` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;