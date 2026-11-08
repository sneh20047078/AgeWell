<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

// Check if the action was confirmed
$confirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

// Get current schema
$result = $GLOBALS['conn']->query("SHOW COLUMNS FROM userdetails LIKE 'status'");
$row = $result->fetch_assoc();
$type = $row['Type'];

// Extract enum values
preg_match("/^enum\(\'(.*)\'\)$/", $type, $matches);
$values = explode("','", $matches[1]);
$hasPending = in_array('pending', $values);

echo "<h1>Alter Database Schema</h1>";

if (!$hasPending) {
    if ($confirmed) {
        // Add pending to enum values
        $newValues = array_merge($values, ['pending']);
        $newEnum = "enum('" . implode("','", $newValues) . "')";
        
        // Alter the table
        $alterQuery = "ALTER TABLE userdetails MODIFY COLUMN status $newEnum";
        $alterResult = $GLOBALS['conn']->query($alterQuery);
        
        if ($alterResult) {
            echo "<div style='background: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px;'>
                  Successfully altered 'status' column to include 'pending'
                  </div>";
            
            // Now update register.php to use pending
            $registerFile = file_get_contents('register.php');
            if (strpos($registerFile, "'inactive'") !== false) {
                $updatedFile = str_replace("'inactive'", "'pending'", $registerFile);
                if (file_put_contents('register.php', $updatedFile)) {
                    echo "<div style='background: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px;'>
                          Successfully updated register.php to use 'pending' status
                          </div>";
                } else {
                    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; margin-bottom: 20px;'>
                          Error writing to register.php
                          </div>";
                }
            }
        } else {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; margin-bottom: 20px;'>
                  Error altering table: " . $GLOBALS['conn']->error . "
                  </div>";
        }
    } else {
        echo "<p>Current status values:</p>";
        echo "<ul>";
        foreach ($values as $value) {
            echo "<li>" . htmlspecialchars($value) . "</li>";
        }
        echo "</ul>";
        
        echo "<p>The 'status' column needs to be altered to include 'pending' as an enum value.</p>";
        echo "<p>This operation will modify your database schema. Are you sure you want to proceed?</p>";
        echo "<a href='alter_status.php?confirm=yes' style='display: inline-block; padding: 10px 20px; background: #dc3545; color: white; text-decoration: none; border-radius: 4px;'>Yes, Alter Database</a>";
        echo " <a href='fix_register.php' style='display: inline-block; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;'>Cancel</a>";
    }
} else {
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px;'>
          The 'status' column already includes 'pending' as an enum value. No changes needed.
          </div>";
}

// Navigation links
echo "<hr>";
echo "<p>";
if ($confirmed || $hasPending) {
    echo "<a href='fix_register.php' style='display: inline-block; padding: 10px 20px; background: #17a2b8; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;'>Back to Fix Register</a>";
}
echo "<a href='check_users.php' style='display: inline-block; padding: 10px 20px; background: #17a2b8; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;'>Check Users</a>";
echo "<a href='admin_dashboard.php' style='display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>Admin Dashboard</a>";
echo "</p>";
?> 