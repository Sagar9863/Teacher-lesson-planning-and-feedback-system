<?php
// login.php
session_start();
require_once 'config/db.php'; // Your database connection

// --- NEW: Check for "Remember Me" cookie ---
if (isset($_COOKIE['remember_me_token']) && !isset($_SESSION['userid'])) {
    $token = $_COOKIE['remember_me_token'];
    $stmt = $conn->prepare("SELECT userid FROM user_tokens WHERE token = ? AND expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result) {
        // Token is valid, log the user in
        $user_id = $result['userid'];
        // Fetch user details to populate the session
        $user_stmt = $conn->prepare("SELECT u.userid, u.username, r.name as role_name FROM users u JOIN role r ON u.roleid = r.roleid WHERE u.userid = ?");
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user = $user_stmt->get_result()->fetch_assoc();

        if ($user) {
            $_SESSION['userid'] = $user['userid'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role_name'];

            // Set role-specific IDs
            if ($user['role_name'] === 'teacher') {
                $teacher_stmt = $conn->prepare("SELECT teacher_id FROM teacher WHERE userid = ?");
                $teacher_stmt->bind_param("i", $user['userid']);
                $teacher_stmt->execute();
                $teacher_result = $teacher_stmt->get_result()->fetch_assoc();
                if ($teacher_result) {
                    $_SESSION['teacher_id'] = $teacher_result['teacher_id'];
                }
            } elseif ($user['role_name'] === 'student') {
                $student_stmt = $conn->prepare("SELECT student_id FROM student WHERE userid = ?");
                $student_stmt->bind_param("i", $user['userid']);
                $student_stmt->execute();
                $student_result = $student_stmt->get_result()->fetch_assoc();
                if ($student_result) {
                    $_SESSION['student_id'] = $student_result['student_id'];
                }
            }
        }
    }
}


// --- Initialize Variables ---
$error_message = null;
$success_message = null;

// --- Check for messages from other pages (like password reset) ---
if (isset($_SESSION['login_message'])) {
    $success_message = $_SESSION['login_message'];
    unset($_SESSION['login_message']);
}

// --- Check for registration success message ---
if (isset($_GET['registration']) && $_GET['registration'] === 'success') {
    $success_message = "Your account has been successfully created! Please log in to continue.";
}
if (isset($_GET['registration']) && $_GET['registration'] === 'already_complete') {
    $error_message = "Your profile has already been completed. Please log in.";
}


// --- Handle Login Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $remember_me = isset($_POST['remember_me']);

    if (empty($username) || empty($password)) {
        $error_message = "Please enter both username and password.";
    } else {
        // Prepare and execute the query to find the user
        $stmt = $conn->prepare("
            SELECT u.userid, u.username, u.password, u.status, r.name as role_name 
            FROM users u 
            JOIN role r ON u.roleid = r.roleid 
            WHERE u.username = ?
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Verify the password
            if (password_verify($password, $user['password'])) {
                
                if ($user['status'] === 'unregistered') {
                    $_SESSION['new_user_id'] = $user['userid'];
                    $_SESSION['new_user_roleid'] = $user['roleid'];
                    if ($user['role_name'] === 'teacher') {
                        header("Location: TeacherRegister.php");
                    } else {
                        header("Location: StudentRegister.php");
                    }
                    exit();
                }

                // --- Login Successful ---
                $_SESSION['userid'] = $user['userid'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role_name'];

                // --- NEW: Handle "Remember Me" ---
                if ($remember_me) {
                    $token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
                    
                    // Store token in the database
                    $token_stmt = $conn->prepare("INSERT INTO user_tokens (userid, token, expires_at) VALUES (?, ?, ?)");
                    $token_stmt->bind_param("iss", $user['userid'], $token, $expires_at);
                    $token_stmt->execute();

                    // Set the cookie
                    setcookie('remember_me_token', $token, time() + (86400 * 30), "/"); // 30 days
                }


                // Redirect based on role
                if ($user['role_name'] === 'admin') {
                    header("Location: AdminDashboard.php");
                    exit();
                } elseif ($user['role_name'] === 'teacher') {
                    $teacher_stmt = $conn->prepare("SELECT teacher_id FROM teacher WHERE userid = ?");
                    $teacher_stmt->bind_param("i", $user['userid']);
                    $teacher_stmt->execute();
                    $teacher_result = $teacher_stmt->get_result()->fetch_assoc();
                    if ($teacher_result) {
                        $_SESSION['teacher_id'] = $teacher_result['teacher_id'];
                    }
                    $teacher_stmt->close();
                    header("Location: TeacherDashboard.php");
                    exit();
                } elseif ($user['role_name'] === 'student') {
                    $student_stmt = $conn->prepare("SELECT student_id FROM student WHERE userid = ?");
                    $student_stmt->bind_param("i", $user['userid']);
                    $student_stmt->execute();
                    $student_result = $student_stmt->get_result()->fetch_assoc();
                    if ($student_result) {
                        $_SESSION['student_id'] = $student_result['student_id'];
                    }
                    $student_stmt->close();
                    header("Location: StudentDashboard.php");
                    exit();
                } else {
                    $error_message = "Unknown user role.";
                }

            } else {
                $error_message = "Invalid username or password.";
            }
        } else {
            $error_message = "Invalid username or password.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-white dark:bg-gray-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Smart Lesson Plan</title>
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
                <h1 class="mt-8 text-4xl font-bold tracking-tight">Welcome Back</h1>
                <p class="mt-4 text-lg text-indigo-200">Login to access your personalized dashboard and manage your educational journey with ease.</p>
            </div>
        </div>

        <!-- Right Panel (Login Form) -->
        <div class="flex flex-1 flex-col justify-center py-12 px-4 sm:px-6 lg:flex-none lg:px-20 xl:px-24">
            <div class="mx-auto w-full max-w-sm lg:w-96">
                <div>
                    <h2 class="mt-6 text-3xl font-extrabold text-gray-900 dark:text-white">Login to your account</h2>
                </div>

                <div class="mt-8">
                    <?php if ($error_message): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                            <p><?php echo $error_message; ?></p>
                        </div>
                    <?php endif; ?>
                     <?php if ($success_message): ?>
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                            <p><?php echo $success_message; ?></p>
                        </div>
                    <?php endif; ?>

                    <form action="login.php" method="POST" class="space-y-6">
                        <input type="hidden" name="login" value="1">
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
                            <div class="mt-1 relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" /></svg></div>
                                <input id="username" name="username" type="text" required class="appearance-none block w-full pl-10 px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            </div>
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                            <div class="mt-1 relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg></div>
                                <input id="password" name="password" type="password" required class="appearance-none block w-full pl-10 px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5">
                                    <svg id="eye-icon" class="h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z" /><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" /></svg>
                                    <svg id="eye-off-icon" class="h-5 w-5 text-gray-500 hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.955 9.955 0 00-4.525.925L3.707 2.293zM10 12a2 2 0 110-4 2 2 0 010 4z" clip-rule="evenodd" /><path d="M2 10s3.923-6 8-6 8 6 8 6-3.923 6-8 6-8-6-8-6z" /></svg>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <input id="remember_me" name="remember_me" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="remember_me" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                                    Remember me
                                </label>
                            </div>
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
                            Don't have an account?
                            <a href="signup.php" class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                                Please sign up.
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('togglePassword').addEventListener('click', function () {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            const eyeOffIcon = document.getElementById('eye-off-icon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.add('hidden');
                eyeOffIcon.classList.remove('hidden');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('hidden');
                eyeOffIcon.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
