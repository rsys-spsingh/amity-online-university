<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// === CONFIG: set these from cPanel credentials ===
$db_host = 'localhost';
$db_name = 'amity_app_db';         // e.g. cpuser_amity_app_db
$db_user = 'amity_user';         // e.g. cpuser_amity_user
$db_pass = 'k#1nQ%$KYcPH';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// connect
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB connection failed: ' . $mysqli->connect_error]);
    exit;
}

// Set charset
$mysqli->set_charset("utf8");

// Accept POST data (works with form POST or fetch with FormData)
$firstName   = isset($_POST['firstName'])   ? trim($_POST['firstName'])   : '';
$lastName    = isset($_POST['lastName'])    ? trim($_POST['lastName'])    : '';
$email       = isset($_POST['email'])       ? trim($_POST['email'])       : '';
$countryCode = isset($_POST['countryCode']) ? trim($_POST['countryCode']) : '';
$phoneNumber = isset($_POST['phoneNumber']) ? trim($_POST['phoneNumber']) : '';
$course      = isset($_POST['course'])      ? trim($_POST['course'])      : '';
$state       = isset($_POST['state'])       ? trim($_POST['state'])       : '';

$errors = [];

// Basic validation
if (strlen($firstName) < 2) $errors[] = 'First name must be at least 2 characters';
if (strlen($lastName) < 2) $errors[] = 'Last name must be at least 2 characters';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
if (!preg_match('/^[0-9]{6,15}$/', preg_replace('/\D/', '', $phoneNumber))) $errors[] = 'Invalid phone number';
if (empty($course)) $errors[] = 'Course selection is required';
if (empty($state)) $errors[] = 'State selection is required';
if (empty($countryCode)) $errors[] = 'Country code is required';

// Check if email already exists
if (empty($errors)) {
    $checkEmail = $mysqli->prepare("SELECT id FROM `applications` WHERE email = ?");
    $checkEmail->bind_param('s', $email);
    $checkEmail->execute();
    $result = $checkEmail->get_result();
    if ($result->num_rows > 0) {
        $errors[] = 'An application with this email already exists';
    }
    $checkEmail->close();
}

// If error -> return
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Insert into DB using prepared stmt
$applicationId = 'AMT' . time() . rand(100,999);
$submissionDate = date('Y-m-d H:i:s');

$stmt = $mysqli->prepare(
    "INSERT INTO `applications` (application_id, first_name, last_name, email, country_code, phone_number, course, state, submission_date)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $mysqli->error]);
    exit;
}

$stmt->bind_param('sssssssss',
    $applicationId,
    $firstName,
    $lastName,
    $email,
    $countryCode,
    $phoneNumber,
    $course,
    $state,
    $submissionDate
);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true, 
        'message' => 'Application submitted successfully',
        'applicationId' => $applicationId
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $stmt->error]);
}

$stmt->close();
$mysqli->close();
?>
