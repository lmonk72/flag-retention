# Security Audit Report - Flag Retention Module

## Critical Security Issues

### 1. SQL Injection Risk in FlagRetentionManager::getExpiredFlags()
**Severity**: High  
**File**: `src/FlagRetentionManager.php`, lines 144-154  
**Issue**: The method constructs dynamic SQL queries using flag_id values directly from the database without proper sanitization when querying the flagging table.

**Code Location**:
```php
foreach ($flags_with_settings as $flag_id => $retention_days) {
    $cutoff_time = $current_time - ($retention_days * 24 * 60 * 60);
    
    $flaggings = $this->database->select('flagging', 'f')
      ->fields('f', ['id'])
      ->condition('flag_id', $flag_id)  // flag_id from untrusted source
      ->condition('created', $cutoff_time, '<')
      ->range(0, $limit)
      ->execute()
      ->fetchCol();
}
```

**Risk**: If flag_id contains malicious SQL, it could lead to SQL injection.

**Recommendation**: 
- Validate flag_id against known flag definitions
- Use parameterized queries (already implemented by Drupal's Database API, but add validation)
- Add type checking to ensure flag_id is a safe string

---

### 2. Access Control Bypass in FlagRetentionController::userClearAccess()
**Severity**: Medium  
**File**: `src/Controller/FlagRetentionController.php`, line 43  
**Issue**: Loose comparison operator (==) used for user ID comparison allows type juggling attacks.

**Code Location**:
```php
if ($user == $account->id() || $account->hasPermission('clear all flags')) {
    return AccessResult::allowed();
}
```

**Risk**: String "0" could match integer 0, potentially allowing unauthorized access.

**Recommendation**: Use strict comparison (===) for security-critical comparisons.

---

### 3. Missing CSRF Protection on AJAX Operations
**Severity**: Medium  
**File**: `src/Plugin/Block/FlagRetentionClearBlock.php`, `src/Plugin/views/field/FlagRetentionClearLink.php`  
**Issue**: AJAX modal operations don't explicitly validate CSRF tokens.

**Risk**: Cross-site request forgery attacks could trick users into clearing flags.

**Recommendation**: 
- Ensure all AJAX operations use Drupal's built-in CSRF token validation
- Add explicit token validation in form submissions

---

### 4. XSS Vulnerability in User Output
**Severity**: Medium  
**File**: Multiple form classes  
**Issue**: User-controlled content (flag labels, counts) rendered without proper sanitization in several places.

**Code Examples**:
- `src/Form/UserFlagClearForm.php`, line 105: `sprintf('%s (%d flags)', $flag->label(), $count)`
- `src/Plugin/Block/FlagRetentionClearBlock.php`, line 163: Flag labels concatenated directly

**Risk**: If flag labels contain malicious HTML/JavaScript, XSS attacks are possible.

**Recommendation**: 
- Always use `\Drupal\Component\Utility\Html::escape()` or `#plain_text` render element
- Use `@placeholder` in t() functions for variable content
- Never concatenate user content directly into markup

---

### 5. Insufficient Input Validation
**Severity**: Medium  
**File**: `src/Form/FlagRetentionConfigForm.php`, `src/FlagRetentionManager.php`  
**Issue**: Form inputs lack comprehensive validation.

**Examples**:
- Custom terminology fields accept any string without length limits
- retention_days not validated for reasonable maximum values
- No validation of flag_id format

**Risk**: Malformed input could break functionality or cause database issues.

**Recommendation**: 
- Add maximum length validation for text fields
- Validate retention_days within reasonable bounds (e.g., 1-3650 days)
- Validate flag_id format matches Drupal machine name requirements

---

### 6. Information Disclosure
**Severity**: Low  
**File**: `src/FlagClearer.php`, line 143  
**Issue**: Exception messages logged may contain sensitive information.

**Code Location**:
```php
$this->loggerFactory->get('flag_retention')->error(
    'Error deleting flaggings: @message',
    ['@message' => $e->getMessage()]
);
```

**Risk**: Detailed error messages could reveal database structure or internal paths.

**Recommendation**: 
- Log full details only for admin users
- Show generic error messages to regular users
- Consider using watchdog_exception() for better exception handling

---

## Moderate Security Concerns

### 7. Missing Rate Limiting
**Severity**: Low  
**File**: All forms  
**Issue**: No rate limiting on flag clearing operations.

**Risk**: Users could spam the clear flags operation, causing performance issues.

**Recommendation**: Implement rate limiting using Drupal's Flood API.

---

### 8. Lack of Audit Trail for Flag Deletions
**Severity**: Low  
**File**: `src/FlagClearer.php`  
**Issue**: While logging can be enabled, there's no permanent audit trail of who deleted what and when.

**Risk**: Compliance and accountability issues in regulated environments.

**Recommendation**: 
- Store deletion audit logs in a separate table
- Include user ID, timestamp, flag type, and count in audit records
- Add admin interface to view audit logs

---

### 9. Service Injection via Static Calls
**Severity**: Low  
**File**: Multiple files  
**Issue**: Use of `\Drupal::service()`, `\Drupal::config()`, etc. instead of dependency injection.

**Examples**:
- `src/FlagRetentionManager.php`, line 177: `$clearer = \Drupal::service('flag_retention.clearer');`
- `src/FlagClearer.php`, line 132: `$storage = \Drupal::entityTypeManager()->getStorage('flagging');`

**Risk**: Makes code harder to test and violates Drupal coding standards.

**Recommendation**: Use constructor dependency injection for all services.

---

## Best Practice Improvements

### 10. Configuration Schema Missing
**Issue**: No configuration schema defined for flag_retention.settings config.

**Recommendation**: Create `config/schema/flag_retention.schema.yml` to define config structure.

---

### 11. Missing Cache Tags
**Issue**: Some operations don't properly invalidate caches when flags are cleared.

**Recommendation**: Invalidate relevant cache tags after flag operations.

---

### 12. Insufficient Permission Granularity
**Issue**: Only three permissions defined; no per-flag-type permissions.

**Recommendation**: Consider adding per-flag-type clearing permissions for better access control.

---

## Summary

**Critical Issues**: 1  
**High Severity**: 1  
**Medium Severity**: 4  
**Low Severity**: 4  

**Immediate Action Required**: 
1. Fix SQL injection risk
2. Fix access control comparison operator
3. Add comprehensive input validation
4. Sanitize all user-facing output
