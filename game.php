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
            $wickets = (int) ($players['wickets'][$index] ?? 0);
            $half_centuries = (int) ($players['half_centuries'][$index] ?? 0);
            $centuries = (int) ($players['centuries'][$index] ?? 0);
            $goals = null;
            $yellow_cards = null;
            $red_cards = null;
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
            $score = $goals;
        }

        $stmt = $conn->prepare("INSERT INTO player_scores (
                                    game_id, player_id, score, runs, overs, wickets,
                                    half_centuries, centuries, goals, yellow_cards, red_cards
                                )
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "iiiidiiiiii",
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
            $red_cards
        );
        $stmt->execute();
        $stmt->close();
    }
}

function updateHeadToHead($conn, $user_id, $team_a_id, $team_b_id, $team_a_score, $team_b_score) {
    $winner_team_id = null;

    if ($team_a_score > $team_b_score) {
        $winner_team_id = $team_a_id;
    } elseif ($team_b_score > $team_a_score) {
        $winner_team_id = $team_b_id;
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
        $team_a_wins = $winner_team_id === $team_a_id ? 1 : 0;
        $team_b_wins = $winner_team_id === $team_b_id ? 1 : 0;

        $stmt = $conn->prepare("INSERT INTO head_to_head (user_id, team_a_id, team_b_id, team_a_wins, team_b_wins)
                                VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiii", $user_id, $team_a_id, $team_b_id, $team_a_wins, $team_b_wins);
        $stmt->execute();
        $stmt->close();
        return;
    }

    if ($winner_team_id === null) {
        return;
    }

    if ((int) $head_to_head['team_a_id'] === $winner_team_id) {
        $stmt = $conn->prepare("UPDATE head_to_head SET team_a_wins = team_a_wins + 1 WHERE head_to_head_id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE head_to_head SET team_b_wins = team_b_wins + 1 WHERE head_to_head_id = ?");
    }

    $stmt->bind_param("i", $head_to_head['head_to_head_id']);
    $stmt->execute();
    $stmt->close();
}

function savePastScore($conn, $game_id, $user_id, $sport_id, $team_a_id, $team_b_id) {
    $stmt = $conn->prepare("INSERT IGNORE INTO past_scores (game_id, user_id, sport_id, team_a_id, team_b_id)
                            VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiii", $game_id, $user_id, $sport_id, $team_a_id, $team_b_id);
    $stmt->execute();
    $stmt->close();
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
        $team_a_wickets = $is_cricket ? (int) $_POST['team_a_wickets'] : null;
        $team_b_wickets = $is_cricket ? (int) $_POST['team_b_wickets'] : null;
        $team_a_players = $_POST['team_a_players'] ?? [];
        $team_b_players = $_POST['team_b_players'] ?? [];
        $game_date = $_POST['game_date'];

        if ($is_cricket && ($team_a_wickets < 0 || $team_a_wickets > 10 || $team_b_wickets < 0 || $team_b_wickets > 10)) {
            $_SESSION['error_message'] = "Wickets must be between 0 and 10.";
            header("Location: game.php?sport_id=" . $sport_id . "#team-submit");
            exit();
        }

        if (countSubmittedPlayers($team_a_players) > $max_players || countSubmittedPlayers($team_b_players) > $max_players) {
            $_SESSION['error_message'] = "You can enter a maximum of " . $max_players . " players per team for " . htmlspecialchars($sport['sport_name']) . ".";
            header("Location: game.php?sport_id=" . $sport_id . "#team-submit");
            exit();
        }

        if ($is_football && (sumPlayerGoals($team_a_players) > $team_a_score || sumPlayerGoals($team_b_players) > $team_b_score)) {
            $_SESSION['error_message'] = "Invalid goals: player goals cannot be more than the team's total goals.";
            header("Location: game.php?sport_id=" . $sport_id . "#team-submit");
            exit();
        }

        if ($is_cricket && (sumPlayerStat($team_a_players, 'runs') > $team_a_score || sumPlayerStat($team_b_players, 'runs') > $team_b_score)) {
            $_SESSION['error_message'] = "Invalid runs: player runs cannot be more than the team's total runs.";
            header("Location: game.php?sport_id=" . $sport_id . "#team-submit");
            exit();
        }

        if ($is_cricket && (sumPlayerStat($team_a_players, 'wickets') > $team_a_wickets || sumPlayerStat($team_b_players, 'wickets') > $team_b_wickets)) {
            $_SESSION['error_message'] = "Invalid wickets: player wickets cannot be more than the team's total wickets.";
            header("Location: game.php?sport_id=" . $sport_id . "#team-submit");
            exit();
        }

        $team_a_id = getTeamId($conn, $sport_id, $team_a_name);
        $team_b_id = getTeamId($conn, $sport_id, $team_b_name);

        // Insert the match scores into the database
        $stmt = $conn->prepare("INSERT INTO games (
                                    user_id, sport_id, team_a_id, team_b_id,
                                    team_a_score, team_a_overs, team_a_wickets,
                                    team_b_score, team_b_overs, team_b_wickets,
                                    game_date
                                )
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "iiiiidiidis",
            $user_id,
            $sport_id,
            $team_a_id,
            $team_b_id,
            $team_a_score,
            $team_a_overs,
            $team_a_wickets,
            $team_b_score,
            $team_b_overs,
            $team_b_wickets,
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
                        $team_a_result = htmlspecialchars($game['team_a_score']) . "/" . htmlspecialchars($game['team_a_wickets']) . " in " . htmlspecialchars($game['team_a_overs']) . " overs";
                        $team_b_result = htmlspecialchars($game['team_b_score']) . "/" . htmlspecialchars($game['team_b_wickets']) . " in " . htmlspecialchars($game['team_b_overs']) . " overs";
                    } else {
                        $team_a_result = htmlspecialchars($game['team_a_score']) . " goals";
                        $team_b_result = htmlspecialchars($game['team_b_score']) . " goals";
                    }

                    echo "<div class='game-item'>
                            <h3>" . htmlspecialchars($game['team_a']) . " vs " . htmlspecialchars($game['team_b']) . "</h3>
                            <p>Date: " . htmlspecialchars($game['game_date']) . "</p>
                            <div class='score-breakdown'>
                                <div>
                                    <span>" . htmlspecialchars($game['team_a']) . "</span>
                                    <strong>" . $team_a_result . "</strong>
                                </div>
                                <div>
                                    <span>" . htmlspecialchars($game['team_b']) . "</span>
                                    <strong>" . $team_b_result . "</strong>
                                </div>
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
                <div class="team-score-grid">
                    <fieldset>
                        <legend>Team A</legend>

                        <label for="team_a">Team Name</label>
                        <input type="text" id="team_a" name="team_a" placeholder="Enter Team A Name" required>

                        <?php if ($is_cricket) { ?>
                            <label for="team_a_score">Runs</label>
                            <input type="number" id="team_a_score" name="team_a_score" min="0" placeholder="Enter Runs" required>

                            <label for="team_a_overs">Overs</label>
                            <input type="number" id="team_a_overs" name="team_a_overs" min="0" step="0.1" placeholder="Enter Overs" required>

                            <label for="team_a_wickets">Wickets</label>
                            <input type="number" id="team_a_wickets" name="team_a_wickets" min="0" max="10" placeholder="Enter Wickets" data-wicket-input required>
                            <p class="wicket-warning" data-wicket-warning>Warning: wickets cannot be more than 10.</p>
                        <?php } else { ?>
                            <label for="team_a_score">Goals</label>
                            <input type="number" id="team_a_score" name="team_a_score" min="0" placeholder="Enter Goals" required>
                        <?php } ?>
                    </fieldset>

                    <fieldset>
                        <legend>Team B</legend>

                        <label for="team_b">Team Name</label>
                        <input type="text" id="team_b" name="team_b" placeholder="Enter Team B Name" required>

                        <?php if ($is_cricket) { ?>
                            <label for="team_b_score">Runs</label>
                            <input type="number" id="team_b_score" name="team_b_score" min="0" placeholder="Enter Runs" required>

                            <label for="team_b_overs">Overs</label>
                            <input type="number" id="team_b_overs" name="team_b_overs" min="0" step="0.1" placeholder="Enter Overs" required>

                            <label for="team_b_wickets">Wickets</label>
                            <input type="number" id="team_b_wickets" name="team_b_wickets" min="0" max="10" placeholder="Enter Wickets" data-wicket-input required>
                            <p class="wicket-warning" data-wicket-warning>Warning: wickets cannot be more than 10.</p>
                        <?php } else { ?>
                            <label for="team_b_score">Goals</label>
                            <input type="number" id="team_b_score" name="team_b_score" min="0" placeholder="Enter Goals" required>
                        <?php } ?>
                    </fieldset>
                </div>

                <label for="game_date">Game Date</label>
                <input type="datetime-local" id="game_date" name="game_date" required>

                <div class="player-score-section" data-max-players="<?php echo $max_players; ?>" data-sport="<?php echo $is_cricket ? 'cricket' : 'football'; ?>">
                    <h3>You can add player scores</h3>
                    <p class="player-limit-note">Maximum <?php echo $max_players; ?> players per team.</p>
                    <p class="player-warning" data-player-warning>Warning: you cannot enter more than <?php echo $max_players; ?> players per team.</p>
                    <p class="player-warning" data-goal-warning>Invalid goals: player goals cannot be more than the team's total goals.</p>
                    <p class="player-warning" data-run-warning>Invalid runs: player runs cannot be more than the team's total runs.</p>
                    <p class="player-warning" data-player-wicket-total-warning>Invalid wickets: player wickets cannot be more than the team's total wickets.</p>

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

        function validateWickets() {
            let hasInvalidWickets = false;

            wicketInputs.forEach((input) => {
                const warning = input.parentElement.querySelector("[data-wicket-warning]");
                const value = Number(input.value);
                const isInvalid = input.value !== "" && (value < 0 || value > 10);

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

        if (scoreForm) {
            scoreForm.addEventListener("submit", (event) => {
                if (!validateWickets() || !validatePlayerCounts() || !validatePlayerGoals() || !validateCricketPlayerTotals()) {
                    event.preventDefault();
                }
            });
        }

        function getPlayerRowFields(teamName) {
            if (sportType === "cricket") {
                return `
                    <input type="text" name="${teamName}[name][]" placeholder="Player Name">
                    <input type="number" name="${teamName}[runs][]" min="0" placeholder="Runs">
                    <input type="number" name="${teamName}[overs][]" min="0" step="0.1" placeholder="Overs">
                    <input type="number" name="${teamName}[wickets][]" min="0" max="10" placeholder="Wickets">
                    <input type="number" name="${teamName}[half_centuries][]" min="0" placeholder="50's">
                    <input type="number" name="${teamName}[centuries][]" min="0" placeholder="100's">
                `;
            }

            return `
                <input type="text" name="${teamName}[name][]" placeholder="Player Name">
                <input type="number" name="${teamName}[goals][]" min="0" placeholder="Goals">
                <input type="number" name="${teamName}[yellow_cards][]" min="0" placeholder="Yellow Cards">
                <input type="number" name="${teamName}[red_cards][]" min="0" placeholder="Red Cards">
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

                const teamGoals = Number(config.totalInput.value || 0);
                let playerGoals = 0;

                config.team.querySelectorAll('input[name$="[goals][]"]').forEach((input) => {
                    playerGoals += Number(input.value || 0);
                });

                config.team.classList.toggle("player-limit-warning", playerGoals > teamGoals);

                if (playerGoals > teamGoals) {
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
                    wicketsInput: document.querySelector("#team_a_wickets")
                },
                {
                    team: document.querySelector('[data-player-team="team_b_players"]'),
                    runsInput: document.querySelector("#team_b_score"),
                    wicketsInput: document.querySelector("#team_b_wickets")
                }
            ];
            let hasInvalidRuns = false;
            let hasInvalidWickets = false;

            teamConfigs.forEach((config) => {
                if (!config.team || !config.runsInput || !config.wicketsInput) {
                    return;
                }

                const teamRuns = Number(config.runsInput.value || 0);
                const teamWickets = Number(config.wicketsInput.value || 0);
                let playerRuns = 0;
                let playerWickets = 0;

                config.team.querySelectorAll('input[name$="[runs][]"]').forEach((input) => {
                    playerRuns += Number(input.value || 0);
                });

                config.team.querySelectorAll('input[name$="[wickets][]"]').forEach((input) => {
                    playerWickets += Number(input.value || 0);
                });

                const invalidRuns = playerRuns > teamRuns;
                const invalidWickets = playerWickets > teamWickets;

                config.team.classList.toggle("player-limit-warning", invalidRuns || invalidWickets);

                if (invalidRuns) {
                    hasInvalidRuns = true;
                }

                if (invalidWickets) {
                    hasInvalidWickets = true;
                }
            });

            if (runWarning) {
                runWarning.classList.toggle("show", hasInvalidRuns);
            }

            if (playerWicketTotalWarning) {
                playerWicketTotalWarning.classList.toggle("show", hasInvalidWickets);
            }

            return !hasInvalidRuns && !hasInvalidWickets;
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
                });
                validatePlayerCounts();
                validatePlayerGoals();
                validateCricketPlayerTotals();
            });
        });

        document.querySelectorAll("#team_a_score, #team_b_score, #team_a_wickets, #team_b_wickets").forEach((input) => {
            input.addEventListener("input", validatePlayerGoals);
            input.addEventListener("input", validateCricketPlayerTotals);
        });
    </script>
</body>
</html>
