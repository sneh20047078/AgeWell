<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/notification_functions.php';
require_once __DIR__ . '/../includes/sms_functions.php';

// Send reminders for upcoming appointments
sendAppointmentReminders(); 