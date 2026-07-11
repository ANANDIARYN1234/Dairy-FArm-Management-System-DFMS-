<?php
/**
 * =========================================================
 * DAIRY FARM MANAGEMENT SYSTEM (DFMS)
 * Form Validation Functions
 * =========================================================
 */

defined('DFMS_EXEC') or die('Access Denied');

/**
 * Validation class
 */
class Validator {
    private $errors = [];
    private $data = [];
    
    public function __construct($data = []) {
        $this->data = $data;
    }
    
    /**
     * Validate required field
     */
    public function required($field, $message = null) {
        $value = $this->data[$field] ?? '';
        
        if (empty(trim($value))) {
            $this->errors[$field] = $message ?? ucfirst($field) . ' is required';
        }
        
        return $this;
    }
    
    /**
     * Validate email
     */
    public function email($field, $message = null) {
        $value = $this->data[$field] ?? '';
        
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $message ?? 'Invalid email format';
        }
        
        return $this;
    }
    
    /**
     * Validate minimum length
     */
    public function min($field, $length, $message = null) {
        $value = $this->data[$field] ?? '';
        
        if (!empty($value) && strlen($value) < $length) {
            $this->errors[$field] = $message ?? ucfirst($field) . " must be at least {$length} characters";
        }
        
        return $this;
    }
    
    /**
     * Validate maximum length
     */
    public function max($field, $length, $message = null) {
        $value = $this->data[$field] ?? '';
        
        if (!empty($value) && strlen($value) > $length) {
            $this->errors[$field] = $message ?? ucfirst($field) . " must not exceed {$length} characters";
        }
        
        return $this;
    }
    
    /**
     * Validate numeric
     */
    public function numeric($field, $message = null) {
        $value = $this->data[$field] ?? '';
        
        if (!empty($value) && !is_numeric($value)) {
            $this->errors[$field] = $message ?? ucfirst($field) . ' must be a number';
        }
        
        return $this;
    }
    
    /**
     * Validate positive number
     */
    public function positive($field, $message = null) {
        $value = $this->data[$field] ?? '';
        
        if (!empty($value) && (!is_numeric($value) || $value <= 0)) {
            $this->errors[$field] = $message ?? ucfirst($field) . ' must be a positive number';
        }
        
        return $this;
    }
    
    /**
     * Validate integer
     */
    public function integer($field, $message = null) {
        $value = $this->data[$field] ?? '';
        
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
            $this->errors[$field] = $message ?? ucfirst($field) . ' must be an integer';
        }
        
        return $this;
    }
    
    /**
     * Validate date format
     */
    public function date($field, $format = 'Y-m-d', $message = null) {
        $value = $this->data[$field] ?? '';
        
        if (!empty($value)) {
            $d = DateTime::createFromFormat($format, $value);
            if (!$d || $d->format($format) !== $value) {
                $this->errors[$field] = $message ?? 'Invalid date format';
            }
        }
        
        return $this;
    }
    
    /**
     * Validate date is before today
     */
    public function before_today($field, $message = null) {
        $value = $this->data[$field] ?? '';
        
        if (!empty($value) && strtotime($value) > time()) {
            $this->errors[$field] = $message ?? 'Date must be before today';
        }
        
        return $this;
    }
    
    /**
     * Validate date is after a date
     */
    public function after($field, $compare_field, $message = null) {
        $value = $this->data[$field] ?? '';
        $compare_value = $this->data[$compare_field] ?? '';
        
        if (!empty($value) && !empty($compare_value) && strtotime($value) <= strtotime($compare_value)) {
            $this->errors[$field] = $message ?? ucfirst($field) . ' must be after ' . $compare_field;
        }
        
        return $this;
    }
    
    /**
     * Validate phone number (Nepal format)
     */
    public function phone($field, $message = null) {
        $value = $this->data[$field] ?? '';
        
        if (!empty($value) && !preg_match('/^(\+977)?[9][6-9]\d{8}$/', $value)) {
            $this->errors[$field] = $message ?? 'Invalid phone number format';
        }
        
        return $this;
    }
    
    /**
     * Validate unique value in database
     */
    public function unique($field, $table, $column, $exclude_id = null, $message = null) {
        global $conn;
        
        $value = $this->data[$field] ?? '';
        
        if (!empty($value)) {
            if ($exclude_id) {
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ? AND {$column}_id != ?");
                $stmt->bind_param("si", $value, $exclude_id);
            } else {
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?");
                $stmt->bind_param("s", $value);
            }
            
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result['count'] > 0) {
                $this->errors[$field] = $message ?? ucfirst($field) . ' already exists';
            }
        }
        
        return $this;
    }
    
    /**
     * Validate field matches another field
     */
    public function match($field, $match_field, $message = null) {
        $value = $this->data[$field] ?? '';
        $match_value = $this->data[$match_field] ?? '';
        
        if ($value !== $match_value) {
            $this->errors[$field] = $message ?? ucfirst($field) . ' must match ' . $match_field;
        }
        
        return $this;
    }
    
    /**
     * Validate file upload
     */
    public function file($field, $allowed_types = [], $max_size = 5242880, $message = null) {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            return $this;
        }
        
        $file = $_FILES[$field];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[$field] = 'File upload failed';
            return $this;
        }
        
        // Check file size
        if ($file['size'] > $max_size) {
            $max_mb = $max_size / 1048576;
            $this->errors[$field] = "File size must not exceed {$max_mb} MB";
            return $this;
        }
        
        // Check file type
        if (!empty($allowed_types)) {
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($file_ext, $allowed_types)) {
                $this->errors[$field] = 'Invalid file type. Allowed: ' . implode(', ', $allowed_types);
            }
        }
        
        return $this;
    }
    
    /**
     * Check if validation passed
     */
    public function passes() {
        return empty($this->errors);
    }
    
    /**
     * Check if validation failed
     */
    public function fails() {
        return !empty($this->errors);
    }
    
    /**
     * Get all errors
     */
    public function errors() {
        return $this->errors;
    }
    
    /**
     * Get first error
     */
    public function first() {
        return !empty($this->errors) ? reset($this->errors) : null;
    }
    
    /**
     * Get error for specific field
     */
    public function error($field) {
        return $this->errors[$field] ?? null;
    }
}

/**
 * Quick validation helper
 */
function validate($data, $rules) {
    $validator = new Validator($data);
    
    foreach ($rules as $field => $rule_string) {
        $rules_array = explode('|', $rule_string);
        
        foreach ($rules_array as $rule) {
            $rule_parts = explode(':', $rule);
            $rule_name = $rule_parts[0];
            $rule_params = isset($rule_parts[1]) ? explode(',', $rule_parts[1]) : [];
            
            if (method_exists($validator, $rule_name)) {
                call_user_func_array([$validator, $rule_name], array_merge([$field], $rule_params));
            }
        }
    }
    
    return $validator;
}
?>