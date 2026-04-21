ALTER TABLE `lobbies`
  ADD COLUMN `timer_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `status`,
  ADD COLUMN `timer_seconds` INT        NOT NULL DEFAULT 60 AFTER `timer_enabled`;
