<?php
/**
 * Request Validation Library
 */

class Validator {
    public static function validateStudent(array $input): array {
        $errors = [];
        if (empty(trim($input['full_name'] ?? ''))) {
            $errors[] = "Full Name is required.";
        }
        if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }
        if (!empty($input['contact_number']) && !preg_match('/^[0-9+\- ]{7,20}$/', $input['contact_number'])) {
            $errors[] = "Invalid contact number format.";
        }
        return $errors;
    }

    public static function validateFee(array $input): array {
        $errors = [];
        if (empty($input['enrollment_id']) || !is_numeric($input['enrollment_id'])) {
            $errors[] = "Valid enrollment_id is required.";
        }
        if (!isset($input['amount_paid']) || !is_numeric($input['amount_paid']) || $input['amount_paid'] < 0) {
            $errors[] = "Valid positive amount_paid is required.";
        }
        return $errors;
    }
}
