<?php
$servername = "127.0.0.1"; //localhost
$username = "root";
$password = "";
$dbname = "canteen_reviews";
$port = 3306; // Add explicit port

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>





