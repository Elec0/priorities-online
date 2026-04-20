-- Priorities Online — Database Schema
-- Engine: InnoDB, Charset: utf8mb4

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── lobbies ──────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `lobbies` (
  `id`         INT          NOT NULL AUTO_INCREMENT,
  `code`       VARCHAR(6)   NOT NULL,
  `host_token` VARCHAR(64)  NOT NULL,
  `status`     ENUM('waiting','playing','finished') NOT NULL DEFAULT 'waiting',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_code` (`code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── players ───────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `players` (
  `id`            INT          NOT NULL AUTO_INCREMENT,
  `lobby_id`      INT          NOT NULL,
  `name`          VARCHAR(50)  NOT NULL,
  `session_token` VARCHAR(64)  NOT NULL,
  `is_host`       TINYINT(1)   NOT NULL DEFAULT 0,
  `turn_order`    INT          NOT NULL DEFAULT 0,
  `status`        ENUM('active','kicked') NOT NULL DEFAULT 'active',
  `joined_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_session_token` (`session_token`),
  KEY `fk_players_lobby` (`lobby_id`),
  CONSTRAINT `fk_players_lobby` FOREIGN KEY (`lobby_id`) REFERENCES `lobbies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── games ─────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `games` (
  `id`                   INT          NOT NULL AUTO_INCREMENT,
  `lobby_id`             INT          NOT NULL,
  `current_round`        INT          NOT NULL DEFAULT 1,
  `target_player_index`  INT          NOT NULL DEFAULT 0,
  `final_decider_index`  INT          NOT NULL DEFAULT 1,
  `status`               ENUM('active','players_win','game_wins','draw') NOT NULL DEFAULT 'active',
  `player_letters`       JSON         NOT NULL,
  `game_letters`         JSON         NOT NULL,
  `deck_order`           JSON         NOT NULL,
  `state_version`        INT          NOT NULL DEFAULT 1,
  `created_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_games_lobby` (`lobby_id`),
  CONSTRAINT `fk_games_lobby` FOREIGN KEY (`lobby_id`) REFERENCES `lobbies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── rounds ────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `rounds` (
  `id`               INT      NOT NULL AUTO_INCREMENT,
  `game_id`          INT      NOT NULL,
  `round_number`     INT      NOT NULL,
  `target_player_id` INT      NOT NULL,
  `final_decider_id` INT      NOT NULL,
  `card_ids`         JSON     NOT NULL,
  `target_ranking`   JSON     DEFAULT NULL,
  `group_ranking`    JSON     DEFAULT NULL,
  `result`           JSON     DEFAULT NULL,
  `status`           ENUM('ranking','guessing','revealed','skipped') NOT NULL DEFAULT 'ranking',
  `ranking_deadline` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_rounds_game` (`game_id`),
  KEY `idx_rounds_status_deadline` (`status`, `ranking_deadline`),
  CONSTRAINT `fk_rounds_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rounds_target` FOREIGN KEY (`target_player_id`) REFERENCES `players` (`id`),
  CONSTRAINT `fk_rounds_decider` FOREIGN KEY (`final_decider_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── chat_messages ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id`         INT      NOT NULL AUTO_INCREMENT,
  `lobby_id`   INT      NOT NULL,
  `player_id`  INT      DEFAULT NULL,
  `message`    TEXT     NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_chat_lobby` (`lobby_id`),
  KEY `idx_chat_created_at` (`created_at`),
  CONSTRAINT `fk_chat_lobby` FOREIGN KEY (`lobby_id`) REFERENCES `lobbies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chat_player` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── cards ─────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `cards` (
  `id`       INT          NOT NULL,
  `content`  VARCHAR(200) NOT NULL,
  `category` VARCHAR(50)  NOT NULL,
  `emoji`    VARCHAR(10)  NOT NULL,
  `letter`   CHAR(1)      NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
