<?php
$host = "localhost";
$user = "root";
$password = "";
$db = "mmustvote";

$conn = new mysqli($host, $user, $password, $db);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$conn->query("
  CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    role VARCHAR(50)
  )
");

$email = $_POST['email'];
$password = $_POST['password'];
$role = $_POST['role'];

// Email validation (must match mmust student format)
if (!preg_match("/^sitb01-\d{9}@student\.mmust\.ac\.ke$/", $email)) {
  header("Location: register.php?error=Invalid+MMUST+student+email+format");
  exit();
}

// Password must be exactly 8 characters
if (strlen($password) !== 8) {
  header("Location: register.php?error=Password+must+be+exactly+8+characters");
  exit();
}

// Check if user already exists
$check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
  header("Location: register.php?error=User+already+exists");
} else {
  $hashed = password_hash($password, PASSWORD_DEFAULT);
  $stmt = $conn->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
  $stmt->bind_param("sss", $email, $hashed, $role);

  if ($stmt->execute()) {
    header("Location: login.php");
  } else {
    header("Location: register.php?error=Registration+failed");
  }
  $stmt->close();
}

$check->close();
$conn->close();
?>
