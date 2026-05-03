<?php
session_start();
include('includes/db.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $sql = "SELECT * FROM users WHERE username='$username' AND password='$password'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $_SESSION['username'] = $username;
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['is_admin'] = strtolower($user['username']) === 'admin';

        if ($_SESSION['is_admin']) {
            header("Location: admin_dashboard.php");
        } else {
            header("Location: dashboard.php");
        }
        exit();
    } else {
        $error = "Invalid username or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-page">
    <?php if (isset($_SESSION['username'])) { ?>
        <div class="page-actions">
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    <?php } ?>
    <div class="login-container">
        <div class="welcome-text">
            <h1 class="modern-title">Welcome to One Scoreboard</h1>
            <p class="modern-subtitle">Please login to store your team scores</p>
        </div>
        <h2>Login</h2>
        <?php if (isset($error)) { echo "<p class='error'>$error</p>"; } ?>
        <form method="POST" action="login.php">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        <div class="admin-login-option">
            <a href="admin_login.php" class="dashboard-action-btn">Admin Login</a>
        </div>
    </div>
</body>
</html>
