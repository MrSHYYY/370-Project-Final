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
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        header("Location: logout.php");
        exit();
    }

    $_SESSION['user_id'] = $user['user_id'];
}

$user_id = (int) $_SESSION['user_id'];
$sport_id = isset($_GET['sport_id']) ? (int) $_GET['sport_id'] : 0;
$team_a_id = isset($_GET['team_a']) ? (int) $_GET['team_a'] : 0;
$team_b_id = isset($_GET['team_b']) ? (int) $_GET['team_b'] : 0;

if ($sport_id <= 0 || $team_a_id <= 0 || $team_b_id <= 0) {
    header("Location: dashboard.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM sports WHERE sport_id = ?");
$stmt->bind_param("i", $sport_id);
$stmt->execute();
$sport_result = $stmt->get_result();
$sport = $sport_result->fetch_assoc();
$stmt->close();

if (!$sport) {
    header("Location: dashboard.php");
    exit();
}

$sport_name = strtolower($sport['sport_name']);
$is_cricket = $sport_name === 'cricket';

$stmt = $conn->prepare("SELECT head_to_head.*, team_a.team_name AS team_a, team_b.team_name AS team_b
                        FROM head_to_head
                        LEFT JOIN teams AS team_a ON head_to_head.team_a_id = team_a.team_id
                        LEFT JOIN teams AS team_b ON head_to_head.team_b_id = team_b.team_id
                        WHERE head_to_head.user_id = ?
                        AND ((head_to_head.team_a_id = ? AND head_to_head.team_b_id = ?)
                            OR (head_to_head.team_a_id = ? AND head_to_head.team_b_id = ?))
                        LIMIT 1");
$stmt->bind_param("iiiii", $user_id, $team_a_id, $team_b_id, $team_b_id, $team_a_id);
$stmt->execute();
$h2h_result = $stmt->get_result();
$head_to_head = $h2h_result->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT games.*, team_a.team_name AS team_a, team_b.team_name AS team_b
                        FROM games
                        LEFT JOIN teams AS team_a ON games.team_a_id = team_a.team_id
                        LEFT JOIN teams AS team_b ON games.team_b_id = team_b.team_id
                        WHERE games.sport_id = ? AND games.user_id = ?
                        AND ((games.team_a_id = ? AND games.team_b_id = ?)
                            OR (games.team_a_id = ? AND games.team_b_id = ?))
                        ORDER BY games.game_date DESC");
$stmt->bind_param("iiiiii", $sport_id, $user_id, $team_a_id, $team_b_id, $team_b_id, $team_a_id);
$stmt->execute();
$games = $stmt->get_result();

function formatTeamScore($game, $team_side, $is_cricket) {
    if ($is_cricket) {
        return htmlspecialchars($game[$team_side . '_score']) . "/" .
            htmlspecialchars($game[$team_side . '_wickets']) . " in " .
            htmlspecialchars($game[$team_side . '_overs']) . " overs";
    }

    return htmlspecialchars($game[$team_side . '_score']) . " goals";
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
        echo "<p class='player-empty'>No player scores added for this team.</p>";
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
    <title>Head to Head</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="game-container">
        <header>
            <h1>Head to Head</h1>
            <div class="header-actions">
                <a href="game.php?sport_id=<?php echo $sport_id; ?>" class="header-link">Back to Matches</a>
                <a href="dashboard.php" class="header-link">Back to Dashboard</a>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

        <div class="head-to-head">
            <h2><?php echo htmlspecialchars($sport['sport_name']); ?> Comparison</h2>

            <?php if ($head_to_head) { ?>
                <div class="h2h-summary">
                    <div>
                        <strong><?php echo htmlspecialchars($head_to_head['team_a']); ?></strong>
                        <span><?php echo htmlspecialchars($head_to_head['team_a_wins']); ?> wins</span>
                    </div>
                    <div>
                        <strong><?php echo htmlspecialchars($head_to_head['team_b']); ?></strong>
                        <span><?php echo htmlspecialchars($head_to_head['team_b_wins']); ?> wins</span>
                    </div>
                </div>
            <?php } ?>

            <div class="h2h-matches">
                <?php if ($games->num_rows > 0) { ?>
                    <?php while ($game = $games->fetch_assoc()) { ?>
                        <div class="game-item h2h-game-item">
                            <h3><?php echo htmlspecialchars($game['team_a']); ?> vs <?php echo htmlspecialchars($game['team_b']); ?></h3>
                            <p>Date: <?php echo htmlspecialchars($game['game_date']); ?></p>

                            <div class="score-breakdown team-player-breakdown">
                                <div class="team-score-panel">
                                    <span><?php echo htmlspecialchars($game['team_a']); ?></span>
                                    <strong><?php echo formatTeamScore($game, 'team_a', $is_cricket); ?></strong>
                                    <?php renderPlayerScores(getPlayerScores($conn, $game['game_id'], $game['team_a_id']), $is_cricket); ?>
                                </div>

                                <div class="team-score-panel">
                                    <span><?php echo htmlspecialchars($game['team_b']); ?></span>
                                    <strong><?php echo formatTeamScore($game, 'team_b', $is_cricket); ?></strong>
                                    <?php renderPlayerScores(getPlayerScores($conn, $game['game_id'], $game['team_b_id']), $is_cricket); ?>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <p>No head-to-head matches found for these teams.</p>
                <?php } ?>
            </div>
        </div>
    </div>
</body>
</html>
