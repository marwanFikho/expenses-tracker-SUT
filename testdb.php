<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$db = new mysqli("localhost", "root", "root", "expense_tracker");

if ($db->connect_error) {
    echo "Failed: " . $db->connect_error;
} else {
    echo "Connected!";
}
