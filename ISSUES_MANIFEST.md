# Flag Retention Module - Issues Manifest

This document provides a structured list of all identified issues from the audit, formatted for easy GitHub issue creation.

## Critical Security Issues

### Issue 1: SQL Injection Risk in getExpiredFlags()
**Title**: [SECURITY] SQL Injection vulnerability in FlagRetentionManager::getExpiredFlags()  
**Labels**: security, critical  
**File**: src/FlagRetentionManager.php, lines 144-154  
**Description**: The method uses flag_id values directly from the database without validation when querying the flagging table, potentially allowing SQL injection if flag_id data is compromised.  
**Fix**: Add validation to ensure flag_id matches known flag definitions before using in queries.

### Issue 2: Access Control Bypass via Type Juggling
**Title**: [SECURITY] Type juggling vulnerability in userClearAccess()  
**Labels**: security, high  
**File**: src/Controller/FlagRetentionController.php, line 43  
**Description**: Uses loose comparison (==) instead of strict comparison (===) for user ID checks, potentially allowing unauthorized access.  
**Fix**: Change `if ($user == $account->id())` to `if ($user === $account->id())`

### Issue 3: Missing CSRF Protection on AJAX Operations
**Title**: [SECURITY] Missing CSRF token validation for AJAX operations  
**Labels**: security, medium  
**Files**: src/Plugin/Block/FlagRetentionClearBlock.php, src/Plugin/views/field/FlagRetentionClearLink.php  
**Description**: AJAX modal operations don't explicitly validate CSRF tokens, potentially allowing cross-site request forgery.  
**Fix**: Ensure all AJAX operations use Drupal's built-in CSRF token validation.

### Issue 4: XSS Vulnerability in User Output
**Title**: [SECURITY] Cross-site scripting (XSS) risk in flag label output  
**Labels**: security, medium  
**Files**: Multiple form classes  
**Description**: Flag labels and user-controlled content rendered without proper sanitization.  
**Fix**: Use Html::escape() or #plain_text render element for all user content.

### Issue 5: Insufficient Input Validation
**Title**: [SECURITY] Missing input validation in configuration forms  
**Labels**: security, medium  
**File**: src/Form/FlagRetentionConfigForm.php  
**Description**: Form inputs lack comprehensive validation (custom terminology, retention_days, flag_id).  
**Fix**: Add maximum length validation, validate retention_days bounds, validate flag_id format.

## Critical Accessibility Issues

### Issue 6: Missing ARIA Labels
**Title**: [A11Y] Missing ARIA labels on interactive elements  
**Labels**: accessibility, wcag, critical  
**WCAG**: 4.1.2 Name, Role, Value (Level A)  
**Files**: src/Plugin/Block/FlagRetentionClearBlock.php, src/Plugin/views/field/FlagRetentionClearLink.php  
**Description**: Buttons and links lack descriptive ARIA labels for screen readers.  
**Fix**: Add aria-label attributes with context-specific descriptions.

### Issue 7: No Keyboard Navigation for Modals
**Title**: [A11Y] Modal dialogs lack keyboard navigation support  
**Labels**: accessibility, wcag, critical  
**WCAG**: 2.1.1 Keyboard (Level A), 2.1.2 No Keyboard Trap (Level A)  
**File**: js/flag_retention_modal.js  
**Description**: Modal dialogs don't implement focus trapping, Escape key handling, or proper keyboard navigation.  
**Fix**: Implement focus trap, Escape key handler, and return focus on close.

### Issue 8: Missing Focus Management
**Title**: [A11Y] No focus management in forms and modals  
**Labels**: accessibility, wcag, high  
**WCAG**: 2.4.3 Focus Order (Level A)  
**Files**: All form classes  
**Description**: Forms and modals don't set focus programmatically when loaded.  
**Fix**: Set focus to first form field when forms load and modals open.

### Issue 9: Insufficient Color Contrast
**Title**: [A11Y] Color contrast ratios fail WCAG AA standards  
**Labels**: accessibility, wcag, high  
**WCAG**: 1.4.3 Contrast (Minimum) (Level AA)  
**File**: css/flag_retention.css  
**Description**: Multiple color combinations don't meet 4.5:1 contrast ratio requirement.  
**Fix**: Test and adjust colors to meet WCAG AA standards.

### Issue 10: No Screen Reader Announcements
**Title**: [A11Y] Dynamic content changes not announced to screen readers  
**Labels**: accessibility, wcag, high  
**WCAG**: 4.1.3 Status Messages (Level AA)  
**Files**: js/flag_retention.js, js/flag_retention_modal.js  
**Description**: AJAX operations don't announce success/error messages to screen readers.  
**Fix**: Use Drupal.announce() for all dynamic content changes.

## Critical Functionality Issues

### Issue 11: Race Condition in Cron Cleanup
**Title**: [FUNC] Race condition in processCronCleanup() allows concurrent execution  
**Labels**: functionality, critical  
**File**: src/FlagRetentionManager.php, lines 170-187  
**Description**: Multiple concurrent cron runs can process the same expired flags, causing duplicate deletion attempts.  
**Fix**: Implement locking using Drupal's State API or database locking.

### Issue 12: Missing Validation in saveRetentionSettings()
**Title**: [FUNC] No validation that flag exists before saving retention settings  
**Labels**: functionality, high  
**File**: src/FlagRetentionManager.php, lines 87-122  
**Description**: Could create retention settings for non-existent flags, leading to orphaned data.  
**Fix**: Validate flag_id exists via flag service before saving.

### Issue 13: No Test Coverage
**Title**: [FUNC] Module completely lacks test coverage  
**Labels**: functionality, testing, critical  
**Description**: No unit, kernel, or functional tests exist for the module.  
**Fix**: Implement comprehensive test suite as outlined in TEST_PLAN.md.

## High Priority Issues

### Issue 14: Missing Type Hints on Route Parameters
**Title**: [FUNC] Route parameters lack proper type hints  
**Labels**: functionality, high  
**Files**: src/Form/UserFlagClearForm.php, src/Form/AdminFlagClearForm.php  
**Description**: Route parameters can be NULL causing type errors if parameter conversion fails.  
**Fix**: Use ParamConverter annotations or handle NULL more gracefully.

### Issue 15: Inconsistent Error Handling
**Title**: [FUNC] Inconsistent error handling across service classes  
**Labels**: functionality, high  
**Files**: Multiple service classes  
**Description**: Different methods use different error patterns (return 0, empty arrays, exceptions).  
**Fix**: Establish consistent error handling pattern module-wide.

### Issue 16: Deprecated jQuery Usage
**Title**: [FUNC] JavaScript uses deprecated jQuery patterns  
**Labels**: functionality, medium  
**File**: js/flag_retention.js  
**Description**: Uses jQuery which is being phased out in Drupal 10+.  
**Fix**: Migrate to vanilla JavaScript or modern Drupal JavaScript APIs.

### Issue 17: Missing Dependency Injection in Controller
**Title**: [FUNC] Controller uses static service calls instead of DI  
**Labels**: functionality, medium, best-practices  
**File**: src/Controller/FlagRetentionController.php  
**Description**: Uses \\Drupal::config() instead of dependency injection.  
**Fix**: Inject ConfigFactory via constructor.

### Issue 18: Insufficient Cache Invalidation
**Title**: [FUNC] Flag clearing doesn't invalidate relevant caches  
**Labels**: functionality, medium  
**Files**: Service classes  
**Description**: Flag counts may be stale in blocks and views after clearing.  
**Fix**: Invalidate cache tags after flag operations.

## Medium Priority Issues

### Issue 19: No Uninstall Hook for Custom Table
**Title**: [FUNC] Custom table not dropped on module uninstall  
**Labels**: functionality, medium  
**File**: flag_retention.install  
**Description**: flag_retention_settings table remains in database after uninstall.  
**Fix**: Add table drop in hook_uninstall().

### Issue 20: Missing Input Sanitization
**Title**: [FUNC] Custom terminology fields lack length and content validation  
**Labels**: functionality, medium  
**Files**: Form classes  
**Description**: Could store unexpected characters or very long strings.  
**Fix**: Add #maxlength and validation callbacks.

### Issue 21: No Logging for User-Initiated Clears
**Title**: [FUNC] User flag clearing not logged even when logging enabled  
**Labels**: functionality, medium  
**File**: src/Form/UserFlagClearForm.php  
**Description**: Only cron cleanup is logged; incomplete audit trail.  
**Fix**: Check logging config and log all clearing operations.

### Issue 22: Memory Issues with Large Datasets
**Title**: [FUNC] Loading all flagging entities at once risks memory exhaustion  
**Labels**: functionality, performance, medium  
**File**: src/FlagClearer.php, line 132  
**Description**: Loads all flagging entities into memory for deletion.  
**Fix**: Process in smaller batches using array_chunk().

### Issue 23: Missing Rate Limiting
**Title**: [FUNC] No rate limiting on flag clearing operations  
**Labels**: functionality, security, low  
**Files**: All forms  
**Description**: Users could spam clear flags operation.  
**Fix**: Implement rate limiting using Drupal's Flood API.

## Documentation Issues

### Issue 24: Incomplete PHPDoc Comments
**Title**: [FUNC] Missing or incomplete PHPDoc blocks  
**Labels**: documentation, low  
**Files**: Multiple  
**Description**: Some methods lack complete PHPDoc with @param and @return tags.  
**Fix**: Add complete PHPDoc to all public methods.

### Issue 25: Limited Help Text
**Title**: [FUNC] hook_help() provides only basic description  
**Labels**: documentation, low  
**File**: flag_retention.module  
**Description**: Users may not understand all features.  
**Fix**: Expand help text with examples and common use cases.

## Testing Issues

### Issue 26: Create Unit Tests
**Title**: [TESTING] Implement comprehensive unit tests  
**Labels**: testing, unit-tests  
**Status**: Partially complete  
**Description**: Complete unit test coverage for all service classes.  
**Reference**: TEST_PLAN.md

### Issue 27: Create Kernel Tests
**Title**: [TESTING] Implement kernel tests for database operations  
**Labels**: testing, kernel-tests  
**Status**: Partially complete  
**Description**: Complete kernel test coverage for database and config operations.  
**Reference**: TEST_PLAN.md

### Issue 28: Create Functional Tests
**Title**: [TESTING] Implement functional tests for user workflows  
**Labels**: testing, functional-tests  
**Status**: Partially complete  
**Description**: Complete functional test coverage for all user-facing features.  
**Reference**: TEST_PLAN.md

## Summary by Priority

**Critical**: 13 issues  
**High**: 5 issues  
**Medium**: 9 issues  
**Low**: 3 issues  
**Total**: 28 issues

## How to Create Issues

1. Copy the title and description from each issue above
2. Apply the specified labels
3. Reference the appropriate audit document
4. Assign to appropriate team member
5. Add to project board/milestone

## Recommended Issue Creation Order

1. Create all Critical issues first (1-13)
2. Create High priority issues (14-18)
3. Create Medium priority issues (19-23)
4. Create Documentation and Testing issues as needed (24-28)

This ensures the most important issues are tracked and addressed first.
