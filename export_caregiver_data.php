<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'vendor/autoload.php';

// Check if user is logged in and is caregiver
if (!isLoggedIn() || $_SESSION['user_role'] !== 'caregiver') {
    setAlert('danger', 'Unauthorized access.');
    redirect('login.php');
}

$userId = $_SESSION['user_id'];

// Get all necessary data
$assignedSeniors = fetchAll("SELECT s.*, 
    (SELECT COUNT(*) FROM appointments a WHERE a.senior_id = s.id AND a.appointment_datetime >= NOW()) as upcoming_appointments,
    (SELECT COUNT(*) FROM healthrecords h WHERE h.senior_id = s.id) as health_records_count
    FROM senior_caregiver_assignments sca 
    JOIN seniorcitizens s ON sca.senior_id = s.id 
    WHERE sca.caregiver_id = ?
    ORDER BY s.name ASC", [$userId]);

// Create PDF with modified settings
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);

// Add first page
$pdf->AddPage();

// Set font and title
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Caregiver Report', 0, 1, 'C');

// Add date (removed extra Ln)
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 10, 'Generated on: ' . date('F d, Y h:i A'), 0, 1, 'R');

// Assigned Seniors Table
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Assigned Seniors', 0, 1, 'L');

// Table headers
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(60, 7, 'Name', 1, 0, 'L', true);
$pdf->Cell(15, 7, 'Age', 1, 0, 'C', true);
$pdf->Cell(35, 7, 'Phone', 1, 0, 'L', true);
$pdf->Cell(40, 7, 'Appointments', 1, 0, 'C', true);
$pdf->Cell(35, 7, 'Health Records', 1, 1, 'C', true);

// Table content
$pdf->SetFont('helvetica', '', 10);
foreach ($assignedSeniors as $senior) {
    $pdf->Cell(60, 7, $senior['name'], 1, 0, 'L');
    $pdf->Cell(15, 7, $senior['age'], 1, 0, 'C');
    $pdf->Cell(35, 7, $senior['phone'], 1, 0, 'L');
    $pdf->Cell(40, 7, $senior['upcoming_appointments'], 1, 0, 'C');
    $pdf->Cell(35, 7, $senior['health_records_count'], 1, 1, 'C');
}

// Get recent appointments
// Check if we need a new page for appointments
if ($pdf->GetY() > 200) {
    $pdf->AddPage();
} else {
    $pdf->Ln(10);
}

// Upcoming Appointments section
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Upcoming Appointments', 0, 1, 'L');

$upcomingAppointments = fetchAll("SELECT a.*, s.name as senior_name 
    FROM appointments a 
    JOIN seniorcitizens s ON a.senior_id = s.id
    JOIN senior_caregiver_assignments sca ON s.id = sca.senior_id
    WHERE sca.caregiver_id = ? 
    AND a.appointment_datetime >= NOW()
    ORDER BY a.appointment_datetime ASC LIMIT 10", [$userId]);

// Appointments table
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(50, 7, 'Senior Name', 1, 0, 'L', true);
$pdf->Cell(40, 7, 'Date & Time', 1, 0, 'L', true);
$pdf->Cell(50, 7, 'Purpose', 1, 0, 'L', true);
$pdf->Cell(45, 7, 'Location', 1, 1, 'L', true);

$pdf->SetFont('helvetica', '', 10);
foreach ($upcomingAppointments as $appointment) {
    $pdf->Cell(50, 7, $appointment['senior_name'], 1, 0, 'L');
    $pdf->Cell(40, 7, date('M d, Y g:i A', strtotime($appointment['appointment_datetime'])), 1, 0, 'L');
    $pdf->Cell(50, 7, $appointment['purpose'], 1, 0, 'L');
    $pdf->Cell(45, 7, $appointment['location'], 1, 1, 'L');
}

// Close and output
$pdf->lastPage();

// Before output, add these lines
$pdf->SetAutoPageBreak(false);
$pdf->deletePage($pdf->getPage() + 1); // Delete any potential blank page
$pdf->Output('caregiver_report_' . date('Y-m-d') . '.pdf', 'D');