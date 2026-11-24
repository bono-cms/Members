-- ---------------------------------------------------------------------
-- Members (base table — chat tables reference it)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `bono_module_members`;
CREATE TABLE `bono_module_members` (
  `id`                    INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `email`                 VARCHAR(254) NOT NULL,
  `password_hash`         VARCHAR(254) NOT NULL,
  `password_changed_at`   DATETIME NULL,
  `company`               VARCHAR(120) NULL,
  `phone`                 VARCHAR(30) NULL,
  `address`               TEXT NULL,
  `country`               CHAR(2) NULL,
  `avatar`                VARCHAR(255) NULL,
  `subscriber`            BOOLEAN DEFAULT NULL,
  `activation_key`        VARCHAR(100) NULL,
  `confirmed`             BOOLEAN DEFAULT NULL,
  `jwt_version`           INT NOT NULL DEFAULT 0,
  `last_seen_at`          DATETIME NULL,
  `is_banned`             TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`            DATETIME NOT NULL,
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Member translations
-- ---------------------------------------------------------------------
CREATE TABLE `bono_module_members_translations` (
  `id`        INT NOT NULL,
  `lang_id`   INT NOT NULL,
  `name`      VARCHAR(120) NULL,
  `title`     VARCHAR(120) NULL,
  `bio`       TEXT NULL,
  PRIMARY KEY (`id`, `lang_id`),
  KEY `idx_lang_id` (`lang_id`),
  CONSTRAINT `fk_members_translations_member`
    FOREIGN KEY (`id`) REFERENCES `bono_module_members` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Profile change submissions (existing moderation pipeline)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `bono_module_members_changes`;
CREATE TABLE `bono_module_members_changes` (
  `id`                INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `member_id`         INT NOT NULL,
  `payload`           LONGTEXT NOT NULL,
  `status`            ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_at`       DATETIME NULL,
  `rejection_reason`  VARCHAR(500) NULL,
  `created_at`        DATETIME NOT NULL,
  KEY `idx_member_status` (`member_id`, `status`),
  KEY `idx_status_created` (`status`, `created_at`),
  CONSTRAINT `fk_members_changes_member`
    FOREIGN KEY (`member_id`) REFERENCES `bono_module_members` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Conversations (direct + group)
--
-- Created WITHOUT the last_message_id FK to avoid a circular
-- dependency with messages. The pointer is maintained by the
-- application, which already has to recompute it on soft delete.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `bono_module_members_conversations`;
CREATE TABLE `bono_module_members_conversations` (
  `id`                INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `type`              ENUM('direct','group') NOT NULL DEFAULT 'direct',
  `title`             VARCHAR(120) NULL,
  `direct_key`        VARCHAR(40) NULL,
  `created_by`        INT NULL,
  `last_message_id`   BIGINT NULL,
  `last_message_at`   DATETIME NULL,
  `message_count`     INT NOT NULL DEFAULT 0,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_direct_key` (`direct_key`),
  KEY `idx_last_message_at` (`last_message_at`),
  KEY `idx_last_message_id` (`last_message_id`),
  CONSTRAINT `fk_conv_creator`
    FOREIGN KEY (`created_by`) REFERENCES `bono_module_members` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Conversation participants (N per conversation)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `bono_module_members_conversation_participants`;
CREATE TABLE `bono_module_members_conversation_participants` (
  `conversation_id`          INT NOT NULL,
  `member_id`                INT NOT NULL,
  `role`                     ENUM('member','admin') NOT NULL DEFAULT 'member',
  `joined_at`                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_read_message_id`     BIGINT NULL,
  `last_read_at`             DATETIME NULL,
  `muted_until`              DATETIME NULL,
  `is_archived`              TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`conversation_id`, `member_id`),
  KEY `idx_member_archive` (`member_id`, `is_archived`),
  CONSTRAINT `fk_part_conv`
    FOREIGN KEY (`conversation_id`) REFERENCES `bono_module_members_conversations` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_part_member`
    FOREIGN KEY (`member_id`) REFERENCES `bono_module_members` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Messages
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `bono_module_members_messages`;
CREATE TABLE `bono_module_members_messages` (
  `id`               BIGINT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `conversation_id`  INT NOT NULL,
  `sender_id`        INT NULL,
  `type`             ENUM('text','image','file','system') NOT NULL DEFAULT 'text',
  `body`             TEXT NOT NULL,
  `client_id`        VARCHAR(36) NULL,
  `reply_to_id`      BIGINT NULL,
  `edited_at`        DATETIME NULL,
  `deleted_at`       DATETIME NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_client` (`conversation_id`, `client_id`),
  KEY `idx_conv_id` (`conversation_id`, `id`),
  KEY `idx_conv_created` (`conversation_id`, `created_at`),
  KEY `idx_sender` (`sender_id`),
  CONSTRAINT `fk_msg_conv`
    FOREIGN KEY (`conversation_id`) REFERENCES `bono_module_members_conversations` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_msg_sender`
    FOREIGN KEY (`sender_id`) REFERENCES `bono_module_members` (`id`)
    ON DELETE SET NULL,
  CONSTRAINT `fk_msg_reply`
    FOREIGN KEY (`reply_to_id`) REFERENCES `bono_module_members_messages` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- Tag groups
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `bono_module_members_tag_groups`;
CREATE TABLE `bono_module_members_tag_groups` (
  `id`         INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `code`       VARCHAR(64) NOT NULL COMMENT 'Stable machine key, e.g. "interests"',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tag group translations
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `bono_module_members_tag_groups_translations`;
CREATE TABLE `bono_module_members_tag_groups_translations` (
  `id`          INT NOT NULL,
  `lang_id`     INT NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) NULL,
  PRIMARY KEY (`id`, `lang_id`),
  KEY `idx_lang_id` (`lang_id`),
  CONSTRAINT `fk_tgrp_tr_group`
    FOREIGN KEY (`id`) REFERENCES `bono_module_members_tag_groups` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tags
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `bono_module_members_tags`;
CREATE TABLE `bono_module_members_tags` (
  `id`         INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `group_id`   INT NOT NULL,
  `code`       VARCHAR(64) NULL COMMENT 'Optional stable key, unique within group',
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  KEY `idx_group_sort` (`group_id`, `sort_order`),
  UNIQUE KEY `uq_group_code` (`group_id`, `code`),
  CONSTRAINT `fk_tag_group`
    FOREIGN KEY (`group_id`) REFERENCES `bono_module_members_tag_groups` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tag translations
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `bono_module_members_tags_translations`;
CREATE TABLE `bono_module_members_tags_translations` (
  `id`      INT NOT NULL,
  `lang_id` INT NOT NULL,
  `label`   VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`, `lang_id`),
  KEY `idx_lang_id` (`lang_id`),
  CONSTRAINT `fk_tag_tr_tag`
    FOREIGN KEY (`id`) REFERENCES `bono_module_members_tags` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Member + tag (what the member picked, junction table)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `bono_module_members_member_tags`;
CREATE TABLE `bono_module_members_member_tags` (
  `member_id` INT NOT NULL,
  `tag_id`    INT NOT NULL,
  PRIMARY KEY (`member_id`, `tag_id`),
  KEY `idx_tag` (`tag_id`),
  CONSTRAINT `fk_mt_member`
    FOREIGN KEY (`member_id`) REFERENCES `bono_module_members` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_mt_tag`
    FOREIGN KEY (`tag_id`) REFERENCES `bono_module_members_tags` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Events (created by members or admins)
--
-- Text content lives in bono_module_members_events_translations.
-- creator_id is NULL for events created by admins on behalf of the
-- association. ON DELETE SET NULL keeps the event in the catalog when
-- its author is removed (it just becomes "authorless").
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `bono_module_members_events`;
CREATE TABLE `bono_module_members_events` (
    `id`                INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    `creator_id`        INT NULL,
    `link`              VARCHAR(500) NULL,
    `location_name`     VARCHAR(255) NOT NULL,
    `latitude`          DECIMAL(10,8) NULL,
    `longitude`         DECIMAL(11,8) NULL,
    `location_source`   ENUM('manual','url','none') NOT NULL DEFAULT 'none',
    `starts_at`         DATETIME NOT NULL,
    `ends_at`           DATETIME NULL,
    `status`            ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `rejection_reason`  VARCHAR(500) NULL,
    `views_count`       INT NOT NULL DEFAULT 0,
    `clicks_count`      INT NOT NULL DEFAULT 0,
    `created_at`        DATETIME NOT NULL,
    `updated_at`        DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_creator` (`creator_id`),
    KEY `idx_status_starts` (`status`, `starts_at`),
    KEY `idx_starts` (`starts_at`),
    CONSTRAINT `fk_event_creator`
        FOREIGN KEY (`creator_id`) REFERENCES `bono_module_members` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Event translations (title, description, organizer)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `bono_module_members_events_translations`;
CREATE TABLE `bono_module_members_events_translations` (
    `id`          INT NOT NULL,
    `lang_id`     INT NOT NULL,
    `title`       VARCHAR(200) NOT NULL,
    `description` TEXT NULL,
    `organizer`   VARCHAR(200) NULL,
    PRIMARY KEY (`id`, `lang_id`),
    KEY `idx_lang_id` (`lang_id`),
    CONSTRAINT `fk_event_tr_event`
        FOREIGN KEY (`id`) REFERENCES `bono_module_members_events` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Event analytics (views + clicks)
--
-- One row per tracked event. The ip_hash is SHA1(ip + salt) so we can
-- deduplicate repeated hits from the same visitor without storing PII.
-- ON DELETE CASCADE drops analytics when the event is removed — there
-- is no reason to keep orphaned stats.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `bono_module_members_event_analytics`;
CREATE TABLE `bono_module_members_event_analytics` (
  `id`          BIGINT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `event_id`    INT NOT NULL,
  `member_id`   INT NULL,
  `type`        ENUM('view','click') NOT NULL,
  `source`      ENUM('card','direct','admin','api') NOT NULL DEFAULT 'card',
  `ip_hash`     VARCHAR(40) NULL,
  `user_agent`  VARCHAR(255) NULL,
  `created_at`  DATETIME NOT NULL,

  KEY `idx_event_type` (`event_id`, `type`),
  KEY `idx_event_created` (`event_id`, `created_at`),
  KEY `idx_member` (`member_id`),
  CONSTRAINT `fk_analytics_event`
    FOREIGN KEY (`event_id`) REFERENCES `bono_module_members_events` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_analytics_member`
    FOREIGN KEY (`member_id`) REFERENCES `bono_module_members` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;