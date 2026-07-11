<?php
/**
 * =========================================================
 * DAIRY FARM MANAGEMENT SYSTEM (DFMS)
 * Date Validation Helper Functions
 * =========================================================
 */

defined('DFMS_EXEC') or die('Access Denied');

/**
 * Validate date format (YYYY-MM-DD)
 */
function validate_date_format($date) {
    if (empty($date)) {
        return false;
    }
    
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Validate date is not in future
 */
function validate_not_future_date($date, $field_name = 'Date') {
    if (!validate_date_format($date)) {
        return ['valid' => false, 'error' => "$field_name format is invalid"];
    }
    
    $input_date = new DateTime($date);
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    $input_date->setTime(0, 0, 0);
    
    if ($input_date > $today) {
        return ['valid' => false, 'error' => "$field_name cannot be in the future"];
    }
    
    return ['valid' => true];
}

/**
 * Validate date is not too old
 */
function validate_not_too_old($date, $max_days_old = 365, $field_name = 'Date') {
    if (!validate_date_format($date)) {
        return ['valid' => false, 'error' => "$field_name format is invalid"];
    }
    
    $input_date = new DateTime($date);
    $today = new DateTime();
    $interval = $today->diff($input_date);
    $days_diff = $interval->days;
    
    if ($days_diff > $max_days_old) {
        return ['valid' => false, 'error' => "$field_name cannot be older than $max_days_old days"];
    }
    
    return ['valid' => true];
}

/**
 * Validate date range (from <= to)
 */
function validate_date_range($from_date, $to_date) {
    if (!validate_date_format($from_date)) {
        return ['valid' => false, 'error' => 'From date format is invalid'];
    }
    
    if (!validate_date_format($to_date)) {
        return ['valid' => false, 'error' => 'To date format is invalid'];
    }
    
    $from = new DateTime($from_date);
    $to = new DateTime($to_date);
    
    if ($from > $to) {
        return ['valid' => false, 'error' => 'From date cannot be later than To date'];
    }
    
    return ['valid' => true];
}

/**
 * Validate complete date range with all checks
 */
function validate_complete_date_range($from_date, $to_date, $max_days_old = 365) {
    // Validate from date
    $from_check = validate_not_future_date($from_date, 'From date');
    if (!$from_check['valid']) {
        return $from_check;
    }
    
    // Validate to date
    $to_check = validate_not_future_date($to_date, 'To date');
    if (!$to_check['valid']) {
        return $to_check;
    }
    
    // Validate range
    $range_check = validate_date_range($from_date, $to_date);
    if (!$range_check['valid']) {
        return $range_check;
    }
    
    // Optionally validate not too old
    if ($max_days_old > 0) {
        $old_check = validate_not_too_old($from_date, $max_days_old, 'From date');
        if (!$old_check['valid']) {
            return $old_check;
        }
    }
    
    return ['valid' => true];
}

/**
 * Validate sales/collection date (max 30 days old, not future)
 */
function validate_transaction_date($date, $field_name = 'Transaction date') {
    // Check format
    if (!validate_date_format($date)) {
        return ['valid' => false, 'error' => "$field_name format is invalid"];
    }
    
    // Check not future
    $future_check = validate_not_future_date($date, $field_name);
    if (!$future_check['valid']) {
        return $future_check;
    }
    
    // Check not too old (max 30 days for transactions)
    $old_check = validate_not_too_old($date, 30, $field_name);
    if (!$old_check['valid']) {
        return $old_check;
    }
    
    return ['valid' => true];
}

/**
 * Validate report date range (max 1 year)
 */
function validate_report_date_range($from_date, $to_date) {
    // Validate basic range
    $range_check = validate_complete_date_range($from_date, $to_date, 0); // No age limit
    if (!$range_check['valid']) {
        return $range_check;
    }
    
    // Check range is not more than 1 year
    $from = new DateTime($from_date);
    $to = new DateTime($to_date);
    $interval = $from->diff($to);
    $days_diff = $interval->days;
    
    if ($days_diff > 365) {
        return ['valid' => false, 'error' => 'Date range cannot exceed 365 days'];
    }
    
    return ['valid' => true];
}

/**
 * Get max date (today) for HTML input
 */
function get_max_date_today() {
    return date('Y-m-d');
}

/**
 * Get min date (X days ago) for HTML input
 */
function get_min_date($days_ago = 365) {
    return date('Y-m-d', strtotime("-$days_ago days"));
}
?>