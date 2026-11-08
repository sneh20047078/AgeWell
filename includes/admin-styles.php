<?php
// File: includes/admin-styles.php
// This file contains shared styles for all admin pages
?>

<style>
    /* Reset styles */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        background: #23242a;
        color: #fff;
        font-family: 'Poppins', sans-serif;
        overflow-x: hidden;
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }
    
    /* Main wrapper structure */
    .main-wrapper {
        display: flex;
        min-height: calc(100vh - 60px);
        margin-top: 60px;
    }
    
    .main-content {
        flex: 1;
        margin-left: 250px;
        min-height: calc(100vh - 60px);
        padding: 20px;
        background: #23242a;
    }
    
    /* Dashboard container */
    .dashboard-container {
        max-width: 1400px;
        margin: 0 auto;
    }
    
    /* Page title */
    .dashboard-title, .page-title {
        color: #45f3ff;
        font-size: 28px;
        margin-bottom: 20px;
        position: relative;
        display: inline-block;
    }
    
    .dashboard-title::after, .page-title::after {
        content: '';
        position: absolute;
        bottom: -5px;
        left: 0;
        width: 100%;
        height: 2px;
        background: linear-gradient(90deg, #45f3ff, transparent);
    }
    
    /* Action buttons */
    .action-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .action-button {
        background: transparent;
        border: 1px solid #45f3ff;
        color: #45f3ff;
        padding: 8px 15px;
        border-radius: 5px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        transition: all 0.3s;
    }
    
    .action-button:hover {
        background: #45f3ff;
        color: #1c1c1c;
        transform: translateY(-2px);
    }
    
    /* Info Cards */
    .info-card {
        background: rgba(28, 28, 28, 0.8);
        border: 1px solid rgba(69, 243, 255, 0.1);
        border-radius: 10px;
        margin-bottom: 20px;
        overflow: hidden;
    }
    
    .info-card-header {
        background: rgba(69, 243, 255, 0.1);
        padding: 15px 20px;
        border-bottom: 1px solid rgba(69, 243, 255, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .info-card-title {
        color: #45f3ff;
        margin: 0;
        font-size: 16px;
    }
    
    .info-card-body {
        padding: 20px;
    }
    
    /* Table Styling */
    .table-responsive {
        overflow-x: auto;
    }
    
    .table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        color: #fff;
    }
    
    .table th {
        background: rgba(69, 243, 255, 0.1);
        color: #45f3ff;
        font-weight: 500;
        padding: 12px 15px;
        text-align: left;
        text-transform: uppercase;
    }
    
    .table td {
        padding: 12px 15px;
        border-bottom: 1px solid rgba(69, 243, 255, 0.1);
    }
    
    .table tbody tr:hover {
        background: rgba(69, 243, 255, 0.05);
    }
    
    .table tbody tr:last-child td {
        border-bottom: none;
    }
    
    /* Form Controls */
    .form-control, .form-select {
        background-color: rgba(28, 28, 28, 0.8);
        border: 1px solid rgba(69, 243, 255, 0.1);
        color: #fff;
        border-radius: 5px;
        padding: 10px 15px;
    }
    
    .form-control:focus, .form-select:focus {
        background-color: rgba(28, 28, 28, 0.9);
        border-color: #45f3ff;
        box-shadow: 0 0 0 0.25rem rgba(69, 243, 255, 0.25);
        color: #fff;
    }
    
    .form-label {
        color: #45f3ff;
        font-weight: 500;
        margin-bottom: 8px;
        display: block;
    }
    
    /* Badge Styles */
    .badge {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .badge-warning {
        background: #ffd700;
        color: #1c1c1c;
    }
    
    .badge-success {
        background: #28a745;
        color: #fff;
    }
    
    .badge-danger {
        background: #ff4757;
        color: #fff;
    }
    
    .badge-info {
        background: #17a2b8;
        color: #fff;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
        }
        
        .action-buttons {
            flex-direction: column;
        }
    }
</style> 