<?php
session_start();

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php'; // PHPMailer via Composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$db = "mmustvote";

$conn = new mysqli($host, $user, $password, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create password_reset_tokens table if not exists
$sql = "CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Fixed: Added default value
    used BOOLEAN DEFAULT FALSE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if (!$conn->query($sql)) {
    error_log("Failed to create password_reset_tokens table: " . $conn->error);
    $messages['error'] = "Database error. Please try again later.";
}

// Handle form submission
$messages = ['success' => '', 'error' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $messages['error'] = "Please enter a valid email address.";
    } else {
        // Check if email exists in users table
        $sql = "SELECT id FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $messages['error'] = "No account found with that email address.";
        } else {
            // Generate a secure reset token
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour

            // Store the token in the database
            $sql = "INSERT INTO password_reset_tokens (email, token, expires_at) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $email, $token, $expires_at);

            if ($stmt->execute()) {
                // Send reset email using PHPMailer
                $mail = new PHPMailer(true);

                try {
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com'; // Replace with your SMTP server
                    $mail->SMTPAuth = true;
                    $mail->Username = 'your-email@gmail.com'; // Replace with your Gmail address
                    $mail->Password = 'your-app-password'; // Replace with your App Password
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port = 465;

                    // Recipients
                    $mail->setFrom('no-reply@mmustvote.com', 'MMUSTVote');
                    $mail->addAddress($email);

                    // Content
                    $reset_link = "http://localhost/mmustvote/reset_password.php?token=" . urlencode($token);
                    $mail->isHTML(true);
                    $mail->Subject = 'MMUSTVote Password Reset Request';
                    $mail->Body = '
                        <h2>Password Reset Request</h2>
                        <p>You have requested to reset your password for MMUSTVote.</p>
                        <p>Please click the link below to reset your password:</p>
                        <p><a href="' . htmlspecialchars($reset_link) . '">Reset Password</a></p>
                        <p>This link will expire in 1 hour.</p>
                        <p>If you did not request this, please ignore this email.</p>
                    ';
                    $mail->AltBody = "You have requested to reset your password for MMUSTVote. Please visit the following link to reset your password: $reset_link\nThis link will expire in 1 hour.\nIf you did not request this, please ignore this email.";

                    $mail->send();
                    $messages['success'] = "A password reset link has been sent to your email.";
                } catch (Exception $e) {
                    $messages['error'] = "Failed to send reset email. Please try again later.";
                    error_log("PHPMailer Error: " . $mail->ErrorInfo);
                }
            } else {
                $messages['error'] = "Error processing request. Please try again.";
                error_log("Database Error: " . $conn->error);
            }
            $stmt->close();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MMUSTVote - Forgot Password</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <img src="mmust-logo.jpg" alt="MMUST Logo">
        <h2>Forgot Password</h2>
        <?php if ($messages['success']): ?>
            <div class="message success"><?php echo htmlspecialchars($messages['success']); ?></div>
        <?php endif; ?>
        <?php if ($messages['error']): ?>
            <div class="message error"><?php echo htmlspecialchars($messages['error']); ?></div>
        <?php endif; ?>
        <form action="forgot_password_process.php" method="POST">
            <input type="email" name="email" placeholder="Enter your email" required><br>
            <button type="submit">Reset Password</button>
        </form>
        <p><a href="login.php">Back to Login</a></p>
    </div>
</body>
</html>