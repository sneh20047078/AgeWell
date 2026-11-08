<?php
// This script can be included at the top of your admin pages
// to automatically apply the admin theme without changing all pages

// Store the original output buffering level
$original_ob_level = ob_get_level();

// Start output buffering
ob_start();

// Set a flag to indicate we're using the theme injector
$using_theme_injector = true;

// Function to inject the theme
function inject_admin_theme($buffer) {
    global $using_theme_injector;
    
    // Only process if we're using the theme injector
    if (!isset($using_theme_injector) || !$using_theme_injector) {
        return $buffer;
    }
    
    // Don't process if this is an AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        return $buffer;
    }
    
    // Don't modify if it already has our theme marker
    if (strpos($buffer, '<!-- ADMIN_THEME_APPLIED -->') !== false) {
        return $buffer;
    }
    
    // Extract the body content
    preg_match('/<body.*?>(.*)<\/body>/s', $buffer, $matches);
    $body_content = isset($matches[1]) ? $matches[1] : $buffer;
    
    // Get the page title
    preg_match('/<title>(.*?)<\/title>/s', $buffer, $title_matches);
    $page_title = isset($title_matches[1]) ? $title_matches[1] : 'Admin Panel';
    
    // Load the admin layout
    ob_start();
    $pageTitle = $page_title;
    include 'admin_layout.php';
    $layout_start = ob_get_clean();
    
    // Load the admin footer
    ob_start();
    include 'admin_footer.php';
    $layout_end = ob_get_clean();
    
    // Clean up the content to remove any existing html, head, body tags
    $body_content = preg_replace('/<html.*?>|<\/html>|<head.*?>.*?<\/head>|<body.*?>|<\/body>/s', '', $body_content);
    
    // Combine everything
    $themed_content = $layout_start . $body_content . $layout_end;
    $themed_content .= '<!-- ADMIN_THEME_APPLIED -->';
    
    return $themed_content;
}

// Register the output buffer callback
ob_start('inject_admin_theme');

// Register a shutdown function to clean up output buffering
register_shutdown_function(function() use ($original_ob_level) {
    // Clean up any remaining output buffers
    while (ob_get_level() > $original_ob_level) {
        ob_end_flush();
    }
});
?> 