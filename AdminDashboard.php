<?php
// AdminDashboard.php

// 1. Define the project's root directory for reliable file paths.
define('ROOT_PATH', __DIR__);

// 2. Include Configuration and Functions.
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/functions/main_functions.php';

// Start the session.
session_start();

// Security Check: Ensure only logged-in administrators can access.
if (!isset($_SESSION['userid']) || !isset($_SESSION['role_name']) || $_SESSION['role_name'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Initialize messages.
$message = '';
$message_type = '';

// --- Page Routing & Academic Year Selection ---
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$all_academic_years = $conn->query("SELECT id, year, session FROM academic_year ORDER BY year DESC, session DESC")->fetch_all(MYSQLI_ASSOC);
$selected_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : ($all_academic_years[0]['id'] ?? 0);


// --- Handle GET Actions (Deletions & Session Advance) ---
if (isset($_GET['action'])) {
    
    if ($_GET['action'] === 'advance_session') {
        $result = advanceAcademicSession($conn);
        if ($result['success']) {
            $_SESSION['form_message'] = "Successfully advanced to {$result['new_session']}. {$result['promoted_count']} students were promoted.";
        } else {
            $_SESSION['form_message'] = "Error advancing session: " . $result['message'];
        }
        $_SESSION['form_message_type'] = $result['success'] ? 'success' : 'error';
        header("Location: AdminDashboard.php?page=dashboard");
        exit();
    }

    if ($_GET['action'] === 'revert_session') {
        $result = revertAcademicSession($conn);
        if ($result['success']) {
            if ($result['demoted_count'] > 0) {
                $_SESSION['form_message'] = "Successfully reverted to {$result['new_session']}. {$result['demoted_count']} students were moved back a semester.";
            } else {
                $_SESSION['form_message'] = "Successfully reverted to {$result['new_session']}. No students were moved back a semester.";
            }
            $_SESSION['form_message_type'] = 'success_delete';
        } else {
            $_SESSION['form_message'] = "Error reverting session: " . $result['message'];
            $_SESSION['form_message_type'] = 'error';
        }
        header("Location: AdminDashboard.php?page=dashboard");
        exit();
    }
    
    if ($_GET['action'] === 'delete_user' && isset($_GET['id'])) {
        $user_id_to_delete = intval($_GET['id']);
        if ($user_id_to_delete === $_SESSION['userid']) {
            $_SESSION['form_message'] = "Error: You cannot delete your own account.";
            $_SESSION['form_message_type'] = 'error';
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE userid = ?");
            $stmt->bind_param("i", $user_id_to_delete);
            if ($stmt->execute()) {
                $_SESSION['form_message'] = "User deleted successfully.";
                $_SESSION['form_message_type'] = 'success_delete';
            } else {
                $_SESSION['form_message'] = "Error: Could not delete user.";
                $_SESSION['form_message_type'] = 'error';
            }
            $stmt->close();
        }
        
        $redirect_url = 'AdminDashboard.php?page=users&action=view_all';
        if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'AdminDashboard.php') !== false) {
            $redirect_url = $_SERVER['HTTP_REFERER'];
        }
        header("Location: " . $redirect_url);
        exit();
    }
    
    if ($_GET['action'] === 'delete_faculty' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $conn->prepare("DELETE FROM faculty WHERE faculty_id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $_SESSION['form_message'] = "Faculty deleted successfully.";
            $_SESSION['form_message_type'] = 'success_delete';
        } else {
            $_SESSION['form_message'] = "Error: Could not delete faculty.";
            $_SESSION['form_message_type'] = 'error';
        }
        $stmt->close();
        header("Location: AdminDashboard.php?page=academics&action=manage_faculty");
        exit();
    }

    if ($_GET['action'] === 'delete_program' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $conn->prepare("DELETE FROM program WHERE program_id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $_SESSION['form_message'] = "Program deleted successfully.";
            $_SESSION['form_message_type'] = 'success_delete';
        } else {
            $_SESSION['form_message'] = "Error: Could not delete program.";
            $_SESSION['form_message_type'] = 'error';
        }
        $stmt->close();
        header("Location: AdminDashboard.php?page=academics&action=manage_programs");
        exit();
    }

    if ($_GET['action'] === 'delete_semester' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $conn->prepare("DELETE FROM semester WHERE semester_id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $_SESSION['form_message'] = "Semester deleted successfully.";
            $_SESSION['form_message_type'] = 'success_delete';
        } else {
            $_SESSION['form_message'] = "Error: Could not delete semester.";
            $_SESSION['form_message_type'] = 'error';
        }
        $stmt->close();
        header("Location: AdminDashboard.php?page=academics&action=manage_semesters");
        exit();
    }
    
    if ($_GET['action'] === 'delete_department' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $conn->prepare("DELETE FROM Department WHERE deptid = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $_SESSION['form_message'] = "Department deleted successfully.";
            $_SESSION['form_message_type'] = 'success_delete';
        } else {
            $_SESSION['form_message'] = "Error: Could not delete department.";
            $_SESSION['form_message_type'] = 'error';
        }
        $stmt->close();
        header("Location: AdminDashboard.php?page=academics&action=manage_department");
        exit();
    }

    if ($_GET['action'] === 'delete_subject' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        try {
            $stmt = $conn->prepare("DELETE FROM subject WHERE subject_id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $_SESSION['form_message'] = "Subject deleted successfully.";
                $_SESSION['form_message_type'] = 'success_delete';
            } else {
                $_SESSION['form_message'] = "An unknown error occurred during deletion.";
                $_SESSION['form_message_type'] = 'error';
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1451) { // Foreign key constraint violation
                $_SESSION['form_message'] = "Error: Cannot delete this subject because it is already in use.";
            } else {
                $_SESSION['form_message'] = "Database error during deletion: " . $e->getMessage();
            }
            $_SESSION['form_message_type'] = 'error';
        }
        header("Location: AdminDashboard.php?page=subjects&view=list");
        exit();
    }
}
    
// 4. Handle POST Actions (Creations & Updates).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle User Creation
    if (isset($_POST['create_user'])) {
        $username = $_POST['username'];
        $email = $_POST['email'];
        $roleid = $_POST['roleid'];
        
        $conn->begin_transaction();
        try {
            $placeholder_pass = 'PENDING_GENERATION';
            $stmt1 = $conn->prepare("INSERT INTO users (username, email, password, roleid, academic_year_id) VALUES (?, ?, ?, ?, ?)");
            $stmt1->bind_param("ssisi", $username, $email, $placeholder_pass, $roleid, $selected_year_id);
            $stmt1->execute();
            $new_userid = $conn->insert_id;
            $stmt1->close();

            $role_name = ($roleid == 2) ? 'teacher' : 'student';
            $generated_password = $role_name . $new_userid;
            $password_hash = password_hash($generated_password, PASSWORD_DEFAULT);

            $stmt2 = $conn->prepare("UPDATE users SET password = ? WHERE userid = ?");
            $stmt2->bind_param("si", $password_hash, $new_userid);
            $stmt2->execute();
            $stmt2->close();

            $conn->commit();
            $message = "User account created! Temporary password: <strong class='font-bold'>" . htmlspecialchars($generated_password) . "</strong>";
            $message_type = 'success';
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $message = "Account creation failed. The username or email may already be in use.";
            $message_type = 'error';
        }
    }

    // Handle User Update
    if (isset($_POST['update_user'])) {
        $userid = intval($_POST['userid']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $roleid = intval($_POST['roleid']);
        $status = trim($_POST['status']);

        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, roleid = ?, status = ? WHERE userid = ?");
        $stmt->bind_param("ssisi", $username, $email, $roleid, $status, $userid);
        if ($stmt->execute()) {
            $_SESSION['form_message'] = "User updated successfully.";
            $_SESSION['form_message_type'] = 'success';
        } else {
            $_SESSION['form_message'] = "Error updating user: " . $stmt->error;
            $_SESSION['form_message_type'] = 'error';
        }
        $stmt->close();
        header("Location: AdminDashboard.php?page=users&action=view_all");
        exit();
    }
    
    // Handle Add Faculty
    if (isset($_POST['add_faculty'])) {
        $faculty_name = trim($_POST['faculty_name']);
        if (!empty($faculty_name)) {
            try {
                $stmt = $conn->prepare("INSERT INTO faculty (faculty_name) VALUES (?)");
                $stmt->bind_param("s", $faculty_name);
                $stmt->execute();
                $_SESSION['form_message'] = "Faculty added successfully.";
                $_SESSION['form_message_type'] = 'success';
                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() == 1062) {
                    $_SESSION['form_message'] = "Error: A faculty with this name already exists.";
                } else {
                    $_SESSION['form_message'] = "Database error: " . $e->getMessage();
                }
                $_SESSION['form_message_type'] = 'error';
            }
        } else {
            $_SESSION['form_message'] = "Faculty name cannot be empty.";
            $_SESSION['form_message_type'] = 'error';
        }
        header("Location: AdminDashboard.php?page=academics&action=manage_faculty");
        exit();
    }

    // Handle Update Faculty
    if (isset($_POST['update_faculty'])) {
        $faculty_id = intval($_POST['faculty_id']);
        $faculty_name = trim($_POST['faculty_name']);
        if (!empty($faculty_name) && $faculty_id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE faculty SET faculty_name = ? WHERE faculty_id = ?");
                $stmt->bind_param("si", $faculty_name, $faculty_id);
                $stmt->execute();
                $_SESSION['form_message'] = "Faculty updated successfully.";
                $_SESSION['form_message_type'] = 'success';
                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() == 1062) {
                    $_SESSION['form_message'] = "Error: A faculty with this name already exists.";
                } else {
                    $_SESSION['form_message'] = "Database error: " . $e->getMessage();
                }
                $_SESSION['form_message_type'] = 'error';
            }
        } else {
            $_SESSION['form_message'] = "Invalid data provided for faculty update.";
            $_SESSION['form_message_type'] = 'error';
        }
        header("Location: AdminDashboard.php?page=academics&action=manage_faculty");
        exit();
    }

    // Handle Add Program
    if (isset($_POST['add_program'])) {
        $program_name = trim($_POST['program_name']);
        $faculty_id = intval($_POST['faculty_id']);
        if (!empty($program_name) && $faculty_id > 0) {
            try {
                $stmt = $conn->prepare("INSERT INTO program (program_name, faculty_id) VALUES (?, ?)");
                $stmt->bind_param("si", $program_name, $faculty_id);
                $stmt->execute();
                $_SESSION['form_message'] = "Program added successfully.";
                $_SESSION['form_message_type'] = 'success';
                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() == 1062) {
                    $_SESSION['form_message'] = "Error: A program with this name already exists.";
                } else {
                    $_SESSION['form_message'] = "Database error: " . $e->getMessage();
                }
                $_SESSION['form_message_type'] = 'error';
            }
        } else {
            $_SESSION['form_message'] = "Program name and faculty are required.";
            $_SESSION['form_message_type'] = 'error';
        }
        header("Location: AdminDashboard.php?page=academics&action=manage_programs");
        exit();
    }

    // Handle Update Program
    if (isset($_POST['update_program'])) {
        $program_id = intval($_POST['program_id']);
        $program_name = trim($_POST['program_name']);
        $faculty_id = intval($_POST['faculty_id']);
        if (!empty($program_name) && $faculty_id > 0 && $program_id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE program SET program_name = ?, faculty_id = ? WHERE program_id = ?");
                $stmt->bind_param("sii", $program_name, $faculty_id, $program_id);
                $stmt->execute();
                $_SESSION['form_message'] = "Program updated successfully.";
                $_SESSION['form_message_type'] = 'success';
                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() == 1062) {
                    $_SESSION['form_message'] = "Error: A program with this name already exists.";
                } else {
                    $_SESSION['form_message'] = "Database error: " . $e->getMessage();
                }
                $_SESSION['form_message_type'] = 'error';
            }
        } else {
            $_SESSION['form_message'] = "Invalid data for program update.";
            $_SESSION['form_message_type'] = 'error';
        }
        header("Location: AdminDashboard.php?page=academics&action=manage_programs");
        exit();
    }

    // Handle Add Semester
    if (isset($_POST['add_semester'])) {
        $program_id = intval($_POST['program_id']);
        $semester_level = intval($_POST['semester_level']);
        if ($program_id > 0 && $semester_level > 0) {
            try {
                $stmt = $conn->prepare("INSERT INTO semester (program_id, semester_level) VALUES (?, ?)");
                $stmt->bind_param("ii", $program_id, $semester_level);
                $stmt->execute();
                $_SESSION['form_message'] = "Semester added successfully.";
                $_SESSION['form_message_type'] = 'success';
                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() == 1062) {
                    $_SESSION['form_message'] = "Error: This semester level already exists for this program.";
                } else {
                    $_SESSION['form_message'] = "Database error: " . $e->getMessage();
                }
                $_SESSION['form_message_type'] = 'error';
            }
        } else {
            $_SESSION['form_message'] = "Program and semester level are required.";
            $_SESSION['form_message_type'] = 'error';
        }
        header("Location: AdminDashboard.php?page=academics&action=manage_semesters");
        exit();
    }

    // Handle Update Semester
    if (isset($_POST['update_semester'])) {
        $semester_id = intval($_POST['semester_id']);
        $program_id = intval($_POST['program_id']);
        $semester_level = intval($_POST['semester_level']);
        if ($program_id > 0 && $semester_level > 0 && $semester_id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE semester SET program_id = ?, semester_level = ? WHERE semester_id = ?");
                $stmt->bind_param("iii", $program_id, $semester_level, $semester_id);
                $stmt->execute();
                $_SESSION['form_message'] = "Semester updated successfully.";
                $_SESSION['form_message_type'] = 'success';
                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() == 1062) {
                    $_SESSION['form_message'] = "Error: This semester level already exists for this program.";
                } else {
                    $_SESSION['form_message'] = "Database error: " . $e->getMessage();
                }
                $_SESSION['form_message_type'] = 'error';
            }
        } else {
            $_SESSION['form_message'] = "Invalid data for semester update.";
            $_SESSION['form_message_type'] = 'error';
        }
        header("Location: AdminDashboard.php?page=academics&action=manage_semesters");
        exit();
    }

    // Handle Add Department
    if (isset($_POST['add_department'])) {
        $department_name = trim($_POST['department_name']);
        if (!empty($department_name)) {
            try {
                $stmt = $conn->prepare("INSERT INTO department (name) VALUES (?)");
                $stmt->bind_param("s", $department_name);
                $stmt->execute();
                $_SESSION['form_message'] = "Department added successfully.";
                $_SESSION['form_message_type'] = 'success';
                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() == 1062) {
                    $_SESSION['form_message'] = "Error: A department with this name already exists.";
                } else {
                    $_SESSION['form_message'] = "Database error: " . $e->getMessage();
                }
                $_SESSION['form_message_type'] = 'error';
            }
        } else {
            $_SESSION['form_message'] = "Department name cannot be empty.";
            $_SESSION['form_message_type'] = 'error';
        }
        header("Location: AdminDashboard.php?page=academics&action=manage_department");
        exit();
    }

    // Handle Update Department
    if (isset($_POST['update_department'])) {
        $deptid = intval($_POST['deptid']);
        $department_name = trim($_POST['department_name']);
        if (!empty($department_name) && $deptid > 0) {
            try {
                $stmt = $conn->prepare("UPDATE department SET name = ? WHERE deptid = ?");
                $stmt->bind_param("si", $department_name, $deptid);
                $stmt->execute();
                $_SESSION['form_message'] = "Department updated successfully.";
                $_SESSION['form_message_type'] = 'success';
                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() == 1062) {
                    $_SESSION['form_message'] = "Error: A department with this name already exists.";
                } else {
                    $_SESSION['form_message'] = "Database error: " . $e->getMessage();
                }
                $_SESSION['form_message_type'] = 'error';
            }
        } else {
            $_SESSION['form_message'] = "Invalid data for department update.";
            $_SESSION['form_message_type'] = 'error';
        }
        header("Location: AdminDashboard.php?page=academics&action=manage_department");
        exit();
    }

    // Handle Add Subject
    if (isset($_POST['add_subject'])) {
        $subject_name = trim($_POST['subject_name']);
        $subject_code = trim($_POST['subject_code']);
        $semester_id = intval($_POST['semester_id']);
        if (!empty($subject_name) && !empty($subject_code) && $semester_id > 0) {
            try {
                $stmt = $conn->prepare("INSERT INTO subject (subject_name, subject_code, semester_id) VALUES (?, ?, ?)");
                $stmt->bind_param("ssi", $subject_name, $subject_code, $semester_id);
                if ($stmt->execute()) {
                    $_SESSION['form_message'] = "Subject added successfully.";
                    $_SESSION['form_message_type'] = 'success';
                }
                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() == 1062) { // Duplicate entry
                    $_SESSION['form_message'] = "Error: A subject with this name or code already exists in the selected semester.";
                } else {
                    $_SESSION['form_message'] = "Database error: " . $e->getMessage();
                }
                $_SESSION['form_message_type'] = 'error';
            }
        } else {
            $_SESSION['form_message'] = "All fields are required to add a subject.";
            $_SESSION['form_message_type'] = 'error';
        }
        header("Location: AdminDashboard.php?page=subjects&view=list");
        exit();
    }
    
    // Handle Update Subject
    if (isset($_POST['update_subject'])) {
        $subject_id = intval($_POST['subject_id']);
        $subject_name = trim($_POST['subject_name']);
        $subject_code = trim($_POST['subject_code']);
        $semester_id = intval($_POST['semester_id']);

        if (!empty($subject_name) && !empty($subject_code) && $semester_id > 0 && $subject_id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE subject SET subject_name = ?, subject_code = ?, semester_id = ? WHERE subject_id = ?");
                $stmt->bind_param("ssii", $subject_name, $subject_code, $semester_id, $subject_id);
                if ($stmt->execute()) {
                    $_SESSION['form_message'] = "Subject updated successfully.";
                    $_SESSION['form_message_type'] = 'success';
                }
                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() == 1062) { 
                    $_SESSION['form_message'] = "Error: A subject with that name or code already exists for the selected semester.";
                } else {
                    $_SESSION['form_message'] = "Error updating subject: " . $e->getMessage();
                }
                $_SESSION['form_message_type'] = 'error';
            }
        } else {
            $_SESSION['form_message'] = "All fields are required to update a subject.";
            $_SESSION['form_message_type'] = 'error';
        }
        header("Location: AdminDashboard.php?page=subjects&view=list");
        exit();
    }
}

// 5. Include Header
require_once ROOT_PATH . '/partials/header.php';
?>

<!-- 6. Main Layout -->
<div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <?php require_once ROOT_PATH . '/partials/sidebar.php'; ?>

    <!-- Main Content Wrapper -->
    <div class="lg:ml-72">
        
        <!-- Top Navigation Bar -->
        <header class="sticky top-0 z-10 bg-white/75 dark:bg-gray-800/75 backdrop-blur-sm shadow-sm">
            <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between">
                    <div class="capitalize">
                        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                            <?php echo htmlspecialchars(str_replace('_', ' ', $page)); ?>
                        </h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </p>
                    </div>
                    <!-- Academic Year Selector -->
                    <form method="GET">
                        <input type="hidden" name="page" value="<?php echo $page; ?>">
                        <select name="academic_year_id" onchange="this.form.submit()" class="p-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm dark:bg-gray-700 dark:text-white">
                            <?php foreach($all_academic_years as $year): ?>
                                <option value="<?php echo $year['id']; ?>" <?php if($year['id'] == $selected_year_id) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($year['session'] . ' ' . $year['year']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main>
            <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
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
                    echo "<div class='border-l-4 {$color_class} p-4 mb-6' role='alert'><p class='font-bold'>" . ucfirst(str_replace('_', ' ', $message_type)) . "</p><p>{$_SESSION['form_message']}</p></div>";
                    unset($_SESSION['form_message']);
                    unset($_SESSION['form_message_type']);
                }

                // Display non-session messages (like from user creation)
                if (!empty($message)) {
                    $color_class = $message_type === 'success' ? 'bg-green-100 border-green-500 text-green-700' : 'bg-red-100 border-red-500 text-red-700';
                    echo "<div class='border-l-4 {$color_class} p-4 mb-6' role='alert'><p class='font-bold'>" . ($message_type === 'success' ? 'Success' : 'Error') . "</p><p>{$message}</p></div>";
                }
                
                // 7. Page Content Router
                if ($page === 'edit_faculty' && isset($_GET['id'])) {
                    include ROOT_PATH . '/pages/edit_faculty_content.php';
                } elseif ($page === 'edit_program' && isset($_GET['id'])) {
                    include ROOT_PATH . '/pages/edit_program_content.php';
                } elseif ($page === 'edit_semester' && isset($_GET['id'])) {
                    include ROOT_PATH . '/pages/edit_semester_content.php';
                } elseif ($page === 'edit_department' && isset($_GET['id'])) {
                    include ROOT_PATH . '/pages/edit_department_content.php';
                } elseif ($page === 'edit_user' && isset($_GET['id'])) {
                    include ROOT_PATH . '/pages/edit_user_content.php';
                } elseif ($page === 'edit_subject' && isset($_GET['id'])) {
                    include ROOT_PATH . '/pages/edit_subject_content.php';
                } elseif ($page === 'admin_users_report') {
                    include ROOT_PATH . '/pages/admin_users_report.php';
                } else {
                    $page_path = ROOT_PATH . '/pages/' . $page . '_content.php';
                    if (file_exists($page_path)) {
                        $user_page_action = isset($_GET['action']) ? $_GET['action'] : 'view_all';
                        $academics_page_action = isset($_GET['action']) ? $_GET['action'] : 'manage_faculty';
                        include $page_path;
                    } else {
                        include ROOT_PATH . '/pages/dashboard_content.php';
                    }
                }
                ?>
            </div>
        </main>
    </div>
</div>

<!-- Details Modal -->
<div id="details-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-3xl" id="details-modal-content">
        <div class="flex justify-between items-center border-b pb-3 dark:border-gray-700">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white" id="modal-title">Details</h3>
            <button id="close-modal-btn" class="text-gray-400 hover:text-gray-600 dark:hover:text-white">&times;</button>
        </div>
        <div class="mt-4 max-h-[60vh] overflow-y-auto" id="modal-body">
            <p class="text-center p-8">Loading...</p>
        </div>
    </div>
</div>

<!-- Deletion Confirmation Modal -->
<div id="delete-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-md" id="modal-content-delete">
        <div class="text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mt-4">Are you sure?</h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Do you really want to delete this record? This action cannot be undone.</p>
        </div>
        <div class="mt-6 flex justify-center space-x-4">
            <button id="cancel-delete-btn" class="px-6 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-300 dark:hover:bg-gray-500">No, Cancel</button>
            <a id="confirm-delete-btn" href="#" class="px-6 py-2 bg-red-600 text-white font-semibold rounded-md hover:bg-red-700">Yes, Delete</a>
        </div>
    </div>
</div>

<!-- Edit Confirmation Modal -->
<div id="edit-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-md">
        <div class="text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mt-4">Confirm Edit</h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Are you sure you want to edit this record?</p>
        </div>
        <div class="mt-6 flex justify-center space-x-4">
            <button id="cancel-edit-btn" class="px-6 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-300 dark:hover:bg-gray-500">No, Cancel</button>
            <a id="confirm-edit-btn" href="#" class="px-6 py-2 bg-indigo-600 text-white font-semibold rounded-md hover:bg-indigo-700">Yes, Edit</a>
        </div>
    </div>
</div>


<!-- Logout Confirmation Modal -->
<div id="logout-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-md" id="modal-content-logout">
        <div class="text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mt-4">Confirm Logout</h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Are you sure you want to end your current session?</p>
        </div>
        <div class="mt-6 flex justify-center space-x-4">
            <button id="cancel-logout-btn" class="px-6 py-2 bg-green-600 text-white font-semibold rounded-md hover:bg-green-700">No, Stay</button>
            <a href="logout.php" class="px-6 py-2 bg-red-600 text-white font-semibold rounded-md hover:bg-red-700">Yes, Logout</a>
        </div>
    </div>
</div>

<!-- Advance Session Confirmation Modal -->
<div id="advance-session-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-md">
        <div class="text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mt-4">Confirm Action</h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Are you sure you want to advance to the next academic session? This will promote all eligible students to their next semester and cannot be undone.</p>
        </div>
        <div class="mt-6 flex justify-center space-x-4">
            <button id="cancel-advance-btn" class="px-6 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">No, Cancel</button>
            <a id="confirm-advance-btn" href="AdminDashboard.php?action=advance_session" class="px-6 py-2 bg-amber-600 text-white font-semibold rounded-md hover:bg-amber-700">Yes, Advance</a>
        </div>
    </div>
</div>

<!-- Revert Session Confirmation Modal -->
<div id="revert-session-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-md">
        <div class="text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mt-4">DANGER: Confirm Revert</h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Are you sure you want to revert to the previous academic session? This will demote all eligible students and cannot be undone.</p>
        </div>
        <div class="mt-6 flex justify-center space-x-4">
            <button id="cancel-revert-btn" class="px-6 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">No, Cancel</button>
            <a id="confirm-revert-btn" href="AdminDashboard.php?action=revert_session" class="px-6 py-2 bg-red-600 text-white font-semibold rounded-md hover:bg-red-700">Yes, Revert</a>
        </div>
    </div>
</div>


<?php
// 8. Include Footer
require_once ROOT_PATH . '/partials/footer.php';
?>

<!-- JavaScript for Modals -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Details Modal Elements
    const detailsModal = document.getElementById('details-modal');
    const closeModalBtn = document.getElementById('close-modal-btn');

    // Deletion Modal Elements
    const deleteModal = document.getElementById('delete-modal');
    const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    const cancelDeleteBtn = document.getElementById('cancel-delete-btn');

    // Edit Modal Elements
    const editModal = document.getElementById('edit-modal');
    const confirmEditBtn = document.getElementById('confirm-edit-btn');
    const cancelEditBtn = document.getElementById('cancel-edit-btn');
    
    // Logout Modal Elements
    const logoutModal = document.getElementById('logout-modal');
    const cancelLogoutBtn = document.getElementById('cancel-logout-btn');

    // Advance/Revert Session Modal Elements
    const advanceSessionModal = document.getElementById('advance-session-modal');
    const cancelAdvanceBtn = document.getElementById('cancel-advance-btn');
    const revertSessionModal = document.getElementById('revert-session-modal');
    const cancelRevertBtn = document.getElementById('cancel-revert-btn');

    // Function to close any modal
    function closeModal(modal) {
        if(modal) modal.classList.add('hidden');
    }

    // Event listener for opening modals
    document.body.addEventListener('click', function(e) {
        const deleteLink = e.target.closest('.delete-link');
        const editLink = e.target.closest('.edit-link');
        const logoutButton = e.target.closest('#logout-btn');
        const advanceSessionButton = e.target.closest('#advance-session-btn');
        const revertSessionButton = e.target.closest('#revert-session-btn');

        if (deleteLink) {
            e.preventDefault();
            const deleteUrl = deleteLink.getAttribute('href');
            if(confirmDeleteBtn) confirmDeleteBtn.setAttribute('href', deleteUrl);
            if(deleteModal) deleteModal.classList.remove('hidden');
        }
        
        if (editLink) {
            e.preventDefault();
            const editUrl = editLink.getAttribute('href');
            if(confirmEditBtn) confirmEditBtn.setAttribute('href', editUrl);
            if(editModal) editModal.classList.remove('hidden');
        }

        if (logoutButton) {
            e.preventDefault();
            if(logoutModal) logoutModal.classList.remove('hidden');
        }

        if (advanceSessionButton) {
            e.preventDefault();
            if(advanceSessionModal) advanceSessionModal.classList.remove('hidden');
        }

        if (revertSessionButton) {
            e.preventDefault();
            if(revertSessionModal) revertSessionModal.classList.remove('hidden');
        }
    });

    // Event listeners for closing modals
    if(closeModalBtn) closeModalBtn.addEventListener('click', () => closeModal(detailsModal));
    if(cancelDeleteBtn) cancelDeleteBtn.addEventListener('click', () => closeModal(deleteModal));
    if(cancelEditBtn) cancelEditBtn.addEventListener('click', () => closeModal(editModal));
    if(cancelLogoutBtn) cancelLogoutBtn.addEventListener('click', () => closeModal(logoutModal));
    if(cancelAdvanceBtn) cancelAdvanceBtn.addEventListener('click', () => closeModal(advanceSessionModal));
    if(cancelRevertBtn) cancelRevertBtn.addEventListener('click', () => closeModal(revertSessionModal));
    
    if(detailsModal) detailsModal.addEventListener('click', (e) => { if (e.target === detailsModal) closeModal(detailsModal); });
    if(deleteModal) deleteModal.addEventListener('click', (e) => { if (e.target === deleteModal) closeModal(deleteModal); });
    if(editModal) editModal.addEventListener('click', (e) => { if (e.target === editModal) closeModal(editModal); });
    if(logoutModal) logoutModal.addEventListener('click', (e) => { if (e.target === logoutModal) closeModal(logoutModal); });
    if(advanceSessionModal) advanceSessionModal.addEventListener('click', (e) => { if (e.target === advanceSessionModal) closeModal(advanceSessionModal); });
    if(revertSessionModal) revertSessionModal.addEventListener('click', (e) => { if (e.target === revertSessionModal) closeModal(revertSessionModal); });
});
</script>
