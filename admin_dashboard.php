<?php
$host = "localhost";
$user = "root";
$password = "";
$db = "mmustvote";

$conn = new mysqli($host, $user, $password, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create electiondate table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS electiondate (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_name VARCHAR(255) NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql);

// Create candidates table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS candidates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    seat VARCHAR(100) NOT NULL,
    picture VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql);

// Handle election form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['election_name'])) {
    $election_name = $conn->real_escape_string($_POST['election_name']);
    $start_datetime = $conn->real_escape_string($_POST['start_datetime']);
    $end_datetime = $conn->real_escape_string($_POST['end_datetime']);
    
    $sql = "INSERT INTO electiondate (election_name, start_datetime, end_datetime) 
            VALUES ('$election_name', '$start_datetime', '$end_datetime')";
    
    if ($conn->query($sql) === TRUE) {
        $success_message = "Election created successfully!";
    } else {
        $error_message = "Error: " . $conn->error;
    }
}

// Handle candidate form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['candidate_name'])) {
    $candidate_name = $conn->real_escape_string($_POST['candidate_name']);
    $seat = $conn->real_escape_string($_POST['seat']);
    
    // Handle file upload
    $picture_path = '';
    if (isset($_FILES['picture']) && $_FILES['picture']['error'] == UPLOAD_ERR_OK) {
        $file_extension = strtolower(pathinfo($_FILES["picture"]["name"], PATHINFO_EXTENSION));
        $unique_filename = uniqid() . '.' . $file_extension;
        $target_file = $unique_filename; // Save directly to root folder
        
        // Check if file is an actual image
        $check = getimagesize($_FILES["picture"]["tmp_name"]);
        if ($check !== false) {
            if (move_uploaded_file($_FILES["picture"]["tmp_name"], $target_file)) {
                $picture_path = $target_file;
            } else {
                $candidate_error_message = "Failed to move uploaded file to root folder. Check permissions.";
            }
        } else {
            $candidate_error_message = "File is not a valid image.";
        }
    } elseif ($_FILES['picture']['error'] !== UPLOAD_ERR_NO_FILE) {
        $candidate_error_message = "File upload error: " . $_FILES['picture']['error'];
    }
    
    // Proceed with database insertion if no errors
    if (empty($candidate_error_message)) {
        $sql = "INSERT INTO candidates (name, seat, picture) 
                VALUES ('$candidate_name', '$seat', '$picture_path')";
        
        if ($conn->query($sql) === TRUE) {
            $candidate_success_message = "Candidate registered successfully!";
            $show_candidate_section = true; // Flag to show candidate section after submission
        } else {
            $candidate_error_message = "Database error: " . $conn->error;
        }
    }
}

// Handle candidate status update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['candidate_id']) && isset($_POST['action'])) {
    $candidate_id = $conn->real_escape_string($_POST['candidate_id']);
    $action = $_POST['action'] == 'approve' ? 'approved' : 'rejected';
    
    $sql = "UPDATE candidates SET status='$action' WHERE id='$candidate_id'";
    
    if ($conn->query($sql) === TRUE) {
        $candidate_success_message = "Candidate status updated successfully!";
        $show_candidate_section = true; // Flag to show candidate section after update
    } else {
        $candidate_error_message = "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard - MMUSTVote</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    /* Reset and Base Styles */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: #f5f7fa;
      color: #333;
    }
    
    /* Top Bar Styles */
    .top-bar {
      background-color: #004080;
      color: white;
      padding: 15px 25px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: fixed;
      width: 100%;
      top: 0;
      z-index: 1000;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .logo-container {
      display: flex;
      align-items: center;
      gap: 15px;
    }
    
    .logo {
      height: 40px;
    }
    
    .top-bar h1 {
      font-size: 1.5rem;
      font-weight: 600;
    }
    
    .top-right {
      display: flex;
      align-items: center;
      gap: 20px;
    }
    
    .admin-alert {
      background-color: #0066cc;
      padding: 5px 10px;
      border-radius: 20px;
      font-size: 0.9rem;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    
    .logout-btn {
      color: white;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 5px;
      transition: opacity 0.3s;
    }
    
    .logout-btn:hover {
      opacity: 0.8;
    }
    
    /* Sidebar Styles */
    .sidebar {
      background-color: #004080;
      color: white;
      width: 250px;
      height: 100vh;
      padding-top: 80px;
      position: fixed;
      top: 0;
      left: 0;
      transition: all 0.3s;
    }
    
    .sidebar ul {
      list-style: none;
    }
    
    .sidebar li {
      transition: all 0.3s;
    }
    
    .sidebar li:hover {
      background-color: #002b5c;
    }
    
    .sidebar a {
      color: #ecf0f1;
      text-decoration: none;
      padding: 15px 25px;
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 0.95rem;
    }
    
    .sidebar a i {
      width: 20px;
      text-align: center;
    }
    
    .sidebar .active {
      background-color: #0066cc;
      border-left: 4px solid #fff;
    }
    
    /* Main Content Styles */
    .main-content {
      margin-left: 250px;
      padding: 90px 25px 25px;
      min-height: 100vh;
    }
    
    /* Section Styles */
    .admin-section {
      background-color: white;
      border-radius: 8px;
      padding: 25px;
      margin-bottom: 25px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .admin-section h2 {
      color: #004080;
      margin-bottom: 20px;
      font-size: 1.5rem;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    /* Form Styles */
    .election-form form {
      display: flex;
      flex-direction: column;
      gap: 15px;
    }
    
    .form-group {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }
    
    .form-group label {
      font-weight: 500;
    }
    
    .form-group input, .form-group select {
      padding: 8px;
      border: 1px solid #ddd;
      border-radius: 4px;
    }
    
    .form-row {
      display: flex;
      gap: 20px;
    }
    
    .form-row .form-group {
      flex: 1;
    }
    
    .submit-btn {
      background-color: #004080;
      color: white;
      border: none;
      padding: 10px;
      border-radius: 4px;
      cursor: pointer;
      transition: background-color 0.3s;
    }
    
    .submit-btn:hover {
      background-color: #002b5c;
    }
    
    .message {
      padding: 10px;
      border-radius: 4px;
      margin-bottom: 15px;
    }
    
    .success {
      background-color: #dff0d8;
      color: #3c763d;
    }
    
    .error {
      background-color: #f2dede;
      color: #a94442;
    }
    
    /* Candidate Table Styles */
    .candidate-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }
    
    .candidate-table th, .candidate-table td {
      padding: 12px;
      text-align: left;
      border-bottom: 1px solid #ddd;
    }
    
    .candidate-table th {
      background-color: #f8f9fa;
      font-weight: 600;
    }
    
    .candidate-picture {
      width: 50px;
      height: 50px;
      object-fit: cover;
      border-radius: 50%;
    }
    
    .action-buttons button {
      padding: 5px 10px;
      margin: 0 5px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
    }
    
    .approve-btn {
      background-color: #28a745;
      color: white;
    }
    
    .reject-btn {
      background-color: #dc3545;
      color: white;
    }
    
    /* Responsive Adjustments */
    @media (max-width: 768px) {
      .sidebar {
        width: 70px;
        overflow: hidden;
      }
      
      .sidebar a span {
        display: none;
      }
      
      .sidebar a i {
        font-size: 1.2rem;
      }
      
      .main-content {
        margin-left: 70px;
      }
      
      .form-row {
        flex-direction: column;
        gap: 15px;
      }
      
      .candidate-table {
        font-size: 0.9rem;
      }
    }
  </style>
</head>
<body class="admin">
  <!-- Top Bar -->
  <div class="top-bar">
    <div class="logo-container">
      <img src="mmust-logo.jpg" alt="MMUST Logo" class="logo">
      <h1>MMUSTVote Admin Panel</h1>
    </div>
    <div class="top-right">
      <span class="admin-alert">
        <i class="fas fa-shield-alt"></i>
        <span>ADMIN</span>
      </span>
      <a href="logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
      </a>
    </div>
  </div>

  <!-- Sidebar Navigation -->
  <nav class="sidebar">
    <ul>
      <li>
        <a href="#" onclick="showSection('election-config')" class="<?php echo !isset($show_candidate_section) ? 'active' : '' ?>">
          <i class="fas fa-calendar-alt"></i>
          <span>Election Setup</span>
        </a>
      </li>
      <li>
        <a href="#" onclick="showSection('candidate-registration')" class="<?php echo isset($show_candidate_section) ? 'active' : '' ?>">
          <i class="fas fa-user-plus"></i>
          <span>Candidate Registration</span>
        </a>
      </li>
      <li>
        <a href="#" onclick="showSection('analytics')">
          <i class="fas fa-chart-bar"></i>
          <span>Analytics</span>
        </a>
      </li>
    </ul>
  </nav>

  <!-- Main Content -->
  <main class="main-content">
    <!-- Election Setup Section -->
    <section id="election-config" class="admin-section" style="<?php echo isset($show_candidate_section) ? 'display:none;' : '' ?>">
      <h2>
        <i class="fas fa-calendar-alt"></i>
        Election Setup
      </h2>
      <div class="election-form">
        <h3>Create New Election</h3>
        <?php if (isset($success_message)): ?>
          <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
          <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        <form method="POST" action="">
          <div class="form-group">
            <label for="election_name">Election Name</label>
            <input type="text" id="election_name" name="election_name" placeholder="E.g. 2024 Student Union Election" required>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="start_datetime">Start Date & Time</label>
              <input type="datetime-local" id="start_datetime" name="start_datetime" required>
            </div>
            <div class="form-group">
              <label for="end_datetime">End Date & Time</label>
              <input type="datetime-local" id="end_datetime" name="end_datetime" required>
            </div>
          </div>
          <button type="submit" class="submit-btn">Create Election</button>
        </form>
      </div>
    </section>

    <!-- Candidate Registration Section -->
    <section id="candidate-registration" class="admin-section" style="<?php echo isset($show_candidate_section) ? '' : 'display:none;' ?>">
      <h2>
        <i class="fas fa-user-plus"></i>
        Candidate Registration
      </h2>
      <div class="candidate-form">
        <h3>Register New Candidate</h3>
        <?php if (isset($candidate_success_message)): ?>
          <div class="message success"><?php echo $candidate_success_message; ?></div>
        <?php endif; ?>
        <?php if (isset($candidate_error_message)): ?>
          <div class="message error"><?php echo $candidate_error_message; ?></div>
        <?php endif; ?>
        <form method="POST" action="" enctype="multipart/form-data">
          <div class="form-row">
            <div class="form-group">
              <label for="candidate_name">Candidate Name</label>
              <input type="text" id="candidate_name" name="candidate_name" required>
            </div>
            <div class="form-group">
              <label for="seat">Seat Vying For</label>
              <select id="seat" name="seat" required>
              <option value="">Select Seat</option>
              <option value="President">President</option>
              <option value="Vice President">Vice President</option>
              <option value="Secretary General">Secretary General</option>
              <option value="Treasurer">Treasurer</option>
              <option value="Academics Secretary">Academics Secretary</option>
              <option value="Co-Curricular Secretary">Co-Curricular Secretary</option>
              <option value="Special Interest Secretary">Special Interest Secretary</option>
              <option value="Director of Gender">Director of Gender</option>
              <option value="Director of Sports and Entertainment">Director of Sports and Entertainment</option>
              <option value="Male Representative">Male Representative</option>
              <option value="Female Representative">Female Representative</option>
              <option value="Female SCI Representative">Female SCI Representative</option>

              </select>
            </div>
          </div>
          <div class="form-group">
            <label for="picture">Candidate Picture</label>
            <input type="file" id="picture" name="picture" accept="image/*">
          </div>
          <button type="submit" class="submit-btn">Register Candidate</button>
        </form>
      </div>
      <div class="candidate-list">
        <h3>Registered Candidates</h3>
        <table class="candidate-table">
          <thead>
            <tr>
              <th>Picture</th>
              <th>Name</th>
              <th>Seat</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $sql = "SELECT * FROM candidates";
            $result = $conn->query($sql);
            
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td><img src='" . ($row['picture'] ? $row['picture'] : 'default-avatar.png') . "' class='candidate-picture' alt='Candidate Picture'></td>";
                    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['seat']) . "</td>";
                    echo "<td>" . ucfirst($row['status']) . "</td>";
                    echo "<td class='action-buttons'>";
                    if ($row['status'] == 'pending') {
                        echo "<form method='POST' action='' style='display:inline;'>";
                        echo "<input type='hidden' name='candidate_id' value='" . $row['id'] . "'>";
                        echo "<button type='submit' name='action' value='approve' class='approve-btn'>Approve</button>";
                        echo "<button type='submit' name='action' value='reject' class='reject-btn'>Reject</button>";
                        echo "</form>";
                    }
                    echo "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='5'>No candidates registered yet</td></tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Analytics Section -->
    <section id="analytics" class="admin-section" style="display:none;">
      <h2>
        <i class="fas fa-chart-bar"></i>
        Analytics Dashboard
      </h2>
      <div class="stats-grid">
        <div class="stat-card">
          <h3>Total Voters</h3>
          <div class="stat-value">1,245</div>
        </div>
        <div class="stat-card">
          <h3>Votes Cast</h3>
          <div class="stat-value">876</div>
        </div>
        <div class="stat-card">
          <h3>Turnout Rate</h3>
          <div class="stat-value">70.4%</div>
        </div>
      </div>
      <div class="chart-container">
        <h3>Voting Progress</h3>
        <div class="chart-placeholder">
          <p>Voting activity chart will appear here</p>
        </div>
      </div>
    </section>
  </main>

  <script>
    // Function to switch between sections
    function showSection(sectionId) {
      document.querySelectorAll('.admin-section').forEach(section => {
        section.style.display = 'none';
      });
      
      document.getElementById(sectionId).style.display = 'block';
      
      document.querySelectorAll('.sidebar a').forEach(link => {
        link.classList.remove('active');
      });
      event.currentTarget.classList.add('active');
    }
    
    // Initialize with correct section based on PHP flag
    document.addEventListener('DOMContentLoaded', function() {
      <?php if (isset($show_candidate_section)): ?>
        showSection('candidate-registration');
      <?php else: ?>
        showSection('election-config');
      <?php endif; ?>
    });
  </script>
</body>
</html>

<?php $conn->close(); ?>