<?php
// ajax_handler.php

/**
 * IMPORTANT: This file assumes it is located in your project's root folder
 * (e.g., C:\xampp\htdocs\Lesson\ajax_handler.php)
 */

// --- FIX: Using the correct filename 'main_functions.php' (with an 's') ---
$config_path = __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';
$functions_path = __DIR__ . DIRECTORY_SEPARATOR . 'functions' . DIRECTORY_SEPARATOR . 'main_functions.php';

if (!file_exists($config_path)) {
    die("CRITICAL ERROR: The database configuration file was not found. Please ensure the file exists at: " . $config_path);
}
if (!file_exists($functions_path)) {
    // This check will now correctly look for 'main_functions.php'
    die("CRITICAL ERROR: The main functions file was not found. Please ensure the file exists at: " . $functions_path);
}

// If the files exist, require them.
require_once $config_path;
require_once $functions_path;


$action = isset($_GET['action']) ? $_GET['action'] : '';

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_log("Starting AJAX request for action: " . $action);

// --- Handle marking feedback as read ---
if ($action === 'mark_feedback_read' && isset($_POST['feedback_id'])) {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['teacher_id'])) {
        echo json_encode(['success' => false, 'message' => 'Authentication error.']);
        exit();
    }

    $feedback_id = intval($_POST['feedback_id']);
    $teacher_id = $_SESSION['teacher_id'];

    $result = markFeedbackAsRead($conn, $feedback_id, $teacher_id);
    echo json_encode($result);
    $conn->close();
    exit();
}


// // --- Live Search Handler ---
// if ($action === 'live_search' && isset($_GET['term'])) {
//     header('Content-Type: application/json');
//     $results = [];
//     $search_term = trim($_GET['term']);
//     if (!empty($search_term)) {
//         $results = getGlobalSearchResults($conn, $search_term);
//     }
//     echo json_encode($results);
//     $conn->close();
//     exit();
// }

// --- Details Modal Handler ---
$html = '<p class="text-center text-gray-500">No data found.</p>';

try {
    switch ($action) {
        case 'view_teachers':
            $data = getUsers($conn, 'teacher');
            if ($data && $data->num_rows > 0) {
                $html = '<table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th></tr></thead><tbody class="bg-white divide-y divide-gray-200">';
                while ($row = $data->fetch_assoc()) {
                    $html .= '<tr><td class="px-6 py-4">' . htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) . '</td><td class="px-6 py-4">' . htmlspecialchars($row['username']) . '</td><td class="px-6 py-4">' . htmlspecialchars($row['email']) . '</td></tr>';
                }
                $html .= '</tbody></table>';
            }
            break;
        
        case 'view_students':
            $data = getUsers($conn, 'student');
            if ($data && $data->num_rows > 0) {
                $html = '<table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th></tr></thead><tbody class="bg-white divide-y divide-gray-200">';
                while ($row = $data->fetch_assoc()) {
                    $html .= '<tr><td class="px-6 py-4">' . htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) . '</td><td class="px-6 py-4">' . htmlspecialchars($row['username']) . '</td><td class="px-6 py-4">' . htmlspecialchars($row['email']) . '</td></tr>';
                }
                $html .= '</tbody></table>';
            }
            break;

        case 'view_faculty':
            $data = getFaculties($conn);
            if ($data && $data->num_rows > 0) {
                $html = '<table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Faculty Name</th></tr></thead><tbody class="bg-white divide-y divide-gray-200">';
                while ($row = $data->fetch_assoc()) {
                    $html .= '<tr><td class="px-6 py-4">' . htmlspecialchars($row['faculty_name']) . '</td></tr>';
                }
                $html .= '</tbody></table>';
            }
            break;

        case 'view_subjects':
            $data = getSubjects($conn);
            if ($data && $data->num_rows > 0) {
                $html = '<table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Program</th></tr></thead><tbody class="bg-white divide-y divide-gray-200">';
                while ($row = $data->fetch_assoc()) {
                    $html .= '<tr><td class="px-6 py-4">' . htmlspecialchars($row['subject_code']) . '</td><td class="px-6 py-4">' . htmlspecialchars($row['subject_name']) . '</td><td class="px-6 py-4">' . htmlspecialchars($row['program_name']) . '</td></tr>';
                }
                $html .= '</tbody></table>';
            }
            break;
    }
} catch (Throwable $e) {
    error_log("AJAX Handler Error: " . $e->getMessage());
    $html = '<div class="p-4 text-center text-red-700 bg-red-100 rounded-lg"><p class="font-bold">A critical error occurred.</p><p class="text-sm mt-1">Could not retrieve data. Please check server error logs.</p></div>';
}

echo $html;
$conn->close();
?>
