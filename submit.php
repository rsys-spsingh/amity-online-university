<?php
// Error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// === CONFIG: Update these with your actual cPanel credentials ===
$db_host = 'localhost'; // Usually 'localhost' for cPanel
$db_name = 'degreedr_amity_app_db'; // Format: cpanel_username_databasename
$db_user = 'degreedr_amity_user';   // Format: cpanel_username_username
$db_pass = 'k#1nQ%$KYcPH'; // Your database password

// Log function for debugging
function logError($message) {
    error_log(date('Y-m-d H:i:s') . " - " . $message . "\n", 3, "form_errors.log");
}

try {
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    // Connect to database
    $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($mysqli->connect_errno) {
        logError('Database connection failed: ' . $mysqli->connect_error);
        throw new Exception('Database connection failed: ' . $mysqli->connect_error);
    }
    
    // Set charset
    $mysqli->set_charset("utf8mb4");
    logError('Database connected successfully');

    // Get POST data with validation
    $firstName   = isset($_POST['firstName'])   ? trim($_POST['firstName'])   : '';
    $lastName    = isset($_POST['lastName'])    ? trim($_POST['lastName'])    : '';
    $email       = isset($_POST['email'])       ? trim(strtolower($_POST['email'])) : '';
    $countryCode = isset($_POST['countryCode']) ? trim($_POST['countryCode']) : '';
    $phoneNumber = isset($_POST['phoneNumber']) ? trim($_POST['phoneNumber']) : '';
    $course      = isset($_POST['course'])      ? trim($_POST['course'])      : '';
    $state       = isset($_POST['state'])       ? trim($_POST['state'])       : '';

    $errors = [];

    // Enhanced Validation
    if (strlen($firstName) < 2 || !preg_match('/^[a-zA-Z\s]+$/', $firstName)) {
        $errors[] = 'First name must be at least 2 characters and contain only letters';
    }
    
    if (strlen($lastName) < 2 || !preg_match('/^[a-zA-Z\s]+$/', $lastName)) {
        $errors[] = 'Last name must be at least 2 characters and contain only letters';
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address format';
    }
    
    if (!preg_match('/^\+[1-9]\d{1,4}$/', $countryCode)) {
        $errors[] = 'Invalid country code format';
    }
    
    if (!preg_match('/^[0-9]{10}$/', $phoneNumber)) {
        $errors[] = 'Phone number must be exactly 10 digits';
    }
    
    if (empty($course)) {
        $errors[] = 'Please select a course';
    }
    
    if (empty($state)) {
        $errors[] = 'Please select a state';
    }

    // Check for validation errors
    if (!empty($errors)) {
        logError('Validation errors: ' . implode(', ', $errors));
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    // Check if email already exists
    $checkStmt = $mysqli->prepare("SELECT id FROM applications WHERE email = ?");
    if (!$checkStmt) {
        throw new Exception('Prepare statement failed: ' . $mysqli->error);
    }
    
    $checkStmt->bind_param('s', $email);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        logError('Duplicate email attempt: ' . $email);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'An application with this email already exists']);
        exit;
    }
    $checkStmt->close();

    // Generate unique application ID
    do {
        $applicationId = 'AMT' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $checkIdStmt = $mysqli->prepare("SELECT id FROM applications WHERE application_id = ?");
        $checkIdStmt->bind_param('s', $applicationId);
        $checkIdStmt->execute();
        $idResult = $checkIdStmt->get_result();
        $checkIdStmt->close();
    } while ($idResult->num_rows > 0);

    $submissionDate = date('Y-m-d H:i:s');

    // Insert into database
    $stmt = $mysqli->prepare(
        "INSERT INTO applications (application_id, first_name, last_name, email, country_code, phone_number, course, state, submission_date) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        throw new Exception('Prepare statement failed: ' . $mysqli->error);
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
        logError('Application submitted successfully: ' . $applicationId);
        echo json_encode([
            'success' => true, 
            'message' => 'Application submitted successfully',
            'applicationId' => $applicationId
        ]);
    } else {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $stmt->close();

} catch (Exception $e) {
    logError('Application submission error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while processing your application. Please try again.',
        'debug' => $e->getMessage() // Remove this in production
    ]);
} finally {
    if (isset($mysqli)) {
        $mysqli->close();
    }
}
?>
