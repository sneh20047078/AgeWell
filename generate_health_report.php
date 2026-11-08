<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'vendor/autoload.php'; // Make sure you have TCPDF installed via composer

// Check if user is logged in
if (!isLoggedIn()) {
    setAlert('danger', 'Please login to access this page.');
    redirect('login.php');
}

// Get parameters
$senior_id = isset($_GET['senior_id']) ? (int)$_GET['senior_id'] : null;
$metric_type = isset($_GET['type']) ? cleanInput($_GET['type']) : null;
$from_date = isset($_GET['from_date']) ? cleanInput($_GET['from_date']) : null;
$to_date = isset($_GET['to_date']) ? cleanInput($_GET['to_date']) : null;

// Get senior details
$senior = getSeniorById($senior_id);
if (!$senior) {
    setAlert('danger', 'Senior citizen not found.');
    redirect('health_metrics.php');
}

// Build query for health metrics
$whereConditions = ['h.senior_id = ?'];
$params = [$senior_id];
$types = 'i';

if ($metric_type) {
    $whereConditions[] = 'h.record_type = ?';
    $params[] = $metric_type;
    $types .= 's';
}

if ($from_date) {
    $whereConditions[] = 'DATE(h.recorded_at) >= ?';
    $params[] = $from_date;
    $types .= 's';
}

if ($to_date) {
    $whereConditions[] = 'DATE(h.recorded_at) <= ?';
    $params[] = $to_date;
    $types .= 's';
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get health metrics data
$sql = "SELECT h.*, s.name as senior_name 
        FROM healthrecords h 
        JOIN seniorcitizens s ON h.senior_id = s.id 
        $whereClause 
        ORDER BY h.recorded_at DESC";

$metrics = fetchAll($sql, $params, $types);

// Create PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('AgeWell Senior Care');
$pdf->SetAuthor('AgeWell Senior Care');
$pdf->SetTitle('Health Metrics Report - ' . $senior['name']);

// Set margins
$pdf->SetMargins(15, 15, 15);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 12);

// Add title
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Health Metrics Report', 0, 1, 'C');
$pdf->Ln(5);

// Add senior information
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Senior Information:', 0, 1);
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 10, 'Name: ' . $senior['name'], 0, 1);
$pdf->Cell(0, 10, 'Date Range: ' . date('M d, Y', strtotime($from_date)) . ' to ' . date('M d, Y', strtotime($to_date)), 0, 1);
$pdf->Ln(5);

// Add metrics table
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Health Metrics:', 0, 1);

// Table header
$pdf->SetFillColor(240, 240, 240);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(50, 7, 'Date', 1, 0, 'C', true);
$pdf->Cell(40, 7, 'Type', 1, 0, 'C', true);
$pdf->Cell(50, 7, 'Value', 1, 0, 'C', true);
$pdf->Cell(50, 7, 'Notes', 1, 1, 'C', true);

// Table content
$pdf->SetFont('helvetica', '', 10);
foreach ($metrics as $metric) {
    $pdf->Cell(50, 7, date('M d, Y g:i A', strtotime($metric['recorded_at'])), 1, 0, 'L');
    $pdf->Cell(40, 7, ucwords(str_replace('_', ' ', $metric['record_type'])), 1, 0, 'L');
    
    // Format value based on type
    $value = $metric['value'];
    switch ($metric['record_type']) {
        case 'blood_pressure':
            $value .= ' mmHg';
            break;
        case 'heart_rate':
            $value .= ' bpm';
            break;
        case 'blood_sugar':
            $value .= ' mg/dL';
            break;
        case 'temperature':
            $value .= ' °F';
            break;
        case 'weight':
            $value .= ' kg';
            break;
    }
    
    $pdf->Cell(50, 7, $value, 1, 0, 'L');
    $pdf->Cell(50, 7, $metric['notes'], 1, 1, 'L');
}

// Output PDF
$filename = 'health_metrics_' . $senior['name'] . '_' . date('Y-m-d') . '.pdf';
$pdf->Output($filename, 'D');