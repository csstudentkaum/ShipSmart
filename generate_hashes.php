<?php
/*
 * File: generate_hashes.php  (project root)
 * Purpose: Generate correct bcrypt hashes for test passwords.
 * 
 * HOW TO USE:
 *   1. Place this file at your project root
 *   2. Open: http://127.0.0.1:8000/generate_hashes.php
 *   3. Copy the hashes shown
 *   4. Paste them into api/login.php test accounts
 *   5. DELETE this file after — never leave hash generators in production
 */

$passwords = [
    'Admin@1234',
    'User@1234',
];

echo '<pre style="font-family:monospace;font-size:14px;padding:20px">';
echo "=== GENERATED HASHES ===\n\n";

foreach ($passwords as $pw) {
    $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost' => 10]);
    $valid = password_verify($pw, $hash);
    echo "Password : {$pw}\n";
    echo "Hash     : {$hash}\n";
    echo "Verified : " . ($valid ? "YES ✓" : "NO ✗") . "\n\n";
}

echo "=== READY TO PASTE INTO api/login.php ===\n\n";

$adminHash = password_hash('Admin@1234', PASSWORD_BCRYPT, ['cost' => 10]);
$userHash  = password_hash('User@1234',  PASSWORD_BCRYPT, ['cost' => 10]);

echo "'admin\@shipsmart.com' => [\n";
echo "    'id' => 1, 'full_name' => 'Admin', 'role' => 'admin',\n";
echo "    'hash' => '{$adminHash}',\n";
echo "],\n\n";

echo "'user\@shipsmart.com' => [\n";
echo "    'id' => 2, 'full_name' => 'Sara Ahmed', 'role' => 'user',\n";
echo "    'hash' => '{$userHash}',\n";
echo "],\n";

echo '</pre>';
?>