/**
 * TMS Form Validation Library
 * Provides client-side validation for Training Management System forms
 */

class TMSValidator {
    constructor(formSelector, options = {}) {
        this.form = document.querySelector(formSelector);
        this.options = {
            validateOnInput: true,
            validateOnSubmit: true,
            showErrors: true,
            errorClass: 'is-invalid',
            successClass: 'is-valid',
            errorMessageClass: 'invalid-feedback',
            ...options
        };
        
        this.rules = {};
        this.customMessages = {};
        this.errors = {};
        
        if (this.form) {
            this.init();
        }
    }
    
    init() {
        if (this.options.validateOnSubmit) {
            this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        }
        
        if (this.options.validateOnInput) {
            this.form.addEventListener('input', (e) => this.handleInput(e));
            this.form.addEventListener('change', (e) => this.handleInput(e));
        }
    }
    
    addRule(fieldName, rules, message = null) {
        this.rules[fieldName] = rules;
        if (message) {
            this.customMessages[fieldName] = message;
        }
        return this;
    }
    
    addRules(rulesObject) {
        Object.keys(rulesObject).forEach(fieldName => {
            const rule = rulesObject[fieldName];
            if (typeof rule === 'object' && rule.rules) {
                this.addRule(fieldName, rule.rules, rule.message);
            } else {
                this.addRule(fieldName, rule);
            }
        });
        return this;
    }
    
    handleSubmit(e) {
        const isValid = this.validateForm();
        if (!isValid) {
            e.preventDefault();
            this.showFormErrors();
            return false;
        }
        return true;
    }
    
    handleInput(e) {
        const fieldName = e.target.name;
        if (fieldName && this.rules[fieldName]) {
            this.validateField(fieldName);
        }
    }
    
    validateForm() {
        this.errors = {};
        let isValid = true;
        
        Object.keys(this.rules).forEach(fieldName => {
            const fieldValid = this.validateField(fieldName);
            if (!fieldValid) {
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    validateField(fieldName) {
        const field = this.form.querySelector(`[name="${fieldName}"]`);
        if (!field) return true;
        
        const rules = this.rules[fieldName];
        const value = this.getFieldValue(field);
        
        delete this.errors[fieldName];
        
        for (const rule of rules) {
            const result = this.applyRule(value, rule, field);
            if (!result.valid) {
                this.errors[fieldName] = result.message;
                this.showFieldError(field, result.message);
                return false;
            }
        }
        
        this.showFieldSuccess(field);
        return true;
    }
    
    getFieldValue(field) {
        if (field.type === 'checkbox' || field.type === 'radio') {
            if (field.type === 'radio') {
                const checked = this.form.querySelector(`[name="${field.name}"]:checked`);
                return checked ? checked.value : '';
            }
            return field.checked;
        }
        return field.value.trim();
    }
    
    applyRule(value, rule, field) {
        const [ruleName, ...params] = rule.split(':');
        
        switch (ruleName) {
            case 'required':
                return this.validateRequired(value, field);
            case 'email':
                return this.validateEmail(value);
            case 'min':
                return this.validateMin(value, parseInt(params[0]));
            case 'max':
                return this.validateMax(value, parseInt(params[0]));
            case 'minLength':
                return this.validateMinLength(value, parseInt(params[0]));
            case 'maxLength':
                return this.validateMaxLength(value, parseInt(params[0]));
            case 'numeric':
                return this.validateNumeric(value);
            case 'integer':
                return this.validateInteger(value);
            case 'alpha':
                return this.validateAlpha(value);
            case 'alphaNumeric':
                return this.validateAlphaNumeric(value);
            case 'url':
                return this.validateUrl(value);
            case 'date':
                return this.validateDate(value);
            case 'dateAfter':
                return this.validateDateAfter(value, params[0]);
            case 'dateBefore':
                return this.validateDateBefore(value, params[0]);
            case 'confirmed':
                return this.validateConfirmed(value, params[0]);
            case 'regex':
                return this.validateRegex(value, params[0]);
            case 'fileSize':
                return this.validateFileSize(field, parseInt(params[0]));
            case 'fileTypes':
                return this.validateFileTypes(field, params);
            default:
                return { valid: true };
        }
    }
    
    validateRequired(value, field) {
        if (field.type === 'checkbox') {
            return {
                valid: field.checked,
                message: 'This field is required.'
            };
        }
        
        if (field.type === 'radio') {
            const checked = this.form.querySelector(`[name="${field.name}"]:checked`);
            return {
                valid: checked !== null,
                message: 'Please select an option.'
            };
        }
        
        return {
            valid: value !== '',
            message: 'This field is required.'
        };
    }
    
    validateEmail(value) {
        if (value === '') return { valid: true };
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return {
            valid: emailRegex.test(value),
            message: 'Please enter a valid email address.'
        };
    }
    
    validateMin(value, min) {
        if (value === '') return { valid: true };
        const num = parseFloat(value);
        return {
            valid: !isNaN(num) && num >= min,
            message: `The value must be at least ${min}.`
        };
    }
    
    validateMax(value, max) {
        if (value === '') return { valid: true };
        const num = parseFloat(value);
        return {
            valid: !isNaN(num) && num <= max,
            message: `The value must be at most ${max}.`
        };
    }
    
    validateMinLength(value, minLength) {
        if (value === '') return { valid: true };
        return {
            valid: value.length >= minLength,
            message: `This field must be at least ${minLength} characters.`
        };
    }
    
    validateMaxLength(value, maxLength) {
        return {
            valid: value.length <= maxLength,
            message: `This field must not exceed ${maxLength} characters.`
        };
    }
    
    validateNumeric(value) {
        if (value === '') return { valid: true };
        return {
            valid: !isNaN(value) && isFinite(value),
            message: 'This field must be a number.'
        };
    }
    
    validateInteger(value) {
        if (value === '') return { valid: true };
        return {
            valid: Number.isInteger(Number(value)),
            message: 'This field must be an integer.'
        };
    }
    
    validateAlpha(value) {
        if (value === '') return { valid: true };
        const alphaRegex = /^[a-zA-Z\s]+$/;
        return {
            valid: alphaRegex.test(value),
            message: 'This field may only contain letters and spaces.'
        };
    }
    
    validateAlphaNumeric(value) {
        if (value === '') return { valid: true };
        const alphaNumericRegex = /^[a-zA-Z0-9\s]+$/;
        return {
            valid: alphaNumericRegex.test(value),
            message: 'This field may only contain letters, numbers, and spaces.'
        };
    }
    
    validateUrl(value) {
        if (value === '') return { valid: true };
        try {
            new URL(value);
            return { valid: true };
        } catch {
            return {
                valid: false,
                message: 'Please enter a valid URL.'
            };
        }
    }
    
    validateDate(value) {
        if (value === '') return { valid: true };
        const date = new Date(value);
        return {
            valid: !isNaN(date.getTime()),
            message: 'Please enter a valid date.'
        };
    }
    
    validateDateAfter(value, afterDate) {
        if (value === '') return { valid: true };
        const date = new Date(value);
        const after = afterDate === 'today' ? new Date() : new Date(afterDate);
        
        return {
            valid: date > after,
            message: `The date must be after ${afterDate === 'today' ? 'today' : afterDate}.`
        };
    }
    
    validateDateBefore(value, beforeDate) {
        if (value === '') return { valid: true };
        const date = new Date(value);
        const before = beforeDate === 'today' ? new Date() : new Date(beforeDate);
        
        return {
            valid: date < before,
            message: `The date must be before ${beforeDate === 'today' ? 'today' : beforeDate}.`
        };
    }
    
    validateConfirmed(value, confirmationFieldName) {
        const confirmationField = this.form.querySelector(`[name="${confirmationFieldName}"]`);
        if (!confirmationField) return { valid: true };
        
        return {
            valid: value === confirmationField.value,
            message: 'The confirmation does not match.'
        };
    }
    
    validateRegex(value, pattern) {
        if (value === '') return { valid: true };
        const regex = new RegExp(pattern);
        return {
            valid: regex.test(value),
            message: 'The field format is invalid.'
        };
    }
    
    validateFileSize(field, maxSizeKB) {
        if (!field.files || field.files.length === 0) return { valid: true };
        
        const file = field.files[0];
        const maxSizeBytes = maxSizeKB * 1024;
        
        return {
            valid: file.size <= maxSizeBytes,
            message: `File size must not exceed ${maxSizeKB}KB.`
        };
    }
    
    validateFileTypes(field, allowedTypes) {
        if (!field.files || field.files.length === 0) return { valid: true };
        
        const file = field.files[0];
        const fileName = file.name.toLowerCase();
        const fileExtension = fileName.split('.').pop();
        
        const isAllowed = allowedTypes.some(type => {
            if (type.startsWith('.')) {
                return fileName.endsWith(type.toLowerCase());
            }
            return file.type.includes(type.toLowerCase());
        });
        
        return {
            valid: isAllowed,
            message: `Allowed file types: ${allowedTypes.join(', ')}`
        };
    }
    
    showFieldError(field, message) {
        if (!this.options.showErrors) return;
        
        field.classList.remove(this.options.successClass);
        field.classList.add(this.options.errorClass);
        
        this.removeExistingFeedback(field);
        
        const errorDiv = document.createElement('div');
        errorDiv.className = this.options.errorMessageClass;
        errorDiv.textContent = message;
        
        field.parentNode.appendChild(errorDiv);
    }
    
    showFieldSuccess(field) {
        if (!this.options.showErrors) return;
        
        field.classList.remove(this.options.errorClass);
        field.classList.add(this.options.successClass);
        
        this.removeExistingFeedback(field);
    }
    
    removeExistingFeedback(field) {
        const existingFeedback = field.parentNode.querySelector('.' + this.options.errorMessageClass);
        if (existingFeedback) {
            existingFeedback.remove();
        }
    }
    
    showFormErrors() {
        if (Object.keys(this.errors).length > 0) {
            const firstErrorField = Object.keys(this.errors)[0];
            const field = this.form.querySelector(`[name="${firstErrorField}"]`);
            if (field) {
                field.focus();
                field.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    }
    
    clearErrors() {
        this.errors = {};
        const errorElements = this.form.querySelectorAll('.' + this.options.errorClass);
        const feedbackElements = this.form.querySelectorAll('.' + this.options.errorMessageClass);
        
        errorElements.forEach(el => {
            el.classList.remove(this.options.errorClass);
            el.classList.remove(this.options.successClass);
        });
        
        feedbackElements.forEach(el => el.remove());
    }
    
    getErrors() {
        return this.errors;
    }
    
    isValid() {
        return Object.keys(this.errors).length === 0;
    }
}

// Utility functions for common validation patterns
const ValidationPatterns = {
    phone: /^[\+]?[1-9][\d]{0,15}$/,
    strongPassword: /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/,
    username: /^[a-zA-Z0-9_]{3,20}$/,
    slug: /^[a-z0-9-]+$/
};

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { TMSValidator, ValidationPatterns };
}