<?php
// export_handler.php
session_start();
require_once 'config/db.php';
require_once 'functions/main_functions.php';
// NEW: Include the FPDF library. Make sure the path is correct.
require_once 'fpdf/fpdf.php'; 

// Security Check: Ensure a user is logged in
if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['userid'];
$user_role = $_SESSION['user_role'];

// Get the data for the export
$export_data = getDataForExport($conn, $user_id, $user_role);

if (empty($export_data['data'])) {
    // Handle case with no data to export
    $_SESSION['form_message'] = "There is no data to export.";
    $_SESSION['form_message_type'] = 'error';
    
    // Redirect back to the correct dashboard
    $dashboard_page = ucfirst($user_role) . "Dashboard.php?page=settings";
    header("Location: " . $dashboard_page);
    exit();
}

// --- NEW: PDF Generation Logic ---

// Create a new PDF instance
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);

// Add a title
$pdf->Cell(0, 10, ucfirst($user_role) . ' Data Export', 0, 1, 'C');
$pdf->Ln(10);

// Set font for the table header
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(230, 230, 230); // Light gray background for header

// Add table headers
$headers = $export_data['headers'];
$cell_width = 190 / count($headers); // Calculate cell width dynamically
foreach ($headers as $header) {
    $pdf->Cell($cell_width, 10, $header, 1, 0, 'C', true);
}
$pdf->Ln();

// Set font for the table body
$pdf->SetFont('Arial', '', 9);

// Add the data rows
foreach ($export_data['data'] as $row) {
    foreach ($row as $cell) {
        $pdf->Cell($cell_width, 10, $cell, 1, 0, 'L');
    }
    $pdf->Ln();
}

$filename = $user_role . "_data_export_" . date('Y-m-d') . ".pdf";

// Output the PDF to the browser for download
$pdf->Output('D', $filename);
exit();
