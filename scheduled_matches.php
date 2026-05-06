<?php
session_start();
include('includes/db.php');
include('includes/schedule_helpers.php');

$user_id = requireLoggedInUser($conn);

if (isset($_SESSION['schedule_message'])) {
    $message = $_SESSION['schedule_message'];
    unset($_SESSION['schedule_message']);
}

if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

$stmt = $conn->prepare("SELECT scheduled_matches.*, sports.sport_name,
                               team_a.team_name AS team_a, team_b.team_name AS team_b
                        FROM scheduled_matches
                        INNER JOIN sports ON scheduled_matches.sport_id = sports.sport_id
                        INNER JOIN teams AS team_a ON scheduled_matches.team_a_id = team_a.team_id
                        INNER JOIN teams AS team_b ON scheduled_matches.team_b_id = team_b.team_id
                        WHERE scheduled_matches.user_id = ?
                          AND scheduled_matches.status = 'scheduled'
                        ORDER BY scheduled_matches.match_date ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$scheduled_matches = $stmt->get_result();
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
                <a href="make_schedule.php" class="header-link">Make a Schedule</a>
                <a href="scheduled_match.php" class="header-link">Schedule Options</a>
                <a href="dashboard.php" class="header-link">Back to Dashboard</a>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

        <section class="games-list schedule-list">
            <h2>Select a Scheduled Match</h2>
            <?php if (isset($message)) { echo "<p class='success schedule-full-row'>" . htmlspecialchars($message) . "</p>"; } ?>
            <?php if (isset($error)) { echo "<p class='error schedule-full-row'>" . htmlspecialchars($error) . "</p>"; } ?>
            <?php if ($scheduled_matches->num_rows > 0) { ?>
                <?php while ($match = $scheduled_matches->fetch_assoc()) { ?>
                    <article class="game-item schedule-match-card">
                        <div class="schedule-meta">
                            <span><?php echo htmlspecialchars($match['sport_name']); ?></span>
                            <time><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($match['match_date']))); ?></time>
                        </div>
                        <h3><?php echo htmlspecialchars($match['team_a']); ?> vs <?php echo htmlspecialchars($match['team_b']); ?></h3>
                        <a class="h2h-btn" href="game.php?sport_id=<?php echo htmlspecialchars($match['sport_id']); ?>&schedule_id=<?php echo htmlspecialchars($match['schedule_id']); ?>#team-submit">
                            Enter Score
                        </a>
                    </article>
                <?php } ?>
            <?php } else { ?>
                <div class="empty-schedule schedule-full-row">
                    <h3>No scheduled matches yet.</h3>
                    <p>Create a schedule first, then it will appear here.</p>
                    <a href="make_schedule.php" class="dashboard-action-btn">Make a Schedule</a>
                </div>
            <?php } ?>
        </section>
    </div>
</body>
</html>
<?php $stmt->close(); ?>
