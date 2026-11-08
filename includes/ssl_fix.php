<?php
// Disable SSL verification (only for development/testing)
stream_context_set_default([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ]
]); 