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
    <form action="forgot_password_process.php" method="POST">
      <input type="email" name="email" placeholder="Enter your email" required><br>
      <button type="submit">Reset Password</button>
    </form>
    <p><a href="login.php">Back to Login</a></p>
  </div>
</body>
</html>
