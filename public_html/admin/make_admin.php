<?php
/**
 * ONE-TIME USE: promotes an existing user (by email) to platform admin.
 * Visit /admin/make_admin.php?email=you@example.com&key=SETUP_KEY once,
 * then DELETE this file from the server.
 */
require_once __DIR__ . '/../includes/db.php';

$SETUP_KEY = 'change-this-before-first-use'; // set your own secret before deploying

$key = $_GET['key'] ?? '';
$email = trim(strtolower($_GET['email'] ?? ''));

if ($key !== $SETUP_KEY || $SETUP_KEY === 'change-this-before-first-use') {
    http_response_code(403);
    die('Set your own SETUP_KEY in this file first, then use it in the URL.');
}
if (!$email) {
    die('Usage: make_admin.php?email=you@example.com&key=YOUR_KEY');
}

$conn = db();
$stmt = $conn->prepare('UPDATE users SET is_platform_admin = 1 WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo "Done: $email is now a platform admin. Now DELETE this file.";
} else {
    echo "No user found with that email, or they were already a platform admin.";
}
$stmt->close();
