<?php
session_start();
if (isset($_SESSION['username'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scoreboard Home</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="home-page">
    <div class="home-container">
        <header>
            <h1>Welcome to the Sports Scoreboard</h1>
            <?php if (isset($_SESSION['username'])) { ?>
                <a href="logout.php" class="logout-btn">Logout</a>
            <?php } ?>
        </header>
        <div class="intro">
            <p>Track live scores for various sports like Cricket and Football.</p>
            <p>Login to see upcoming games, scores, and head-to-head stats!</p>
        </div>
        <div class="actions">
            <a href="login.php" class="btn">Login</a>
            <a href="register.php" class="btn">Sign Up</a>
        </div>
    </div>
</body>
</html>
