<?php
// Database connection
$servername = "localhost";
$username = "root"; // default MySQL username
$password = ""; // default MySQL password (empty for XAMPP)
$dbname = "scoreboard"; // Replace with your actual database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function ensureColumnExists($conn, $table, $column, $definition) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS column_exists
                            FROM INFORMATION_SCHEMA.COLUMNS
                            WHERE TABLE_SCHEMA = DATABASE()
                              AND TABLE_NAME = ?
                              AND COLUMN_NAME = ?");
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ((int) $result['column_exists'] === 0) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

function columnExists($conn, $table, $column) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS column_exists
                            FROM INFORMATION_SCHEMA.COLUMNS
                            WHERE TABLE_SCHEMA = DATABASE()
                              AND TABLE_NAME = ?
                              AND COLUMN_NAME = ?");
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) $result['column_exists'] > 0;
}

function foreignKeyExists($conn, $table, $constraint) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS constraint_exists
                            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                            WHERE CONSTRAINT_SCHEMA = DATABASE()
                              AND TABLE_NAME = ?
                              AND CONSTRAINT_NAME = ?
                              AND CONSTRAINT_TYPE = 'FOREIGN KEY'");
    $stmt->bind_param("ss", $table, $constraint);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) $result['constraint_exists'] > 0;
}

function indexExists($conn, $table, $index) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS index_exists
                            FROM INFORMATION_SCHEMA.STATISTICS
                            WHERE TABLE_SCHEMA = DATABASE()
                              AND TABLE_NAME = ?
                              AND INDEX_NAME = ?");
    $stmt->bind_param("ss", $table, $index);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) $result['index_exists'] > 0;
}

function dropForeignKeyIfExists($conn, $table, $constraint) {
    if (foreignKeyExists($conn, $table, $constraint)) {
        $conn->query("ALTER TABLE `$table` DROP FOREIGN KEY `$constraint`");
    }
}

function dropIndexIfExists($conn, $table, $index) {
    if (indexExists($conn, $table, $index)) {
        $conn->query("ALTER TABLE `$table` DROP INDEX `$index`");
    }
}

function dropColumnIfExists($conn, $table, $column) {
    if (columnExists($conn, $table, $column)) {
        $conn->query("ALTER TABLE `$table` DROP COLUMN `$column`");
    }
}

function ensureUniqueIndexIfClean($conn, $table, $index, $columns) {
    if (indexExists($conn, $table, $index)) {
        return;
    }

    $column_list = implode(", ", array_map(function($column) {
        return "`" . $column . "`";
    }, $columns));

    $duplicate_check = $conn->query("SELECT COUNT(*) AS duplicate_groups
                                     FROM (
                                         SELECT $column_list
                                         FROM `$table`
                                         GROUP BY $column_list
                                         HAVING COUNT(*) > 1
                                     ) AS duplicates");

    if ($duplicate_check && (int) $duplicate_check->fetch_assoc()['duplicate_groups'] === 0) {
        $conn->query("ALTER TABLE `$table` ADD UNIQUE KEY `$index` ($column_list)");
    }
}

$conn->query("CREATE TABLE IF NOT EXISTS `scheduled_matches` (
    `schedule_id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `team_a_id` INT NOT NULL,
    `team_b_id` INT NOT NULL,
    `match_date` DATETIME NOT NULL,
    `status` ENUM('scheduled', 'completed') NOT NULL DEFAULT 'scheduled',
    `game_id` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`schedule_id`),
    INDEX `idx_scheduled_user_status` (`user_id`, `status`),
    INDEX `idx_scheduled_team_a` (`team_a_id`),
    INDEX `idx_scheduled_team_b` (`team_b_id`),
    INDEX `idx_scheduled_game` (`game_id`),
    CONSTRAINT `fk_scheduled_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_scheduled_team_a` FOREIGN KEY (`team_a_id`) REFERENCES `teams` (`team_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_scheduled_team_b` FOREIGN KEY (`team_b_id`) REFERENCES `teams` (`team_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_scheduled_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`game_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

dropForeignKeyIfExists($conn, 'scheduled_matches', 'fk_scheduled_sport');
dropIndexIfExists($conn, 'scheduled_matches', 'idx_scheduled_sport');
dropColumnIfExists($conn, 'scheduled_matches', 'sport_id');

dropForeignKeyIfExists($conn, 'past_scores', 'past_scores_sport_fk');
dropForeignKeyIfExists($conn, 'past_scores', 'past_scores_team_a_fk');
dropForeignKeyIfExists($conn, 'past_scores', 'past_scores_team_b_fk');
dropForeignKeyIfExists($conn, 'past_scores', 'past_scores_user_fk');
dropIndexIfExists($conn, 'past_scores', 'sport_id');
dropIndexIfExists($conn, 'past_scores', 'past_scores_team_a_fk');
dropIndexIfExists($conn, 'past_scores', 'past_scores_team_b_fk');
dropIndexIfExists($conn, 'past_scores', 'user_id');
dropColumnIfExists($conn, 'past_scores', 'sport_id');
dropColumnIfExists($conn, 'past_scores', 'team_a_id');
dropColumnIfExists($conn, 'past_scores', 'team_b_id');
dropColumnIfExists($conn, 'past_scores', 'user_id');

dropForeignKeyIfExists($conn, 'games', 'games_ibfk_1');
dropIndexIfExists($conn, 'games', 'sport_id');
dropColumnIfExists($conn, 'games', 'sport_id');

ensureUniqueIndexIfClean($conn, 'teams', 'uq_teams_sport_name', ['sport_id', 'team_name']);
ensureUniqueIndexIfClean($conn, 'players', 'uq_players_team_name', ['team_id', 'player_name']);
ensureColumnExists($conn, 'games', 'team_a_extras', 'INT NOT NULL DEFAULT 0 AFTER `team_a_wickets`');
ensureColumnExists($conn, 'games', 'team_b_extras', 'INT NOT NULL DEFAULT 0 AFTER `team_b_wickets`');
ensureColumnExists($conn, 'head_to_head', 'draws', 'INT NOT NULL DEFAULT 0 AFTER `team_b_wins`');
ensureColumnExists($conn, 'player_scores', 'rating', 'DECIMAL(3,1) NULL DEFAULT NULL AFTER `red_cards`');
$conn->query("ALTER TABLE `player_scores` MODIFY COLUMN `rating` DECIMAL(3,1) NULL DEFAULT NULL");
?>
