-- Final Schema for SongGame Rewrite

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `songs`;
DROP TABLE IF EXISTS `sessions`;

CREATE TABLE `sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `game_code` varchar(10) NOT NULL,
  `is_host_active` tinyint(1) DEFAULT 0,
  `playlist_id` varchar(255) DEFAULT NULL,
  `autoplay` tinyint(1) DEFAULT 1,
  `pause_duration` int(11) DEFAULT 5,
  `play_duration` int(11) DEFAULT 30,
  `current_video_id` varchar(20) DEFAULT NULL,
  `phase` enum('idle','playing','paused') NOT NULL DEFAULT 'idle',
  `started_at` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `game_code` (`game_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `songs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `video_id` varchar(20) NOT NULL,
  `start_offset` int(11) NOT NULL DEFAULT 0,
  `title` varchar(255) DEFAULT NULL,
  `was_viewed` tinyint(1) DEFAULT 0,
  `embeddable` tinyint(1) NOT NULL DEFAULT 1,
  `playlist_added` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_songs_session` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE IF NOT EXISTS `app_config` (
  `key` varchar(64) NOT NULL,
  `value` text DEFAULT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Keeps Migrations.php from re-running steps already covered by this file.
INSERT INTO `app_config` (`key`, `value`) VALUES ('schema_version', '1')
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
