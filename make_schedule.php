<?php
session_start();
include('includes/db.php');
include('includes/schedule_helpers.php');

$user_id = requireLoggedInUser($conn);

if (isset($_SESSION['schedule_message'])) {
    $message = $_SESSION['schedule_message'];
    unset($_SESSION['schedule_message']);
}

if (isset($_SESSION['schedule_error'])) {
    $error = $_SESSION['schedule_error'];
    unset($_SESSION['schedule_error']);
}

$sports = $conn->query("SELECT sport_id, sport_name FROM sports ORDER BY sport_name ASC");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $sport_id = (int) ($_POST['sport_id'] ?? 0);
    $team_a_name = trim($_POST['team_a'] ?? '');
    $team_b_name = trim($_POST['team_b'] ?? '');
    $match_date = trim($_POST['match_date'] ?? '');
    $today = date('Y-m-d');

    $stmt = $conn->prepare("SELECT sport_id FROM sports WHERE sport_id = ? LIMIT 1");
    $stmt->bind_param("i", $sport_id);
    $stmt->execute();
    $sport = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$sport) {
        $_SESSION['schedule_error'] = "Please select a valid sport.";
    } elseif ($team_a_name === '' || $team_b_name === '') {
        $_SESSION['schedule_error'] = "Please enter both team names.";
    } elseif (strcasecmp($team_a_name, $team_b_name) === 0) {
        $_SESSION['schedule_error'] = "Team names must be different.";
    } elseif ($match_date === '' || date('Y-m-d', strtotime($match_date)) < $today) {
        $_SESSION['schedule_error'] = "Schedule date cannot be in the past.";
    } else {
        $team_a_id = getOrCreateTeamId($conn, $sport_id, $team_a_name);
        $team_b_id = getOrCreateTeamId($conn, $sport_id, $team_b_name);
        $formatted_match_date = date('Y-m-d H:i:s', strtotime($match_date));

        $stmt = $conn->prepare("INSERT INTO scheduled_matches (user_id, sport_id, team_a_id, team_b_id, match_date)
                                VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiis", $user_id, $sport_id, $team_a_id, $team_b_id, $formatted_match_date);

        if ($stmt->execute()) {
            $_SESSION['schedule_message'] = "Schedule saved successfully.";
            $stmt->close();
            header("Location: scheduled_matches.php");
            exit();
        }

        $_SESSION['schedule_error'] = "Could not save schedule: " . $conn->error;
        $stmt->close();
    }

    header("Location: make_schedule.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make a Schedule</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="game-container">
        <header>
            <h1>Make a Schedule</h1>
            <div class="header-actions">
                <a href="scheduled_match.php" class="header-link">Schedule Options</a>
                <a href="dashboard.php" class="header-link">Back to Dashboard</a>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

        <section class="score-form schedule-form-panel">
            <h2>New Scheduled Match</h2>
            <?php if (isset($message)) { echo "<p class='success'>" . htmlspecialchars($message) . "</p>"; } ?>
            <?php if (isset($error)) { echo "<p class='error'>" . htmlspecialchars($error) . "</p>"; } ?>
            <form method="POST" action="make_schedule.php">
                <fieldset class="schedule-sport-section">
                    <legend>Sport</legend>
                    <input type="hidden" id="sport_id" name="sport_id" data-schedule-sport-input>
                    <div class="schedule-sport-grid" role="group" aria-label="Select sport">
                    <?php while ($sport = $sports->fetch_assoc()) { ?>
                        <button type="button" class="schedule-sport-btn" data-schedule-sport="<?php echo htmlspecialchars($sport['sport_id']); ?>">
                            <?php echo htmlspecialchars($sport['sport_name']); ?>
                        </button>
                    <?php } ?>
                    </div>
                    <p class="player-warning" data-schedule-sport-warning>Please select a sport.</p>
                </fieldset>

                <div class="team-score-grid">
                    <fieldset>
                        <legend>Team A</legend>
                        <label for="team_a">Team Name</label>
                        <input type="text" id="team_a" name="team_a" placeholder="Enter Team A Name" required>
                    </fieldset>
                    <fieldset>
                        <legend>Team B</legend>
                        <label for="team_b">Team Name</label>
                        <input type="text" id="team_b" name="team_b" placeholder="Enter Team B Name" required>
                    </fieldset>
                </div>

                <label for="match_date">Match Date</label>
                <input type="datetime-local" id="match_date" name="match_date" min="<?php echo date('Y-m-d'); ?>T00:00" required>

                <button type="submit">Save Schedule</button>
            </form>
        </section>
    </div>
    <script>
        const scheduleForm = document.querySelector(".schedule-form-panel form");
        const scheduleSportInput = document.querySelector("[data-schedule-sport-input]");
        const scheduleSportButtons = document.querySelectorAll("[data-schedule-sport]");
        const scheduleSportWarning = document.querySelector("[data-schedule-sport-warning]");

        scheduleSportButtons.forEach((button) => {
            button.addEventListener("click", () => {
                scheduleSportInput.value = button.dataset.scheduleSport;

                scheduleSportButtons.forEach((sportButton) => {
                    sportButton.classList.toggle("is-selected", sportButton === button);
                });

                if (scheduleSportWarning) {
                    scheduleSportWarning.classList.remove("show");
                }
            });
        });

        if (scheduleForm) {
            scheduleForm.addEventListener("submit", (event) => {
                const hasSelectedSport = scheduleSportInput && scheduleSportInput.value !== "";

                if (!hasSelectedSport) {
                    event.preventDefault();
                    if (scheduleSportWarning) {
                        scheduleSportWarning.classList.add("show");
                    }
                }
            });
        }
    </script>
</body>
</html>
