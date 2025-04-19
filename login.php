<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MMUSTVote - Login</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <img src="mmust-logo.jpg" alt="MMUST Logo">
    <h2>Login to MMUSTVote</h2>

    <?php if (isset($_GET['error'])): ?>
      <p style="color: red;"><?php echo htmlspecialchars($_GET['error']); ?></p>
    <?php endif; ?>

    <form action="login_process.php" method="POST">
      <input type="email" name="email" placeholder="Email" required><br>
      <input type="password" name="password" placeholder="Password" required><br>
      <button type="submit">Login</button>
    </form>
    <p><a href="forgot_password.php">Forgot Password?</a></p>
    <p><a href="register.php">Create Account</a></p>
  </div>
</body>
</html>
