<?php
// File: admin_report_generator.php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/functions/main_functions.php';
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// --- Security Check: Ensure only administrators can access this report file ---
if (!isset($_SESSION['userid']) || $_SESSION['role_name'] !== 'admin') {
    die("Access denied. This report is for administrators only.");
}

// Check for required parameters in the URL
if (!isset($_GET['id']) || !isset($_GET['role'])) {
    die("Error: User ID and role are required to generate a report.");
}

$user_id = intval($_GET['id']);
$user_role = $_GET['role'];

// Set up Dompdf
$options = new Options();
$options->set('defaultFont', 'Helvetica');
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

$html_content = '';
$report_title = '';

if ($user_role === 'teacher') {
    $teacher_details = getTeacherDetails($conn, $user_id);
    if (!$teacher_details) {
        die("Error: Teacher not found.");
    }
    
    $lesson_plans = getPublishedLessonPlansForTeacher($conn, $user_id);
    
    $report_title = "Lesson Plan Report for " . htmlspecialchars($teacher_details['firstname'] . ' ' . $teacher_details['lastname']);

    $html_content = "
        <h1 style='text-align: center; color: #4f46e5;'>Lesson Plan Report</h1>
        <h3 style='text-align: center;'>Teacher: " . htmlspecialchars($teacher_details['firstname'] . ' ' . $teacher_details['lastname']) . "</h3>
        <hr style='border: 1px solid #e5e7eb; margin: 20px 0;'>
    ";

    if (!empty($lesson_plans)) {
        $html_content .= "<table width='100%' border='1' cellspacing='0' cellpadding='10' style='border-collapse: collapse;'>
            <thead style='background-color: #f3f4f6;'>
                <tr>
                    <th>S.N.</th>
                    <th>Day No.</th>
                    <th>Title</th>
                    <th>Subject</th>
                    <th>Objective</th>
                    <th>Notes</th>
                    <th>Teaching Methods</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                </tr>
            </thead>
            <tbody>";

        $sn = 1;
        foreach ($lesson_plans as $plan) {
            $methods_list = '<ul>';
            if (!empty($plan['teaching_methods'])) {
                foreach ($plan['teaching_methods'] as $method) {
                    $methods_list .= '<li>' . htmlspecialchars($method) . '</li>';
                }
            } else {
                $methods_list .= '<li>N/A</li>';
            }
            $methods_list .= '</ul>';

            $html_content .= "
                <tr>
                    <td>" . $sn++ . "</td>
                    <td>" . htmlspecialchars($plan['day_no']) . "</td>
                    <td>" . htmlspecialchars($plan['title']) . "</td>
                    <td>" . htmlspecialchars($plan['subject_name']) . "</td>
                    <td>" . nl2br(htmlspecialchars($plan['objective'])) . "</td>
                    <td>" . nl2br(htmlspecialchars($plan['notes'])) . "</td>
                    <td>" . $methods_list . "</td>
                    <td>" . htmlspecialchars($plan['start_date']) . "</td>
                    <td>" . htmlspecialchars($plan['deadline_date']) . "</td>
                </tr>";
        }
        $html_content .= "</tbody></table>";
    } else {
        $html_content .= "<p style='text-align: center;'>No lesson plans found for this teacher.</p>";
    }

} elseif ($user_role === 'student') {
    $student_details = getStudentDetails($conn, $user_id);
    if (!$student_details) {
        die("Error: Student not found.");
    }

    $feedback_data = getStudentFeedbackReportData($conn, $user_id);

    $report_title = "Feedback Report for " . htmlspecialchars($student_details['firstname'] . ' ' . $student_details['lastname']);
    $html_content = "
        <h1 style='text-align: center; color: #4f46e5;'>Student Feedback Report</h1>
        <h3 style='text-align: center;'>Student: " . htmlspecialchars($student_details['firstname'] . ' ' . $student_details['lastname']) . "</h3>
        <hr style='border: 1px solid #e5e7eb; margin: 20px 0;'>
    ";

    if (!empty($feedback_data)) {
        $html_content .= "<table width='100%' border='1' cellspacing='0' cellpadding='10' style='border-collapse: collapse;'>
            <thead style='background-color: #f3f4f6;'>
                <tr>
                    <th>S.N.</th>
                    <th>Lesson Plan Title</th>
                    <th>Teacher</th>
                    <th>Subject</th>
                    <th>Avg. Rating</th>
                    <th>Comments</th>
                </tr>
            </thead>
            <tbody>";
        $sn = 1;
        foreach ($feedback_data as $feedback) {
            $ratings_html = '';
            if (!empty($feedback['ratings'])) {
                foreach ($feedback['ratings'] as $rating) {
                    $ratings_html .= "<strong>" . htmlspecialchars($rating['parameter_name']) . ":</strong> " . htmlspecialchars($rating['rating']) . " <br>";
                }
            } else {
                $ratings_html = "N/A";
            }

            $html_content .= "
                <tr>
                    <td>" . $sn++ . "</td>
                    <td>" . htmlspecialchars($feedback['lessonplan_title']) . "</td>
                    <td>" . htmlspecialchars($feedback['teacher_firstname'] . ' ' . $feedback['teacher_lastname']) . "</td>
                    <td>" . htmlspecialchars($feedback['subject_name']) . "</td>
                    <td>" . htmlspecialchars(number_format($feedback['average_rating'], 2)) . " </td>
                    <td>" . nl2br(htmlspecialchars($feedback['comment'])) . "</td>
                </tr>";
        }
        $html_content .= "</tbody></table>";
    } else {
        $html_content .= "<p style='text-align: center;'>No feedback found for this student.</p>";
    }

} else {
    die("Error: Invalid user role specified.");
}

$dompdf->loadHtml($html_content);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream($report_title . ".pdf", ["Attachment" => true]);
?>
