<?php
/**
 * Input Validation Functions
 * Handles input validation and sanitization
 */

/**
 * Validate required field
 */
function validateRequired($value, $fieldName = 'Field') {
    if (empty($value) && $value !== '0') {
        return ["success" => false, "message" => "$fieldName is required"];
    }
    return ["success" => true];
}

/**
 * Validate email
 */
function validateEmail($email, $fieldName = 'Email') {
    if (empty($email)) {
        return ["success" => false, "message" => "$fieldName is required"];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ["success" => false, "message" => "Invalid $fieldName format"];
    }
    
    return ["success" => true];
}

/**
 * Validate minimum length
 */
function validateMinLength($value, $minLength, $fieldName = 'Field') {
    if (strlen($value) < $minLength) {
        return ["success" => false, "message" => "$fieldName must be at least $minLength characters"];
    }
    return ["success" => true];
}

/**
 * Validate maximum length
 */
function validateMaxLength($value, $maxLength, $fieldName = 'Field') {
    if (strlen($value) > $maxLength) {
        return ["success" => false, "message" => "$fieldName must not exceed $maxLength characters"];
    }
    return ["success" => true];
}

/**
 * Validate length range
 */
function validateLengthRange($value, $minLength, $maxLength, $fieldName = 'Field') {
    $length = strlen($value);
    
    if ($length < $minLength) {
        return ["success" => false, "message" => "$fieldName must be at least $minLength characters"];
    }
    
    if ($length > $maxLength) {
        return ["success" => false, "message" => "$fieldName must not exceed $maxLength characters"];
    }
    
    return ["success" => true];
}

/**
 * Validate numeric value
 */
function validateNumeric($value, $fieldName = 'Field') {
    if (!is_numeric($value)) {
        return ["success" => false, "message" => "$fieldName must be a number"];
    }
    return ["success" => true];
}

/**
 * Validate integer
 */
function validateInteger($value, $fieldName = 'Field') {
    if (!filter_var($value, FILTER_VALIDATE_INT)) {
        return ["success" => false, "message" => "$fieldName must be an integer"];
    }
    return ["success" => true];
}

/**
 * Validate positive number
 */
function validatePositive($value, $fieldName = 'Field') {
    if (!is_numeric($value) || $value <= 0) {
        return ["success" => false, "message" => "$fieldName must be a positive number"];
    }
    return ["success" => true];
}

/**
 * Validate decimal number
 */
function validateDecimal($value, $decimals = 2, $fieldName = 'Field') {
    if (!preg_match("/^[0-9]+(\.[0-9]{1,$decimals})?$/", $value)) {
        return ["success" => false, "message" => "$fieldName must be a valid decimal number"];
    }
    return ["success" => true];
}

/**
 * Validate phone number (Kenyan format)
 */
function validateKenyanPhone($phone, $fieldName = 'Phone') {
    if (empty($phone)) {
        return ["success" => false, "message" => "$fieldName is required"];
    }
    
    // Remove any non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Check if it's a valid Kenyan phone number
    // Must start with 07 or 01 (10 digits) or 254 (12 digits)
    if (preg_match('/^(07|01)/', $phone) && strlen($phone) === 10) {
        return ["success" => true];
    }
    
    if (preg_match('/^254/', $phone) && strlen($phone) === 12) {
        return ["success" => true];
    }
    
    return ["success" => false, "message" => "Invalid $fieldName format. Use format: 07XXXXXXXX or 254XXXXXXXXX"];
}

/**
 * Validate URL
 */
function validateUrl($url, $fieldName = 'URL') {
    if (empty($url)) {
        return ["success" => false, "message" => "$fieldName is required"];
    }
    
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return ["success" => false, "message" => "Invalid $fieldName format"];
    }
    
    return ["success" => true];
}

/**
 * Validate date
 */
function validateDate($date, $fieldName = 'Date') {
    if (empty($date)) {
        return ["success" => false, "message" => "$fieldName is required"];
    }
    
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!($d && $d->format('Y-m-d') === $date)) {
        return ["success" => false, "message" => "Invalid $fieldName format. Use YYYY-MM-DD"];
    }
    
    return ["success" => true];
}

/**
 * Validate datetime
 */
function validateDateTime($datetime, $fieldName = 'DateTime') {
    if (empty($datetime)) {
        return ["success" => false, "message" => "$fieldName is required"];
    }
    
    $d = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
    if (!($d && $d->format('Y-m-d H:i:s') === $datetime)) {
        return ["success" => false, "message" => "Invalid $fieldName format. Use YYYY-MM-DD HH:MM:SS"];
    }
    
    return ["success" => true];
}

/**
 * Validate date range
 */
function validateDateRange($startDate, $endDate, $startName = 'Start Date', $endName = 'End Date') {
    $start = strtotime($startDate);
    $end = strtotime($endDate);
    
    if ($start > $end) {
        return ["success" => false, "message" => "$startName must be before $endName"];
    }
    
    return ["success" => true];
}

/**
 * Validate username
 */
function validateUsername($username) {
    if (empty($username)) {
        return ["success" => false, "message" => "Username is required"];
    }
    
    if (strlen($username) < 3) {
        return ["success" => false, "message" => "Username must be at least 3 characters"];
    }
    
    if (strlen($username) > 50) {
        return ["success" => false, "message" => "Username must not exceed 50 characters"];
    }
    
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        return ["success" => false, "message" => "Username can only contain letters, numbers, and underscores"];
    }
    
    return ["success" => true];
}

/**
 * Validate password
 */
function validatePassword($password, $minLength = 6) {
    if (empty($password)) {
        return ["success" => false, "message" => "Password is required"];
    }
    
    if (strlen($password) < $minLength) {
        return ["success" => false, "message" => "Password must be at least $minLength characters"];
    }
    
    return ["success" => true];
}

/**
 * Validate password strength
 */
function validatePasswordStrength($password) {
    if (empty($password)) {
        return ["success" => false, "message" => "Password is required"];
    }
    
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "at least 8 characters";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "one uppercase letter";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "one lowercase letter";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "one number";
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "one special character";
    }
    
    if (!empty($errors)) {
        return ["success" => false, "message" => "Password must contain " . implode(", ", $errors)];
    }
    
    return ["success" => true];
}

/**
 * Validate password match
 */
function validatePasswordMatch($password, $confirmPassword) {
    if ($password !== $confirmPassword) {
        return ["success" => false, "message" => "Passwords do not match"];
    }
    return ["success" => true];
}

/**
 * Validate select option
 */
function validateSelect($value, $allowedValues, $fieldName = 'Field') {
    if (!in_array($value, $allowedValues)) {
        return ["success" => false, "message" => "Invalid $fieldName selection"];
    }
    return ["success" => true];
}

/**
 * Validate checkbox
 */
function validateCheckbox($value, $fieldName = 'Checkbox') {
    if ($value !== '1' && $value !== 'on' && $value !== true) {
        return ["success" => false, "message" => "$fieldName must be checked"];
    }
    return ["success" => true];
}

/**
 * Validate file upload
 */
function validateFileUpload($file, $allowedTypes = [], $maxSize = 5242880) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server maximum size',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form maximum size',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        return ["success" => false, "message" => $errors[$file['error']] ?? 'Upload error'];
    }
    
    if ($file['size'] > $maxSize) {
        return ["success" => false, "message" => 'File size exceeds maximum allowed size of ' . ($maxSize / 1048576) . 'MB'];
    }
    
    if (!empty($allowedTypes)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            return ["success" => false, "message" => 'File type not allowed'];
        }
    }
    
    return ["success" => true];
}

/**
 * Validate IP address
 */
function validateIP($ip, $fieldName = 'IP Address') {
    if (empty($ip)) {
        return ["success" => false, "message" => "$fieldName is required"];
    }
    
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return ["success" => false, "message" => "Invalid $fieldName format"];
    }
    
    return ["success" => true];
}

/**
 * Validate alphanumeric
 */
function validateAlphanumeric($value, $fieldName = 'Field') {
    if (!preg_match('/^[a-zA-Z0-9]+$/', $value)) {
        return ["success" => false, "message" => "$fieldName must contain only letters and numbers"];
    }
    return ["success" => true];
}

/**
 * Validate alpha only
 */
function validateAlpha($value, $fieldName = 'Field') {
    if (!preg_match('/^[a-zA-Z]+$/', $value)) {
        return ["success" => false, "message" => "$fieldName must contain only letters"];
    }
    return ["success" => true];
}

/**
 * Validate array
 */
function validateArray($value, $fieldName = 'Field') {
    if (!is_array($value) || empty($value)) {
        return ["success" => false, "message" => "$fieldName must be a non-empty array"];
    }
    return ["success" => true];
}

/**
 * Validate matches another field
 */
function validateMatches($value, $compareValue, $fieldName = 'Field') {
    if ($value !== $compareValue) {
        return ["success" => false, "message" => "$fieldName does not match"];
    }
    return ["success" => true];
}

/**
 * Validate unique (for database)
 */
function validateUnique($pdo, $table, $column, $value, $excludeId = null) {
    try {
        $sql = "SELECT id FROM $table WHERE $column = ?";
        $params = [$value];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->fetch()) {
            return ["success" => false, "message" => "This value already exists"];
        }
        
        return ["success" => true];
        
    } catch (PDOException $e) {
        return ["success" => false, "message" => "Validation error"];
    }
}

/**
 * Validate exists (for database)
 */
function validateExists($pdo, $table, $column, $value) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM $table WHERE $column = ?");
        $stmt->execute([$value]);
        
        if (!$stmt->fetch()) {
            return ["success" => false, "message" => "This value does not exist"];
        }
        
        return ["success" => true];
        
    } catch (PDOException $e) {
        return ["success" => false, "message" => "Validation error"];
    }
}

/**
 * Run multiple validations
 */
function validate($data, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $fieldRules) {
        $value = $data[$field] ?? null;
        $fieldName = ucfirst($field);
        
        foreach ($fieldRules as $rule => $params) {
            $result = null;
            
            switch ($rule) {
                case 'required':
                    $result = validateRequired($value, $fieldName);
                    break;
                    
                case 'email':
                    $result = validateEmail($value, $fieldName);
                    break;
                    
                case 'min':
                    $result = validateMinLength($value, $params, $fieldName);
                    break;
                    
                case 'max':
                    $result = validateMaxLength($value, $params, $fieldName);
                    break;
                    
                case 'numeric':
                    $result = validateNumeric($value, $fieldName);
                    break;
                    
                case 'integer':
                    $result = validateInteger($value, $fieldName);
                    break;
                    
                case 'positive':
                    $result = validatePositive($value, $fieldName);
                    break;
                    
                case 'url':
                    $result = validateUrl($value, $fieldName);
                    break;
                    
                case 'date':
                    $result = validateDate($value, $fieldName);
                    break;
                    
                case 'phone':
                    $result = validateKenyanPhone($value, $fieldName);
                    break;
                    
                case 'username':
                    $result = validateUsername($value);
                    break;
                    
                case 'password':
                    $result = validatePassword($value, $params ?? 6);
                    break;
                    
                case 'password_strength':
                    $result = validatePasswordStrength($value);
                    break;
                    
                case 'alphanumeric':
                    $result = validateAlphanumeric($value, $fieldName);
                    break;
                    
                case 'alpha':
                    $result = validateAlpha($value, $fieldName);
                    break;
            }
            
            if ($result && !$result['success']) {
                $errors[$field] = $result['message'];
                break; // Stop on first error for this field
            }
        }
    }
    
    if (empty($errors)) {
        return ["success" => true];
    }
    
    return ["success" => false, "errors" => $errors];
}
