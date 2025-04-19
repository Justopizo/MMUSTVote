<?php
session_start();

$host = "localhost";
$user = "root";
$password = "";
$db = "mmustvote";

$conn = new mysqli($host, $user, $password, $db);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Sanitize and receive input
$email = trim($_POST['email']);
$password = $_POST['password'];

// Validate email format (MMUST student format)
$pattern = "/^sitb01-\d{9}@student\.mmust\.ac\.ke$/";
if (!preg_match($pattern, $email)) {
  header("Location: login.php?error=Invalid MMUST student email format");
  exit;
}

// Check if user exists
$stmt = $conn->prepare("SELECT id, password, role FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  header("Location: login.php?error=Account not found. Please register.");
  exit;
} else {
  $user = $result->fetch_assoc();
  if (password_verify($password, $user['password'])) {
    // Login successful, store user data in session
    $_SESSION['email'] = $email; // Store email in session
    $_SESSION['role'] = $user['role']; // Store role in session (optional)

    // Redirect based on the role
    $role = $user['role'];
    if ($role == 'student') {
      header("Location: student_dashboard.php");
    } elseif ($role == 'admin') {
      header("Location: admin_dashboard.php");
    } elseif ($role == 'commissioner') {
      header("Location: electoral_commissioner_dashboard.php");
    } else {
      // Default if the role is not recognized
      header("Location: login.php?error=Invalid role");
    }
    exit;
  } else {
    // Incorrect password
    header("Location: login.php?error=Incorrect password");
    exit;
  }
}

$stmt->close();
$conn->close();
?>
