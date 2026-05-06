<?php
session_start();
include('includes/db.php');

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

if (!empty($_SESSION['is_admin'])) {
    header("Location: admin_dashboard.php");
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
$sql = "SELECT * FROM sports";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="dashboard-page">
    <div class="dashboard-container">
        <header>
            <h1>Welcome to the Scoreboard, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </header>
        <div class="dashboard-layout">
            <aside class="dashboard-sidebar">
                <h2>Quick actions</h2>
                <a href="past_scores.php" class="dashboard-action-btn">Watch Past Scores</a>
                <a href="individual_score.php" class="dashboard-action-btn">Individual Score</a>
                <a href="scheduled_match.php" class="dashboard-action-btn">Scheduled Match</a>
            </aside>

            <div class="sports-list">
                <h2>Select a sport</h2>
                <?php while ($row = $result->fetch_assoc()) { ?>
                    <a href="game.php?sport_id=<?php echo $row['sport_id']; ?>" class="sport">
                        <?php echo $row['sport_name']; ?>
                    </a>
                <?php } ?>
            </div>
        </div>
    </div>
</body>
</html>
