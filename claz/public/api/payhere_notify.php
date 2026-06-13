<?php
/**
 * PayHere Payment Notification Handler
 * Receives asynchronous payment status updates from PayHere
 */

require_once '../../src/config.php';
require_once '../../src/db.php';

// Log all incoming requests for debugging
$logFile = __DIR__ . '/../../logs/payhere_notify.log';
$logDir = dirname($logFile);
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

$logEntry = date('Y-m-d H:i:s') . " - Notification received\n";
$logEntry .= "POST data: " . json_encode($_POST) . "\n";
$logEntry .= "Server: " . $_SERVER['REMOTE_ADDR'] . "\n\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);

// Extract PayHere parameters
$merchant_id = $_POST['merchant_id'] ?? '';
$order_id = $_POST['order_id'] ?? '';
$payhere_amount = $_POST['payhere_amount'] ?? '';
$payhere_currency = $_POST['payhere_currency'] ?? '';
$status_code = $_POST['status_code'] ?? '';
$md5sig = $_POST['md5sig'] ?? '';
$payment_id = $_POST['payment_id'] ?? '';
$custom_1 = $_POST['custom_1'] ?? ''; // user_id
$custom_2 = $_POST['custom_2'] ?? ''; // paper_id

// Validate required fields
if (!$merchant_id || !$order_id || !$md5sig) {
    http_response_code(400);
    exit('Missing required parameters');
}

// Verify merchant ID
if ($merchant_id !== PAYHERE_MERCHANT_ID) {
    http_response_code(403);
    exit('Invalid merchant');
}

// Verify hash signature
$merchant_secret = PAYHERE_MERCHANT_SECRET;
$hashedSecret = strtoupper(md5($merchant_secret));
$local_md5sig = strtoupper(md5($merchant_id . $order_id . $payhere_amount . $payhere_currency . $status_code . $hashedSecret));

if ($local_md5sig !== $md5sig) {
    http_response_code(403);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Hash verification failed\n", FILE_APPEND);
    exit('Hash verification failed');
}

// Status code 2 means successful payment
if ($status_code == '2') {
    try {
        $pdo = get_db();
        
        // Update payment record
        $stmt = $pdo->prepare("
            UPDATE payments 
            SET status = 'completed', 
                transaction_id = :transaction_id,
                paid_at = NOW()
            WHERE order_id = :order_id
        ");
        $stmt->execute([
            ':transaction_id' => $payment_id,
            ':order_id' => $order_id
        ]);
        
        if ($stmt->rowCount() > 0) {
            // Grant paper access if we have user_id and paper_id
            if ($custom_1 && $custom_2) {
                $stmt = $pdo->prepare("
                    INSERT INTO paper_access (user_id, paper_id, granted_at)
                    VALUES (:user_id, :paper_id, NOW())
                    ON DUPLICATE KEY UPDATE granted_at = NOW()
                ");
                $stmt->execute([
                    ':user_id' => $custom_1,
                    ':paper_id' => $custom_2
                ]);
            }
            
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Payment completed for order $order_id\n", FILE_APPEND);
            http_response_code(200);
            echo 'OK';
        } else {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Payment record not found for order $order_id\n", FILE_APPEND);
            http_response_code(404);
            echo 'Payment record not found';
        }
    } catch (PDOException $e) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Database error: " . $e->getMessage() . "\n", FILE_APPEND);
        http_response_code(500);
        echo 'Database error';
    }
} else {
    // Handle failed/cancelled payments
    try {
        $pdo = get_db();
        $stmt = $pdo->prepare("
            UPDATE payments 
            SET status = 'failed',
                transaction_id = :transaction_id
            WHERE order_id = :order_id
        ");
        $stmt->execute([
            ':transaction_id' => $payment_id,
            ':order_id' => $order_id
        ]);
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Payment failed for order $order_id (status: $status_code)\n", FILE_APPEND);
        http_response_code(200);
        echo 'OK';
    } catch (PDOException $e) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Database error: " . $e->getMessage() . "\n", FILE_APPEND);
        http_response_code(500);
        echo 'Database error';
    }
}
