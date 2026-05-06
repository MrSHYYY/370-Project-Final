<?php
session_start();
include('includes/db.php');
include('includes/schedule_helpers.php');

requireLoggedInUser($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scheduled Match</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="game-container">
        <header>
            <h1>Scheduled Match</h1>
            <div class="header-actions">
                <a href="dashboard.php" class="header-link">Back to Dashboard</a>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

        <section class="schedule-choice-panel">
            <h2>Choose an action</h2>
            <div class="schedule-choice-grid">
                <a href="make_schedule.php" class="schedule-choice-card">
                    <span>1</span>
                    <strong>Make a Schedule</strong>
                    <p>Add teams, sport, and match date.</p>
                </a>
                <a href="scheduled_matches.php" class="schedule-choice-card">
                    <span>2</span>
                    <strong>Scheduled Match</strong>
                    <p>Open a saved match and enter its score.</p>
                </a>
            </div>
        </section>
    </div>
</body>
</html>
