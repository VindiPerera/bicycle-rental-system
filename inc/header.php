<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Bicycle Rental System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#2563eb',
                        secondary: '#64748b'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-lg border-b-2 border-red-500">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="flex justify-between h-20">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <h1 class="text-2xl font-bold text-black">🚴 Bicycle Rental</h1>
                    </div>
                    <div class="hidden md:ml-8 md:flex md:space-x-8">
                        <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'border-red-500 text-red-600' : 'border-transparent text-black hover:text-red-600 hover:border-red-300'; ?> inline-flex items-center px-2 pt-1 border-b-3 text-lg font-bold">
                            Dashboard
                        </a>
                        <a href="settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'border-red-500 text-red-600' : 'border-transparent text-black hover:text-red-600 hover:border-red-300'; ?> inline-flex items-center px-2 pt-1 border-b-3 text-lg font-bold">
                            Settings
                        </a>
                    </div>
                </div>
                <div class="flex items-center space-x-6">
                    <span class="text-base text-black font-semibold">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="logout.php" class="bg-black text-white px-6 py-3 rounded-xl text-base font-bold hover:bg-gray-800">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-8 px-6 lg:px-12">