<?php
// ============================================================
// Fast2SMS API Wrapper
// ============================================================
require_once __DIR__ . '/constants.php';

/**
 * Send SMS via Fast2SMS API (India).
 * NOTE: Free tier only sends to the registered developer number.
 * Production: use paid transactional route with DLT registration.
 *
 * @param string $phone  10-digit Indian mobile number (no +91)
 * @param string $message SMS text (max ~160 chars for single SMS)
 * @return bool
 */
function sendSMS(string $phone, string $message): bool {
    if (empty(FAST2SMS_API_KEY) || FAST2SMS_API_KEY === 'YOUR_FAST2SMS_API_KEY_HERE') {
        error_log('Fast2SMS: API key not configured.');
        return false;
    }

    // Strip leading 0 or +91 if provided
    $phone = preg_replace('/^(\+91|0)/', '', trim($phone));
    if (strlen($phone) !== 10 || !ctype_digit($phone)) {
        error_log("Fast2SMS: Invalid phone number: {$phone}");
        return false;
    }

    $data = http_build_query([
        'route'   => 'q',     // quick transactional
        'message' => $message,
        'numbers' => $phone,
    ]);

    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Authorization: " . FAST2SMS_API_KEY . "\r\n"
                       . "Content-Type: application/x-www-form-urlencoded\r\n"
                       . "Cache-Control: no-cache\r\n",
            'content' => $data,
            'timeout' => 10,
            'ignore_errors' => true,
        ]
    ]);

    try {
        $result = @file_get_contents(FAST2SMS_URL, false, $context);
        if ($result === false) {
            error_log("Fast2SMS: HTTP request failed for {$phone}");
            return false;
        }
        $response = json_decode($result, true);
        if (!empty($response['return']) && $response['return'] === true) {
            return true;
        }
        error_log('Fast2SMS error: ' . ($response['message'][0] ?? $result));
        return false;
    } catch (Throwable $e) {
        error_log('Fast2SMS exception: ' . $e->getMessage());
        return false;
    }
}
