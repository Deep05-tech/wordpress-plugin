<?php
require_once __DIR__ . '/../../wp-load.php';

header('Content-Type: text/plain');

echo "=== VCPG ISOLATION & SECURITY TEST ===\n\n";

// 1. Test Page Isolation Helper
echo "1. Testing is_vcpg_generated_page():\n";
if (function_exists('is_vcpg_generated_page')) {
    echo "  [PASS] is_vcpg_generated_page function exists.\n";
    $test_non_vcpg = is_vcpg_generated_page(1); // Usually home page or sample page
    echo "  non-VCPG page test (ID 1): " . ($test_non_vcpg ? "FAILED (returned true)" : "PASSED (returned false)") . "\n";
} else {
    echo "  [FAIL] is_vcpg_generated_page function not found!\n";
}

// 2. Test Honeypot Anti-Spam Trap
echo "\n2. Testing Honeypot Anti-Spam Trap via AJAX:\n";
$url = admin_url('admin-ajax.php');

$spam_data = array(
    'action'       => 'vcpg_submit_inquiry',
    'fullname'     => 'Spam Bot Malicious Lead',
    'email'        => 'spambot@malware-domain.org',
    'vcpg_hp_trap' => 'I am a spam bot filling hidden field',
    'page_url'     => home_url('/test-page'),
);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $spam_data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$spam_res = curl_exec($ch);
curl_close($ch);

echo "  Spam bot payload submission response:\n  " . $spam_res . "\n";

// Check if spam lead was discarded from DB
global $wpdb;
$table_name = $wpdb->prefix . 'vcpg_inquiries';
$spam_in_db = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE email = %s", 'spambot@malware-domain.org'));

if ($spam_in_db == 0) {
    echo "  [PASS] Spam lead was trapped by honeypot and discarded from DB!\n";
} else {
    echo "  [FAIL] Spam lead entered DB!\n";
}

echo "\n3. Testing Valid Lead Submission:\n";
$valid_data = array(
    'action'       => 'vcpg_submit_inquiry',
    'fullname'     => 'Verified Security Lead',
    'email'        => 'security-test@vispansolutions.com',
    'phone'        => '9876543210',
    'service'      => 'SEO Audit',
    'company'      => 'Vispan Test',
    'vcpg_hp_trap' => '', // Empty honeypot for human
    'page_url'     => home_url('/test-page'),
);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $valid_data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$valid_res = curl_exec($ch);
curl_close($ch);

echo "  Valid submission response:\n  " . $valid_res . "\n";

unlink(__FILE__);
