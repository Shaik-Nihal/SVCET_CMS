<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
destroySession();
header('Location: ' . APP_URL . '/auth/login');
exit;
