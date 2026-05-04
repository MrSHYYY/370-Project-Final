<?php
session_start();
include('includes/db.php');

if (!isset($_SESSION['username']) || empty($_SESSION['is_admin'])) {
    header("Location: admin_login.php");
    exit();
}

function deleteGameData($conn, $game_id) {
    $stmt = $conn->prepare("DELETE FROM player_scores WHERE game_id = ?");
    $stmt->bind_param("i", $game_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM past_scores WHERE game_id = ?");
    $stmt->bind_param("i", $game_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM games WHERE game_id = ?");
    $stmt->bind_param("i", $game_id);
    $stmt->execute();
    $stmt->close();
}

function deleteUserData($conn, $user_id) {
    $stmt = $conn->prepare("SELECT game_id FROM games WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $games = $stmt->get_result();

    while ($game = $games->fetch_assoc()) {
        deleteGameData($conn, (int) $game['game_id']);
    }

    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM head_to_head WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND LOWER(username) <> 'admin'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['delete_game_id'])) {
        deleteGameData($conn, (int) $_POST['delete_game_id']);
        $_SESSION['admin_message'] = "Past score removed.";
        header("Location: admin_dashboard.php");
        exit();
    }

    if (isset($_POST['delete_user_id'])) {
        deleteUserData($conn, (int) $_POST['delete_user_id']);
        $_SESSION['admin_message'] = "User and their data removed.";
        header("Location: admin_dashboard.php");
        exit();
    }
}

if (isset($_SESSION['admin_message'])) {
    $admin_message = $_SESSION['admin_message'];
    unset($_SESSION['admin_message']);
}

$scores = $conn->query("SELECT games.*, users.username, sports.sport_name, team_a.team_name AS team_a, team_b.team_name AS team_b
                        FROM games
                        LEFT JOIN users ON games.user_id = users.user_id
                        LEFT JOIN sports ON games.sport_id = sports.sport_id
                        LEFT JOIN teams AS team_a ON games.team_a_id = team_a.team_id
                        LEFT JOIN teams AS team_b ON games.team_b_id = team_b.team_id
                        ORDER BY games.game_date DESC");

$users = $conn->query("SELECT user_id, username, created_at FROM users WHERE LOWER(username) <> 'admin' ORDER BY created_at DESC");

function formatAdminScore($score) {
    if (strtolower($score['sport_name']) === 'cricket') {
        return [
            htmlspecialchars($score['team_a_score']) . "/" . htmlspecialchars($score['team_a_wickets']) . " in " . htmlspecialchars($score['team_a_overs']) . " overs",
            htmlspecialchars($score['team_b_score']) . "/" . htmlspecialchars($score['team_b_wickets']) . " in " . htmlspecialchars($score['team_b_overs']) . " overs"
        ];
    }

    return [
        htmlspecialchars($score['team_a_score']) . " goals",
        htmlspecialchars($score['team_b_score']) . " goals"
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="game-container">
        <header>
            <h1>Admin Dashboard</h1>
            <div class="header-actions">
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

        <?php if (isset($admin_message)) { ?>
            <p class="success"><?php echo htmlspecialchars($admin_message); ?></p>
        <?php } ?>

        <div class="admin-section">
            <h2>Remove Past Scores</h2>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Sport</th>
                            <th>Match</th>
                            <th>Score</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($score = $scores->fetch_assoc()) { ?>
                            <?php $formatted_score = formatAdminScore($score); ?>
                            <tr>
                                <td><?php echo htmlspecialchars($score['username'] ?? 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars($score['sport_name']); ?></td>
                                <td><?php echo htmlspecialchars($score['team_a']); ?> vs <?php echo htmlspecialchars($score['team_b']); ?></td>
                                <td><?php echo htmlspecialchars($score['team_a']); ?>: <?php echo $formatted_score[0]; ?><br><?php echo htmlspecialchars($score['team_b']); ?>: <?php echo $formatted_score[1]; ?></td>
                                <td><?php echo htmlspecialchars($score['game_date']); ?></td>
                                <td>
                                    <form method="POST" action="admin_dashboard.php" onsubmit="return confirm('Remove this past score?');">
                                        <input type="hidden" name="delete_game_id" value="<?php echo htmlspecialchars($score['game_id']); ?>">
                                        <button type="submit" class="danger-btn">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="admin-section">
            <h2>Remove Users</h2>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                                <td>
                                    <form method="POST" action="admin_dashboard.php" onsubmit="return confirm('Remove this user and all their data?');">
                                        <input type="hidden" name="delete_user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                        <button type="submit" class="danger-btn">Remove User</button>
                                    </form>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
