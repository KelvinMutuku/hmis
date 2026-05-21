<?php
// includes/db_connect.php

$host = "sql113.ezyro.com";
$username = "ezyro_41856803"; // Change this if you have a specific database user
$password = "fa41a9c94f63";     // Add your database password here
$database = "ezyro_41856803_hospital_db";

// Set PHP timezone to Nairobi
date_default_timezone_set('Africa/Nairobi');
// Create connection
$conn = new mysqli($host, $username, $password, $database);
// Set MySQL database timezone to EAT (+03:00)
$conn->query("SET time_zone = '+03:00'");
// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>