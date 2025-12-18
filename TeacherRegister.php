<?php
// TeacherRegister.php
session_start();
require_once 'config/db.php';
require_once 'functions/main_functions.php';

// Security check: Ensure user has come from the signup/login page and is a teacher
if (!isset($_SESSION['new_user_id']) || !isset($_SESSION['new_user_roleid']) || $_SESSION['new_user_roleid'] != 2) {
    header("Location: signup.php");
    exit();
}

$user_id = $_SESSION['new_user_id'];
$error_message = null;

// --- Check if a teacher profile for this user already exists ---
$stmt_check = $conn->prepare("SELECT teacher_id FROM teacher WHERE userid = ?");
$stmt_check->bind_param("i", $user_id);
$stmt_check->execute();
$stmt_check->store_result();
if ($stmt_check->num_rows > 0) {
    // This user has already completed their registration.
    session_unset();
    session_destroy();
    header("Location: login.php?registration=already_complete");
    exit();
}
$stmt_check->close();


// Fetch data for dropdowns
$departments = getAllDepartments($conn);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_teacher_profile'])) {
    $firstname = trim($_POST['firstname'] ?? '');
    $middlename = trim($_POST['middlename'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $join_date = $_POST['join_date'] ?? '';
    $deptid = $_POST['deptid'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // --- Start Validation ---
    $errors = [];
    if (empty($firstname) || empty($lastname) || empty($contact_number) || empty($join_date) || empty($deptid) || empty($password)) {
        $errors[] = "All required fields must be filled out.";
    }
    if (!preg_match('/^(97|98)\d{8}$/', $contact_number)) {
        $errors[] = "Please enter a valid 10-digit contact number starting with 97 or 98.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Your new passwords do not match.";
    }
    
    $is_strong_password = true;
    if (strlen($password) < 8) { $is_strong_password = false; }
    if (!preg_match('/[A-Z]/', $password)) { $is_strong_password = false; }
    if (!preg_match('/[a-z]/', $password)) { $is_strong_password = false; }
    if (!preg_match('/[0-9]/', $password)) { $is_strong_password = false; }
    if (!preg_match('/[\'^£$%&*()}{@#~?><>,|=_+¬-]/', $password)) { $is_strong_password = false; }

    if (!$is_strong_password) {
        $errors[] = "Your new password does not meet all the strength requirements.";
    }

    if (!empty($errors)) {
        $error_message = implode('<br>', $errors);
    } else {
        $status = 'active'; 
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $middlename_db = !empty($middlename) ? $middlename : null;

        $conn->begin_transaction();
        try {
            // --- FIX: Use INSERT to create the new teacher profile ---
            $stmt_teacher = $conn->prepare("
                INSERT INTO teacher (userid, firstname, middlename, lastname, contact_number, status, join_date, deptid)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_teacher->bind_param("issssssi", $user_id, $firstname, $middlename_db, $lastname, $contact_number, $status, $join_date, $deptid);
            $stmt_teacher->execute();
            $stmt_teacher->close();

            // 2. Update the users table with the new hashed password AND the new status
            $stmt_user = $conn->prepare("UPDATE users SET password = ?, status = 'active' WHERE userid = ?");
            $stmt_user->bind_param("si", $hashed_password, $user_id);
            $stmt_user->execute();
            $stmt_user->close();

            // If both queries were successful, commit the changes
            $conn->commit();

            // Registration complete, clear session and redirect to login
            session_unset();
            session_destroy();
            header("Location: login.php?registration=success");
            exit();

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $error_message = "An error occurred while saving your profile. Please try again.";
            error_log("Teacher profile update error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-100 dark:bg-gray-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Teacher Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        .validation-item { transition: color 0.3s ease; }
        .validation-item.valid { color: #10b981; }
        .validation-item.invalid { color: #ef4444; }
        .dark .validation-item.valid { color: #34d399; }
        .dark .validation-item.invalid { color: #f87171; }
    </style>
</head>
<body class="h-full flex items-center justify-center py-20 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl w-full space-y-8">
        <div>
            <div class="flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                <svg class="h-12 w-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                </svg>
            </div>
            <h2 class="mt-4 text-center text-3xl font-extrabold text-gray-900 dark:text-white">
                Teacher Registration
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
                Please provide your details below to finalize your account and get started.
            </p>
        </div>
         <?php if ($error_message): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                <p><?php echo $error_message; ?></p>
            </div>
        <?php endif; ?>
        <form class="mt-8 space-y-6" action="TeacherRegister.php" method="POST" id="teacher-register-form">
            <input type="hidden" name="complete_teacher_profile" value="1">
            <div class="rounded-xl shadow-lg p-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                    <!-- Left Column: Details -->
                    <div class="space-y-6">
                        <div><h3 class="text-lg font-medium text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2">Personal Details</h3></div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div>
                                <label for="firstname" class="block text-sm font-medium text-gray-700 dark:text-gray-300">First Name</label>
                                <input id="firstname" name="firstname" type="text" required class="mt-1 appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                            <div>
                                <label for="middlename" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Middle Name <span class="text-gray-400">(Optional)</span></label>
                                <input id="middlename" name="middlename" type="text" class="mt-1 appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                            <div class="sm:col-span-2">
                                <label for="lastname" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last Name</label>
                                <input id="lastname" name="lastname" type="text" required class="mt-1 appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                        </div>
                        <div class="pt-4"><h3 class="text-lg font-medium text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2">Professional Details</h3></div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div>
                                <label for="contact_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contact Number</label>
                                <input id="contact_number" name="contact_number" type="tel" required pattern="^(97|98)\d{8}$" title="Please enter a 10-digit number starting with 97 or 98." class="mt-1 appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                            <div>
                                <label for="join_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Join Date</label>
                                <input id="join_date" name="join_date" type="date" required class="mt-1 appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                            <div class="sm:col-span-2">
                                <label for="deptid" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Department</label>
                                <select name="deptid" id="deptid" required class="mt-1 block w-full p-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-md shadow-sm">
                                    <option value="">Select Department...</option>
                                     <?php foreach($departments as $dept) { echo "<option value='{$dept['deptid']}'>" . htmlspecialchars($dept['name']) . "</option>"; } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <!-- Right Column: Security -->
                    <div class="space-y-6">
                        <div><h3 class="text-lg font-medium text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2">Set Your New Password</h3></div>
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">New Password</label>
                            <div class="mt-1 relative">
                                 <input id="password" name="password" type="password" required class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                        </div>
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm New Password</label>
                            <div class="mt-1 relative">
                                <input id="confirm_password" name="confirm_password" type="password" required class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                        </div>
                        <!-- Password Strength Requirements -->
                        <div id="password-requirements" class="text-xs space-y-1 text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/50 p-4 rounded-md">
                            <p class="font-medium text-gray-700 dark:text-gray-200">Password must contain:</p>
                            <p id="length-req" class="validation-item invalid">✓ At least 8 characters</p>
                            <p id="upper-req" class="validation-item invalid">✓ One uppercase letter (A-Z)</p>
                            <p id="lower-req" class="validation-item invalid">✓ One lowercase letter (a-z)</p>
                            <p id="number-req" class="validation-item invalid">✓ One number (0-9)</p>
                            <p id="special-req" class="validation-item invalid">✓ One special character (!@#...)</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="pt-4">
                <button type="submit" id="submit-button" class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <span class="btn-text">Complete Registration</span>
                    <span class="loader hidden animate-spin rounded-full h-5 w-5 border-b-2 border-white"></span>
                </button>
            </div>
            <div class="text-center text-sm mt-2">
                <a href="login.php" class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                    Back to Login
                </a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const requirements = {
                length: { el: document.getElementById('length-req'), regex: /.{8,}/ },
                upper: { el: document.getElementById('upper-req'), regex: /[A-Z]/ },
                lower: { el: document.getElementById('lower-req'), regex: /[a-z]/ },
                number: { el: document.getElementById('number-req'), regex: /[0-9]/ },
                special: { el: document.getElementById('special-req'), regex: /[\'^£$%&*()}{@#~?><>,|=_+¬-]/ }
            };

            passwordInput.addEventListener('keyup', () => {
                const password = passwordInput.value;
                Object.values(requirements).forEach(req => {
                    if (req.el && req.el.classList) {
                        if (req.regex.test(password)) {
                            req.el.classList.replace('invalid', 'valid');
                        } else {
                            req.el.classList.replace('valid', 'invalid');
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>
