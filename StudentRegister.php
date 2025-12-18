<?php
// StudentRegister.php
session_start();
require_once 'config/db.php';
require_once 'functions/main_functions.php';

// --- AJAX Handler for fetching semesters ---
if (isset($_GET['fetch_semesters']) && isset($_GET['program_id'])) {
    header('Content-Type: application/json');
    $program_id = intval($_GET['program_id']);
    $semesters_result = getSemestersByProgramId($conn, $program_id);
    $semesters = [];
    if ($semesters_result) {
        while ($row = $semesters_result->fetch_assoc()) {
            $semesters[] = $row;
        }
    }
    echo json_encode($semesters);
    exit();
}


// Security check
if (!isset($_SESSION['new_user_id']) || !isset($_SESSION['new_user_roleid']) || $_SESSION['new_user_roleid'] != 3) {
    header("Location: signup.php");
    exit();
}

$user_id = $_SESSION['new_user_id'];
$error_message = null;

// Check if the student profile is already completed
$stmt_check = $conn->prepare("SELECT student_id FROM student WHERE userid = ?");
$stmt_check->bind_param("i", $user_id);
$stmt_check->execute();
$stmt_check->store_result();
if ($stmt_check->num_rows > 0) {
    session_unset();
    session_destroy();
    header("Location: login.php?registration=already_complete");
    exit();
}
$stmt_check->close();


// Fetch data for dropdowns
$programs = getPrograms($conn);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_student_profile'])) {
    $firstname = ucfirst(strtolower(trim($_POST['firstname'] ?? '')));
    $middlename = !empty(trim($_POST['middlename'])) ? ucfirst(strtolower(trim($_POST['middlename']))) : null;
    $lastname = ucfirst(strtolower(trim($_POST['lastname'] ?? '')));
    $contact_number = trim($_POST['contact_number'] ?? '');
    $address = ucfirst(strtolower(trim($_POST['address'] ?? '')));
    $program_id = $_POST['program_id'] ?? '';
    $semester_id = $_POST['semester_id'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $join_date = date('Y-m-d'); // Set join date to current date

    // --- Start Validation ---
    $errors = [];
    if (empty($firstname) || empty($lastname) || empty($contact_number) || empty($address) || empty($program_id) || empty($semester_id) || empty($password)) {
        $errors[] = "All required fields must be filled out.";
    }
    if (!preg_match('/^(97|98)\d{8}$/', $contact_number)) {
        $errors[] = "Please enter a valid 10-digit contact number starting with 97 or 98.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Your new passwords do not match.";
    }
    if (strlen($password) < 8) { $errors[] = "Password must be at least 8 characters long."; }
    // ... other password checks

    if (!empty($errors)) {
        $error_message = implode('<br>', $errors);
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $conn->begin_transaction();
        try {
            // --- FIX: Use INSERT to create the new student profile ---
            $stmt_student = $conn->prepare("
                INSERT INTO student (userid, firstname, middlename, lastname, contact_number, address, program_id, semester_id, status, join_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)
            ");
            $stmt_student->bind_param("isssssiis", $user_id, $firstname, $middlename, $lastname, $contact_number, $address, $program_id, $semester_id, $join_date);
            $stmt_student->execute();
            $stmt_student->close();

            // 2. Update the users table with the new hashed password and status
            $stmt_user = $conn->prepare("UPDATE users SET password = ?, status = 'registered' WHERE userid = ?");
            $stmt_user->bind_param("si", $hashed_password, $user_id);
            $stmt_user->execute();
            $stmt_user->close();

            $conn->commit();

            session_unset();
            session_destroy();
            header("Location: login.php?registration=success");
            exit();

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $error_message = "An error occurred while saving your profile. Please try again.";
            error_log("Student profile update error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-100 dark:bg-gray-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Student Profile</title>
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
<body class="bg-gray-100 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl w-full space-y-8 mx-auto">
        <div>
            <div class="flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                <svg class="h-12 w-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" /></svg>
            </div>
            <h2 class="mt-4 text-center text-3xl font-extrabold text-gray-900 dark:text-white">
                Student Registration
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
        <form class="mt-8 space-y-6" action="StudentRegister.php" method="POST" id="student-register-form">
            <input type="hidden" name="complete_student_profile" value="1">
            <div class="rounded-xl shadow-lg p-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                    <!-- Left Column: Details -->
                    <div class="space-y-6">
                        <div><h3 class="text-lg font-medium text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2">Personal Details</h3></div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div>
                                <label for="firstname" class="block text-sm font-medium text-gray-700 dark:text-gray-300">First Name</label>
                                <input id="firstname" name="firstname" type="text" required class="mt-1 appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                            </div>
                            <div>
                                <label for="middlename" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Middle Name <span class="text-gray-400">(Optional)</span></label>
                                <input id="middlename" name="middlename" type="text" class="mt-1 appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                            </div>
                            <div class="sm:col-span-2">
                                <label for="lastname" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last Name</label>
                                <input id="lastname" name="lastname" type="text" required class="mt-1 appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                            </div>
                        </div>
                        <div class="pt-4"><h3 class="text-lg font-medium text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2">Academic & Contact Information</h3></div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div>
                                <label for="contact_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contact Number</label>
                                <input id="contact_number" name="contact_number" type="tel" required pattern="^(97|98)\d{8}$" title="Enter a 10-digit number starting with 97 or 98." maxlength="10" oninput="this.value=this.value.replace(/[^0-9]/g,'');" class="mt-1 appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                            </div>
                            <div>
                                <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Address</label>
                                <input id="address" name="address" type="text" required class="mt-1 appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                            </div>
                             <div>
                                <label for="join_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Join Date</label>
                                <input id="join_date" name="join_date" type="date" required max="<?php echo date('Y-m-d'); ?>" class="mt-1 appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                            </div>
                            <div class="sm:col-span-2">
                                <label for="program_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Your Program</label>
                                <select name="program_id" id="program_id" required class="mt-1 block w-full p-2 border border-gray-300 bg-white rounded-md shadow-sm">
                                    <option value="">Select your program...</option>
                                    <?php if ($programs && $programs->num_rows > 0): ?>
                                        <?php while($program = $programs->fetch_assoc()): ?>
                                            <option value="<?php echo $program['program_id']; ?>">
                                                <?php echo htmlspecialchars($program['program_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <label for="semester_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Your Semester</label>
                                <select name="semester_id" id="semester_id" required class="mt-1 block w-full p-2 border border-gray-300 bg-white rounded-md shadow-sm" disabled>
                                    <option value="">Please select a program first</option>
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
                                 <input id="password" name="password" type="password" required class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                            </div>
                        </div>
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm New Password</label>
                            <div class="mt-1 relative">
                                <input id="confirm_password" name="confirm_password" type="password" required class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                            </div>
                        </div>
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
                <button type="submit" id="submit-button" class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
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

            // --- Dependent Dropdown Logic ---
            const programSelect = document.getElementById('program_id');
            const semesterSelect = document.getElementById('semester_id');

            programSelect.addEventListener('change', function() {
                const programId = this.value;
                semesterSelect.disabled = true;
                semesterSelect.innerHTML = '<option value="">Loading semesters...</option>';

                if (programId) {
                    fetch(`StudentRegister.php?fetch_semesters=true&program_id=${programId}`)
                        .then(response => response.json())
                        .then(data => {
                            semesterSelect.innerHTML = '<option value="">Select your semester...</option>';
                            data.forEach(semester => {
                                const option = document.createElement('option');
                                option.value = semester.semester_id;
                                option.textContent = `Semester ${semester.semester_level}`;
                                semesterSelect.appendChild(option);
                            });
                            semesterSelect.disabled = false;
                        })
                        .catch(error => {
                            console.error('Error fetching semesters:', error);
                            semesterSelect.innerHTML = '<option value="">Could not load semesters</option>';
                        });
                } else {
                    semesterSelect.innerHTML = '<option value="">Please select a program first</option>';
                }
            });

            // Form submission loading indicator
            const form = document.getElementById('student-register-form');
            const submitButton = document.getElementById('submit-button');
            const btnText = submitButton.querySelector('.btn-text');
            const loader = submitButton.querySelector('.loader');

            form.addEventListener('submit', function() {
                btnText.textContent = 'Processing...';
                loader.classList.remove('hidden');
                submitButton.disabled = true;
            });
        });
    </script>
</body>
</html>
