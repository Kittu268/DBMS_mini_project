<?php
<?php include 'header.php'; ?>

session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}
$username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AI Assistant - Airline Reservation System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .chat-container {
            max-width: 700px;
            margin: 30px auto;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 70vh;
        }
        .chat-history {
            flex-grow: 1;
            padding: 15px;
            overflow-y: auto;
            background-color: #f8f9fa;
        }
        .chat-input {
            padding: 10px 15px;
            border-top: 1px solid #ddd;
            background-color: #fff;
        }
        .message {
            margin-bottom: 10px;
            padding: 8px 12px;
            border-radius: 15px;
            max-width: 80%;
        }
        .user-message {
            background-color: #007bff;
            color: white;
            margin-left: auto;
            text-align: right;
        }
        .assistant-message {
            background-color: #e9ecef;
            color: #343a40;
            margin-right: auto;
            text-align: left;
        }
    </style>
</head>
<body>
<div class="chat-container shadow">
    <div class="p-3 bg-primary text-white text-center">
        <h5>🤖 Airline Assistant</h5>
    </div>

    <!-- Chat history -->
    <div class="chat-history" id="chatHistory">
        <div class="message assistant-message">
            Hello, <?php echo htmlspecialchars($username); ?>! Ask about flights or reservations.
        </div>
    </div>

    <!-- Predefined query dropdown -->
    <div class="p-2 border-bottom bg-light">
        <label for="predefinedQuery"><strong>Run a predefined query:</strong></label>
        <select id="predefinedQuery" class="form-control">
            <option value="">-- Select Query --</option>
            <option value="SELECT * FROM flight;">All Flights</option>
            <option value="SELECT * FROM reservation;">All Reservations</option>
            <option value="SELECT Email, COUNT(*) AS Total_Reservations FROM reservation GROUP BY Email;">Reservation Count per Customer</option>
            <option value="SELECT f.Flight_number, fl.Departure_airport_code, fl.Arrival_airport_code FROM flight f JOIN flight_leg fl ON f.Flight_number = fl.Flight_number;">Flight + Legs</option>
            <option value="SELECT a.Airplane_id, a.Type_name, t.Company FROM airplane a JOIN airplane_type t ON a.Type_name = t.Type_name;">Airplanes with Type & Company</option>
        </select>
        <button class="btn btn-success btn-block mt-2" onclick="runPredefinedQuery()">Run Query</button>
    </div>

    <!-- User input -->
    <div class="chat-input">
        <div class="input-group">
            <input type="text" id="userInput" class="form-control" placeholder="Type your message..." onkeypress="if(event.key==='Enter') sendMessage()">
            <div class="input-group-append">
                <button class="btn btn-primary" type="button" onclick="sendMessage()">Send</button>
            </div>
        </div>
    </div>
</div>

<script>
const chatHistoryDiv = document.getElementById('chatHistory');
const userInput = document.getElementById('userInput');
const predefinedQuery = document.getElementById('predefinedQuery');

function appendMessage(sender, message, isHTML=false) {
    const messageDiv = document.createElement('div');
    messageDiv.classList.add('message', sender==='user'?'user-message':'assistant-message');
    if (isHTML) messageDiv.innerHTML = message;
    else messageDiv.textContent = message;
    chatHistoryDiv.appendChild(messageDiv);
    chatHistoryDiv.scrollTop = chatHistoryDiv.scrollHeight;
}

// Send free-text message
async function sendMessage() {
    const message = userInput.value.trim();
    if (!message) return;
    appendMessage('user', message);
    userInput.value = '';
    appendMessage('assistant', '...');

    try {
        const res = await fetch('ai_assistant_api.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            credentials: 'include',
            body: JSON.stringify({prompt: message})
        });
        const data = await res.json();
        chatHistoryDiv.removeChild(chatHistoryDiv.lastChild);
        appendMessage('assistant', data.reply, true);
    } catch(err) {
        chatHistoryDiv.removeChild(chatHistoryDiv.lastChild);
        appendMessage('assistant', `⚠️ Error: ${err.message}`);
    }
}

// Run predefined query
function runPredefinedQuery() {
    const query = predefinedQuery.value;
    if (!query) return alert("Please select a query.");
    appendMessage('user', "[Predefined Query] " + query);
    userInput.value = "sql " + query; // format for API
    sendMessage();
}
</script>
</body>
</html>
