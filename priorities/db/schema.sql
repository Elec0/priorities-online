CREATE TABLE IF NOT EXISTS `lobbies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(6) NOT NULL UNIQUE,
  `host_token` VARCHAR(64) NOT NULL,
  `status` ENUM('waiting','playing','finished') NOT NULL DEFAULT 'waiting',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `players` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `lobby_id` INT NOT NULL,
  `name` VARCHAR(50) NOT NULL,
  `session_token` VARCHAR(64) NOT NULL UNIQUE,
  `is_host` TINYINT(1) NOT NULL DEFAULT 0,
  `turn_order` INT NOT NULL DEFAULT 0,
  `status` ENUM('active','kicked') NOT NULL DEFAULT 'active',
  `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`lobby_id`) REFERENCES `lobbies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `games` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `lobby_id` INT NOT NULL UNIQUE,
  `current_round` INT NOT NULL DEFAULT 1,
  `target_player_index` INT NOT NULL DEFAULT 0,
  `final_decider_index` INT NOT NULL DEFAULT 1,
  `status` ENUM('active','players_win','game_wins','draw') NOT NULL DEFAULT 'active',
  `player_letters` JSON NOT NULL,
  `game_letters` JSON NOT NULL,
  `deck_order` JSON NOT NULL,
  `state_version` INT NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`lobby_id`) REFERENCES `lobbies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `rounds` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `game_id` INT NOT NULL,
  `round_number` INT NOT NULL,
  `target_player_id` INT NOT NULL,
  `final_decider_id` INT NOT NULL,
  `card_ids` JSON NOT NULL,
  `target_ranking` JSON DEFAULT NULL,
  `group_ranking` JSON DEFAULT NULL,
  `result` JSON DEFAULT NULL,
  `status` ENUM('ranking','guessing','revealed','skipped') NOT NULL DEFAULT 'ranking',
  `ranking_deadline` DATETIME DEFAULT NULL,
  FOREIGN KEY (`game_id`) REFERENCES `games`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`target_player_id`) REFERENCES `players`(`id`),
  FOREIGN KEY (`final_decider_id`) REFERENCES `players`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `lobby_id` INT NOT NULL,
  `player_id` INT DEFAULT NULL,
  `message` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`lobby_id`) REFERENCES `lobbies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cards` (
  `id` INT PRIMARY KEY,
  `content` VARCHAR(200) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `emoji` VARCHAR(10) NOT NULL,
  `letter` CHAR(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
