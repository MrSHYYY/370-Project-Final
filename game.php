<?php
session_start();
include('includes/db.php');

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

function getTeamId($conn, $sport_id, $team_name) {
    $team_name = trim($team_name);

    $stmt = $conn->prepare("SELECT team_id FROM teams WHERE sport_id = ? AND team_name = ? LIMIT 1");
    $stmt->bind_param("is", $sport_id, $team_name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($team = $result->fetch_assoc()) {
        $stmt->close();
        return $team['team_id'];
    }

    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO teams (sport_id, team_name) VALUES (?, ?)");
    $stmt->bind_param("is", $sport_id, $team_name);
    $stmt->execute();
    $team_id = $conn->insert_id;
    $stmt->close();

    return $team_id;
}

function getPlayerId($conn, $team_id, $player_name) {
    $player_name = trim($player_name);

    $stmt = $conn->prepare("SELECT player_id FROM players WHERE team_id = ? AND player_name = ? LIMIT 1");
    $stmt->bind_param("is", $team_id, $player_name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($player = $result->fetch_assoc()) {
        $stmt->close();
        return $player['player_id'];
    }

    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO players (team_id, player_name) VALUES (?, ?)");
    $stmt->bind_param("is", $team_id, $player_name);
    $stmt->execute();
    $player_id = $conn->insert_id;
    $stmt->close();

    return $player_id;
}

function countSubmittedPlayers($players) {
    if (!isset($players['name']) || !is_array($players['name'])) {
        return 0;
    }

    $count = 0;

    foreach ($players['name'] as $player_name) {
        if (trim($player_name) !== '') {
            $count++;
        }
    }

    return $count;
}

function sumPlayerGoals($players) {
    if (!isset($players['goals']) || !is_array($players['goals'])) {
        return 0;
    }

    $total_goals = 0;

    foreach ($players['goals'] as $goals) {
        $total_goals += (int) $goals;
    }

    return $total_goals;
}

function sumPlayerStat($players, $stat_name) {
    if (!isset($players[$stat_name]) || !is_array($players[$stat_name])) {
        return 0;
    }

    $total = 0;

    foreach ($players[$stat_name] as $value) {
        $total += (int) $value;
    }

    return $total;
}

function savePlayerScores($conn, $game_id, $team_id, $players, $is_cricket) {
    if (!isset($players['name']) || !is_array($players['name'])) {
        return;
    }

    foreach ($players['name'] as $index => $player_name) {
        $player_name = trim($player_name);

        if ($player_name === '') {
            continue;
        }

        $player_id = getPlayerId($conn, $team_id, $player_name);

        if ($is_cricket) {
            $runs = (int) ($players['runs'][$index] ?? 0);
            $overs = (float) ($players['overs'][$index] ?? 0);
            
            if (round(($overs - floor($overs)) * 10) > 5) {
                $overs = (float) (floor($overs) + 1);
            }

            $wickets = (int) ($players['wickets'][$index] ?? 0);
            $half_centuries = $runs >= 50 && $runs < 100 ? 1 : 0;
            $centuries = $runs >= 100 ? 1 : 0;
            $goals = null;
            $yellow_cards = null;
            $red_cards = null;
            $rating = null;
            $score = $runs;
        } else {
            $runs = null;
            $overs = null;
            $wickets = null;
            $half_centuries = null;
            $centuries = null;
            $goals = (int) ($players['goals'][$index] ?? 0);
            $yellow_cards = (int) ($players['yellow_cards'][$index] ?? 0);
            $red_cards = (int) ($players['red_cards'][$index] ?? 0);
            $rating = isset($players['rating'][$index]) && $players['rating'][$index] !== '' ? (float) $players['rating'][$index] : null;
            $score = $goals;
        }

        $stmt = $conn->prepare("INSERT INTO player_scores (
                                    game_id, player_id, score, runs, overs, wickets,
                                    half_centuries, centuries, goals, yellow_cards, red_cards, rating
                                )
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "iiiidiiiiiid",
            $game_id,
            $player_id,
            $score,
            $runs,
            $overs,
            $wickets,
            $half_centuries,
            $centuries,
            $goals,
            $yellow_cards,
            $red_cards,
            $rating
        );
        $stmt->execute();
        $stmt->close();
    }
}

function updateHeadToHead($conn, $user_id, $team_a_id, $team_b_id, $team_a_score, $team_b_score) {
    $winner_team_id = null;
    $is_draw = false;

    if ($team_a_score > $team_b_score) {
        $winner_team_id = $team_a_id;
    } elseif ($team_b_score > $team_a_score) {
        $winner_team_id = $team_b_id;
    } else {
        $is_draw = true;
    }

    $stmt = $conn->prepare("SELECT * FROM head_to_head
                            WHERE user_id = ?
                            AND ((team_a_id = ? AND team_b_id = ?) OR (team_a_id = ? AND team_b_id = ?))
                            LIMIT 1");
    $stmt->bind_param("iiiii", $user_id, $team_a_id, $team_b_id, $team_b_id, $team_a_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $head_to_head = $result->fetch_assoc();
    $stmt->close();

    if (!$head_to_head) {
        $team_a_wins = (int)$winner_team_id === (int)$team_a_id ? 1 : 0;
        $team_b_wins = (int)$winner_team_id === (int)$team_b_id ? 1 : 0;
        $draws = $is_draw ? 1 : 0;

        $stmt = $conn->prepare("INSERT INTO head_to_head (user_id, team_a_id, team_b_id, team_a_wins, team_b_wins, draws)
                                VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiiii", $user_id, $team_a_id, $team_b_id, $team_a_wins, $team_b_wins, $draws);
        $stmt->execute();
        $stmt->close();
        return;
    }

    if ($is_draw) {
        $stmt = $conn->prepare("UPDATE head_to_head SET draws = draws + 1 WHERE head_to_head_id = ?");
        $stmt->bind_param("i", $head_to_head['head_to_head_id']);
        $stmt->execute();
        $stmt->close();
    } elseif ($winner_team_id !== null) {
        if ((int)$head_to_head['team_a_id'] === (int)$winner_team_id) {
            $stmt = $conn->prepare("UPDATE head_to_head SET team_a_wins = team_a_wins + 1 WHERE head_to_head_id = ?");
        } else {
            $stmt = $conn->prepare("UPDATE head_to_head SET team_b_wins = team_b_wins + 1 WHERE head_to_head_id = ?");
        }
        $stmt->bind_param("i", $head_to_head['head_to_head_id']);
        $stmt->execute();
        $stmt->close();
    }
}

function savePastScore($conn, $game_id, $user_id, $sport_id, $team_a_id, $team_b_id) {
    $stmt = $conn->prepare("INSERT IGNORE INTO past_scores (game_id, user_id, sport_id, team_a_id, team_b_id)
                            VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiii", $game_id, $user_id, $sport_id, $team_a_id, $team_b_id);
    $stmt->execute();
    $stmt->close();
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

// Check if the user is logged in
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

// Get sport_id from the URL
if (isset($_GET['sport_id'])) {
    $sport_id = (int) $_GET['sport_id'];

    // Fetch the selected sport's name
    $stmt = $conn->prepare("SELECT * FROM sports WHERE sport_id = ?");
    $stmt->bind_param("i", $sport_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $sport = $result->fetch_assoc();
    $stmt->close();

    if (!$sport) {
        header("Location: dashboard.php");
        exit();
    }

    $sport_name = strtolower($sport['sport_name']);
    $is_cricket = $sport_name === 'cricket';
    $is_football = $sport_name === 'football';
    $max_players = $is_cricket ? 12 : 16;

    // Handle score submission
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $team_a_name = $_POST['team_a'];
        $team_b_name = $_POST['team_b'];
        $team_a_score = (int) $_POST['team_a_score'];
        $team_b_score = (int) $_POST['team_b_score'];
        $team_a_overs = $is_cricket ? (float) $_POST['team_a_overs'] : null;
        $team_b_overs = $is_cricket ? (float) $_POST['team_b_overs'] : null;
        $team_a_extras = $is_cricket ? (int) $_POST['team_a_extras'] : 0;
        $team_b_extras = $is_cricket ? (int) $_POST['team_b_extras'] : 0;
        $cricket_match_type = $is_cricket ? ($_POST['cricket_match_type'] ?? '') : null;

        if ($is_cricket) {
            if (round(($team_a_overs - floor($team_a_overs)) * 10) > 5) {
                $team_a_overs = (float) (floor($team_a_overs) + 1);
            }
            if (round(($team_b_overs - floor($team_b_overs)) * 10) > 5) {
                $team_b_overs = (float) (floor($team_b_overs) + 1);
            }
        }

        $team_a_wickets = $is_cricket ? (int) $_POST['team_a_wickets'] : null;
        $team_b_wickets = $is_cricket ? (int) $_POST['team_b_wickets'] : null;
        $team_a_players = $_POST['team_a_players'] ?? [];
        $team_b_players = $_POST['team_b_players'] ?? [];
        $game_date = $_POST['game_date'];

        if ($is_cricket) {
            $over_limits = [
                'odi' => 50,
                't20' => 20,
                'custom' => null
            ];

            if (!array_key_exists($cricket_match_type, $over_limits)) {
                $_SESSION['error_message'] = "Please select ODI, T20, or Custom before entering cricket scores.";
                header("Location: game.php?sport_id=" . $sport_id . "#team-submit");
                exit();
            }

            $over_limit = $over_limits[$cricket_match_type];

            if ($team_a_overs < 0 || $team_b_overs < 0) {
                $_SESSION['error_message'] = "Overs cannot be negative.";
                header("Location: game.php?sport_id=" . $sport_id . "#team-submit");
                exit();
            }

            if ($over_limit !== null && ($team_a_overs > $over_limit || $team_b_overs > $over_limit)) {
                $_SESSION['error_message'] = strtoupper($cricket_match_type) . " matches cannot exceed " . $over_limit . " overs.";
                header("Location: game.php?sport_id=" . $sport_id . "#team-submit");
                exit();
            }
        }

        if ($is_cricket && ($team_a_wickets < 0 || $team_a_wickets > 10 || $team_b_wickets < 0 || $team_b_wickets > 10)) {
            $_SESSION['error_message'] = "Wickets must be between 0 and 10.";
            header("Location: game.php?sport_id=" . $sport_id . "#team-submit");
            exit();
        }

        if ($is_cricket && ($team_a_extras < 0 || $team_b_extras < 0)) {
            $_SESSION['error_message'] = "Extras cannot be negative.";
            header("Location: game.php?sport_id=" . $sport_id . "#team-submit");
            exit();
        }

        if (countSubmittedPlayers($team_a_players) > $max_players || countSubmittedPlayers($team_b_players) > $max_players) {
            $_SESSION['error_message'] = "You can enter a maximum of " . $max_players . " players per team for " . htmlspecialchars($sport['sport_name']) . ".";
            header("Location: game.php?sport_id=" . $sport_id . "#team-submit");
            exit();
        }

        if ($is_football && (sumPlayerGoals($team_a_players) > $team_a_score || sumPlayerGoals($team_b_players) > $team_b_score)) {
            $_SESSION['error_message'] = "Warning: sum of player and team goals not matched";
            header("Location: game.php?sport_id=" . $sport_id . "#team-submit");
            exit();
        }

        if ($is_football) {
            $has_card_error = false;
            $check_cards = function($players) use (&$has_card_error) {
                if (!isset($players['name']) || !is_array($players['name'])) return;
                foreach ($players['name'] as $index => $name) {
                    if (trim($name) === '') continue;
                    $yc = (int) ($players['yellow_cards'][$index] ?? 0);
                    $rc = (int) ($players['red_cards'][$index] ?? 0);
                    if ($yc > 1 || $rc > 1 || ($yc + $rc > 1)) {
                        $has_card_error = true;
                    }
                }
            };
            $check_cards($team_a_players);
            $check_cards($team_b_players);
            if ($has_card_error) {
                $_SESSION['error_message'] = "Invalid cards: A player can only have a maximum of 1 card, either Yellow or Red.";
                header("Location: game.php?sport_id=" . $sport_id . "#team-submit");
                exit();
            }

            $has_rating_error = false;
            $check_ratings = function($players) use (&$has_rating_error) {
                if (!isset($players['name']) || !is_array($players['name'])) return;
                foreach ($players['name'] as $index => $name) {
                    if (trim($name) === '') continue;
                    $rating = $players['rating'][$index] ?? '';
                    if ($rating !== '' && (!is_numeric($rating) || (float) $rating < 0 || (float) $rating > 5)) {
                        $has_rating_error = true;
                    }
                }
            };
            $check_ratings($team_a_players);
            $check_ratings($team_b_players);
            if ($has_rating_error) {
                $_SESSION['error_message'] = "Invalid rating: player ratings must be between 0 and 5.";
                header("Location: game.php?sport_id=" . $sport_id . "#team-submit");
                exit();
            }
        }

        if ($is_cricket && (
            sumPlayerStat($team_a_players, 'runs') + $team_a_extras !== $team_a_score ||
            sumPlayerStat($team_b_players, 'runs') + $team_b_extras !== $team_b_score
        )) {
            $_SESSION['error_message'] = "Invalid runs: player runs plus extras must equal the team's total runs.";
            header("Location: game.php?sport_id=" . $sport_id . "#team-submit");
            exit();
        }

        if ($is_cricket && (sumPlayerStat($team_a_players, 'wickets') > $team_b_wickets || sumPlayerStat($team_b_players, 'wickets') > $team_a_wickets)) {
            $_SESSION['error_message'] = "Invalid wickets: player wickets cannot be more than the opposing team's total wickets.";
            header("Location: game.php?sport_id=" . $sport_id . "#team-submit");
            exit();
        }

        if ($is_cricket) {
            $check_overs = function($players, $team_overs) {
                if (!isset($players['name']) || !is_array($players['name'])) return true;
                $team_balls = floor($team_overs) * 6 + round(($team_overs - floor($team_overs)) * 10);
                $player_balls = 0;
                foreach ($players['name'] as $index => $name) {
                    if (trim($name) === '') continue;
                    $o = (float) ($players['overs'][$index] ?? 0);
                    $player_balls += floor($o) * 6 + round(($o - floor($o)) * 10);
                }
                return $player_balls <= $team_balls;
            };

            if (!$check_overs($team_a_players, (float)$team_b_overs) || !$check_overs($team_b_players, (float)$team_a_overs)) {
                $_SESSION['error_message'] = "Invalid overs: the sum of player overs cannot exceed the opposing team's total overs.";
                header("Location: game.php?sport_id=" . $sport_id . "#team-submit");
                exit();
            }
        }

        $team_a_id = getTeamId($conn, $sport_id, $team_a_name);
        $team_b_id = getTeamId($conn, $sport_id, $team_b_name);

        // Insert the match scores into the database
        $stmt = $conn->prepare("INSERT INTO games (
                                    user_id, sport_id, team_a_id, team_b_id,
                                    team_a_score, team_a_overs, team_a_wickets, team_a_extras,
                                    team_b_score, team_b_overs, team_b_wickets, team_b_extras,
                                    game_date
                                )
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "iiiiidiiidiis",
            $user_id,
            $sport_id,
            $team_a_id,
            $team_b_id,
            $team_a_score,
            $team_a_overs,
            $team_a_wickets,
            $team_a_extras,
            $team_b_score,
            $team_b_overs,
            $team_b_wickets,
            $team_b_extras,
            $game_date
        );

        if ($stmt->execute()) {
            $game_id = $conn->insert_id;
            updateHeadToHead($conn, $user_id, $team_a_id, $team_b_id, $team_a_score, $team_b_score);
            savePastScore($conn, $game_id, $user_id, $sport_id, $team_a_id, $team_b_id);
            savePlayerScores($conn, $game_id, $team_a_id, $team_a_players, $is_cricket);
            savePlayerScores($conn, $game_id, $team_b_id, $team_b_players, $is_cricket);
            $_SESSION['message'] = "Match score added successfully!";
        } else {
            $_SESSION['error_message'] = "Error: " . $conn->error;
        }

        $stmt->close();
        header("Location: game.php?sport_id=" . $sport_id . "#team-submit");
        exit();
    }

    // Fetch games for the selected sport
    $stmt = $conn->prepare("SELECT games.*, team_a.team_name AS team_a, team_b.team_name AS team_b
                            FROM games
                            LEFT JOIN teams AS team_a ON games.team_a_id = team_a.team_id
                            LEFT JOIN teams AS team_b ON games.team_b_id = team_b.team_id
                            WHERE games.sport_id = ? AND games.user_id = ?
                            ORDER BY games.game_date DESC");
    $stmt->bind_param("ii", $sport_id, $user_id);
    $stmt->execute();
    $result_games = $stmt->get_result();

} else {
    // Redirect to dashboard if no sport_id is provided
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($sport['sport_name']); ?> Matches</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="game-container">
        <header>
            <h1><?php echo htmlspecialchars($sport['sport_name']); ?> Matches</h1>
            <div class="header-actions">
                <a href="#team-submit" class="header-link">Submit Score</a>
                <a href="dashboard.php" class="header-link">Back to Dashboard</a>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

        <div class="games-list">
            <h2>Completed Matches</h2>
            <?php
            if ($result_games->num_rows > 0) {
                while ($game = $result_games->fetch_assoc()) {
                    if ($is_cricket) {
                        $team_a_result = htmlspecialchars($game['team_a_score']) . "/" . htmlspecialchars($game['team_a_wickets']) . " in " . htmlspecialchars($game['team_a_overs']) . " overs, extras " . htmlspecialchars($game['team_a_extras']);
                        $team_b_result = htmlspecialchars($game['team_b_score']) . "/" . htmlspecialchars($game['team_b_wickets']) . " in " . htmlspecialchars($game['team_b_overs']) . " overs, extras " . htmlspecialchars($game['team_b_extras']);
                    } else {
                        $team_a_result = htmlspecialchars($game['team_a_score']) . " goals";
                        $team_b_result = htmlspecialchars($game['team_b_score']) . " goals";
                    }

                    echo "<div class='game-item'>
                            <h3>" . htmlspecialchars($game['team_a']) . " vs " . htmlspecialchars($game['team_b']) . "</h3>
                            <p>Date: " . htmlspecialchars($game['game_date']) . "</p>
                            <div class='score-breakdown team-player-breakdown'>
                                <div class='team-score-panel'>
                                    <span>" . htmlspecialchars($game['team_a']) . "</span>
                                    <strong>" . $team_a_result . "</strong>";
                                    renderPlayerScores(getPlayerScores($conn, $game['game_id'], $game['team_a_id']), $is_cricket);
                    echo "      </div>
                                <div class='team-score-panel'>
                                    <span>" . htmlspecialchars($game['team_b']) . "</span>
                                    <strong>" . $team_b_result . "</strong>";
                                    renderPlayerScores(getPlayerScores($conn, $game['game_id'], $game['team_b_id']), $is_cricket);
                    echo "      </div>
                            </div>
                            <a class='h2h-btn' href='head_to_head.php?sport_id=" . $sport_id . "&team_a=" . htmlspecialchars($game['team_a_id']) . "&team_b=" . htmlspecialchars($game['team_b_id']) . "'>Head to Head</a>
                          </div>";
                }
            } else {
                echo "<p>No completed matches found for this sport.</p>";
            }
            ?>
        </div>

        <div class="score-form" id="team-submit">
            <h2>Enter Match Scores</h2>
            <form method="POST" action="game.php?sport_id=<?php echo $sport_id; ?>#team-submit">
                <?php if ($is_cricket) { ?>
                    <fieldset class="match-format-section">
                        <legend>Match Format</legend>
                        <input type="hidden" id="cricket_match_type" name="cricket_match_type" data-cricket-format required>
                        <div class="format-button-group" role="group" aria-label="Select cricket match format">
                            <button type="button" class="format-option-btn" data-format-option="odi" data-over-limit="50">
                                <strong>ODI - 50 OVERS</strong>
                            </button>
                            <button type="button" class="format-option-btn" data-format-option="t20" data-over-limit="20">
                                <strong>T20 - 20 OVERS</strong>
                            </button>
                            <button type="button" class="format-option-btn" data-format-option="custom" data-over-limit="">
                                <strong>Custom</strong>
                            </button>
                        </div>
                        <p class="selected-format-text" data-selected-format>Format: Not selected</p>
                        <p class="player-warning" data-format-over-warning>Select a valid format and keep team overs inside the selected limit.</p>
                    </fieldset>
                <?php } ?>

                <div class="team-score-grid">
                    <fieldset>
                        <legend>Team A</legend>

                        <label for="team_a">Team Name</label>
                        <input type="text" id="team_a" name="team_a" placeholder="Enter Team A Name" required>

                        <?php if ($is_cricket) { ?>
                            <label for="team_a_score">Runs</label>
                            <input type="number" id="team_a_score" name="team_a_score" min="0" placeholder="Enter Runs" required>
                            <label for="team_a_extras">Extras</label>
                            <input type="number" id="team_a_extras" name="team_a_extras" min="0" step="1" placeholder="Enter Extras" required>
                            <label for="team_a_overs">Overs</label>
                            <input type="number" id="team_a_overs" name="team_a_overs" min="0" step="0.1" placeholder="Enter Overs" data-over-input required>
                            <label for="team_a_wickets">Wickets</label>
                            <input type="number" id="team_a_wickets" name="team_a_wickets" min="0" max="10" step="1" placeholder="Enter Wickets" data-wicket-input required>
                            <p class="wicket-warning" data-wicket-warning>Warning: invalid wickets.</p>
                        <?php } else { ?>
                            <label for="team_a_score">Goals</label>
                            <input type="number" id="team_a_score" name="team_a_score" min="0" step="1" placeholder="Enter Goals" required>
                        <?php } ?>
                    </fieldset>
                    <fieldset>
                        <legend>Team B</legend>

                        <label for="team_b">Team Name</label>
                        <input type="text" id="team_b" name="team_b" placeholder="Enter Team B Name" required>

                        <?php if ($is_cricket) { ?>
                            <label for="team_b_score">Runs</label>
                            <input type="number" id="team_b_score" name="team_b_score" min="0" placeholder="Enter Runs" required>
                            <label for="team_b_extras">Extras</label>
                            <input type="number" id="team_b_extras" name="team_b_extras" min="0" step="1" placeholder="Enter Extras" required>
                            <label for="team_b_overs">Overs</label>
                            <input type="number" id="team_b_overs" name="team_b_overs" min="0" step="0.1" placeholder="Enter Overs" data-over-input required>
                            <label for="team_b_wickets">Wickets</label>
                            <input type="number" id="team_b_wickets" name="team_b_wickets" min="0" max="10" step="1" placeholder="Enter Wickets" data-wicket-input required>
                            <p class="wicket-warning" data-wicket-warning>Warning: invalid wickets.</p>
                        <?php } else { ?>
                            <label for="team_b_score">Goals</label>
                            <input type="number" id="team_b_score" name="team_b_score" min="0" step="1" placeholder="Enter Goals" required>
                        <?php } ?>
                    </fieldset>
                </div>

                <label for="game_date">Game Date</label>
                <input type="datetime-local" id="game_date" name="game_date" required>

                <div class="player-score-section" data-max-players="<?php echo $max_players; ?>" data-sport="<?php echo $is_cricket ? 'cricket' : 'football'; ?>">
                    <h3>You can add player scores</h3>
                    <p class="player-limit-note">Maximum <?php echo $max_players; ?> players per team.</p>
                    <p class="player-warning" data-player-warning>Warning: you cannot enter more than <?php echo $max_players; ?> players per team.</p>
                    <p class="player-warning" data-goal-warning>Warning: sum of player and team goals not matched</p>
                    <p class="player-warning" data-run-warning>Invalid runs: player runs plus extras must equal the team's total runs.</p>
                    <p class="player-warning" data-player-wicket-total-warning>Warning: invalid wickets.</p>
                    <p class="player-warning" data-over-total-warning>Invalid overs: sum of player overs cannot exceed the opposing team's total overs.</p>
                    <p class="player-warning" data-card-warning>Invalid cards: a player can only have 1 card (Yellow or Red).</p>
                    <p class="player-warning" data-rating-warning>Invalid rating: player ratings must be between 0 and 5.</p>
                    <div class="player-team-grid">
                        <div class="player-team" data-player-team="team_a_players">
                            <div class="player-team-header">
                                <h4>Team A Players</h4>
                                <button type="button" class="secondary-btn" data-add-player>Add Player</button>
                            </div>
                            <div class="player-list" data-player-list></div>
                        </div>

                        <div class="player-team" data-player-team="team_b_players">
                            <div class="player-team-header">
                                <h4>Team B Players</h4>
                                <button type="button" class="secondary-btn" data-add-player>Add Player</button>
                            </div>
                            <div class="player-list" data-player-list></div>
                        </div>
                    </div>
                </div>

                <button type="submit">Submit Score</button>
            </form>
            <?php
            if (isset($message)) {
                echo "<p class='success'>$message</p>";
            }
            if (isset($error_message)) {
                echo "<p class='error'>$error_message</p>";
            }
            ?>
        </div>
    </div>
    <script>
        const scoreForm = document.querySelector(".score-form form");
        const wicketInputs = document.querySelectorAll("[data-wicket-input]");
        const playerSection = document.querySelector(".player-score-section");
        const maxPlayers = playerSection ? Number(playerSection.dataset.maxPlayers) : 0;
        const sportType = playerSection ? playerSection.dataset.sport : "";
        const playerWarning = document.querySelector("[data-player-warning]");
        const goalWarning = document.querySelector("[data-goal-warning]");
        const runWarning = document.querySelector("[data-run-warning]");
        const playerWicketTotalWarning = document.querySelector("[data-player-wicket-total-warning]");
        const overTotalWarning = document.querySelector("[data-over-total-warning]");
        const cardWarning = document.querySelector("[data-card-warning]");
        const ratingWarning = document.querySelector("[data-rating-warning]");
        const cricketFormatSelect = document.querySelector("[data-cricket-format]");
        const formatOptionButtons = document.querySelectorAll("[data-format-option]");
        const formatOverWarning = document.querySelector("[data-format-over-warning]");
        const selectedFormatText = document.querySelector("[data-selected-format]");

        const formatLabels = {
            odi: "ODI",
            t20: "T20",
            custom: "Custom"
        };

        function getSelectedOverLimit() {
            if (!cricketFormatSelect) {
                return null;
            }

            if (cricketFormatSelect.value === "odi") {
                return 50;
            }

            if (cricketFormatSelect.value === "t20") {
                return 20;
            }

            return null;
        }

        function updateTeamOverLimits() {
            const overLimit = getSelectedOverLimit();

            document.querySelectorAll("#team_a_overs, #team_b_overs").forEach((input) => {
                if (overLimit === null) {
                    input.removeAttribute("max");
                } else {
                    input.max = overLimit;
                }
            });
        }

        function validateCricketFormatOvers() {
            if (sportType !== "cricket") {
                return true;
            }

            const hasSelectedFormat = cricketFormatSelect && cricketFormatSelect.value !== "";
            const overLimit = getSelectedOverLimit();
            let hasInvalidOvers = !hasSelectedFormat;

            if (cricketFormatSelect) {
                cricketFormatSelect.classList.toggle("input-warning", !hasSelectedFormat);
            }

            document.querySelectorAll("#team_a_overs, #team_b_overs").forEach((input) => {
                const value = Number(input.value || 0);
                const isInvalid = value < 0 || (overLimit !== null && value > overLimit);
                input.classList.toggle("input-warning", isInvalid);

                if (isInvalid) {
                    hasInvalidOvers = true;
                }
            });

            if (formatOverWarning) {
                formatOverWarning.classList.toggle("show", hasInvalidOvers);
            }

            return !hasInvalidOvers;
        }

        function validateWickets() {
            let hasInvalidWickets = false;

            wicketInputs.forEach((input) => {
                const warning = input.parentElement.querySelector("[data-wicket-warning]");
                const val = input.value;
                const value = Number(val);
                const isInvalid = val !== "" && (value < 0 || value > 10 || !Number.isInteger(value));

                input.classList.toggle("input-warning", isInvalid);
                warning.classList.toggle("show", isInvalid);

                if (isInvalid) {
                    hasInvalidWickets = true;
                }
            });

            return !hasInvalidWickets;
        }

        wicketInputs.forEach((input) => {
            input.addEventListener("input", validateWickets);
        });

        document.addEventListener("input", (event) => {
            if (event.target.matches("[data-over-input]")) {
                const input = event.target;
                if (!input.value) return;
                const value = parseFloat(input.value);
                if (isNaN(value)) return;

                const decimalPart = Math.round((value - Math.floor(value)) * 10);
                if (decimalPart > 5) {
                    input.value = Math.floor(value) + 1;
                }
            }
        });

        if (scoreForm) {
            scoreForm.addEventListener("submit", (event) => {
                updateCricketMilestones();
                if (!validateWickets() || !validateCricketFormatOvers() || !validatePlayerCounts() || !validatePlayerGoals() || !validateCricketPlayerTotals() || !validatePlayerCards() || !validateFootballRatings()) {
                    event.preventDefault();
                }
            });
        }

        function getPlayerRowFields(teamName) {
            if (sportType === "cricket") {
                return `
                    <input type="text" name="${teamName}[name][]" placeholder="Player Name">
                    <input type="number" name="${teamName}[runs][]" min="0" placeholder="Runs">
                    <input type="number" name="${teamName}[overs][]" min="0" step="0.1" placeholder="Overs" data-over-input>
                    <input type="number" name="${teamName}[wickets][]" min="0" max="10" step="1" placeholder="Wickets">
                    <input type="hidden" name="${teamName}[half_centuries][]" value="0" data-half-century-input>
                    <input type="hidden" name="${teamName}[centuries][]" value="0" data-century-input>
                `;
            }

            return `
                <input type="text" name="${teamName}[name][]" placeholder="Player Name">
                <input type="number" name="${teamName}[goals][]" min="0" step="1" placeholder="Goals">
                <input type="number" name="${teamName}[yellow_cards][]" min="0" max="1" step="1" placeholder="Yellow Cards">
                <input type="number" name="${teamName}[red_cards][]" min="0" max="1" step="1" placeholder="Red Cards">
                <input type="number" name="${teamName}[rating][]" min="0" max="5" step="0.1" placeholder="Rating">
            `;
        }

        function validatePlayerCounts() {
            let hasTooManyPlayers = false;

            document.querySelectorAll("[data-player-team]").forEach((team) => {
                const playerCount = team.querySelectorAll(".player-row").length;
                team.classList.toggle("player-limit-warning", playerCount > maxPlayers);

                if (playerCount > maxPlayers) {
                    hasTooManyPlayers = true;
                }
            });

            if (playerWarning) {
                playerWarning.classList.toggle("show", hasTooManyPlayers);
            }

            return !hasTooManyPlayers;
        }

        function validatePlayerGoals() {
            if (sportType !== "football") {
                return true;
            }

            const teamConfigs = [
                {
                    team: document.querySelector('[data-player-team="team_a_players"]'),
                    totalInput: document.querySelector("#team_a_score")
                },
                {
                    team: document.querySelector('[data-player-team="team_b_players"]'),
                    totalInput: document.querySelector("#team_b_score")
                }
            ];
            let hasTooManyGoals = false;

            teamConfigs.forEach((config) => {
                if (!config.team || !config.totalInput) {
                    return;
                }

                const teamGoalsValue = config.totalInput.value;
                const teamGoals = Number(teamGoalsValue || 0);
                let playerGoals = 0;
                let hasDecimal = teamGoalsValue !== "" && !Number.isInteger(Number(teamGoalsValue));

                config.team.querySelectorAll('input[name$="[goals][]"]').forEach((input) => {
                    const val = input.value;
                    playerGoals += Number(val || 0);
                    if (val !== "" && !Number.isInteger(Number(val))) {
                        hasDecimal = true;
                    }
                });

                const isInvalid = playerGoals > teamGoals || hasDecimal;
                config.team.classList.toggle("player-limit-warning", isInvalid);

                if (isInvalid) {
                    hasTooManyGoals = true;
                }
            });

            if (goalWarning) {
                goalWarning.classList.toggle("show", hasTooManyGoals);
            }

            return !hasTooManyGoals;
        }

        function validateCricketPlayerTotals() {
            if (sportType !== "cricket") {
                return true;
            }

            const teamConfigs = [
                {
                    team: document.querySelector('[data-player-team="team_a_players"]'),
                    runsInput: document.querySelector("#team_a_score"),
                    extrasInput: document.querySelector("#team_a_extras"),
                    wicketsInput: document.querySelector("#team_b_wickets"),
                    oversInput: document.querySelector("#team_b_overs")
                },
                {
                    team: document.querySelector('[data-player-team="team_b_players"]'),
                    runsInput: document.querySelector("#team_b_score"),
                    extrasInput: document.querySelector("#team_b_extras"),
                    wicketsInput: document.querySelector("#team_a_wickets"),
                    oversInput: document.querySelector("#team_a_overs")
                }
            ];
            let hasInvalidRuns = false;
            let hasInvalidWickets = false;
            let hasInvalidOvers = false;

            teamConfigs.forEach((config) => {
                if (!config.team || !config.runsInput || !config.extrasInput || !config.wicketsInput || !config.oversInput) {
                    return;
                }

                const teamRunsValue = config.runsInput.value;
                const extrasValue = config.extrasInput.value;
                const teamWicketsValue = config.wicketsInput.value;
                const teamRuns = Number(teamRunsValue || 0);
                const extras = Number(extrasValue || 0);
                const teamWickets = Number(teamWicketsValue || 0);
                const teamOvers = Number(config.oversInput.value || 0);
                const teamTotalBalls = Math.floor(teamOvers) * 6 + Math.round((teamOvers - Math.floor(teamOvers)) * 10);
                let playerRuns = 0;
                let playerWickets = 0;
                let playerBalls = 0;
                let hasDecimalInWickets = (teamWicketsValue !== "" && !Number.isInteger(Number(teamWicketsValue)));
                let hasDecimalInRuns = (teamRunsValue !== "" && !Number.isInteger(Number(teamRunsValue))) ||
                    (extrasValue !== "" && !Number.isInteger(Number(extrasValue)));

                config.team.querySelectorAll('input[name$="[runs][]"]').forEach((input) => {
                    const val = input.value;
                    playerRuns += Number(val || 0);
                    if (val !== "" && !Number.isInteger(Number(val))) {
                        hasDecimalInRuns = true;
                    }
                });

                config.team.querySelectorAll('input[name$="[wickets][]"]').forEach((input) => {
                    const val = input.value;
                    playerWickets += Number(val || 0);
                    if (val !== "" && !Number.isInteger(Number(val))) {
                        hasDecimalInWickets = true;
                    }
                });

                config.team.querySelectorAll('input[name$="[overs][]"]').forEach((input) => {
                    const o = Number(input.value || 0);
                    playerBalls += Math.floor(o) * 6 + Math.round((o - Math.floor(o)) * 10);
                });

                const invalidRuns = playerRuns + extras !== teamRuns || extras < 0 || hasDecimalInRuns;
                const invalidWickets = playerWickets > teamWickets || hasDecimalInWickets;
                const invalidOvers = playerBalls > teamTotalBalls;

                config.team.classList.toggle("player-limit-warning", invalidRuns || invalidWickets || invalidOvers);
                config.runsInput.classList.toggle("input-warning", invalidRuns);
                config.extrasInput.classList.toggle("input-warning", invalidRuns);

                if (invalidRuns) {
                    hasInvalidRuns = true;
                }

                if (invalidWickets) {
                    hasInvalidWickets = true;
                }

                if (invalidOvers) {
                    hasInvalidOvers = true;
                }
            });

            if (runWarning) {
                runWarning.classList.toggle("show", hasInvalidRuns);
            }

            if (playerWicketTotalWarning) {
                playerWicketTotalWarning.classList.toggle("show", hasInvalidWickets);
            }

            if (overTotalWarning) {
                overTotalWarning.classList.toggle("show", hasInvalidOvers);
            }

            return !hasInvalidRuns && !hasInvalidWickets && !hasInvalidOvers;
        }

        function validatePlayerCards() {
            if (sportType !== "football") {
                return true;
            }

            let hasInvalidCards = false;

            document.querySelectorAll(".player-row").forEach(row => {
                const ycInput = row.querySelector('input[name$="[yellow_cards][]"]');
                const rcInput = row.querySelector('input[name$="[red_cards][]"]');
                
                if (ycInput && rcInput) {
                    const yc = Number(ycInput.value || 0);
                    const rc = Number(rcInput.value || 0);
                    const isInvalid = yc > 1 || rc > 1 || (yc + rc > 1);

                    ycInput.classList.toggle("input-warning", isInvalid);
                    rcInput.classList.toggle("input-warning", isInvalid);

                    if (isInvalid) hasInvalidCards = true;
                }
            });

            if (cardWarning) cardWarning.classList.toggle("show", hasInvalidCards);

            return !hasInvalidCards;
        }

        function validateFootballRatings() {
            if (sportType !== "football") {
                return true;
            }

            let hasInvalidRating = false;

            document.querySelectorAll('input[name$="[rating][]"]').forEach((input) => {
                const value = input.value;
                const numberValue = Number(value);
                const isInvalid = value !== "" && (numberValue < 0 || numberValue > 5 || Number.isNaN(numberValue));
                input.classList.toggle("input-warning", isInvalid);

                if (isInvalid) {
                    hasInvalidRating = true;
                }
            });

            if (ratingWarning) {
                ratingWarning.classList.toggle("show", hasInvalidRating);
            }

            return !hasInvalidRating;
        }

        function updateCricketMilestones() {
            if (sportType !== "cricket") {
                return;
            }

            document.querySelectorAll(".player-row").forEach(row => {
                const runsInput = row.querySelector('input[name$="[runs][]"]');
                const fiftiesInput = row.querySelector('input[name$="[half_centuries][]"]');
                const hundredsInput = row.querySelector('input[name$="[centuries][]"]');

                if (runsInput && fiftiesInput && hundredsInput) {
                    const runs = Number(runsInput.value || 0);
                    fiftiesInput.value = runs >= 50 && runs < 100 ? "1" : "0";
                    hundredsInput.value = runs >= 100 ? "1" : "0";
                }
            });
        }

        document.querySelectorAll("[data-add-player]").forEach((button) => {
            button.addEventListener("click", () => {
                const team = button.closest("[data-player-team]");
                const list = team.querySelector("[data-player-list]");
                const teamName = team.dataset.playerTeam;
                const row = document.createElement("div");

                row.className = "player-row";
                row.innerHTML = `
                    <div class="player-fields">
                        ${getPlayerRowFields(teamName)}
                    </div>
                    <button type="button" class="remove-player-btn" aria-label="Remove player">Remove</button>
                `;

                list.appendChild(row);
                row.querySelector(".remove-player-btn").addEventListener("click", () => {
                    row.remove();
                    validatePlayerCounts();
                    validatePlayerGoals();
                    validateCricketPlayerTotals();
                });
                row.querySelectorAll("input").forEach((input) => {
                    input.addEventListener("input", validatePlayerGoals);
                    input.addEventListener("input", validateCricketPlayerTotals);
                    input.addEventListener("input", validatePlayerCards);
                    input.addEventListener("input", validateFootballRatings);
                    input.addEventListener("input", updateCricketMilestones);
                });
                validatePlayerCounts();
                validatePlayerGoals();
                validateCricketPlayerTotals();
                validatePlayerCards();
                validateFootballRatings();
                updateCricketMilestones();
            });
        });

        document.querySelectorAll("#team_a_score, #team_b_score, #team_a_extras, #team_b_extras, #team_a_overs, #team_b_overs, #team_a_wickets, #team_b_wickets").forEach((input) => {
            input.addEventListener("input", validatePlayerGoals);
            input.addEventListener("input", validateCricketPlayerTotals);
            input.addEventListener("input", validateCricketFormatOvers);
        });

        if (cricketFormatSelect) {
            formatOptionButtons.forEach((button) => {
                button.addEventListener("click", () => {
                    cricketFormatSelect.value = button.dataset.formatOption;
                    if (selectedFormatText) {
                        selectedFormatText.textContent = "Format: " + formatLabels[cricketFormatSelect.value];
                    }
                    formatOptionButtons.forEach((option) => {
                        const isSelected = option === button;
                        option.classList.toggle("is-selected", isSelected);
                        option.setAttribute("aria-pressed", isSelected ? "true" : "false");
                    });
                    updateTeamOverLimits();
                    validateCricketFormatOvers();
                    validateCricketPlayerTotals();
                });
            });
            updateTeamOverLimits();
        }
    </script>
</body>
</html>
