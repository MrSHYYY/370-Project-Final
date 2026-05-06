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

$conn->query("CREATE TABLE IF NOT EXISTS `scheduled_matches` (
    `schedule_id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `sport_id` INT NOT NULL,
    `team_a_id` INT NOT NULL,
    `team_b_id` INT NOT NULL,
    `match_date` DATETIME NOT NULL,
    `status` ENUM('scheduled', 'completed') NOT NULL DEFAULT 'scheduled',
    `game_id` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`schedule_id`),
    INDEX `idx_scheduled_user_status` (`user_id`, `status`),
    INDEX `idx_scheduled_sport` (`sport_id`),
    INDEX `idx_scheduled_team_a` (`team_a_id`),
    INDEX `idx_scheduled_team_b` (`team_b_id`),
    INDEX `idx_scheduled_game` (`game_id`),
    CONSTRAINT `fk_scheduled_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_scheduled_sport` FOREIGN KEY (`sport_id`) REFERENCES `sports` (`sport_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_scheduled_team_a` FOREIGN KEY (`team_a_id`) REFERENCES `teams` (`team_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_scheduled_team_b` FOREIGN KEY (`team_b_id`) REFERENCES `teams` (`team_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_scheduled_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`game_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

ensureColumnExists($conn, 'games', 'team_a_extras', 'INT NOT NULL DEFAULT 0 AFTER `team_a_wickets`');
ensureColumnExists($conn, 'games', 'team_b_extras', 'INT NOT NULL DEFAULT 0 AFTER `team_b_wickets`');
ensureColumnExists($conn, 'head_to_head', 'draws', 'INT NOT NULL DEFAULT 0 AFTER `team_b_wins`');
ensureColumnExists($conn, 'player_scores', 'rating', 'DECIMAL(3,1) NULL DEFAULT NULL AFTER `red_cards`');
$conn->query("ALTER TABLE `player_scores` MODIFY COLUMN `rating` DECIMAL(3,1) NULL DEFAULT NULL");
?>
