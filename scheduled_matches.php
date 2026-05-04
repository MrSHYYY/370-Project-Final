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

// Helper function to get or create team ID (copied from game.php, could be moved to includes/functions.php)
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

// Handle adding a new schedule
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_schedule'])) {
    $sport_id = (int) $_POST['sport_id'];
    $team_a_name = $_POST['team_a'];
    $team_b_name = $_POST['team_b'];
    $scheduled_date = $_POST['scheduled_date'];

    if (empty($team_a_name) || empty($team_b_name) || empty($scheduled_date) || $sport_id <= 0) {
        $_SESSION['error_message'] = "Please fill all fields to schedule a match.";
    } else {
        $team_a_id = getTeamId($conn, $sport_id, $team_a_name);
        $team_b_id = getTeamId($conn, $sport_id, $team_b_name);

        $stmt = $conn->prepare("INSERT INTO scheduled_matches (user_id, sport_id, team_a_id, team_b_id, scheduled_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiis", $user_id, $sport_id, $team_a_id, $team_b_id, $scheduled_date);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Match scheduled successfully!";
        } else {
            $_SESSION['error_message'] = "Error scheduling match: " . $conn->error;
        }
        $stmt->close();
    }
    header("Location: scheduled_matches.php");
    exit();
}

// Handle deleting a schedule
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_schedule'])) {
    $schedule_id = (int) $_POST['schedule_id'];

    $stmt = $conn->prepare("DELETE FROM scheduled_matches WHERE schedule_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $schedule_id, $user_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Scheduled match deleted.";
    } else {
        $_SESSION['error_message'] = "Error deleting scheduled match: " . $conn->error;
    }
    $stmt->close();
    header("Location: scheduled_matches.php");
    exit();
}

// Fetch available sports for the form
$sports_query = $conn->query("SELECT sport_id, sport_name FROM sports ORDER BY sport_name ASC");

// Fetch scheduled matches for the current user
$stmt = $conn->prepare("SELECT sm.*, s.sport_name, ta.team_name AS team_a_name, tb.team_name AS team_b_name
                        FROM scheduled_matches sm
                        JOIN sports s ON sm.sport_id = s.sport_id
                        JOIN teams ta ON sm.team_a_id = ta.team_id
                        JOIN teams tb ON sm.team_b_id = tb.team_id
                        WHERE sm.user_id = ? AND sm.is_completed = FALSE
                        ORDER BY sm.scheduled_date ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$scheduled_matches = $stmt->get_result();
$stmt->close();

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scheduled Matches</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="game-container">
        <header>
            <h1>Scheduled Matches</h1>
            <div class="header-actions">
                <a href="dashboard.php" class="header-link">Back to Dashboard</a>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

        <?php if (isset($message)) { echo "<p class='success'>$message</p>"; } ?>
        <?php if (isset($error_message)) { echo "<p class='error'>$error_message</p>"; } ?>

        <div class="score-form">
            <h2>Add New Scheduled Match</h2>
            <form method="POST" action="scheduled_matches.php">
                <input type="hidden" name="add_schedule" value="1">
                <label for="sport_id">Sport</label>
                <select id="sport_id" name="sport_id" required>
                    <option value="">Select Sport</option>
                    <?php while ($sport = $sports_query->fetch_assoc()) { ?>
                        <option value="<?php echo htmlspecialchars($sport['sport_id']); ?>"><?php echo htmlspecialchars($sport['sport_name']); ?></option>
                    <?php } ?>
                </select>

                <label for="team_a">Team A Name</label>
                <input type="text" id="team_a" name="team_a" placeholder="Enter Team A Name" required>

                <label for="team_b">Team B Name</label>
                <input type="text" id="team_b" name="team_b" placeholder="Enter Team B Name" required>

                <label for="scheduled_date">Scheduled Date & Time</label>
                <input type="datetime-local" id="scheduled_date" name="scheduled_date" required>

                <button type="submit">Schedule Match</button>
            </form>
        </div>

        <div class="games-list">
            <h2>Upcoming Matches</h2>
            <?php if ($scheduled_matches->num_rows > 0) { ?>
                <?php while ($match = $scheduled_matches->fetch_assoc()) { ?>
                    <div class="game-item">
                        <h3><?php echo htmlspecialchars($match['team_a_name']); ?> vs <?php echo htmlspecialchars($match['team_b_name']); ?></h3>
                        <p>Sport: <?php echo htmlspecialchars($match['sport_name']); ?></p>
                        <p>Scheduled: <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($match['scheduled_date']))); ?></p>
                        <div class="past-score-actions">
                            <a class="h2h-btn" href="game.php?sport_id=<?php echo htmlspecialchars($match['sport_id']); ?>&schedule_id=<?php echo htmlspecialchars($match['schedule_id']); ?>">Enter Score</a>
                            <form method="POST" action="scheduled_matches.php" onsubmit="return confirm('Are you sure you want to delete this scheduled match?');">
                                <input type="hidden" name="delete_schedule" value="1">
                                <input type="hidden" name="schedule_id" value="<?php echo htmlspecialchars($match['schedule_id']); ?>">
                                <button type="submit" class="danger-btn">Delete Schedule</button>
                            </form>
                        </div>
                    </div>
                <?php } ?>
            <?php } else { ?>
                <p>No upcoming matches scheduled. Add one above!</p>
            <?php } ?>
        </div>
    </div>
</body>
</html>