<?php
// Remove any whitespace or output before this point
ob_start();

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'lib/fpdf/fpdf.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Get export type
$type = isset($_GET['type']) ? $_GET['type'] : '';

// Create new PDF document
$pdf = new FPDF('P', 'mm', 'A4');

// Set document properties
$pdf->SetTitle('Senior Citizens Report');
$pdf->SetAuthor('AgeWell Senior Care');
$pdf->SetCreator('AgeWell Senior Care System');

// Add a page
$pdf->AddPage();

switch ($type) {
    case 'seniors':
        exportSeniors($pdf);
        break;
    case 'appointments':
        exportAppointments($pdf);
        break;
    case 'health_metrics':
        exportHealthMetrics($pdf);
        break;
    case 'all':
        exportAll($pdf);
        break;
    default:
        redirect('dashboard.php');
}

// Clear any output buffers before sending PDF
ob_end_clean();

// Send PDF headers
header('Content-Type: application/pdf');
header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0, max-age=1');
header('Pragma: public');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
header('Content-Disposition: attachment; filename="'.SITE_NAME.'_'.$type.'_report.pdf"');

// Output PDF
$pdf->Output('D', SITE_NAME.'_'.$type.'_report.pdf');
exit();

function exportSeniors($pdf) {
    // Get data
    $seniors = fetchAll("SELECT name, age, phone as contact, address FROM seniorcitizens ORDER BY name");
    
    if (!$seniors) {
        $seniors = array();
    }
    
    // Reset position
    $pdf->SetXY(10, 10);
    
    // Add title
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 15, 'Senior Citizens List', 0, 1, 'C');
    
    // Add header row
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetFillColor(200, 200, 200);
    $pdf->Cell(60, 10, 'Name', 1, 0, 'C', true);
    $pdf->Cell(20, 10, 'Age', 1, 0, 'C', true);
    $pdf->Cell(40, 10, 'Contact', 1, 0, 'C', true);
    $pdf->Cell(70, 10, 'Address', 1, 1, 'C', true);
    
    // Add data rows
    $pdf->SetFont('Arial', '', 11);
    if (!empty($seniors)) {
        foreach ($seniors as $senior) {
            // Check if we need a new page
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
                // Repeat headers on new page
                $pdf->SetFont('Arial', 'B', 12);
                $pdf->SetFillColor(200, 200, 200);
                $pdf->Cell(60, 10, 'Name', 1, 0, 'C', true);
                $pdf->Cell(20, 10, 'Age', 1, 0, 'C', true);
                $pdf->Cell(40, 10, 'Contact', 1, 0, 'C', true);
                $pdf->Cell(70, 10, 'Address', 1, 1, 'C', true);
                $pdf->SetFont('Arial', '', 11);
            }
            
            $pdf->Cell(60, 8, $senior['name'] ?? '', 1, 0, 'L');
            $pdf->Cell(20, 8, $senior['age'] ?? '', 1, 0, 'C');
            $pdf->Cell(40, 8, $senior['contact'] ?? '', 1, 0, 'L');
            $pdf->Cell(70, 8, $senior['address'] ?? '', 1, 1, 'L');
        }
    } else {
        $pdf->Cell(0, 10, 'No senior citizens found', 1, 1, 'C');
    }
}

function exportAppointments($pdf) {
    // Get appointments with senior and caregiver names
    $appointments = fetchAll("SELECT a.*, s.name as senior_name, u.name as caregiver_name 
                            FROM appointments a 
                            LEFT JOIN seniorcitizens s ON a.senior_id = s.id 
                            LEFT JOIN userdetails u ON a.caregiver_id = u.id 
                            ORDER BY a.appointment_datetime");
    
    if (!$appointments) {
        $appointments = array();
    }
    
    // Reset position
    $pdf->SetXY(10, 10);
    
    // Add title
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 15, 'Appointments List', 0, 1, 'C');
    
    // Add header row
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetFillColor(200, 200, 200);
    $pdf->Cell(40, 10, 'Date & Time', 1, 0, 'C', true);
    $pdf->Cell(50, 10, 'Senior Name', 1, 0, 'C', true);
    $pdf->Cell(40, 10, 'Doctor', 1, 0, 'C', true);
    $pdf->Cell(60, 10, 'Purpose', 1, 1, 'C', true);
    
    // Add data rows
    $pdf->SetFont('Arial', '', 11);
    if (!empty($appointments)) {
        foreach ($appointments as $appointment) {
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
                // Repeat headers
                $pdf->SetFont('Arial', 'B', 12);
                $pdf->SetFillColor(200, 200, 200);
                $pdf->Cell(40, 10, 'Date & Time', 1, 0, 'C', true);
                $pdf->Cell(50, 10, 'Senior Name', 1, 0, 'C', true);
                $pdf->Cell(40, 10, 'Doctor', 1, 0, 'C', true);
                $pdf->Cell(60, 10, 'Purpose', 1, 1, 'C', true);
                $pdf->SetFont('Arial', '', 11);
            }
            
            $pdf->Cell(40, 8, date('Y-m-d H:i', strtotime($appointment['appointment_datetime'])), 1, 0, 'L');
            $pdf->Cell(50, 8, $appointment['senior_name'] ?? '', 1, 0, 'L');
            $pdf->Cell(40, 8, $appointment['doctor_name'] ?? '', 1, 0, 'L');
            $pdf->Cell(60, 8, $appointment['purpose'] ?? '', 1, 1, 'L');
        }
    } else {
        $pdf->Cell(0, 10, 'No appointments found', 1, 1, 'C');
    }
}

function exportHealthMetrics($pdf) {
    // Get health records with senior names
    $records = fetchAll("SELECT h.*, s.name as senior_name 
                        FROM healthrecords h 
                        LEFT JOIN seniorcitizens s ON h.senior_id = s.id 
                        ORDER BY h.recorded_at DESC");
    
    if (!$records) {
        $records = array();
    }
    
    // Reset position
    $pdf->SetXY(10, 10);
    
    // Add title
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 15, 'Health Metrics Report', 0, 1, 'C');
    
    // Add header row
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetFillColor(200, 200, 200);
    $pdf->Cell(40, 10, 'Date & Time', 1, 0, 'C', true);
    $pdf->Cell(50, 10, 'Senior Name', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'Type', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'Value', 1, 0, 'C', true);
    $pdf->Cell(40, 10, 'Notes', 1, 1, 'C', true);
    
    // Add data rows
    $pdf->SetFont('Arial', '', 11);
    if (!empty($records)) {
        foreach ($records as $record) {
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
                // Repeat headers
                $pdf->SetFont('Arial', 'B', 12);
                $pdf->SetFillColor(200, 200, 200);
                $pdf->Cell(40, 10, 'Date & Time', 1, 0, 'C', true);
                $pdf->Cell(50, 10, 'Senior Name', 1, 0, 'C', true);
                $pdf->Cell(30, 10, 'Type', 1, 0, 'C', true);
                $pdf->Cell(30, 10, 'Value', 1, 0, 'C', true);
                $pdf->Cell(40, 10, 'Notes', 1, 1, 'C', true);
                $pdf->SetFont('Arial', '', 11);
            }
            
            $pdf->Cell(40, 8, date('Y-m-d H:i', strtotime($record['recorded_at'])), 1, 0, 'L');
            $pdf->Cell(50, 8, $record['senior_name'] ?? '', 1, 0, 'L');
            $pdf->Cell(30, 8, str_replace('_', ' ', ucfirst($record['record_type'])), 1, 0, 'L');
            $pdf->Cell(30, 8, $record['value'], 1, 0, 'C');
            $pdf->Cell(40, 8, substr($record['notes'] ?? '', 0, 20), 1, 1, 'L');
        }
    } else {
        $pdf->Cell(0, 10, 'No health records found', 1, 1, 'C');
    }
}

function exportAll($pdf) {
    $pdf->AddPage();
    exportSeniors($pdf);
    
    $pdf->AddPage();
    exportAppointments($pdf);
    
    $pdf->AddPage();
    exportHealthMetrics($pdf);
}