<?php
/**
 * TMS Server-side Validation Library
 * Provides server-side validation for Training Management System forms
 */

class TMSValidator
{
    private $errors = array();
    private $data = array();
    private $rules = array();
    private $messages = array();
    
    public function __construct($data = array())
    {
        $this->data = $data;
    }
    
    /**
     * Set validation rules
     */
    public function rules($rules)
    {
        $this->rules = $rules;
        return $this;
    }
    
    /**
     * Set custom error messages
     */
    public function messages($messages)
    {
        $this->messages = $messages;
        return $this;
    }
    
    /**
     * Validate data against rules
     */
    public function validate()
    {
        $this->errors = array();
        
        foreach ($this->rules as $field => $fieldRules) {
            $value = isset($this->data[$field]) ? $this->data[$field] : '';
            $this->validateField($field, $value, $fieldRules);
        }
        
        return empty($this->errors);
    }
    
    /**
     * Validate individual field
     */
    private function validateField($field, $value, $rules)
    {
        $rulesArray = is_string($rules) ? explode('|', $rules) : $rules;
        
        foreach ($rulesArray as $rule) {
            if (strpos($rule, ':') !== false) {
                list($ruleName, $parameter) = explode(':', $rule, 2);
            } else {
                $ruleName = $rule;
                $parameter = null;
            }
            
            $result = $this->applyRule($field, $value, $ruleName, $parameter);
            
            if (!$result) {
                break; // Stop validating this field on first error
            }
        }
    }
    
    /**
     * Apply validation rule
     */
    private function applyRule($field, $value, $ruleName, $parameter = null)
    {
        switch ($ruleName) {
            case 'required':
                return $this->validateRequired($field, $value);
            case 'email':
                return $this->validateEmail($field, $value);
            case 'numeric':
                return $this->validateNumeric($field, $value);
            case 'integer':
                return $this->validateInteger($field, $value);
            case 'min':
                return $this->validateMin($field, $value, $parameter);
            case 'max':
                return $this->validateMax($field, $value, $parameter);
            case 'min_length':
                return $this->validateMinLength($field, $value, $parameter);
            case 'max_length':
                return $this->validateMaxLength($field, $value, $parameter);
            case 'alpha':
                return $this->validateAlpha($field, $value);
            case 'alpha_numeric':
                return $this->validateAlphaNumeric($field, $value);
            case 'url':
                return $this->validateUrl($field, $value);
            case 'date':
                return $this->validateDate($field, $value);
            case 'date_after':
                return $this->validateDateAfter($field, $value, $parameter);
            case 'date_before':
                return $this->validateDateBefore($field, $value, $parameter);
            case 'confirmed':
                return $this->validateConfirmed($field, $value, $parameter);
            case 'regex':
                return $this->validateRegex($field, $value, $parameter);
            case 'in':
                return $this->validateIn($field, $value, $parameter);
            case 'not_in':
                return $this->validateNotIn($field, $value, $parameter);
            case 'unique':
                return $this->validateUnique($field, $value, $parameter);
            case 'exists':
                return $this->validateExists($field, $value, $parameter);
            case 'file':
                return $this->validateFile($field, $value);
            case 'image':
                return $this->validateImage($field, $value);
            case 'file_size':
                return $this->validateFileSize($field, $value, $parameter);
            case 'file_types':
                return $this->validateFileTypes($field, $value, $parameter);
            default:
                return true;
        }
    }
    
    /**
     * Validation methods
     */
    private function validateRequired($field, $value)
    {
        if (is_array($value)) {
            $isEmpty = empty($value);
        } else {
            $isEmpty = trim($value) === '';
        }
        
        if ($isEmpty) {
            $this->addError($field, 'required');
            return false;
        }
        
        return true;
    }
    
    private function validateEmail($field, $value)
    {
        if (empty($value)) return true;
        
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'email');
            return false;
        }
        
        return true;
    }
    
    private function validateNumeric($field, $value)
    {
        if (empty($value)) return true;
        
        if (!is_numeric($value)) {
            $this->addError($field, 'numeric');
            return false;
        }
        
        return true;
    }
    
    private function validateInteger($field, $value)
    {
        if (empty($value)) return true;
        
        if (!ctype_digit(strval($value))) {
            $this->addError($field, 'integer');
            return false;
        }
        
        return true;
    }
    
    private function validateMin($field, $value, $min)
    {
        if (empty($value)) return true;
        
        if (!is_numeric($value) || $value < $min) {
            $this->addError($field, 'min', array('min' => $min));
            return false;
        }
        
        return true;
    }
    
    private function validateMax($field, $value, $max)
    {
        if (empty($value)) return true;
        
        if (!is_numeric($value) || $value > $max) {
            $this->addError($field, 'max', array('max' => $max));
            return false;
        }
        
        return true;
    }
    
    private function validateMinLength($field, $value, $minLength)
    {
        if (empty($value)) return true;
        
        if (strlen($value) < $minLength) {
            $this->addError($field, 'min_length', array('min_length' => $minLength));
            return false;
        }
        
        return true;
    }
    
    private function validateMaxLength($field, $value, $maxLength)
    {
        if (strlen($value) > $maxLength) {
            $this->addError($field, 'max_length', array('max_length' => $maxLength));
            return false;
        }
        
        return true;
    }
    
    private function validateAlpha($field, $value)
    {
        if (empty($value)) return true;
        
        if (!preg_match('/^[a-zA-Z\s]+$/', $value)) {
            $this->addError($field, 'alpha');
            return false;
        }
        
        return true;
    }
    
    private function validateAlphaNumeric($field, $value)
    {
        if (empty($value)) return true;
        
        if (!preg_match('/^[a-zA-Z0-9\s]+$/', $value)) {
            $this->addError($field, 'alpha_numeric');
            return false;
        }
        
        return true;
    }
    
    private function validateUrl($field, $value)
    {
        if (empty($value)) return true;
        
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, 'url');
            return false;
        }
        
        return true;
    }
    
    private function validateDate($field, $value)
    {
        if (empty($value)) return true;
        
        $date = DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            $this->addError($field, 'date');
            return false;
        }
        
        return true;
    }
    
    private function validateDateAfter($field, $value, $afterDate)
    {
        if (empty($value)) return true;
        
        $date = new DateTime($value);
        $after = $afterDate === 'today' ? new DateTime() : new DateTime($afterDate);
        
        if ($date <= $after) {
            $this->addError($field, 'date_after', array('date' => $afterDate));
            return false;
        }
        
        return true;
    }
    
    private function validateDateBefore($field, $value, $beforeDate)
    {
        if (empty($value)) return true;
        
        $date = new DateTime($value);
        $before = $beforeDate === 'today' ? new DateTime() : new DateTime($beforeDate);
        
        if ($date >= $before) {
            $this->addError($field, 'date_before', array('date' => $beforeDate));
            return false;
        }
        
        return true;
    }
    
    private function validateConfirmed($field, $value, $confirmationField)
    {
        $confirmationValue = isset($this->data[$confirmationField]) ? $this->data[$confirmationField] : '';
        
        if ($value !== $confirmationValue) {
            $this->addError($field, 'confirmed');
            return false;
        }
        
        return true;
    }
    
    private function validateRegex($field, $value, $pattern)
    {
        if (empty($value)) return true;
        
        if (!preg_match($pattern, $value)) {
            $this->addError($field, 'regex');
            return false;
        }
        
        return true;
    }
    
    private function validateIn($field, $value, $options)
    {
        if (empty($value)) return true;
        
        $optionsArray = explode(',', $options);
        
        if (!in_array($value, $optionsArray)) {
            $this->addError($field, 'in', array('values' => $options));
            return false;
        }
        
        return true;
    }
    
    private function validateNotIn($field, $value, $options)
    {
        if (empty($value)) return true;
        
        $optionsArray = explode(',', $options);
        
        if (in_array($value, $optionsArray)) {
            $this->addError($field, 'not_in', array('values' => $options));
            return false;
        }
        
        return true;
    }
    
    private function validateUnique($field, $value, $tableColumn)
    {
        if (empty($value)) return true;
        
        global $conn;
        if (!$conn) {
            $conn = connectDB();
        }
        
        list($table, $column) = explode('.', $tableColumn);
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?");
        $stmt->bind_param("s", $value);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if ($row['count'] > 0) {
            $this->addError($field, 'unique');
            return false;
        }
        
        return true;
    }
    
    private function validateExists($field, $value, $tableColumn)
    {
        if (empty($value)) return true;
        
        global $conn;
        if (!$conn) {
            $conn = connectDB();
        }
        
        list($table, $column) = explode('.', $tableColumn);
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?");
        $stmt->bind_param("s", $value);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if ($row['count'] === 0) {
            $this->addError($field, 'exists');
            return false;
        }
        
        return true;
    }
    
    private function validateFile($field, $value)
    {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            return true;
        }
        
        if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            $this->addError($field, 'file');
            return false;
        }
        
        return true;
    }
    
    private function validateImage($field, $value)
    {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            return true;
        }
        
        if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            $this->addError($field, 'image');
            return false;
        }
        
        $allowedTypes = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        
        if (!in_array($_FILES[$field]['type'], $allowedTypes)) {
            $this->addError($field, 'image');
            return false;
        }
        
        return true;
    }
    
    private function validateFileSize($field, $value, $maxSizeKB)
    {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            return true;
        }
        
        $maxSizeBytes = $maxSizeKB * 1024;
        
        if ($_FILES[$field]['size'] > $maxSizeBytes) {
            $this->addError($field, 'file_size', array('max_size' => $maxSizeKB));
            return false;
        }
        
        return true;
    }
    
    private function validateFileTypes($field, $value, $allowedTypes)
    {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            return true;
        }
        
        $allowedTypesArray = explode(',', $allowedTypes);
        $fileName = $_FILES[$field]['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $isAllowed = false;
        foreach ($allowedTypesArray as $type) {
            $type = trim($type);
            if (strpos($type, '.') === 0) {
                // Extension check
                if ('.' . $fileExtension === $type) {
                    $isAllowed = true;
                    break;
                }
            } else {
                // MIME type check
                if (strpos($_FILES[$field]['type'], $type) !== false) {
                    $isAllowed = true;
                    break;
                }
            }
        }
        
        if (!$isAllowed) {
            $this->addError($field, 'file_types', array('types' => $allowedTypes));
            return false;
        }
        
        return true;
    }
    
    /**
     * Add error message
     */
    private function addError($field, $rule, $parameters = array())
    {
        $message = $this->getErrorMessage($field, $rule, $parameters);
        $this->errors[$field] = $message;
    }
    
    /**
     * Get error message for rule
     */
    private function getErrorMessage($field, $rule, $parameters = array())
    {
        // Check for custom message
        $messageKey = $field . '.' . $rule;
        if (isset($this->messages[$messageKey])) {
            return $this->messages[$messageKey];
        }
        
        if (isset($this->messages[$rule])) {
            return $this->messages[$rule];
        }
        
        // Default messages
        $messages = array(
            'required' => 'The :field field is required.',
            'email' => 'The :field field must be a valid email address.',
            'numeric' => 'The :field field must be numeric.',
            'integer' => 'The :field field must be an integer.',
            'min' => 'The :field field must be at least :min.',
            'max' => 'The :field field must not be greater than :max.',
            'min_length' => 'The :field field must be at least :min_length characters.',
            'max_length' => 'The :field field must not exceed :max_length characters.',
            'alpha' => 'The :field field may only contain letters and spaces.',
            'alpha_numeric' => 'The :field field may only contain letters, numbers, and spaces.',
            'url' => 'The :field field must be a valid URL.',
            'date' => 'The :field field must be a valid date.',
            'date_after' => 'The :field field must be after :date.',
            'date_before' => 'The :field field must be before :date.',
            'confirmed' => 'The :field confirmation does not match.',
            'regex' => 'The :field field format is invalid.',
            'in' => 'The selected :field is invalid.',
            'not_in' => 'The selected :field is invalid.',
            'unique' => 'The :field has already been taken.',
            'exists' => 'The selected :field is invalid.',
            'file' => 'The :field must be a file.',
            'image' => 'The :field must be an image.',
            'file_size' => 'The :field may not be greater than :max_size KB.',
            'file_types' => 'The :field must be a file of type: :types.'
        );
        
        $message = isset($messages[$rule]) ? $messages[$rule] : 'The :field field is invalid.';
        
        // Replace placeholders
        $message = str_replace(':field', ucwords(str_replace('_', ' ', $field)), $message);
        
        foreach ($parameters as $key => $value) {
            $message = str_replace(':' . $key, $value, $message);
        }
        
        return $message;
    }
    
    /**
     * Get all errors
     */
    public function errors()
    {
        return $this->errors;
    }
    
    /**
     * Get first error for field
     */
    public function firstError($field)
    {
        return isset($this->errors[$field]) ? $this->errors[$field] : null;
    }
    
    /**
     * Check if validation has errors
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }
    
    /**
     * Get errors as JSON
     */
    public function errorsAsJson()
    {
        return json_encode($this->errors);
    }
    
    /**
     * Static validation method
     */
    public static function make($data, $rules, $messages = array())
    {
        $validator = new self($data);
        return $validator->rules($rules)->messages($messages);
    }
}

/**
 * Helper function for quick validation
 */
function validate($data, $rules, $messages = array())
{
    return TMSValidator::make($data, $rules, $messages);
}

/**
 * Sanitization functions
 */
function sanitizeString($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function sanitizeEmail($input)
{
    return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
}

function sanitizeInt($input)
{
    return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
}

function sanitizeFloat($input)
{
    return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}

function sanitizeUrl($input)
{
    return filter_var(trim($input), FILTER_SANITIZE_URL);
}
?>