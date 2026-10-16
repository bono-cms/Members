DROP TABLE IF EXISTS `bono_module_members`;
CREATE TABLE `bono_module_members` (
  `id` INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `email` VARCHAR(254) NOT NULL,
  `login` VARCHAR(254) NOT NULL,
  `password_hash` VARCHAR(254) NOT NULL,
  `password_changed_at` DATETIME,
  `name` VARCHAR(120) NULL,
  `phone` VARCHAR(30) NULL,
  `address` TEXT NULL,
  `country` CHAR(2) NULL,
  `avatar` VARCHAR(255) NULL,
  `subscriber` BOOLEAN DEFAULT NULL,
  `activation_key` VARCHAR(100) NULL,
  `confirmed` BOOLEAN DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_email` (`email`),
  UNIQUE KEY `uq_login` (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `bono_module_members_translations`;
CREATE TABLE `bono_module_members_translations` (
  `id` INT NOT NULL,
  `lang_id` INT NOT NULL,
  `title` VARCHAR(120) NULL,
  `company` VARCHAR(120) NULL,
  `bio` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;