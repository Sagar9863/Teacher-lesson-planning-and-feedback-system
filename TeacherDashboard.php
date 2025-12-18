<?php
// TeacherDashboard.php
session_start();
require_once 'config/db.php';
require_once 'functions/main_functions.php';

// --- Security Check: Ensure a teacher is logged in ---
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'teacher') {
    header("Location: login.php"); // Redirect to login if not a teacher
    exit();
}
$teacher_id = $_SESSION['teacher_id']; // This is set on login
$user_id = $_SESSION['userid']; // Get the general userid for profile/password actions

// --- Get the current academic session ---
$current_session = getCurrentAcademicSession($conn);
$academic_year_id = $current_session['id'];


// --- Centralized Form & Deletion Logic for Teacher Pages ---

// Handle POST Actions (Creations/Updates)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle adding a new subject allocation
    if (isset($_POST['add_subject_allocation'])) {
        $subject_id = $_POST['subject_id'];
        if (!empty($subject_id) && !empty($teacher_id)) {
            if (allocateSubjectToTeacher($conn, $teacher_id, $subject_id)) {
                $_SESSION['form_message'] = "Subject added to your list successfully.";
                $_SESSION['form_message_type'] = 'success';
            } else {
                $_SESSION['form_message'] = "Error adding subject. You may have already added it.";
                $_SESSION['form_message_type'] = 'error';
            }
        }
        header("Location: TeacherDashboard.php?page=subjects");
        exit();
    }
    
    // Handle Batch Add Subjects
    if (isset($_POST['batch_add_subjects'])) {
        $subjects_to_add = $_POST['subjects_to_add'] ?? [];
        if (!empty($subjects_to_add)) {
            $count = 0;
            foreach ($subjects_to_add as $subject_id) {
                if(allocateSubjectToTeacher($conn, $teacher_id, $subject_id)) {
                    $count++;
                }
            }
            $_SESSION['form_message'] = "Successfully added {$count} subject(s).";
            $_SESSION['form_message_type'] = 'success';
        } else {
            $_SESSION['form_message'] = "No subjects were selected to add.";
            $_SESSION['form_message_type'] = 'error';
        }
        header("Location: TeacherDashboard.php?page=subjects&mode=batch");
        exit();
    }

    // Handle Batch Remove Subjects
    if (isset($_POST['batch_remove_subjects'])) {
        $allocations_to_remove = $_POST['allocations_to_remove'] ?? [];
        if (!empty($allocations_to_remove)) {
            $placeholders = implode(',', array_fill(0, count($allocations_to_remove), '?'));
            $sql = "DELETE FROM allocatedsubject WHERE allocation_id IN ($placeholders) AND teacher_id = ?";
            $stmt = $conn->prepare($sql);
            
            $types = str_repeat('i', count($allocations_to_remove)) . 'i';
            $params = array_merge($allocations_to_remove, [$teacher_id]);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                $count = $stmt->affected_rows;
                $_SESSION['form_message'] = "Successfully removed {$count} subject(s).";
                $_SESSION['form_message_type'] = 'success_delete';
            } else {
                $_SESSION['form_message'] = "An error occurred while removing subjects.";
                $_SESSION['form_message_type'] = 'error';
            }
        } else {
            $_SESSION['form_message'] = "No subjects were selected to remove.";
            $_SESSION['form_message_type'] = 'error';
        }
        header("Location: TeacherDashboard.php?page=subjects&mode=batch");
        exit();
    }

    // Handle adding a new lesson plan
    if (isset($_POST['add_lesson_plan'])) {
        $subject_id = intval($_POST['subject_id']);
        $day_no = intval($_POST['day_no']);
        $start_date = $_POST['start_date'];
        $title = trim($_POST['title']);
        $objective = trim($_POST['objective']);
        $tm_ids = $_POST['tm_ids'] ?? [];
        $notes = trim($_POST['notes']);
        $deadline_date = $_POST['deadline_date'];
        $status = isset($_POST['save_as_draft']) ? 'draft' : 'published';

        if (!empty($subject_id) && !empty($day_no) && !empty($start_date) && !empty($title) && !empty($objective) && !empty($tm_ids) && !empty($deadline_date)) {
             // NEW: Validation for deadline date
            if (strtotime($deadline_date) < strtotime($start_date)) {
                $_SESSION['form_message'] = "Error: Deadline date cannot be before the start date.";
                $_SESSION['form_message_type'] = 'error';
                header("Location: TeacherDashboard.php?page=lesson_plan&view=create");
                exit();
            }

            // NEW: Check if a lesson plan already exists for this subject and day number
            if (lessonPlanExistsForDay($conn, $teacher_id, $subject_id, $day_no)) {
                $_SESSION['form_message'] = "Error: A lesson plan for this subject and day number already exists.";
                $_SESSION['form_message_type'] = 'error';
                header("Location: TeacherDashboard.php?page=lesson_plan&view=create");
                exit();
            }

            $result = createLessonPlan($conn, $teacher_id, $subject_id, $day_no, $start_date, $title, $objective, $tm_ids, $notes, $deadline_date, $status);
            if($result['success']) {
                $_SESSION['form_message'] = "Lesson plan saved as {$status} successfully.";
                $_SESSION['form_message_type'] = 'success';

                if ($status === 'published') {
                    // Notification logic here
                }
                 // NEW: Redirect to list view on success
                header("Location: TeacherDashboard.php?page=lesson_plan&view=list");
                exit();
            } else {
                $_SESSION['form_message'] = "Error: Could not create the lesson plan. " . $result['message'];
                $_SESSION['form_message_type'] = 'error';
                 // NEW: Redirect to create view on error
                header("Location: TeacherDashboard.php?page=lesson_plan&view=create");
                exit();
            }
        } else {
            $_SESSION['form_message'] = "Please fill out all required fields.";
            $_SESSION['form_message_type'] = 'error';
             // NEW: Redirect to create view on error
            header("Location: TeacherDashboard.php?page=lesson_plan&view=create");
            exit();
        }
    }

    // Handle updating a lesson plan
    if (isset($_POST['update_lesson_plan'])) {
        $lesson_plan_id = intval($_POST['lessonplan_id']);
        $subject_id = intval($_POST['subject_id']);
        $day_no = intval($_POST['day_no']);
        $start_date = $_POST['start_date'];
        $title = trim($_POST['title']);
        $objective = trim($_POST['objective']);
        $tm_ids = $_POST['tm_ids'] ?? [];
        $notes = trim($_POST['notes']);
        $deadline_date = $_POST['deadline_date'];
        $status = isset($_POST['publish_plan']) ? 'published' : 'draft';

        if (!empty($lesson_plan_id) && !empty($subject_id) && !empty($day_no) && !empty($start_date) && !empty($title) && !empty($objective) && !empty($tm_ids) && !empty($deadline_date)) {
             // NEW: Validation for deadline date
            if (strtotime($deadline_date) < strtotime($start_date)) {
                $_SESSION['form_message'] = "Error: Deadline date cannot be before the start date.";
                $_SESSION['form_message_type'] = 'error';
                header("Location: TeacherDashboard.php?page=lesson_plan&view=create&edit={$lesson_plan_id}");
                exit();
            }

            // NEW: Check if a lesson plan already exists for this subject and day number (excluding the current one)
            if (lessonPlanExistsForDay($conn, $teacher_id, $subject_id, $day_no, $lesson_plan_id)) {
                $_SESSION['form_message'] = "Error: A lesson plan for this subject and day number already exists.";
                $_SESSION['form_message_type'] = 'error';
                header("Location: TeacherDashboard.php?page=lesson_plan&view=create&edit={$lesson_plan_id}");
                exit();
            }
            
            $result = updateLessonPlan($conn, $lesson_plan_id, $teacher_id, $subject_id, $day_no, $start_date, $title, $objective, $tm_ids, $notes, $deadline_date, $status);
            if ($result['success']) {
                $_SESSION['form_message'] = "Lesson plan updated and saved as {$status} successfully.";
                $_SESSION['form_message_type'] = 'success';
            } else {
                $_SESSION['form_message'] = "Error: " . $result['message'];
                $_SESSION['form_message_type'] = 'error';
            }
        } else {
            $_SESSION['form_message'] = "Please fill out all required fields.";
            $_SESSION['form_message_type'] = 'error';
        }
        header("Location: TeacherDashboard.php?page=lesson_plan&view=list");
        exit();
    }
    
    // Handle Profile Update
    if (isset($_POST['update_profile'])) {
        $firstname = trim($_POST['firstname']);
        $lastname = trim($_POST['lastname']);
        
        if (!empty($firstname) && !empty($lastname)) {
            if (updateUserProfile($conn, $user_id, 'teacher', $firstname, $lastname)) {
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
        header("Location: TeacherDashboard.php?page=settings");
        exit();
    }

    // Handle Password Change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];

        $result = changeUserPassword($conn, $user_id, $current_password, $new_password);

        if ($result === true) {
            session_destroy();
            session_start();
            $_SESSION['login_message'] = "Password changed successfully. Please log in with your new password.";
            header("Location: login.php");
            exit();
        } else {
            $_SESSION['form_message'] = $result;
            $_SESSION['form_message_type'] = 'error';
            header("Location: TeacherDashboard.php?page=settings");
            exit();
        }
    }
}

// Handle GET Actions (Deletions/Publishing)
if (isset($_GET['action'])) {
    // Handle deleting a single subject allocation
    if ($_GET['action'] === 'delete_subject_allocation' && isset($_GET['id'])) {
        $allocation_id = intval($_GET['id']);
        if (deleteAllocatedSubject($conn, $allocation_id, $teacher_id)) {
            $_SESSION['form_message'] = "Subject removed from your list successfully.";
            $_SESSION['form_message_type'] = 'success_delete';
        } else {
            $_SESSION['form_message'] = "Error removing subject.";
            $_SESSION['form_message_type'] = 'error';
        }
        header("Location: TeacherDashboard.php?page=subjects");
        exit();
    }

    // Handle publishing a lesson plan from the drafts page.
    if ($_GET['action'] === 'publish_lesson_plan' && isset($_GET['id'])) {
        $lesson_plan_id = intval($_GET['id']);
        if (publishLessonPlan($conn, $lesson_plan_id, $teacher_id)) {
            $_SESSION['form_message'] = "Lesson plan published successfully.";
            $_SESSION['form_message_type'] = 'success';
        } else {
            $_SESSION['form_message'] = "Error publishing lesson plan.";
            $_SESSION['form_message_type'] = 'error';
        }
        header("Location: TeacherDashboard.php?page=my_drafts");
        exit();
    }

    // Handle deleting a lesson plan
    if ($_GET['action'] === 'delete_lesson_plan' && isset($_GET['id'])) {
        $lesson_plan_id = intval($_GET['id']);
        if (deleteLessonPlan($conn, $lesson_plan_id, $teacher_id)) {
            $_SESSION['form_message'] = "Lesson plan deleted successfully.";
            $_SESSION['form_message_type'] = 'success_delete';
        } else {
            $_SESSION['form_message'] = "Error deleting lesson plan.";
            $_SESSION['form_message_type'] = 'error';
        }
        header("Location: TeacherDashboard.php?page=my_drafts");
        exit();
    }
}


// --- Page Routing ---
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-100 dark:bg-gray-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="styles/style.css">
</head>
<body class="h-full">
    <div class="min-h-full">
        <?php include 'templates/teacher_sidebar.php'; ?>
        <div class="lg:pl-64 flex flex-col flex-1">
            <header class="sticky top-0 z-10 bg-white/75 dark:bg-gray-900/75 backdrop-blur-sm shadow-sm">
                <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between">
                        <div class="capitalize">
                            <h1 class="text-xl font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars(str_replace('_', ' ', $page)); ?></h1>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                        </div>
                        <div class="text-right">
                             <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Current Session</p>
                             <p class="text-lg font-semibold text-indigo-600 dark:text-indigo-400"><?php echo htmlspecialchars($current_session['session'] . ' ' . $current_session['year']); ?></p>
                        </div>
                    </div>
                </div>
            </header>
            <main class="flex-1 pb-8">
                <?php
                // Display session-based messages centrally
                if (isset($_SESSION['form_message'])) {
                    $message_type = $_SESSION['form_message_type'] ?? 'info';
                    $color_class = '';
                    switch ($message_type) {
                        case 'success':
                            $color_class = 'bg-green-100 border-green-500 text-green-700';
                            break;
                        case 'success_delete':
                            $color_class = 'bg-yellow-100 border-yellow-500 text-yellow-700';
                            break;
                        case 'error':
                            $color_class = 'bg-red-100 border-red-500 text-red-700';
                            break;
                    }
                    echo "<div id='form-feedback-message' class='mx-auto max-w-7xl px-4 mt-6 border-l-4 {$color_class} p-4' role='alert'><p class='font-bold'>" . ucfirst(str_replace('_', ' ', $message_type)) . "</p><p>{$_SESSION['form_message']}</p></div>";
                    unset($_SESSION['form_message']);
                    unset($_SESSION['form_message_type']);
                }

                switch ($page) {
                    case 'subjects': include 'pages/teacher_subjects_content.php'; break;
                    case 'lesson_plan': include 'pages/teacher_lesson_plan_content.php'; break;
                    case 'my_drafts': include 'pages/teacher_my_drafts_content.php'; break;
                    case 'feedback': include 'pages/teacher_feedback_content.php'; break;
                    case 'leaderboard': include 'pages/teacher_leaderboard_content.php'; break;
                    case 'settings': include 'pages/settings_content.php'; break;
                    default: include 'pages/teacher_dashboard_content.php'; break;
                }
                ?>
            </main>
        </div>
    </div>

    <div id="delete-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 hidden">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-md" id="modal-content-delete">
            <div class="text-center">
                <svg class="h-12 w-12 mx-auto text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mt-4">Are you sure?</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Do you really want to delete this record? This action cannot be undone.</p>
            </div>
            <div class="mt-6 flex justify-center space-x-4">
                <button id="cancel-delete-btn" class="px-6 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">No, Cancel</button>
                <a id="confirm-delete-btn" href="#" class="px-6 py-2 bg-red-600 text-white font-semibold rounded-md hover:bg-red-700">Yes, Delete</a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const feedbackMessage = document.getElementById('form-feedback-message');
            if (feedbackMessage) {
                setTimeout(() => {
                    feedbackMessage.style.transition = 'opacity 0.5s ease';
                    feedbackMessage.style.opacity = '0';
                    setTimeout(() => feedbackMessage.style.display = 'none', 500);
                }, 10000);
            }

            const deleteModal = document.getElementById('delete-modal');
            const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
            const cancelDeleteBtn = document.getElementById('cancel-delete-btn');

            document.body.addEventListener('click', function(e) {
                if (e.target.closest('.delete-link')) {
                    e.preventDefault();
                    const deleteUrl = e.target.closest('.delete-link').getAttribute('href');
                    confirmDeleteBtn.setAttribute('href', deleteUrl);
                    deleteModal.classList.remove('hidden');
                }
            });

            cancelDeleteBtn.addEventListener('click', () => deleteModal.classList.add('hidden'));
            deleteModal.addEventListener('click', (e) => { if (e.target === deleteModal) deleteModal.classList.add('hidden'); });
        });
    </script>
</body>
</html>
