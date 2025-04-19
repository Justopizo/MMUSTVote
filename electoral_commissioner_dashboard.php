<?php
session_start();

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$host = "localhost";
$user = "root";
$password = "";
$db = "mmustvote";

$conn = new mysqli($host, $user, $password, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create necessary tables
$tables = [
    "users" => "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('student', 'admin', 'commissioner') NOT NULL,
        name VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "candidates" => "CREATE TABLE IF NOT EXISTS candidates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        seat VARCHAR(100) NOT NULL,
        picture VARCHAR(255),
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "votes" => "CREATE TABLE IF NOT EXISTS votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        voter_id INT,
        candidate_id INT,
        seat VARCHAR(100) NOT NULL,
        vote_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (voter_id) REFERENCES users(id),
        FOREIGN KEY (candidate_id) REFERENCES candidates(id)
    )"
];

foreach ($tables as $table => $sql) {
    if (!$conn->query($sql)) {
        error_log("Failed to create table $table: " . $conn->error);
    }
}

// Handle form submissions
$messages = ['success' => '', 'error' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'register_candidate':
                    $candidate_name = filter_input(INPUT_POST, 'candidate_name', FILTER_SANITIZE_STRING);
                    $seat = filter_input(INPUT_POST, 'seat', FILTER_SANITIZE_STRING);
                    $picture_path = '';

                    if (empty($candidate_name) || empty($seat)) {
                        $messages['error'] = "Candidate name and seat are required.";
                        break;
                    }

                    if (isset($_FILES['picture']) && $_FILES['picture']['error'] == UPLOAD_ERR_OK) {
                        $upload_dir = 'Uploads/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }

                        $file_extension = strtolower(pathinfo($_FILES["picture"]["name"], PATHINFO_EXTENSION));
                        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                        $max_file_size = 2 * 1024 * 1024; // 2MB

                        if (!in_array($file_extension, $allowed_extensions)) {
                            $messages['error'] = "Only JPG, PNG, and GIF files are allowed.";
                            break;
                        }

                        if ($_FILES['picture']['size'] > $max_file_size) {
                            $messages['error'] = "Image file is too large. Maximum size is 2MB.";
                            break;
                        }

                        $unique_filename = uniqid() . '.' . $file_extension;
                        $picture_path = $upload_dir . $unique_filename;

                        $check = getimagesize($_FILES["picture"]["tmp_name"]);
                        if ($check === false || !move_uploaded_file($_FILES["picture"]["tmp_name"], $picture_path)) {
                            $messages['error'] = "Invalid image or upload failed.";
                            break;
                        }
                    }

                    if (empty($messages['error'])) {
                        $sql = "INSERT INTO candidates (name, seat, picture) VALUES (?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("sss", $candidate_name, $seat, $picture_path);

                        if ($stmt->execute()) {
                            $messages['success'] = "Candidate registered successfully.";
                        } else {
                            $messages['error'] = "Error registering candidate: " . $conn->error;
                        }
                        $stmt->close();
                    }
                    break;

                case 'update_candidate_status':
                    $candidate_id = filter_input(INPUT_POST, 'candidate_id', FILTER_SANITIZE_NUMBER_INT);
                    $status = $_POST['status'] == 'approve' ? 'approved' : 'rejected';

                    if (empty($candidate_id)) {
                        $messages['error'] = "Invalid candidate ID.";
                        break;
                    }

                    $sql = "UPDATE candidates SET status=? WHERE id=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("si", $status, $candidate_id);

                    if ($stmt->execute()) {
                        $messages['success'] = "Candidate status updated successfully.";
                    } else {
                        $messages['error'] = "Error updating candidate status: " . $conn->error;
                    }
                    $stmt->close();
                    break;
            }
        }
    } catch (Exception $e) {
        $messages['error'] = "An error occurred: " . $e->getMessage();
        error_log("Form submission error: " . $e->getMessage());
    }
} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
    $messages['error'] = "Invalid CSRF token.";
}

// Generate random election results
$total_students = 15000;
$max_votes = round($total_students * 0.8);
$candidates = $conn->query("SELECT id, name, seat FROM candidates WHERE status='approved'");
$results = [];
$seat_votes = [];
$total_votes_cast = 0;

$candidate_list = [];
while ($candidate = $candidates->fetch_assoc()) {
    $candidate_list[] = $candidate;
}

$remaining_votes = $max_votes;
$num_candidates = count($candidate_list);
foreach ($candidate_list as $index => $candidate) {
    if ($index == $num_candidates - 1) {
        $votes = max(0, $remaining_votes);
    } else {
        $max_possible = $remaining_votes - ($num_candidates - $index - 1) * 100;
        $votes = rand(100, min(5000, max(100, $max_possible)));
    }
    $remaining_votes -= $votes;
    $total_votes_cast += $votes;
    $results[] = [
        'id' => $candidate['id'],
        'name' => $candidate['name'],
        'seat' => $candidate['seat'],
        'votes' => $votes,
        'position' => 0
    ];
    $seat_votes[$candidate['seat']] = ($seat_votes[$candidate['seat']] ?? 0) + $votes;
}

$seats = array_unique(array_column($results, 'seat'));
foreach ($seats as $seat) {
    $seat_results = array_filter($results, fn($r) => $r['seat'] == $seat);
    usort($seat_results, fn($a, $b) => $b['votes'] <=> $a['votes']);
    $position = 1;
    foreach ($seat_results as $result) {
        foreach ($results as &$r) {
            if ($r['id'] == $result['id']) {
                $r['position'] = $position;
                break;
            }
        }
        $position++;
    }
}

$results_by_seat = [];
foreach ($results as $result) {
    $results_by_seat[$result['seat']][] = $result;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Electoral Commissioner Dashboard - MMUSTVote</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background-color: #f0f2f5;
            color: #333;
        }

        .top-bar {
            background-color: #003087;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
            height: 45px;
        }

        .top-bar h1 {
            font-size: 1.6rem;
            font-weight: 600;
        }

        .top-right {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .commissioner-badge {
            background-color: #0066cc;
            padding: 6px 12px;
            border-radius: 15px;
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
            gap: 8px;
            transition: opacity 0.3s;
        }

        .logout-btn:hover {
            opacity: 0.8;
        }

        .sidebar {
            background-color: #003087;
            color: white;
            width: 260px;
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
            color: white;
            text-decoration: none;
            padding: 15px 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1rem;
            cursor: pointer;
        }

        .sidebar a.active {
            background-color: #0066cc;
            border-left: 5px solid #fff;
        }

        .main-content {
            margin-left: 260px;
            padding: 90px 30px 30px;
            min-height: 100vh;
        }

        .admin-section {
            background-color: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            display: none;
        }

        .admin-section.active {
            display: block;
        }

        .admin-section h2 {
            color: #003087;
            margin-bottom: 25px;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .form-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-weight: 500;
            color: #333;
        }

        .form-group input,
        .form-group select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }

        .form-row {
            display: flex;
            gap: 25px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .submit-btn {
            background-color: #003087;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s;
        }

        .submit-btn:hover {
            background-color: #002b5c;
        }

        .print-btn {
            background-color: #2e7d32;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            margin-bottom: 20px;
            transition: background-color 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .print-btn:hover {
            background-color: #1b5e20;
        }

        .message {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .success {
            background-color: #e6f4ea;
            color: #2e7d32;
        }

        .error {
            background-color: #ffebee;
            color: #c62828;
        }

        .table-container {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .data-table th,
        .data-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .data-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .candidate-picture {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 50%;
        }

        .action-buttons button {
            padding: 8px 15px;
            margin: 0 5px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .approve-btn {
            background-color: #2e7d32;
            color: white;
        }

        .reject-btn {
            background-color: #c62828;
            color: white;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-card h3 {
            color: #555;
            font-size: 1.1rem;
            margin-bottom: 10px;
        }

        .stat-value {
            color: #003087;
            font-size: 1.8rem;
            font-weight: 600;
        }

        .chart-container {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .seat-section {
            margin-bottom: 30px;
        }

        .seat-section h3 {
            color: #003087;
            margin-bottom: 15px;
            font-size: 1.4rem;
        }

        .no-js-fallback {
            display: none;
            background-color: #ffebee;
            color: #c62828;
            padding: 15px;
            margin: 15px;
            border-radius: 5px;
        }

        .no-js-fallback.active {
            display: block;
        }

        @media print {
            .top-bar, .sidebar, .print-btn, .no-js-fallback {
                display: none;
            }
            .main-content {
                margin……

            }
            .admin-section {
                box-shadow: none;
                padding: 0;
            }
            body {
                background: white;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
            }

            .sidebar a span {
                display: none;
            }

            .main-content {
                margin-left: 80px;
            }

            .form-row {
                flex-direction: column;
            }

            .data-table {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="no-js-fallback active">
        JavaScript is disabled. Please enable JavaScript to use the dashboard fully, or use the links below:
        <ul>
            <li><a href="#candidate-management">Candidate Management</a></li>
            <li><a href="#results">Election Results</a></li>
            <li><a href="#analytics">Analytics</a></li>
        </ul>
    </div>

    <div class="top-bar">
        <div class="logo-container">
            <img src="mmust-logo.jpg" alt="MMUST Logo" class="logo">
            <h1>Electoral Commissioner Dashboard</h1>
        </div>
        <div class="top-right">
            <span class="commissioner-badge">
                <i class="fas fa-shield-alt"></i>
                <span>COMMISSIONER</span>
            </span>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <nav class="sidebar">
        <ul>
            <li><a data-section="candidate-management">
                <i class="fas fa-user-plus"></i>
                <span>Candidate Management</span>
            </a></li>
            <li><a data-section="results">
                <i class="fas fa-poll"></i>
                <span>Election Results</span>
            </a></li>
            <li><a data-section="analytics">
                <i class="fas fa-chart-bar"></i>
                <span>Analytics</span>
            </a></li>
        </ul>
    </nav>

    <main class="main-content">
        <!-- Candidate Management -->
        <section id="candidate-management" class="admin-section">
            <h2><i class="fas fa-user-plus"></i> Candidate Management</h2>
            <?php if ($messages['success']): ?>
                <div class="message success"><?php echo htmlspecialchars($messages['success']); ?></div>
            <?php endif; ?>
            <?php if ($messages['error']): ?>
                <div class="message error"><?php echo htmlspecialchars($messages['error']); ?></div>
            <?php endif; ?>
            <div class="form-container">
                <h3>Register New Candidate</h3>
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="register_candidate">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
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
                        <input type="file" id="picture" name="picture" accept="image/jpeg,image/png,image/gif">
                    </div>
                    <button type="submit" class="submit-btn">Register Candidate</button>
                </form>
            </div>
            <div class="table-container">
                <h3>Registered Candidates</h3>
                <table class="data-table">
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
                        $result = $conn->query("SELECT * FROM candidates");
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td><img src='" . (file_exists($row['picture']) && $row['picture'] ? htmlspecialchars($row['picture']) : 'default-avatar.png') . "' class='candidate-picture' alt='Candidate Picture'></td>";
                            echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['seat']) . "</td>";
                            echo "<td>" . ucfirst($row['status']) . "</td>";
                            echo "<td class='action-buttons'>";
                            if ($row['status'] == 'pending') {
                                echo "<form method='POST' action='' style='display:inline;'>";
                                echo "<input type='hidden' name='action' value='update_candidate_status'>";
                                echo "<input type='hidden' name='candidate_id' value='{$row['id']}'>";
                                echo "<input type='hidden' name='csrf_token' value='" . htmlspecialchars($_SESSION['csrf_token']) . "'>";
                                echo "<button type='submit' name='status' value='approve' class='approve-btn'>Approve</button>";
                                echo "<button type='submit' name='status' value='reject' class='reject-btn'>Reject</button>";
                                echo "</form>";
                            }
                            echo "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Election Results -->
        <section id="results" class="admin-section">
            <h2><i class="fas fa-poll"></i> Election Results</h2>
            <button class="print-btn" onclick="printResults()">
                <i class="fas fa-print"></i> Print Results
            </button>
            <?php foreach ($results_by_seat as $seat => $seat_results): ?>
                <div class="seat-section">
                    <h3><?php echo htmlspecialchars($seat); ?></h3>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Votes</th>
                                    <th>Position</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($seat_results as $result): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($result['name']); ?></td>
                                        <td><?php echo $result['votes']; ?></td>
                                        <td><?php echo $result['position']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <!-- Analytics -->
        <section id="analytics" class="admin-section">
            <h2><i class="fas fa-chart-bar"></i> Analytics Dashboard</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Voters</h3>
                    <div class="stat-value"><?php echo $total_students; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Votes Cast</h3>
                    <div class="stat-value"><?php echo $total_votes_cast; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Turnout Rate</h3>
                    <div class="stat-value"><?php echo $total_students > 0 ? round(($total_votes_cast / $total_students) * 100, 1) : 0; ?>%</div>
                </div>
            </div>
            <div class="chart-container">
                <h3>Voting Progress by Seat</h3>
                <canvas id="votingChart"></canvas>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>
    <script>
        // Confirm script is loaded
        console.log('Dashboard script loaded');

        // Remove no-js fallback if JavaScript is enabled
        document.querySelector('.no-js-fallback').classList.remove('active');

        // Sidebar section toggling
        document.addEventListener('DOMContentLoaded', () => {
            const sections = document.querySelectorAll('.admin-section');
            const sidebarLinks = document.querySelectorAll('.sidebar a');

            // Log number of links found for debugging
            console.log(`Found ${sidebarLinks.length} sidebar links`);

            // Function to show a specific section
            function showSection(sectionId, clickedLink) {
                console.log(`Switching to section: ${sectionId}`);
                // Hide all sections
                sections.forEach(section => section.classList.remove('active'));
                // Show the target section
                const targetSection = document.getElementById(sectionId);
                if (targetSection) {
                    targetSection.classList.add('active');
                } else {
                    console.error(`Section "${sectionId}" not found`);
                }
                // Update sidebar link styles
                sidebarLinks.forEach(link => link.classList.remove('active'));
                clickedLink.classList.add('active');
            }

            // Attach click handlers to sidebar links
            sidebarLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const sectionId = link.getAttribute('data-section');
                    console.log(`Clicked link for section: ${sectionId}`);
                    showSection(sectionId, link);
                });
            });

            // Show analytics section by default
            const defaultLink = document.querySelector('.sidebar a[data-section="analytics"]');
            if (defaultLink) {
                console.log('Setting default section to analytics');
                showSection('analytics', defaultLink);
            } else {
                console.error('Default analytics link not found');
            }

            // Chart.js setup
            try {
                const ctx = document.getElementById('votingChart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode(array_keys($seat_votes)); ?>,
                        datasets: [{
                            label: 'Votes by Seat',
                            data: <?php echo json_encode(array_values($seat_votes)); ?>,
                            backgroundColor: '#003087',
                            borderColor: '#002b5c',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        },
                        plugins: {
                            legend: {
                                display: true
                            }
                        }
                    }
                });
                console.log('Chart initialized successfully');
            } catch (error) {
                console.error('Error initializing chart:', error);
            }
        });

        // Print results function
        function printResults() {
            // Store the current scroll position
            const scrollY = window.scrollY;
            
            // Show only the results section for printing
            const sections = document.querySelectorAll('.admin-section');
            sections.forEach(section => {
                if (section.id !== 'results') {
                    section.style.display = 'none';
                }
            });

            // Print the page
            window.print();

            // Restore the view
            sections.forEach(section => {
                section.style.display = section.id === 'results' ? 'block' : 'none';
                if (section.classList.contains('active')) {
                    section.style.display = 'block';
                }
            });

            // Restore scroll position
            window.scrollTo(0, scrollY);
        }
    </script>
</body>
</html>

<?php $conn->close(); ?>