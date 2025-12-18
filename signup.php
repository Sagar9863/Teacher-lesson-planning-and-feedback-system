<?php
// signup.php
session_start();
require_once 'config/db.php';

// --- Initialize Variables ---
$error_message = null;

// --- Handle Verification Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // --- Start Validation ---
    $errors = [];
    if (empty($username) || empty($email) || empty($password)) {
        $errors[] = "All fields are required.";
    }

    if (empty($errors)) {
        // 1. Check if the username exists first
        $stmt = $conn->prepare("SELECT userid, email, password, roleid FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // 2. If username exists, check if the email matches that account
            if ($user['email'] !== $email) {
                $error_message = "The email you entered does not match the account for that username.";
            }
            // 3. If email matches, check the password
            elseif (!password_verify($password, $user['password'])) {
                $error_message = "The password you entered is incorrect.";
            }
            // 4. If all details match, proceed
            else {
                // Block admins from using this flow
                if ($user['roleid'] == 1) {
                    $error_message = "Administrators cannot register through this page.";
                } else {
                    // --- Verification Successful ---
                    $_SESSION['new_user_id'] = $user['userid'];
                    $_SESSION['new_user_roleid'] = $user['roleid'];

                    // Redirect to the correct profile completion page
                    if ($user['roleid'] == 2) { // Teacher
                        header("Location: TeacherRegister.php");
                        exit();
                    } elseif ($user['roleid'] == 3) { // Student
                        header("Location: StudentRegister.php");
                        exit();
                    }
                }
            }
        } else {
            // No user found with that username
            $error_message = "No account found with that username.";
        }
        $stmt->close();
    } else {
        $error_message = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-white dark:bg-gray-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Account - Smart Lesson Plan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="styles/style.css">
</head>
<body class="h-full">
    <div class="flex min-h-full">
        <!-- Left Panel -->
        <div class="hidden lg:flex flex-1 w-0 relative">
            <div class="absolute inset-0 h-full w-full bg-gradient-to-br from-purple-800 to-indigo-800"></div>
            <div class="relative z-10 flex flex-col justify-center text-white px-24">
                <div class="flex items-center text-white">
                    <svg class="h-10 w-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                    </svg>
                    <span class="ml-3 text-3xl font-bold">Smart Lesson Plan</span>
                </div>
                <h1 class="mt-8 text-4xl font-bold tracking-tight">Verify Your Account</h1>
                <p class="mt-4 text-lg text-purple-200">Please enter the credentials provided by your administrator to begin the registration process.</p>
            </div>
        </div>

        <!-- Right Panel (Verification Form) -->
        <div class="flex flex-1 flex-col justify-center py-12 px-4 sm:px-6 lg:flex-none lg:px-20 xl:px-24">
            <div class="mx-auto w-full max-w-sm lg:w-96">
                <div>
                    <h2 class="mt-6 text-3xl font-extrabold text-gray-900 dark:text-white">Account Verification</h2>
                </div>

                <div class="mt-8">
                    <?php if ($error_message): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                            <p><?php echo $error_message; ?></p>
                        </div>
                    <?php endif; ?>

                    <form action="signup.php" method="POST" class="space-y-6">
                        <input type="hidden" name="verify" value="1">
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
                            <div class="mt-1 relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" /></svg></div>
                                <input id="username" name="username" type="text" required class="appearance-none block w-full pl-10 px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            </div>
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email Address</label>
                            <div class="mt-1 relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M2.003 5.884L10 2.5l7.997 3.384A2 2 0 0019 7.5v.19l-7-3.548a2 2 0 00-2 0L2 7.69V7.5a2 2 0 011.003-1.616z" /><path fill-rule="evenodd" d="M2 10.5V7.5a2 2 0 011.003-1.616l7-3.548a2 2 0 011.994 0l7 3.548A2 2 0 0118 7.5v3a2 2 0 01-1.003 1.616l-7 3.548a2 2 0 01-1.994 0l-7-3.548A2 2 0 012 10.5zm10.5 1.5a.5.5 0 00-.5-.5h-2a.5.5 0 000 1h2a.5.5 0 00.5-.5z" clip-rule="evenodd" /></svg></div>
                                <input id="email" name="email" type="email" required class="appearance-none block w-full pl-10 px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            </div>
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Temporary Password</label>
                            <div class="mt-1 relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg></div>
                                <input id="password" name="password" type="password" required class="appearance-none block w-full pl-10 px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5">
                                    <svg id="eye-icon" class="h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z" /><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" /></svg>
                                    <svg id="eye-off-icon" class="h-5 w-5 text-gray-500 hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.955 9.955 0 00-4.525.925L3.707 2.293zM10 12a2 2 0 110-4 2 2 0 010 4z" clip-rule="evenodd" /><path d="M2 10s3.923-6 8-6 8 6 8 6-3.923 6-8 6-8-6-8-6z" /></svg>
                                </button>
                            </div>
                        </div>

                        <div>
                            <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Verify & Continue
                            </button>
                        </div>
                    </form>

                    <div class="mt-6">
                        <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
                            Already have an account?
                            <a href="login.php" class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                                Please login.
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
