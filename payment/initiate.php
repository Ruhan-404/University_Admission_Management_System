<?php
require_once '../includes/db.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: ../login.php"); exit;
}

$student_id   = (int)$_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';
$student_dept = $_SESSION['student_dept'] ?? '';
$gst_roll     = $_SESSION['student_gst']  ?? '';
$email        = $_SESSION['student_email'] ?? 'noemail@university.edu';
$phone        = $_SESSION['student_phone'] ?? '01700000000';

// ── SSLCommerz Sandbox Credentials ───────────────────────────
// Replace with your real Store ID and Password from sslcommerz.com
$store_id   = 'testbox';          // sandbox store id
$store_pass = 'qwerty';           // sandbox store password
$is_live    = false;              // set true for production

$base_url   = 'http://' . $_SERVER['HTTP_HOST'] . '/uni_admission_fixed';

$tran_id = 'UNI-' . $student_id . '-' . time();

// Save transaction ID to DB for verification later
$conn->query("
    CREATE TABLE IF NOT EXISTS payments (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        student_id   INT NOT NULL,
        tran_id      VARCHAR(60) NOT NULL UNIQUE,
        amount       DECIMAL(10,2) NOT NULL DEFAULT 5000.00,
        method       VARCHAR(30) DEFAULT NULL,
        status       ENUM('pending','verified','failed') DEFAULT 'pending',
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$ins = $conn->prepare("INSERT IGNORE INTO payments (student_id, tran_id, amount) VALUES (?, ?, 5000.00)");
$ins->bind_param("is", $student_id, $tran_id);
$ins->execute();
$ins->close();

$post_data = [
    'store_id'          => $store_id,
    'store_passwd'      => $store_pass,
    'total_amount'      => '5000.00',
    'currency'          => 'BDT',
    'tran_id'           => $tran_id,
    'success_url'       => $base_url . '/payment/success.php',
    'fail_url'          => $base_url . '/payment/fail.php',
    'cancel_url'        => $base_url . '/payment/cancel.php',
    'ipn_url'           => $base_url . '/payment/ipn.php',
    'cus_name'          => $student_name,
    'cus_email'         => $email,
    'cus_phone'         => $phone,
    'cus_add1'          => 'University of Barishal',
    'cus_city'          => 'Barishal',
    'cus_country'       => 'Bangladesh',
    'shipping_method'   => 'NO',
    'product_name'      => 'University Admission Fee',
    'product_category'  => 'Education',
    'product_profile'   => 'non-physical-goods',
    'value_a'           => $student_id,   // pass student id for IPN
];

$api_url = $is_live
    ? 'https://securepay.sslcommerz.com/gwprocess/v4/api.php'
    : 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
$err      = curl_error($ch);
curl_close($ch);

if ($err || !$response) {
    die("Payment gateway error: " . htmlspecialchars($err ?: 'No response'));
}

$result = json_decode($response, true);

if (isset($result['GatewayPageURL']) && $result['GatewayPageURL']) {
    header("Location: " . $result['GatewayPageURL']);
    exit;
} else {
    echo "<p style='color:red; font-family:sans-serif; padding:20px;'>
        Could not initiate payment. Gateway response: <br>
        <pre>" . htmlspecialchars(print_r($result, true)) . "</pre>
    </p>";
}
