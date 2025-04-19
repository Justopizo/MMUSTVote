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

// Fetch election dates
$elections = [];
$sql = "SELECT election_name, start_datetime, end_datetime 
        FROM electiondate 
        ORDER BY created_at DESC 
        LIMIT 1";
$result = $conn->query($sql);
if ($result === false) {
    die("Error fetching elections: " . $conn->error);
}
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $elections[] = $row;
    }
}

// Fetch approved candidates
$candidates = [];
$sql = "SELECT name, seat, picture 
        FROM candidates 
        WHERE status = 'approved'";
$result = $conn->query($sql);
if ($result === false) {
    die("Error fetching candidates: " . $conn->error);
}
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $candidates[] = $row;
    }
}

// Fetch vote counts
$vote_counts = [];
$sql = "SELECT name, seat, COUNT(*) as vote_count 
        FROM candidates 
        GROUP BY name, seat";
/* Alternative query if votes table uses candidate_id:
$sql = "SELECT c.name as candidate, c.seat, COUNT(*) as vote_count 
        FROM votes v 
        JOIN candidates c ON v.candidate_id = c.id 
        GROUP BY c.name, c.seat";
*/
$result = $conn->query($sql);
if ($result === false) {
    die("Error fetching vote counts: " . $conn->error);
}
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $vote_counts[$row['seat']][$row['name']] = $row['vote_count'];
    }
}

$conn->close();

// Group candidates by seat for voting section
$candidates_by_seat = [];
foreach ($candidates as $candidate) {
    $seat = $candidate['seat'];
    if (!isset($candidates_by_seat[$seat])) {
        $candidates_by_seat[$seat] = [];
    }
    $candidates_by_seat[$seat][] = $candidate;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Student Dashboard - MMUSTVote</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
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
    
    .user-greeting {
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
    
    .sidebar-contact {
      padding: 20px;
      font-size: 0.85rem;
      border-top: 1px solid #ccc;
      position: absolute;
      bottom: 0;
      width: 100%;
    }
    
    .main-content {
      margin-left: 250px;
      padding: 90px 25px 25px;
      min-height: 100vh;
    }
    
    .section-content {
      background-color: white;
      border-radius: 8px;
      padding: 25px;
      margin-bottom: 25px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .section-header {
      color: #004080;
      margin-bottom: 20px;
      font-size: 1.5rem;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .election-list ul {
      list-style-type: none;
      padding-left: 0;
    }
    
    .election-list li {
      margin: 10px 0;
      font-size: 1rem;
      color: #333;
    }
    
    .candidate-container {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
    }
    
    .candidate-card {
      background-color: white;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      width: calc(33.33% - 20px);
      box-sizing: border-box;
      text-align: center;
    }
    
    .candidate-card img {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      margin-bottom: 10px;
      object-fit: cover;
    }
    
    .candidate-card h3 {
      color: #004080;
      margin-bottom: 5px;
      font-size: 1.2rem;
    }
    
    .candidate-card p {
      color: #555;
      font-size: 0.9rem;
    }
    
    .vote-candidate {
      display: flex;
      align-items: center;
      gap: 15px;
      padding: 15px;
      border-bottom: 1px solid #ddd;
    }
    
    .vote-candidate img {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      object-fit: cover;
    }
    
    .vote-candidate input[type="radio"] {
      transform: scale(1.2);
    }
    
    .vote-button {
      background-color: #004080;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 4px;
      cursor: pointer;
      margin-top: 20px;
      transition: background-color 0.3s;
    }
    
    .vote-button:hover {
      background-color: #002b5c;
    }
    
    .vote-button:disabled {
      background-color: #ccc;
      cursor: not-allowed;
    }
    
    .error-message {
      color: #a94442;
      font-size: 1rem;
      margin-top: 15px;
      background-color: #f2dede;
      padding: 10px;
      border-radius: 4px;
    }
    
    .seat-group {
      margin-bottom: 20px;
    }
    
    .seat-group h3 {
      color: #004080;
      font-size: 1.3rem;
      margin-bottom: 10px;
      border-bottom: 2px solid #004080;
      padding-bottom: 5px;
    }
    
    .result-card {
      background-color: white;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      margin: 10px 0;
      text-align: center;
    }
    
    .result-card h3 {
      color: #004080;
      font-size: 1.2rem;
    }
    
    .result-card p {
      color: #555;
      font-size: 0.9rem;
    }
    
    .news-card {
      background-color: white;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      margin-bottom: 20px;
    }
    
    .news-card h3 {
      color: #004080;
      margin-top: 0;
      font-size: 1.2rem;
    }
    
    .news-card p {
      color: #555;
      line-height: 1.6;
      font-size: 0.9rem;
    }
    
    .news-card .date {
      color: #888;
      font-size: 0.8rem;
      margin-top: 10px;
    }
    
    .refresh-btn {
      background-color: #004080;
      color: white;
      border: none;
      padding: 8px 15px;
      border-radius: 4px;
      cursor: pointer;
      margin-bottom: 20px;
      transition: background-color 0.3s;
    }
    
    .refresh-btn:hover {
      background-color: #002b5c;
    }
    
    .voting-status {
      color: #a94442;
      font-size: 1rem;
      margin-top: 15px;
      padding: 10px;
      border-radius: 4px;
      background-color: #f2dede;
    }
    
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
      
      .candidate-card {
        width: calc(50% - 20px);
      }
    }
    
    @media (max-width: 480px) {
      .candidate-card {
        width: 100%;
      }
    }
  </style>
</head>
<body>

  <div class="top-bar">
    <div class="logo-container">
      <img src="mmust-logo.jpg" alt="MMUST Logo" class="logo">
      <h1>MMUSTVote Dashboard</h1>
    </div>
    <div class="top-right">
      <span class="user-greeting">
        <i class="fas fa-user-circle"></i>
        Hi, Comrade
      </span>
      <a href="logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
      </a>
    </div>
  </div>

  <nav class="sidebar">
    <ul>
      <li>
        <a href="#" onclick="showSection('home-content')" class="active">
          <i class="fas fa-home"></i>
          <span>Home</span>
        </a>
      </li>
      <li>
        <a href="#" onclick="showSection('candidates-content')">
          <i class="fas fa-users"></i>
          <span>Candidates</span>
        </a>
      </li>
      <li>
        <a href="#" onclick="showSection('vote-content')">
          <i class="fas fa-vote-yea"></i>
          <span>Vote</span>
        </a>
      </li>
      <li>
        <a href="#" onclick="showSection('results-content')">
          <i class="fas fa-poll"></i>
          <span>Results</span>
        </a>
      </li>
      <li>
        <a href="#" onclick="showSection('news-content')">
          <i class="fas fa-newspaper"></i>
          <span>News</span>
        </a>
      </li>
    </ul>
    <div class="sidebar-contact">
      <p>Contact: 0112581756</p>
      <p><a href="mailto:mmustvote@mmust.ac.ke">mmustvote@mmust.ac.ke</a></p>
      
    </div>
  </nav>

  <main class="main-content">
    <section id="home-content" class="section-content">
      <h2 class="section-header">
        <i class="fas fa-calendar-alt"></i>
        Upcoming Elections
      </h2>
      <div class="election-list">
        <ul>
          <?php if (empty($elections)): ?>
            <li>No upcoming elections scheduled.</li>
          <?php else: ?>
            <?php foreach ($elections as $election): ?>
              <li>
                <?php echo htmlspecialchars($election['election_name']); ?> – 
                <?php echo date('F j, Y, g:i A', strtotime($election['start_datetime'])); ?>
              </li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ul>
      </div>

      <h2 class="section-header">
        <i class="fas fa-user-tie"></i>
        Current Student Executive Council
      </h2>
      <div class="box">
        <ul>
          <li><strong>Chairperson:</strong> Kelvin Otieno</li>
          <li><strong>Secretary:</strong> Grace Akinyi</li>
          <li><strong>Treasurer:</strong> Brian Mwangi</li>
        </ul>
      </div>

      <h2 class="section-header">
        <i class="fas fa-bullhorn"></i>
        Election News & Announcements
      </h2>
      <div class="box">
        <ul>
          <li>Nomination deadline extended to June 5, 2025</li>
          <li>Presidential debate scheduled on July 10, 2025</li>
          <li>Student election campaign starts on June 20, 2025</li>
        </ul>
      </div>
    </section>

    <section id="candidates-content" class="section-content" style="display: none;">
      <h2 class="section-header">
        <i class="fas fa-users"></i>
        Current Election Candidates
      </h2>
      <div class="candidate-container">
        <?php if (empty($candidates)): ?>
          <p>No approved candidates available.</p>
        <?php else: ?>
          <?php foreach ($candidates as $candidate): ?>
            <div class="candidate-card">
              <img src="<?php echo $candidate['picture'] ? htmlspecialchars($candidate['picture']) : 'default-avatar.png'; ?>" alt="<?php echo htmlspecialchars($candidate['name']); ?>">
              <h3><?php echo htmlspecialchars($candidate['name']); ?></h3>
              <p><?php echo htmlspecialchars($candidate['seat']); ?></p>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

    <section id="vote-content" class="section-content" style="display: none;">
      <h2 class="section-header">
        <i class="fas fa-vote-yea"></i>
        Vote for Your Preferred Candidates
      </h2>
      <form id="vote-form">
        <div id="vote-candidates">
          <?php if (empty($candidates_by_seat)): ?>
            <p>No approved candidates available for voting.</p>
          <?php else: ?>
            <?php $index = 0; ?>
            <?php foreach ($candidates_by_seat as $seat => $seat_candidates): ?>
              <div class="seat-group">
                <h3><?php echo htmlspecialchars($seat); ?></h3>
                <?php foreach ($seat_candidates as $candidate): ?>
                  <div class="vote-candidate">
                    <input type="radio" id="vote-<?php echo $index; ?>" name="vote[<?php echo htmlspecialchars($seat); ?>]" value="<?php echo htmlspecialchars($candidate['name']); ?>">
                    <label for="vote-<?php echo $index; ?>">
                      <img src="<?php echo $candidate['picture'] ? htmlspecialchars($candidate['picture']) : 'default-avatar.png'; ?>" alt="<?php echo htmlspecialchars($candidate['name']); ?>">
                      <div>
                        <h3><?php echo htmlspecialchars($candidate['name']); ?></h3>
                        <p><?php echo htmlspecialchars($candidate['seat']); ?></p>
                      </div>
                    </label>
                  </div>
                  <?php $index++; ?>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <button type="button" class="vote-button" onclick="submitVote()">Submit Vote</button>
      </form>
      <p class="error-message" id="error-message"></p>
    </section>

    <section id="results-content" class="section-content" style="display: none;">
      <h2 class="section-header">
        <i class="fas fa-poll"></i>
        Election Results
      </h2>
      <div id="result-list">
        <p>Please vote to see results.</p>
      </div>
    </section>

    <section id="news-content" class="section-content" style="display: none;">
      <h2 class="section-header">
        <i class="fas fa-newspaper"></i>
        Latest Election News
      </h2>
      <button class="refresh-btn" onclick="generateRandomNews()">
        <i class="fas fa-sync-alt"></i> Refresh News
      </button>
      <div id="news-container"></div>
    </section>
  </main>

  <script>
    const elections = <?php echo json_encode($elections); ?>;
    const candidates = <?php echo json_encode($candidates); ?>;
    const voteCounts = <?php echo json_encode($vote_counts); ?>;

    const newsTopics = [
      "Election Updates", "Campaign News", "Candidate Profiles", 
      "Debate Highlights", "Voter Information", "Election Rules",
      "Important Deadlines", "Student Opinions", "Campus Events"
    ];

    const newsVerbs = [
      "announces", "reports", "reveals", "confirms", "denies", 
      "explains", "highlights", "discusses", "shares", "updates"
    ];

    const newsNouns = [
      "new voting procedures", "candidate platforms", "student concerns",
      "election timeline", "campaign strategies", "debate outcomes",
      "voter turnout expectations", "election security measures",
      "candidate qualifications", "student feedback"
    ];

    const newsDetails = [
      "The election committee has made significant changes to the voting process this year.",
      "Students are encouraged to participate in the upcoming debates to learn more about the candidates.",
      "New security measures have been implemented to ensure a fair and transparent election.",
      "Several candidates have proposed innovative ideas for improving student life on campus.",
      "The deadline for voter registration has been extended due to popular demand.",
      "A record number of students are expected to participate in this year's elections.",
      "Candidates are focusing on key issues such as tuition fees and campus facilities.",
      "The election results will be announced live in the main auditorium.",
      "Student organizations are hosting forums to discuss the election platforms.",
      "Early voting will be available for students with scheduling conflicts."
    ];

    function showSection(sectionId) {
      document.querySelectorAll('.section-content').forEach(section => {
        section.style.display = 'none';
      });
      
      document.getElementById(sectionId).style.display = 'block';
      
      document.querySelectorAll('.sidebar a').forEach(link => {
        link.classList.remove('active');
      });
      event.currentTarget.classList.add('active');
    }

    function submitVote() {
      const selectedCandidates = [];
      document.querySelectorAll('input[name^="vote["]:checked').forEach(input => {
        const seat = input.name.match(/vote\[(.+)\]/)[1];
        selectedCandidates.push({ name: input.value, seat: seat });
      });

      const errorMessage = document.getElementById("error-message");
      const currentDate = new Date();

      if (selectedCandidates.length === 0) {
        errorMessage.textContent = "Please select at least one candidate.";
        return;
      }

      let votingOpen = false;
      let activeElection = null;
      for (const election of elections) {
        const startDate = new Date(election.start_datetime);
        const endDate = new Date(election.end_datetime);
        if (currentDate >= startDate && currentDate <= endDate) {
          votingOpen = true;
          activeElection = election;
          break;
        }
      }

      if (!votingOpen) {
        errorMessage.textContent = "No active elections are currently open for voting.";
        return;
      }

      alert(`You have successfully voted in ${activeElection.election_name}`);
      errorMessage.textContent = "";
      showSection('results-content');
      displayResults(selectedCandidates);
    }

    function displayResults(selectedCandidates) {
      const resultList = document.getElementById("result-list");
      resultList.innerHTML = "";

      if (selectedCandidates.length === 0) {
        resultList.innerHTML = "<p>No candidates selected.</p>";
        return;
      }

      const currentDate = new Date();
      let votingEnded = true;
      for (const election of elections) {
        const endDate = new Date(election.end_datetime);
        if (currentDate <= endDate) {
          votingEnded = false;
          break;
        }
      }

      // Calculate total votes per seat
      const totalVotesBySeat = {};
      for (const seat in voteCounts) {
        totalVotesBySeat[seat] = Object.values(voteCounts[seat]).reduce((sum, count) => sum + count, 0);
      }

      selectedCandidates.forEach(candidate => {
        const resultCard = document.createElement("div");
        resultCard.classList.add("result-card");
        
        const votes = voteCounts[candidate.seat]?.[candidate.name] || 0;
        const totalVotes = totalVotesBySeat[candidate.seat] || 1; // Avoid division by zero
        const percentage = ((votes / totalVotes) * 100).toFixed(2);

        resultCard.innerHTML = `
          <h3>Your Vote: ${candidate.name} (${candidate.seat})</h3>
          <p>Current Votes: ${votes}</p>
          <p>Percentage of Votes: ${percentage}%</p>
        `;
        resultList.appendChild(resultCard);
      });

      if (!votingEnded) {
        const statusMessage = document.createElement("p");
        statusMessage.classList.add("voting-status");
        statusMessage.textContent = "Results are still coming in. Please check back after voting ends.";
        resultList.appendChild(statusMessage);
      }
    }

    function generateRandomNews() {
      const newsContainer = document.getElementById("news-container");
      newsContainer.innerHTML = "";
      
      const numArticles = Math.floor(Math.random() * 4) + 3;
      
      for (let i = 0; i < numArticles; i++) {
        const topic = newsTopics[Math.floor(Math.random() * newsTopics.length)];
        const verb = newsVerbs[Math.floor(Math.random() * newsVerbs.length)];
        const noun = newsNouns[Math.floor(Math.random() * newsNouns.length)];
        const detail = newsDetails[Math.floor(Math.random() * newsDetails.length)];
        
        const daysAgo = Math.floor(Math.random() * 30);
        const newsDate = new Date();
        newsDate.setDate(newsDate.getDate() - daysAgo);
        const formattedDate = newsDate.toLocaleDateString('en-US', { 
          year: 'numeric', 
          month: 'long', 
          day: 'numeric' 
        });
        
        const randomCandidate = candidates[Math.floor(Math.random() * candidates.length)] || { name: "Election Committee" };
        
        const newsCard = document.createElement("div");
        newsCard.classList.add("news-card");
        newsCard.innerHTML = `
          <h3>${topic}: ${randomCandidate.name} ${verb} ${noun}</h3>
          <p>${detail}</p>
          <p class="date">Published: ${formattedDate}</p>
        `;
        
        newsContainer.appendChild(newsCard);
      }
    }

    document.addEventListener('DOMContentLoaded', function() {
      showSection('home-content');
    });
  </script>

</body>
</html>