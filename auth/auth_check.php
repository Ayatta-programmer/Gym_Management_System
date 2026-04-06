<?php
// ============================================
// FitPulse - Auth Guard
// Include at top of protected pages
// ============================================

require_once __DIR__ . '/../config/database.php';

// Function to check auth and role
function checkAuth($allowedRoles = []) {
    if (!isLoggedIn()) {
        redirect('../auth/login.php');
    }

    if (!empty($allowedRoles) && !in_array(getUserRole(), $allowedRoles)) {
        // Redirect to appropriate dashboard
        $role = getUserRole();
        switch ($role) {
            case 'admin':
                redirect('../admin/dashboard.php');
                break;
            case 'trainer':
                redirect('../trainer/dashboard.php');
                break;
            case 'member':
                redirect('../member/dashboard.php');
                break;
            default:
                redirect('../auth/login.php');
        }
    }
}

?>
