<?php
/**
 * Tiny forward-only schema migrator.
 *
 * schema.sql only runs when the database is created from scratch, so existing
 * installations need new columns applied on the fly. The applied version is
 * remembered in app_config, which costs one cheap SELECT per request.
 */
class Migrations {
    /** Bump this and add a matching entry in steps() when the schema changes. */
    const TARGET_VERSION = 1;

    private static $done = false;

    public static function run(Database $db) {
        if (self::$done) {
            return;
        }
        self::$done = true;

        try {
            $db->execute(
                "CREATE TABLE IF NOT EXISTS `app_config` (
                    `key` varchar(64) NOT NULL,
                    `value` text DEFAULT NULL,
                    PRIMARY KEY (`key`)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $row = $db->fetchOne("SELECT `value` FROM app_config WHERE `key` = 'schema_version'");
            $current = $row ? (int)$row['value'] : 0;
            if ($current >= self::TARGET_VERSION) {
                return;
            }

            foreach (self::steps() as $version => $step) {
                if ($version > $current) {
                    $step($db);
                }
            }

            $db->execute(
                "INSERT INTO app_config (`key`, `value`) VALUES ('schema_version', ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
                [(string)self::TARGET_VERSION]
            );
        } catch (Exception $e) {
            // A migration failure must not take the whole app down — the pages
            // fall back to sensible defaults when a column is missing.
            goose_log('Migration failed', $e->getMessage());
        }
    }

    /** version => callable(Database): void — each step must be idempotent. */
    private static function steps() {
        return [
            1 => function (Database $db) {
                self::addColumn($db, 'songs', 'embeddable',
                    "ALTER TABLE `songs` ADD COLUMN `embeddable` tinyint(1) NOT NULL DEFAULT 1");
            },
        ];
    }

    private static function addColumn(Database $db, $table, $column, $sql) {
        $exists = $db->fetchOne(
            "SELECT 1 AS present FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$table, $column]
        );
        if (!$exists) {
            $db->execute($sql);
        }
    }
}
