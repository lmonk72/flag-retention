# Flag Retention Module - Quick Fix Checklist

This is a quick reference guide for developers to track fixes for the most critical issues.

## ⚠️ Critical Security Fixes (Must Do First)

- [ ] **SQL Injection Risk** - `src/FlagRetentionManager.php:144`
  - Add flag_id validation before database queries
  - Verify flag exists via FlagService
  - Add type checking for safety

- [ ] **Access Control Bypass** - `src/Controller/FlagRetentionController.php:43`
  - Change `==` to `===` in user ID comparison
  - One line fix: `if ($user === $account->id())`

- [ ] **XSS Protection** - Multiple files
  - Use `Html::escape()` for flag labels in forms
  - Use `#plain_text` in render arrays
  - Never concatenate user content into markup

- [ ] **Input Validation** - `src/Form/FlagRetentionConfigForm.php`
  - Add `#maxlength` to text fields (100 chars)
  - Validate `retention_days` range (0-3650)
  - Validate `flag_id` format matches Drupal machine names

- [ ] **CSRF Tokens** - AJAX operations
  - Verify Drupal's AJAX system includes tokens
  - Add explicit validation if needed

## 🎯 Critical Accessibility Fixes (Must Do Second)

- [ ] **ARIA Labels** - `src/Plugin/Block/FlagRetentionClearBlock.php:217`
  ```php
  '#attributes' => [
    'aria-label' => $this->t('Clear @count @items', [
      '@count' => $total_flags,
      '@items' => $item_term_plural,
    ]),
  ],
  ```

- [ ] **Modal Keyboard Navigation** - `js/flag_retention_modal.js`
  - Add focus trap
  - Add Escape key handler
  - Return focus to trigger on close

- [ ] **Focus Management** - All forms
  - Set focus to first field on form load
  - Move focus to modal on open

- [ ] **Color Contrast** - `css/flag_retention.css:9`
  ```css
  /* Change from #e74c3c to darker shade */
  .flag-retention-clear-link {
    background-color: #c0392b; /* Better contrast */
  }
  ```

- [ ] **Screen Reader Announcements** - JavaScript files
  ```javascript
  Drupal.announce(Drupal.t('Cleared @count flags', {'@count': count}));
  ```

## 🔧 Critical Functionality Fixes (Must Do Third)

- [ ] **Cron Race Condition** - `src/FlagRetentionManager.php:170`
  ```php
  public function processCronCleanup() {
    $lock_name = 'flag_retention_cron';
    if (!\Drupal::lock()->acquire($lock_name, 300)) {
      return; // Another process is running
    }
    try {
      // ... existing cleanup code ...
    }
    finally {
      \Drupal::lock()->release($lock_name);
    }
  }
  ```

- [ ] **Flag Validation** - `src/FlagRetentionManager.php:87`
  ```php
  public function saveRetentionSettings($flag_id, $retention_days, $auto_clear = 0) {
    // Validate flag exists
    $flag = $this->flagService->getFlagById($flag_id);
    if (!$flag) {
      throw new \InvalidArgumentException("Flag $flag_id does not exist");
    }
    
    // Validate retention_days
    if ($retention_days < 0) {
      throw new \InvalidArgumentException("Retention days must be non-negative");
    }
    
    // ... rest of method ...
  }
  ```

- [ ] **Type Hints** - Form classes
  - Add proper NULL handling in buildForm()
  - Document expected parameter types

- [ ] **Error Handling** - Service classes
  - Standardize on returning arrays vs. exceptions
  - Document error behavior in PHPDoc

## 📝 Quick Wins (Easy Fixes)

- [ ] **Config Schema** - ✅ Already created
  - File: `config/schema/flag_retention.schema.yml`

- [ ] **Uninstall Hook** - `flag_retention.install`
  ```php
  function flag_retention_uninstall() {
    \Drupal::configFactory()->getEditable('flag_retention.settings')->delete();
    \Drupal::database()->schema()->dropTable('flag_retention_settings');
  }
  ```

- [ ] **Focus Styles** - `css/flag_retention.css`
  ```css
  .flag-retention-clear-link:focus,
  .flag-retention-clear-button:focus {
    outline: 3px solid #0066cc;
    outline-offset: 2px;
  }
  ```

- [ ] **Reduced Motion** - `css/flag_retention.css`
  ```css
  @media (prefers-reduced-motion: reduce) {
    .flag-retention-clear-link {
      transition: none;
    }
  }
  ```

## 🧪 Testing Checklist

- [x] **Test Infrastructure Created**
  - PHPUnit config: `phpunit.xml.dist`
  - Bootstrap: `tests/bootstrap.php`
  - Directory structure: `tests/src/{Unit,Kernel,Functional}`

- [x] **Basic Tests Created**
  - Unit: FlagRetentionManagerTest, FlagClearerTest
  - Kernel: FlagRetentionDatabaseTest
  - Functional: ConfigFormTest, PermissionsTest

- [ ] **Run Existing Tests**
  ```bash
  cd /path/to/drupal
  vendor/bin/phpunit modules/custom/flag_retention
  ```

- [ ] **Create Additional Tests**
  - Complete unit test coverage
  - Add kernel tests for cron
  - Add functional tests for user workflows

## 📊 Progress Tracking

### Security: 0/5 Fixed
- [ ] SQL Injection
- [ ] Access Control
- [ ] XSS Protection
- [ ] Input Validation
- [ ] CSRF Tokens

### Accessibility: 0/5 Fixed
- [ ] ARIA Labels
- [ ] Keyboard Navigation
- [ ] Focus Management
- [ ] Color Contrast
- [ ] Screen Reader Announcements

### Functionality: 0/4 Fixed
- [ ] Cron Race Condition
- [ ] Flag Validation
- [ ] Type Hints
- [ ] Error Handling

### Quick Wins: 1/4 Fixed
- [x] Config Schema
- [ ] Uninstall Hook
- [ ] Focus Styles
- [ ] Reduced Motion

## 🎯 Daily Goals

### Day 1: Security
- Fix SQL injection
- Fix access control
- Add input validation

### Day 2: Accessibility
- Add ARIA labels
- Fix color contrast
- Add focus styles

### Day 3: Functionality
- Add cron locking
- Add flag validation
- Fix error handling

### Day 4: Testing
- Run existing tests
- Fix any test failures
- Add missing tests

### Day 5: Review & Polish
- Code review
- Documentation
- Final testing

## ✅ Definition of Done

Each fix should include:
1. ✅ Code changes implemented
2. ✅ Tests added/updated
3. ✅ Documentation updated
4. ✅ Code review passed
5. ✅ All tests passing
6. ✅ Issue marked as resolved

## 🚀 Quick Commands

```bash
# Run all tests
vendor/bin/phpunit modules/custom/flag_retention

# Run specific test suite
vendor/bin/phpunit --testsuite unit modules/custom/flag_retention

# Check coding standards
phpcs --standard=Drupal modules/custom/flag_retention

# Fix coding standards automatically
phpcbf --standard=Drupal modules/custom/flag_retention

# Run security check
drush pm:security

# Clear caches
drush cr
```

## 📚 References

- [SECURITY_AUDIT.md](SECURITY_AUDIT.md) - Detailed security findings
- [ACCESSIBILITY_AUDIT.md](ACCESSIBILITY_AUDIT.md) - WCAG compliance issues
- [FUNCTIONALITY_AUDIT.md](FUNCTIONALITY_AUDIT.md) - Code quality issues
- [TEST_PLAN.md](TEST_PLAN.md) - Testing strategy
- [AUDIT_SUMMARY.md](AUDIT_SUMMARY.md) - Overview and roadmap
- [ISSUES_MANIFEST.md](ISSUES_MANIFEST.md) - Complete issue list

---

**Last Updated**: December 24, 2025  
**Completion**: 1/17 items (5.9%)
