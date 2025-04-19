<?php
$error = isset($_GET['error']) ? $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>MMUSTVote - Register</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .error { color: red; font-size: 14px; }
  </style>
</head>
<body>
  <div class="container">
    <img src="mmust-logo.jpg" alt="MMUST Logo">
    <h2>Create Account</h2>
    <form action="register_process.php" method="POST">
      <input type="email" name="email" placeholder="Email" required><br>
      <?php if (!empty($error)) echo "<div class='error'>$error</div>"; ?>
      <input type="password" name="password" placeholder="Password" required><br>
      <div>
        <label><input type="radio" name="role" value="admin" required> Admin</label>
        <label><input type="radio" name="role" value="student"> Student</label>
        <label><input type="radio" name="role" value="commissioner"> Electoral Commissioner</label>
      </div><br>
      <button type="submit">Register</button>
    </form>
    <p><a href="login.php">Already have an account?</a></p>
  </div>
</body>
</html>
