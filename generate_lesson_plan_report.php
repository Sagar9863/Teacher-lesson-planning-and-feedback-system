<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/functions/main_functions.php';

// Include the Dompdf autoloader
require_once __DIR__ . '/vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

session_start();

// --- Security Check ---
// Ensure a teacher is logged in and we have their ID
if (!isset($_SESSION['teacher_id'])) {
    die("Error: Teacher not logged in.");
}
$teacher_id = $_SESSION['teacher_id'];
$teacher_details = getTeacherDetails($conn, $teacher_id);
$teacher_name = htmlspecialchars($teacher_details['firstname'] . ' ' . $teacher_details['lastname']);

// Get the subject filter from the URL
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : null;

// --- Fetch Data ---
$lesson_plans = getPublishedLessonPlansForTeacher($conn, $teacher_id, $subject_id);

// --- Generate HTML Content ---
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Lesson Plan Report for <?= $teacher_name ?></title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; margin: 0; padding: 0; }
        .container { padding: 20px; }
        h1, h2 { text-align: center; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { font-size: 20px; margin: 0; }
        .header p { font-size: 14px; margin: 0; color: #555; }
        .teacher-info { margin-bottom: 30px; text-align: center; }
        .teacher-info p { margin: 5px 0; }
        .lesson-plan-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .lesson-plan-table th, .lesson-plan-table td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
        .lesson-plan-table th { background-color: #f2f2f2; color: #333; font-weight: bold; }
        .lesson-plan-table tr:nth-child(even) { background-color: #f9f9f9; }
        .lesson-plan-table td { font-size: 11px; }
        .teaching-methods-list { padding-left: 15px; margin: 0; }
        .teaching-methods-list li { margin-bottom: 2px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Lesson Plan Report</h1>
            <p>Generated on <?= date('Y-m-d H:i:s') ?></p>
        </div>
        <div class="teacher-info">
            <h2>Teacher: <?= $teacher_name ?></h2>
            <p>Total Published Lesson Plans: <?= count($lesson_plans) ?></p>
        </div>

        <?php if (!empty($lesson_plans)): ?>
            <table class="lesson-plan-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Subject</th>
                        <th>Day No.</th>
                        <th>Start Date</th>
                        <th>Objective</th>
                        <th>Teaching Methods</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lesson_plans as $plan): ?>
                        <tr>
                            <td><?= htmlspecialchars($plan['title']) ?></td>
                            <td><?= htmlspecialchars($plan['subject_name']) ?></td>
                            <td><?= htmlspecialchars($plan['day_no']) ?></td>
                            <td><?= htmlspecialchars($plan['start_date']) ?></td>
                            <td><?= htmlspecialchars($plan['objective']) ?></td>
                            <td>
                                <?php if (!empty($plan['teaching_methods'])): ?>
                                    <ul class="teaching-methods-list">
                                        <?php foreach ($plan['teaching_methods'] as $method): ?>
                                            <li><?= htmlspecialchars($method) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align: center;">No published lesson plans found for this teacher.</p>
        <?php endif; ?>

    </div>
</body>
</html>
<?php
$html = ob_get_clean();

// --- Dompdf Configuration ---
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

// Load HTML to Dompdf
$dompdf->loadHtml($html);

// Set paper size and orientation
$dompdf->setPaper('A4', 'portrait');

// Render the HTML as PDF
$dompdf->render();

// Output the generated PDF to Browser
$filename = "lesson_plan_report_" . str_replace(' ', '_', $teacher_name) . "_" . date('Ymd_His') . ".pdf";
$dompdf->stream($filename, ["Attachment" => true]);
?>
