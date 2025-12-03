<?php
session_start();
require_once '../src/db.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Query user from database
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid username or password!";
        }
    } else {
        $error = "Invalid username or password!";
    }
    
    $stmt->close();
}
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
                    class="w-full bg-blue-600 text-white p-3 rounded font-semibold">
                Login
            </button>

        </form>

    </div>

</body>
</html>
