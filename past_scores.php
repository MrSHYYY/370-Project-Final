<?php
session_start();
include('includes/db.php');

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $_SESSION['username']);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $user = $user_result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        header("Location: logout.php");
        exit();
    }

    $_SESSION['user_id'] = $user['user_id'];
}

$user_id = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT past_scores.*, games.*, sports.sport_name, team_a.team_name AS team_a, team_b.team_name AS team_b
                        FROM past_scores
                        INNER JOIN games ON past_scores.game_id = games.game_id
                        LEFT JOIN sports ON past_scores.sport_id = sports.sport_id
                        LEFT JOIN teams AS team_a ON past_scores.team_a_id = team_a.team_id
                        LEFT JOIN teams AS team_b ON past_scores.team_b_id = team_b.team_id
                        WHERE past_scores.user_id = ?
                        ORDER BY games.game_date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$past_scores = $stmt->get_result();

function formatPastScore($game) {
    if (strtolower($game['sport_name']) === 'cricket') {
        return [
            htmlspecialchars($game['team_a_score']) . "/" . htmlspecialchars($game['team_a_wickets']) . " in " . htmlspecialchars($game['team_a_overs']) . " overs, extras " . htmlspecialchars($game['team_a_extras']),
            htmlspecialchars($game['team_b_score']) . "/" . htmlspecialchars($game['team_b_wickets']) . " in " . htmlspecialchars($game['team_b_overs']) . " overs, extras " . htmlspecialchars($game['team_b_extras'])
        ];
    }

    return [
        htmlspecialchars($game['team_a_score']) . " goals",
        htmlspecialchars($game['team_b_score']) . " goals"
    ];
}

function getPlayerScores($conn, $game_id, $team_id) {
    $stmt = $conn->prepare("SELECT players.player_name, player_scores.*
                            FROM player_scores
                            INNER JOIN players ON player_scores.player_id = players.player_id
                            WHERE player_scores.game_id = ? AND players.team_id = ?
                            ORDER BY player_scores.score DESC, players.player_name ASC");
    $stmt->bind_param("ii", $game_id, $team_id);
    $stmt->execute();
    return $stmt->get_result();
}

function renderPlayerScores($player_scores, $is_cricket) {
    if ($player_scores->num_rows === 0) {
        return;
    }

    echo "<div class='player-table-container'>";
    echo "<table class='player-score-table'>";
    echo "<thead><tr>";
    echo "<th>#</th>";
    echo "<th>Player</th>";
    if ($is_cricket) {
        echo "<th>Runs</th><th>Overs</th><th>W</th><th>50s</th><th>100s</th>";
    } else {
        echo "<th>Goals</th><th>YC</th><th>RC</th>";
    }
    echo "</tr></thead>";
    echo "<tbody>";

    $rank = 1;
    while ($player = $player_scores->fetch_assoc()) {
        echo "<tr>";
        echo "<td><span class='player-rank-mini'>{$rank}</span></td>";
        echo "<td><strong>" . htmlspecialchars($player['player_name']) . "</strong></td>";
        if ($is_cricket) {
            echo "<td>" . htmlspecialchars($player['runs']) . "</td>";
            echo "<td>" . htmlspecialchars($player['overs']) . "</td>";
            echo "<td>" . htmlspecialchars($player['wickets']) . "</td>";
            echo "<td>" . htmlspecialchars($player['half_centuries']) . "</td>";
            echo "<td>" . htmlspecialchars($player['centuries']) . "</td>";
        } else {
            echo "<td>" . htmlspecialchars($player['goals']) . "</td>";
            echo "<td>" . htmlspecialchars($player['yellow_cards']) . "</td>";
            echo "<td>" . htmlspecialchars($player['red_cards']) . "</td>";
        }
        echo "</tr>";
        $rank++;
    }

    echo "</tbody></table></div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Past Scores</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="game-container">
        <header>
            <h1>Past Scores</h1>
            <div class="header-actions top-right-actions">
                <a href="dashboard.php" class="header-link">Back to Dashboard</a>
                <a href="dashboard.php" class="header-link">Back to Add Scores</a>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

        <div class="games-list past-scores-list">
            <h2>Select a Past Score</h2>
            <?php if ($past_scores->num_rows > 0) { ?>
                <?php while ($game = $past_scores->fetch_assoc()) { ?>
                    <?php $scores = formatPastScore($game); ?>
                    <div class="game-item">
                        <h3><?php echo htmlspecialchars($game['team_a']); ?> vs <?php echo htmlspecialchars($game['team_b']); ?></h3>
                        <p>Sport: <?php echo htmlspecialchars($game['sport_name']); ?></p>
                        <p>Date: <?php echo htmlspecialchars($game['game_date']); ?></p>
                        <div class="score-breakdown team-player-breakdown">
                            <div class="team-score-panel">
                                <span><?php echo htmlspecialchars($game['team_a']); ?></span>
                                <strong><?php echo $scores[0]; ?></strong>
                                <?php renderPlayerScores(getPlayerScores($conn, $game['game_id'], $game['team_a_id']), strtolower($game['sport_name']) === 'cricket'); ?>
                            </div>
                            <div class="team-score-panel">
                                <span><?php echo htmlspecialchars($game['team_b']); ?></span>
                                <strong><?php echo $scores[1]; ?></strong>
                                <?php renderPlayerScores(getPlayerScores($conn, $game['game_id'], $game['team_b_id']), strtolower($game['sport_name']) === 'cricket'); ?>
                            </div>
                        </div>
                        <div class="past-score-actions">
                            <a class="h2h-btn" href="head_to_head.php?sport_id=<?php echo htmlspecialchars($game['sport_id']); ?>&team_a=<?php echo htmlspecialchars($game['team_a_id']); ?>&team_b=<?php echo htmlspecialchars($game['team_b_id']); ?>">View Head to Head</a>
                            <a class="header-link past-score-select" href="game.php?sport_id=<?php echo htmlspecialchars($game['sport_id']); ?>">Back to Add Scores</a>
                        </div>
                    </div>
                <?php } ?>
            <?php } else { ?>
                <p>No past scores found. Add a match score from a sport page first.</p>
            <?php } ?>
        </div>
    </div>
</body>
</html>
