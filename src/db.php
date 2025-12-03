<?php

$host = "localhost";
$user = "root";
$pass = "";
$db   = "bicycle_rental";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("DB Connection Failed: " . mysqli_connect_error());
}
