<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/auth.php';
startSecureSession();

// Redirect to appropriate dashboard if logged in, else to login
if (!empty($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] === 'staff') {
        if (currentStaffHasPermission('admin.access')) {
            header('Location: ' . APP_URL . '/admin/dashboard');
        } else {
            header('Location: ' . APP_URL . '/staff/dashboard');
        }
    } else {
        header('Location: ' . APP_URL . '/user/dashboard');
    }
} else {
    header('Location: ' . APP_URL . '/auth/login');
}
exit;
