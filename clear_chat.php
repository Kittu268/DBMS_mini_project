<?php
session_start();
unset($_SESSION['chat_history']);
echo json_encode(["status" => "cleared"]);
?>
