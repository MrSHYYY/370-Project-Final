<?php
session_start();
include('includes/db.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND password = ? AND LOWER(username) = 'admin'");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['is_admin'] = true;
        header("Location: admin_dashboard.php");
        exit();
    } else {
        $error = "Invalid admin username or password";
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-page admin-auth-page">
    <div class="login-container">
        <h2>Admin Login</h2>
        <?php if (isset($error)) { echo "<p class='error'>" . htmlspecialchars($error) . "</p>"; } ?>
        <form method="POST" action="admin_login.php">
            <input type="text" name="username" placeholder="Admin Username" required>
            <input type="password" name="password" placeholder="Admin Password" required>
            <button type="submit">Login as Admin</button>
        </form>
        <p><a href="login.php">Back to User Login</a></p>
    </div>
</body>
</html>
