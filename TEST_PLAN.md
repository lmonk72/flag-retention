# Test Plan - Flag Retention Module

## Overview

This document outlines the comprehensive test strategy for the Flag Retention module, following Drupal PSR-4 and PHPUnit best practices.

## Test Structure

```
tests/
├── src/
│   ├── Unit/               # Pure unit tests (no Drupal bootstrap)
│   │   ├── FlagRetentionManagerTest.php
│   │   ├── FlagClearerTest.php
│   │   └── Plugin/
│   │       └── Block/
│   │           └── FlagRetentionClearBlockTest.php
│   ├── Kernel/             # Kernel tests (minimal Drupal bootstrap)
│   │   ├── FlagRetentionDatabaseTest.php
│   │   ├── FlagRetentionConfigTest.php
│   │   └── FlagRetentionCronTest.php
│   └── Functional/         # Functional tests (full Drupal)
│       ├── FlagRetentionConfigFormTest.php
│       ├── FlagRetentionUserClearTest.php
│       ├── FlagRetentionAdminClearTest.php
│       ├── FlagRetentionPermissionsTest.php
│       └── FlagRetentionViewsIntegrationTest.php
```

## Test Categories

### 1. Unit Tests (No Drupal Bootstrap)

**Purpose**: Test individual classes in isolation using mocks.

#### FlagRetentionManagerTest
- ✓ Test getRetentionSettings() with existing and non-existing flags
- ✓ Test saveRetentionSettings() creates and updates records
- ✓ Test getExpiredFlags() returns correct flaggings
- ✓ Test getExpiredFlags() respects batch limit
- ✓ Test getAllFlagsWithSettings() combines data correctly
- ✓ Test processCronCleanup() calls clearer service
- ✓ Test processCronCleanup() respects batch size from config

#### FlagClearerTest
- ✓ Test clearUserFlags() filters by user_id
- ✓ Test clearUserFlags() filters by user_id and flag_id
- ✓ Test clearAllFlagsByType() deletes all flags of type
- ✓ Test clearOldFlags() filters by age correctly
- ✓ Test deleteFlaggingsByIds() calls entity storage correctly
- ✓ Test deleteFlaggingsByIds() handles exceptions
- ✓ Test getUserFlagCount() returns correct counts
- ✓ Test getUserFlagCount() respects allowed flags filter
- ✓ Test getAllowedFlags() returns correct flags based on config
- ✓ Test isFlagAllowed() checks config correctly
- ✓ Test getFlagStatistics() aggregates correctly

#### FlagRetentionClearBlockTest
- ✓ Test defaultConfiguration() returns expected values
- ✓ Test blockAccess() denies access when user clearing disabled
- ✓ Test blockAccess() denies access without permission
- ✓ Test blockAccess() allows access with permission and enabled
- ✓ Test build() returns no flags message when count is 0
- ✓ Test build() includes count when show_count is TRUE
- ✓ Test build() includes summary when show_summary is TRUE
- ✓ Test build() adds modal attributes when use_modal is TRUE

### 2. Kernel Tests (Minimal Bootstrap)

**Purpose**: Test database operations, config, and service integration.

#### FlagRetentionDatabaseTest
- ✓ Test flag_retention_settings table is created correctly
- ✓ Test retention settings can be saved and retrieved
- ✓ Test retention settings can be updated
- ✓ Test unique constraint on flag_id works
- ✓ Test indexes exist on auto_clear and retention_days
- ✓ Test created and changed timestamps are set correctly

#### FlagRetentionConfigTest
- ✓ Test default configuration is created on install
- ✓ Test configuration can be read and written
- ✓ Test configuration is deleted on uninstall
- ✓ Test invalid config values are rejected (when validation added)
- ✓ Test enabled_flags array is stored correctly
- ✓ Test flag_access_mode setting works correctly

#### FlagRetentionCronTest
- ✓ Test flag_retention_cron() calls manager service
- ✓ Test expired flags are identified correctly
- ✓ Test expired flags are deleted by cron
- ✓ Test cron respects batch_size configuration
- ✓ Test cron logging works when enabled
- ✓ Test cron with no expired flags doesn't error
- ✓ Test multiple flag types processed in single cron run

### 3. Functional Tests (Full Drupal)

**Purpose**: Test complete user workflows and integrations.

#### FlagRetentionConfigFormTest
- ✓ Test admin can access config form
- ✓ Test non-admin cannot access config form
- ✓ Test form displays current configuration
- ✓ Test form saves configuration correctly
- ✓ Test form validation works (min/max values)
- ✓ Test terminology settings are saved
- ✓ Test flag access control settings work
- ✓ Test cron batch size validation

#### FlagRetentionUserClearTest
- ✓ Test user can access their own clear flags page
- ✓ Test user cannot access other user's clear flags page
- ✓ Test user with 'clear all flags' can access any user's page
- ✓ Test access denied when user_clearing is disabled
- ✓ Test form displays user's flags correctly
- ✓ Test "clear all" checkbox works
- ✓ Test selective flag clearing works
- ✓ Test confirmation is required
- ✓ Test success message shown after clearing
- ✓ Test redirect after submission
- ✓ Test no flags message when user has no flags

#### FlagRetentionAdminClearTest
- ✓ Test admin can access admin clear form
- ✓ Test non-admin cannot access admin clear form
- ✓ Test form displays flag statistics correctly
- ✓ Test clearing all flags of type works
- ✓ Test confirmation is required
- ✓ Test success message shown
- ✓ Test redirect after submission

#### FlagRetentionPermissionsTest
- ✓ Test 'administer flag retention' permission controls access
- ✓ Test 'clear own flags' permission works
- ✓ Test 'clear all flags' permission works
- ✓ Test permission combinations work correctly
- ✓ Test anonymous users are blocked
- ✓ Test entity operations only show for users with permission

#### FlagRetentionViewsIntegrationTest
- ✓ Test views field plugin renders correctly
- ✓ Test views area plugin renders correctly
- ✓ Test views plugins respect permissions
- ✓ Test views plugins show correct flag counts
- ✓ Test views plugins work with empty results

## Test Data Strategy

### Fixtures
- Create test flags with different settings
- Create test users with different permissions
- Create test flaggings with various ages
- Use known timestamps for predictable date calculations

### Mocking Strategy
- Mock database connections in unit tests
- Mock entity storage in unit tests
- Mock config factory in unit tests
- Mock services that have external dependencies
- Use real services in kernel and functional tests

## PHPUnit Configuration

Create `phpunit.xml.dist`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.3/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         beStrictAboutTestsThatDoNotTestAnything="true"
         beStrictAboutOutputDuringTests="true"
         beStrictAboutChangesToGlobalState="true">
    <testsuites>
        <testsuite name="unit">
            <directory>./tests/src/Unit</directory>
        </testsuite>
        <testsuite name="kernel">
            <directory>./tests/src/Kernel</directory>
        </testsuite>
        <testsuite name="functional">
            <directory>./tests/src/Functional</directory>
        </testsuite>
    </testsuites>
    <coverage>
        <include>
            <directory suffix=".php">./src</directory>
        </include>
        <exclude>
            <directory>./tests</directory>
        </exclude>
    </coverage>
</phpunit>
```

## PSR-4 Autoloading

Ensure `composer.json` includes:

```json
{
  "autoload": {
    "psr-4": {
      "Drupal\\flag_retention\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "Drupal\\Tests\\flag_retention\\": "tests/src/"
    }
  }
}
```

## Coverage Goals

- **Unit Tests**: 80%+ coverage of service classes
- **Kernel Tests**: 100% coverage of database operations
- **Functional Tests**: All user-facing features covered

## Running Tests

```bash
# Run all tests
phpunit

# Run specific test suite
phpunit --testsuite unit
phpunit --testsuite kernel
phpunit --testsuite functional

# Run specific test class
phpunit tests/src/Unit/FlagRetentionManagerTest.php

# Run with coverage report
phpunit --coverage-html coverage/

# Run tests in Drupal context (from Drupal root)
vendor/bin/phpunit -c core modules/custom/flag_retention/tests/src/Unit
```

## Continuous Integration

Recommended CI checks:
1. PHPUnit tests (all suites)
2. PHP_CodeSniffer (Drupal coding standards)
3. PHPStan (static analysis)
4. Security audit (Drupal security advisories)

## Test Maintenance

- Update tests when adding new features
- Review test failures before merging code
- Keep test data fixtures up to date
- Document any test-specific configuration needed
- Review coverage reports regularly

## Dependencies for Testing

Required for testing:
- phpunit/phpunit: ^9.5
- drupal/core-dev: For Drupal test base classes
- mockery/mockery: For advanced mocking (optional)

## Notes

- Tests should be independent and can run in any order
- Use tearDown() to clean up test data
- Use dataProviders for testing multiple scenarios
- Add @group annotations for test categorization
- Add @covers annotations to document what's being tested
