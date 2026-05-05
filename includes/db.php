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

ensureColumnExists($conn, 'games', 'team_a_extras', 'INT NOT NULL DEFAULT 0 AFTER `team_a_wickets`');
ensureColumnExists($conn, 'games', 'team_b_extras', 'INT NOT NULL DEFAULT 0 AFTER `team_b_wickets`');
ensureColumnExists($conn, 'head_to_head', 'draws', 'INT NOT NULL DEFAULT 0 AFTER `team_b_wins`');
ensureColumnExists($conn, 'player_scores', 'rating', 'DECIMAL(3,1) NULL DEFAULT NULL AFTER `red_cards`');
$conn->query("ALTER TABLE `player_scores` MODIFY COLUMN `rating` DECIMAL(3,1) NULL DEFAULT NULL");
?>
