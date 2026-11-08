<?php
// Replace these with your ACTUAL Twilio credentials
if (!defined('TWILIO_ACCOUNT_SID')) {
    define('TWILIO_ACCOUNT_SID', getenv('AGEWELL_TWILIO_ACCOUNT_SID') ?: '');
}
if (!defined('TWILIO_AUTH_TOKEN')) {
    define('TWILIO_AUTH_TOKEN', getenv('AGEWELL_TWILIO_AUTH_TOKEN') ?: '');
}
if (!defined('TWILIO_PHONE_NUMBER')) {
    define('TWILIO_PHONE_NUMBER', getenv('AGEWELL_TWILIO_PHONE_NUMBER') ?: '');
}
