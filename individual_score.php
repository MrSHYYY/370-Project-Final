<?php
session_start();
include('includes/db.php');
include('includes/schedule_helpers.php');

$user_id = requireLoggedInUser($conn);
$player_name = trim($_GET['player_name'] ?? '');
$has_searched = $player_name !== '';
$player_exists = false;
$scores_by_sport = [
    'cricket' => [],
    'football' => []
];
$match_counts = [
    'cricket' => 0,
    'football' => 0
];

function sportScoreLabel($row) {
    if (strtolower($row['sport_name']) === 'cricket') {
        return htmlspecialchars($row['runs'] ?? 0) . " runs, " .
               htmlspecialchars($row['wickets'] ?? 0) . " wickets, " .
               htmlspecialchars($row['overs'] ?? 0) . " overs";
    }

    $rating = $row['rating'] !== null ? ", rating " . htmlspecialchars($row['rating']) : "";
    return htmlspecialchars($row['goals'] ?? 0) . " goals, " .
           htmlspecialchars($row['yellow_cards'] ?? 0) . " YC, " .
           htmlspecialchars($row['red_cards'] ?? 0) . " RC" . $rating;
}

if ($has_searched) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total_players FROM players WHERE LOWER(player_name) = LOWER(?)");
    $stmt->bind_param("s", $player_name);
    $stmt->execute();
    $player_total = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $player_exists = (int) $player_total['total_players'] > 0;

    if ($player_exists) {
        $stmt = $conn->prepare("SELECT players.player_name, teams.team_name, sports.sport_name,
                                       games.game_id, games.game_date,
                                       team_a.team_name AS team_a_name, team_b.team_name AS team_b_name,
                                       player_scores.score, player_scores.runs, player_scores.overs,
                                       player_scores.wickets, player_scores.half_centuries,
                                       player_scores.centuries, player_scores.goals,
                                       player_scores.yellow_cards, player_scores.red_cards,
                                       player_scores.rating
                                FROM players
                                INNER JOIN teams ON players.team_id = teams.team_id
                                INNER JOIN player_scores ON players.player_id = player_scores.player_id
                                INNER JOIN games ON player_scores.game_id = games.game_id
                                INNER JOIN sports ON teams.sport_id = sports.sport_id
                                LEFT JOIN teams AS team_a ON games.team_a_id = team_a.team_id
                                LEFT JOIN teams AS team_b ON games.team_b_id = team_b.team_id
                                WHERE LOWER(players.player_name) = LOWER(?)
                                  AND games.user_id = ?
                                ORDER BY sports.sport_name ASC, teams.team_name ASC, games.game_date DESC");
        $stmt->bind_param("si", $player_name, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $counted_games = [
            'cricket' => [],
            'football' => []
        ];

        while ($row = $result->fetch_assoc()) {
            $sport_key = strtolower($row['sport_name']);

            if (!isset($scores_by_sport[$sport_key])) {
                continue;
            }

            $team_name = $row['team_name'];
            if (!isset($scores_by_sport[$sport_key][$team_name])) {
                $scores_by_sport[$sport_key][$team_name] = [];
            }

            $scores_by_sport[$sport_key][$team_name][] = $row;
            $counted_games[$sport_key][$row['game_id']] = true;
        }

        $stmt->close();

        foreach ($counted_games as $sport => $games) {
            $match_counts[$sport] = count($games);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Individual Score</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="game-container">
        <header>
            <h1>Individual Score</h1>
            <div class="header-actions">
                <a href="dashboard.php" class="header-link">Back to Dashboard</a>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

        <section class="individual-score-shell">
            <form method="GET" action="individual_score.php" class="individual-search-form">
                <label for="player_name">Player Name</label>
                <div class="individual-search-row">
                    <input type="text" id="player_name" name="player_name" placeholder="Enter player name" value="<?php echo htmlspecialchars($player_name); ?>" required>
                    <button type="submit">Search</button>
                </div>
            </form>

            <?php if ($has_searched && !$player_exists) { ?>
                <div class="empty-schedule individual-empty">
                    <h3>PLayer not found</h3>
                    <p>Enter a new name and search again.</p>
                </div>
            <?php } elseif ($has_searched) { ?>
                <div class="individual-summary">
                    <div>
                        <span>Player</span>
                        <strong><?php echo htmlspecialchars($player_name); ?></strong>
                    </div>
                    <div>
                        <span>Cricket Matches</span>
                        <strong><?php echo htmlspecialchars($match_counts['cricket']); ?></strong>
                    </div>
                    <div>
                        <span>Football Matches</span>
                        <strong><?php echo htmlspecialchars($match_counts['football']); ?></strong>
                    </div>
                </div>

                <?php foreach (['cricket' => 'Cricket', 'football' => 'Football'] as $sport_key => $sport_label) { ?>
                    <section class="individual-sport-section">
                        <div class="individual-section-heading">
                            <h2><?php echo $sport_label; ?> Scores</h2>
                            <span><?php echo htmlspecialchars($match_counts[$sport_key]); ?> matches</span>
                        </div>

                        <?php if (empty($scores_by_sport[$sport_key])) { ?>
                            <p class="individual-no-score">no score for '<?php echo strtolower($sport_label); ?>'</p>
                        <?php } else { ?>
                            <?php foreach ($scores_by_sport[$sport_key] as $team_name => $team_scores) { ?>
                                <div class="individual-team-block">
                                    <div class="individual-team-heading">
                                        <h3><?php echo htmlspecialchars($team_name); ?></h3>
                                        <span><?php echo count($team_scores); ?> matches</span>
                                    </div>
                                    <div class="player-table-container individual-table-wrap">
                                        <table class="player-score-table individual-score-table">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Match</th>
                                                    <th>Score</th>
                                                    <?php if ($sport_key === 'cricket') { ?>
                                                        <th>50s</th>
                                                        <th>100s</th>
                                                    <?php } ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($team_scores as $score_row) { ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars(date('M d, Y', strtotime($score_row['game_date']))); ?></td>
                                                        <td><?php echo htmlspecialchars($score_row['team_a_name'] . " vs " . $score_row['team_b_name']); ?></td>
                                                        <td><strong><?php echo sportScoreLabel($score_row); ?></strong></td>
                                                        <?php if ($sport_key === 'cricket') { ?>
                                                            <td><?php echo htmlspecialchars($score_row['half_centuries']); ?></td>
                                                            <td><?php echo htmlspecialchars($score_row['centuries']); ?></td>
                                                        <?php } ?>
                                                    </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php } ?>
                        <?php } ?>
                    </section>
                <?php } ?>
            <?php } ?>
        </section>
    </div>
</body>
</html>
