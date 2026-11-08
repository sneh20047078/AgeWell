<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';

// Check if user is logged in
if (!isLoggedIn()) {
    setAlert('danger', 'Please login to access this page.');
    redirect('login.php');
}

// Check if senior ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setAlert('danger', 'Senior ID is missing.');
    redirect('seniors.php');
}

// Get senior ID and fetch details
$seniorId = (int)$_GET['id'];
$senior = getSeniorById($seniorId);

if (!$senior) {
    setAlert('danger', 'Senior citizen not found.');
    redirect('seniors.php');
}

// Ensure all required fields exist
$senior = array_merge([
    'name' => '',
    'age' => '',
    'gender' => '',
    'phone' => '',
    'address' => '',
    'emergency_contact' => '',
    'emergency_phone' => '',
    'medical_history' => '',
    'medications' => '',
    'allergies' => ''
], $senior);

// Create new PDF document
$pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('AgeWell Senior Care');
$pdf->SetAuthor('AgeWell Senior Care');
$pdf->SetTitle('Senior Citizen Report - ' . $senior['name']);

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 12);

// Add content
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Senior Citizen Report', 0, 1, 'C');
$pdf->Ln(10);

// Basic Information
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Basic Information', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 12);

$pdf->Cell(50, 7, 'Name:', 0, 0);
$pdf->Cell(0, 7, $senior['name'], 0, 1);

$pdf->Cell(50, 7, 'Age:', 0, 0);
$pdf->Cell(0, 7, $senior['age'], 0, 1);

$pdf->Cell(50, 7, 'Gender:', 0, 0);
$pdf->Cell(0, 7, ucfirst($senior['gender']), 0, 1);

$pdf->Cell(50, 7, 'Phone:', 0, 0);
$pdf->Cell(0, 7, $senior['phone'], 0, 1);

$pdf->Cell(50, 7, 'Address:', 0, 0);
$pdf->Cell(0, 7, $senior['address'], 0, 1);

// Emergency Contact
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Emergency Contact', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 12);

$pdf->Cell(50, 7, 'Name:', 0, 0);
$pdf->Cell(0, 7, $senior['emergency_contact'], 0, 1);

$pdf->Cell(50, 7, 'Phone:', 0, 0);
$pdf->Cell(0, 7, $senior['emergency_phone'], 0, 1);

// Medical Information
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Medical Information', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 12);

$pdf->Cell(0, 7, 'Medical History:', 0, 1);
$pdf->MultiCell(0, 7, $senior['medical_history'], 0, 'L');

$pdf->Ln(5);
$pdf->Cell(0, 7, 'Current Medications:', 0, 1);
$pdf->MultiCell(0, 7, $senior['medications'], 0, 'L');

$pdf->Ln(5);
$pdf->Cell(0, 7, 'Allergies:', 0, 1);
$pdf->MultiCell(0, 7, $senior['allergies'], 0, 'L');

// Output PDF
$pdf->Output('Senior_Report_' . $senior['name'] . '.pdf', 'D');