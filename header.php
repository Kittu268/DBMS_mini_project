<?php
// ----------------------------------------------
// FINAL HEADER FILE (NO ERRORS, NO DUPLICATION)
// ----------------------------------------------

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>✈️ Airline System</title>

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Global Styles -->
    <link rel="stylesheet" href="global-styles.css">

    <style>
        /* AI Chat Box Styles */
        #aiChatBox { 
            display: none;
            flex-direction: column; 
            position: fixed; 
            bottom: 80px; 
            right: 25px; 
            width: 360px; 
            height: 460px; 
            background: white; 
            border-radius: 12px; 
            border: 1px solid #ccc; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.2); 
            resize: both; 
            overflow: hidden; 
            min-width: 260px; 
            min-height: 300px; 
            max-width: 800px; 
            max-height: 700px; 
            z-index: 9999; 
        }
        #aiChatHeader { 
            background: linear-gradient(to right, #667eea, #764ba2); 
            color: white; 
            padding: 10px 12px; 
            font-weight: bold; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            cursor: move;
        }
        #aiChatBody { 
            flex: 1; 
            padding: 10px; 
            overflow-y: auto; 
            background: #f8f9fa; 
            font-size: 14px;
        }
        .ai-msg, .user-msg {
            margin: 6px 0; 
            padding: 8px 10px; 
            border-radius: 10px; 
            max-width: 90%; 
            word-wrap: break-word;
        }
        .ai-msg { 
            background: #e9ecef; 
            text-align: left; 
        }
        .user-msg { 
            background: #cfe2ff; 
            text-align: right; 
            margin-left: auto; 
        }
        #aiChatInput {
            display: flex; 
            border-top: 1px solid #ccc; 
        }
        #aiInput {
            flex: 1; 
            border: none; 
            padding: 8px; 
            outline: none;
        }
        #aiSend {
            border: none; 
            background: linear-gradient(to right, #667eea, #764ba2); 
            color: white; 
            padding: 8px 12px; 
            cursor: pointer;
        }
        #aiSend:hover { 
            opacity: 0.9; 
        }
    </style>
    <!-- AI Assistant Script -->
<script src="assets/js/ai_assistant.js" defer></script>

</head>
<body>

<?php
// #############################################
// SHOW BACKGROUND ONLY IF PAGE DOES NOT DISABLE IT
// #############################################
if (!isset($disable_background) || $disable_background !== true) {
    include __DIR__ . "/includes/background_elements.php";
}
?>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg card fixed-top" 
     style="backdrop-filter: blur(10px); margin:0; padding:8px 20px; z-index:9999;">

    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="index.php" style="color:#667eea;">
            ✈️ Airline System
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">

            <ul class="navbar-nav ms-auto align-items-center">

                <?php if(isset($_SESSION['user'])): ?>

                    <li class="nav-item"><a class="nav-link" href="index.php">🏠 Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="available_flights.php">🛫 Available Flights</a></li>
                    <li class="nav-item"><a class="nav-link" href="make_reservation.php">➕ Book</a></li>
                    <li class="nav-item"><a class="nav-link" href="view_reservations.php">📋 View</a></li>
                    <li class="nav-item"><a class="nav-link" href="cancel_reservation.php">❌ Cancel</a></li>
                    <?php if (isset($_SESSION['email']) && $_SESSION['email'] === 'admin@airline.com'): ?>
                        <li class="nav-item"><a class="nav-link" href="all_tables.php">📊 Tables</a></li>
                        <li class="nav-item"><a class="nav-link" href="query_executor.php">🧠 SQL</a></li>
                    <?php endif; ?>


                    <li class="nav-item"><a class="nav-link" href="profile.php">👤 Profile</a></li>


                    <?php if(isset($_SESSION['email']) && $_SESSION['email'] === "admin@airline.com"): ?>
                        <li class="nav-item"><a class="nav-link text-warning fw-bold" href="admin/dashboard.php">⚙️ Admin</a></li>
                    <?php endif; ?>

                    <li class="nav-item">
                        <button id="aiBtn" class="btn btn-primary btn-sm ms-2">💬 AI Assistant</button>
                    </li>

                    <li class="nav-item">
                        <span class="nav-link fw-semibold" style="color:#764ba2;">
                            👤 <?= htmlspecialchars($_SESSION['user']['Cname'] ?? 'User'); ?>
                        </span>
                    </li>

                    <li class="nav-item"><a class="nav-link text-danger" href="logout.php">🚪 Logout</a></li>

                <?php else: ?>

                    <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="signup.php">Sign Up</a></li>

                <?php endif; ?>

            </ul>

        </div>
    </div>
</nav>

<!-- AI Chat Popup -->
<div id="aiChatBox">
    <div id="aiChatHeader">🤖 Airline Assistant <span id="aiClose" style="cursor:pointer;">❌</span></div>
    <div id="aiChatBody">
        <div class="ai-msg">👋 Hello! I'm your Airline Assistant. Ask about flights or reservations!</div>
    </div>
    <div id="aiChatInput">
        <input type="text" id="aiInput" placeholder="Type your question...">
        <button id="aiSend">Send</button>
    </div>
</div>

<script>
// Chat Feature
const aiBtn = document.getElementById("aiBtn");
const aiBox = document.getElementById("aiChatBox");
const aiClose = document.getElementById("aiClose");
const aiInput = document.getElementById("aiInput");
const aiSend = document.getElementById("aiSend");
const aiBody = document.getElementById("aiChatBody");
const aiHeader = document.getElementById("aiChatHeader");

// Toggle visibility
aiBtn?.addEventListener("click", () => aiBox.style.display = "flex");
aiClose?.addEventListener("click", () => aiBox.style.display = "none");

// Send message
aiSend.addEventListener("click", sendMessage);
aiInput.addEventListener("keypress", e => { if (e.key === "Enter") sendMessage(); });

async function sendMessage() {
    const text = aiInput.value.trim();
    if (!text) return;

    aiBody.innerHTML += `<div class='user-msg'>${text}</div>`;
    aiInput.value = "";

    const res = await fetch("ai_assistant_api.php", {
        method: "POST",
        headers: {"Content-Type":"application/json"},
        body: JSON.stringify({prompt:text})
    });

    const data = await res.json();
    aiBody.innerHTML += `<div class='ai-msg'>${data.reply}</div>`;
    aiBody.scrollTop = aiBody.scrollHeight;
}

// Drag Chat Window
let dragging = false, offsetX=0, offsetY=0;
aiHeader.addEventListener("mousedown", e => {
    dragging = true;
    const rect = aiBox.getBoundingClientRect();
    offsetX = e.clientX - rect.left;
    offsetY = e.clientY - rect.top;
});
document.addEventListener("mousemove", e => {
    if (!dragging) return;
    aiBox.style.left = `${e.clientX - offsetX}px`;
    aiBox.style.top = `${e.clientY - offsetY}px`;
});
document.addEventListener("mouseup", () => dragging = false);
</script>
