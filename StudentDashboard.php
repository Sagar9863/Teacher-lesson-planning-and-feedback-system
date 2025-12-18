<?php
// StudentDashboard.php
session_start();
require_once 'config/db.php';
require_once 'functions/main_functions.php';

// --- Security Check: Ensure a student is logged in ---
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'student') {
    header("Location: login.php"); // Redirect to login if not a student
    exit();
}
$student_id = $_SESSION['student_id'];
$user_id = $_SESSION['userid']; // Get the general userid for profile/password actions

// --- Handle POST Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Handle Feedback Form Submission ---
    if (isset($_POST['submit_feedback'])) {
        $lessonplan_id = intval($_POST['lessonplan_id']);
        $ratings = $_POST['ratings'] ?? [];
        $comment = trim($_POST['comment'] ?? '');

        if ($lessonplan_id > 0 && !empty($ratings)) {
            $result = submitFeedback($conn, $student_id, $lessonplan_id, $ratings, $comment);
            
            if ($result === true) {
                $_SESSION['form_message'] = "Your feedback has been submitted successfully. Thank you!";
                $_SESSION['form_message_type'] = 'success';
            } else {
                $_SESSION['form_message'] = "An error occurred. DB Error: " . $result;
                $_SESSION['form_message_type'] = 'error';
            }
        } else {
            $_SESSION['form_message'] = "Please select a lesson plan and provide a rating for all criteria.";
            $_SESSION['form_message_type'] = 'error';
        }
        header("Location: StudentDashboard.php?page=feedback");
        exit();
    }

    // --- Handle Feedback Form Update ---
    if (isset($_POST['update_feedback'])) {
        $feedback_id = intval($_POST['feedback_id']);
        $ratings = $_POST['ratings'] ?? [];
        $comment = trim($_POST['comment'] ?? '');

        // Ensure we have a valid feedback ID and ratings
        if ($feedback_id > 0 && !empty($ratings)) {
            $result = updateFeedback($conn, $feedback_id, $student_id, $ratings, $comment);
            
            if ($result === true) {
                $_SESSION['form_message'] = "Your feedback has been updated successfully.";
                $_SESSION['form_message_type'] = 'success';
            } else {
                $_SESSION['form_message'] = "An error occurred while updating. DB Error: " . $result;
                $_SESSION['form_message_type'] = 'error';
            }
        } else {
            $_SESSION['form_message'] = "Could not update. Please ensure all ratings are provided.";
            $_SESSION['form_message_type'] = 'error';
        }
        // Redirect back to the feedback page
        header("Location: StudentDashboard.php?page=feedback");
        exit();
    }

    // --- Handle Profile Update ---
    if (isset($_POST['update_profile'])) {
        $firstname = trim($_POST['firstname']);
        $lastname = trim($_POST['lastname']);
        
        if (!empty($firstname) && !empty($lastname)) {
            if (updateUserProfile($conn, $user_id, 'student', $firstname, $lastname)) {
                $_SESSION['form_message'] = "Your profile has been updated successfully.";
                $_SESSION['form_message_type'] = 'success';
            } else {
                $_SESSION['form_message'] = "An error occurred while updating your profile.";
                $_SESSION['form_message_type'] = 'error';
            }
        } else {
            $_SESSION['form_message'] = "First name and last name cannot be empty.";
            $_SESSION['form_message_type'] = 'error';
        }
        header("Location: StudentDashboard.php?page=settings");
        exit();
    }

    // --- Handle Password Change ---
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];

        $result = changeUserPassword($conn, $user_id, $current_password, $new_password);

        if ($result === true) {
            // Destroy the current session to log the user out
            session_destroy();
            // Start a new session just to pass the message
            session_start();
            $_SESSION['login_message'] = "Password changed successfully. Please log in with your new password.";
            header("Location: login.php");
            exit();
        } else {
            $_SESSION['form_message'] = $result;
            $_SESSION['form_message_type'] = 'error';
            header("Location: StudentDashboard.php?page=settings");
            exit();
        }
    }
}


// --- Page Routing ---
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard'; // Default to dashboard view

// --- Fetch student's details for the header and other pages ---
$student_details = getStudentDetails($conn, $student_id);
$student_name = $student_details ? htmlspecialchars($student_details['firstname']) : 'Student';

?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-100 dark:bg-gray-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="styles/style.css">
    <script>
        // --- Theme handling script from your settings page ---
        const storedTheme = localStorage.getItem('theme') || 'system';
        if (storedTheme === 'dark' || (storedTheme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="h-full">
    <div class="min-h-full">
        <!-- Main Sidebar Navigation -->
        <?php include 'templates/student_sidebar.php'; ?>

        <div class="lg:pl-64 flex flex-col flex-1">
            <!-- Professional Header -->
            <header class="sticky top-0 z-10 bg-white/75 dark:bg-gray-900/75 backdrop-blur-sm shadow-sm">
                <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between">
                        <div class="capitalize">
                            <h1 class="text-xl font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars(str_replace('_', ' ', $page)); ?></h1>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Welcome, <?php echo $student_name; ?></p>
                        </div>
                    </div>
                </div>
            </header>
            
            <main class="flex-1 pb-8">
                <?php
                // --- Content Area ---
                // This will load the correct page content based on the URL parameter.
                switch ($page) {
                    case 'feedback':
                        include 'pages/student_feedback_content.php';
                        break;
                    case 'settings':
                        include 'pages/settings_content.php';
                        break;
                    case 'my_subjects':
                        include 'pages/student_my_subjects_content.php';
                        break;
                    case 'feedback_history':
                        include 'pages/student_feedback_history_content.php';
                        break;
                    case 'planned_lessons':
                        include 'pages/student_planned_lessons_content.php';
                        break;
                    case 'leaderboard':
                        include 'pages/student_leaderboard_content.php';
                        break;
                    case 'dashboard':
                    default:
                        include 'pages/student_dashboard_content.php';
                        break;
                }
                ?>
            </main>
        </div>
    </div>
</body>
</html>
