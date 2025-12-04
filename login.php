<?php
session_start();
require_once 'inc/functions.php';

// Handle login form submission
$error = handleLoginActions();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 flex items-center justify-center h-screen">

    <div class="w-96 bg-white p-8 rounded-lg shadow-lg">

        <h2 class="text-2xl font-bold mb-6 text-center">Admin Login</h2>

        <?php if ($error) { ?>
            <div class="bg-red-100 text-red-600 p-3 mb-4 rounded">
                <?= $error ?>
            </div>
        <?php } ?>

        <form method="POST">

            <input type="text" name="username" 
                   class="w-full p-3 mb-4 border rounded"
                   placeholder="Username" required>

            <input type="password" name="password"
                   class="w-full p-3 mb-6 border rounded"
                   placeholder="Password" required>

            <button type="submit" 
                    class="w-full bg-black text-white p-3 rounded font-semibold hover:bg-gray-800">
                Login
            </button>

        </form>

    </div>

</body>
</html>