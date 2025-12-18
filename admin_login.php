<?php
// admin_login.php

session_start();

// If admin is already logged in, redirect to the dashboard.
if (isset($_SESSION['userid']) && isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'admin') {
    header("Location: AdminDashboard.php");
    exit();
}

require_once 'config/db.php';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error_message = "Username and password are required.";
    } else {
        // Prepare SQL to fetch user by username
        $stmt = $conn->prepare("
            SELECT u.userid, u.username, u.password, r.name as role_name 
            FROM users u 
            JOIN role r ON u.roleid = r.roleid 
            WHERE u.username = ? AND r.name = 'admin'
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Step 2: If username is correct, now check the password.
            if ($password === $user['password']) {
                // --- Login Successful ---
                session_regenerate_id(true); // Prevent session fixation

                $_SESSION['userid'] = $user['userid'];
                $_SESSION['username'] = $user['username'];
                // *** FIX: Use the correct session variable 'role_name' ***
                $_SESSION['role_name'] = $user['role_name'];

                // Redirect to the Admin Dashboard
                header("Location: AdminDashboard.php");
                exit();
            } else {
                // Password check failed
                $error_message = "Incorrect password for this username.";
            }
        } else {
            // Username check failed or user is not an admin
            $error_message = "Admin username not found.";
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-white dark:bg-gray-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Smart Lesson Plan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="styles/style.css">
</head>
<body class="h-full">
    <div class="flex min-h-full">
        <!-- Left Panel -->
        <div class="hidden lg:flex flex-1 w-0 relative">
            <div class="absolute inset-0 h-full w-full bg-gradient-to-br from-indigo-800 to-purple-800"></div>
            <div class="relative z-10 flex flex-col justify-center text-white px-24">
                <div class="flex items-center text-white">
                    <svg class="h-10 w-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                    </svg>
                    <span class="ml-3 text-3xl font-bold">Smart Lesson Plan</span>
                </div>
                <h1 class="mt-8 text-4xl font-bold tracking-tight">Administrator Access</h1>
                <p class="mt-4 text-lg text-indigo-200">Manage your educational ecosystem with powerful and intuitive tools.</p>
            </div>
        </div>

        <!-- Right Panel (Login Form) -->
        <div class="flex flex-1 flex-col justify-center py-12 px-4 sm:px-6 lg:flex-none lg:px-20 xl:px-24">
            <div class="mx-auto w-full max-w-sm lg:w-96">
                <div>
                    <h2 class="mt-6 text-3xl font-extrabold text-gray-900 dark:text-white">Admin Login</h2>
                </div>

                <div class="mt-8">
                    <?php if ($error_message): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                            <p><?php echo htmlspecialchars($error_message); ?></p>
                        </div>
                    <?php endif; ?>

                    <form action="admin_login.php" method="POST" class="space-y-6">
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
                            <div class="mt-1">
                                <input id="username" name="username" type="text" required class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            </div>
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                            <div class="mt-1">
                                <input id="password" name="password" type="password" required class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            </div>
                        </div>

                        <div class="flex items-center justify-end">
                            <div class="text-sm">
                                <a href="forgot_password.php" class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">
                                    Forgot your password?
                                </a>
                            </div>
                        </div>

                        <div>
                            <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Login
                            </button>
                        </div>
                    </form>
                     <div class="mt-6">
                        <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
                            Are you a teacher or student?
                            <a href="login.php" class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                                Login here.
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
