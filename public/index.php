<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Admin';
$role = $_SESSION['role'] ?? 'admin';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <link rel="stylesheet" href="/css/style.css">
</head>

<body class="bg-gray-100 p-10">

    <div class="bg-white p-6 rounded shadow max-w-xl mx-auto">
        <h1 class="text-3xl font-bold mb-4">Welcome, <?= htmlspecialchars($username) ?>!</h1>
        <p class="text-gray-600 mb-6">Role: <span class="font-semibold"><?= htmlspecialchars($role) ?></span></p>

        <a href="logout.php" 
           class="bg-red-600 text-white px-4 py-2 rounded">
           Logout
        </a>
    </div>

</body>
</html>
