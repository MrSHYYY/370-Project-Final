<?php
// Register new users
session_start();
include('includes/db.php');

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Check if username already exists
    $sql = "SELECT * FROM users WHERE username='$username'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $error = "Username already taken";
    } else {
        // Insert new user into the database
        $sql = "INSERT INTO users (username, password) VALUES ('$username', '$password')";
        
        if ($conn->query($sql) === TRUE) {
            $_SESSION['username'] = $username;
            $_SESSION['user_id'] = $conn->insert_id;
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Error: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-page">
    <?php if (isset($_SESSION['username'])) { ?>
        <div class="page-actions">
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    <?php } ?>
    <div class="register-container">
        <div class="auth-header">
            <p class="auth-kicker">Scoreboard</p>
            <h2>Create Account</h2>
            <p>Join the scoreboard and start tracking your match results.</p>
        </div>
        <?php if (isset($error)) { echo "<p class='error'>$error</p>"; } ?>
        <form method="POST" action="register.php">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Sign Up</button>
        </form>
        <div class="signup-actions">
            <a href="login.php" class="dashboard-action-btn">Login Instead</a>
        </div>
    </div>
</body>
</html>
