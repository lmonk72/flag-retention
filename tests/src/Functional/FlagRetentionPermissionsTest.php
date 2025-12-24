<?php

namespace Drupal\Tests\flag_retention\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests permissions for flag retention module.
 *
 * @group flag_retention
 */
class FlagRetentionPermissionsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['flag_retention', 'flag', 'user', 'system'];

  /**
   * Tests 'administer flag retention' permission controls access.
   */
  public function testAdministerFlagRetentionPermission() {
    // User with permission
    $admin_user = $this->drupalCreateUser([
      'administer flag retention',
      'access administration pages',
    ]);

    // User without permission
    $regular_user = $this->drupalCreateUser([
      'access content',
    ]);

    // Test admin can access config pages
    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/config/system/flag-retention');
    $this->assertSession()->statusCodeEquals(200);
    
    $this->drupalGet('admin/structure/flags/retention');
    $this->assertSession()->statusCodeEquals(200);

    // Test regular user cannot access
    $this->drupalLogin($regular_user);
    $this->drupalGet('admin/config/system/flag-retention');
    $this->assertSession()->statusCodeEquals(403);
    
    $this->drupalGet('admin/structure/flags/retention');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests 'clear own flags' permission works.
   */
  public function testClearOwnFlagsPermission() {
    // Enable user clearing
    $config = $this->config('flag_retention.settings');
    $config->set('enable_user_clearing', TRUE);
    $config->save();

    // User with permission
    $user_with_permission = $this->drupalCreateUser([
      'clear own flags',
    ]);

    // User without permission
    $user_without_permission = $this->drupalCreateUser([]);

    // Test user with permission can access their clear page
    $this->drupalLogin($user_with_permission);
    $this->drupalGet('user/' . $user_with_permission->id() . '/flag-clear');
    $this->assertSession()->statusCodeEquals(200);

    // Test user without permission cannot access
    $this->drupalLogin($user_without_permission);
    $this->drupalGet('user/' . $user_without_permission->id() . '/flag-clear');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests 'clear all flags' permission works.
   */
  public function testClearAllFlagsPermission() {
    // User with 'clear all flags' permission
    $admin_user = $this->drupalCreateUser([
      'clear all flags',
    ]);

    // Regular user
    $regular_user = $this->drupalCreateUser([
      'clear own flags',
    ]);

    // Test admin can access bulk clear
    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/structure/flags/bulk-clear');
    $this->assertSession()->statusCodeEquals(200);

    // Test admin can access other users' clear pages
    $this->drupalGet('user/' . $regular_user->id() . '/flag-clear');
    $this->assertSession()->statusCodeEquals(200);

    // Test regular user cannot access bulk clear
    $this->drupalLogin($regular_user);
    $this->drupalGet('admin/structure/flags/bulk-clear');
    $this->assertSession()->statusCodeEquals(403);

    // Test regular user cannot access other users' clear pages
    $this->drupalGet('user/' . $admin_user->id() . '/flag-clear');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests access when user clearing is disabled.
   */
  public function testAccessWhenUserClearingDisabled() {
    // Disable user clearing
    $config = $this->config('flag_retention.settings');
    $config->set('enable_user_clearing', FALSE);
    $config->save();

    // User with 'clear own flags' permission
    $user = $this->drupalCreateUser([
      'clear own flags',
    ]);

    // Test user cannot access even with permission
    $this->drupalLogin($user);
    $this->drupalGet('user/' . $user->id() . '/flag-clear');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests anonymous users are blocked from all operations.
   */
  public function testAnonymousUsersBlocked() {
    // Enable user clearing
    $config = $this->config('flag_retention.settings');
    $config->set('enable_user_clearing', TRUE);
    $config->save();

    // Test anonymous access to various pages
    $this->drupalGet('admin/config/system/flag-retention');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('admin/structure/flags/retention');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('admin/structure/flags/bulk-clear');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('user/1/flag-clear');
    $this->assertSession()->statusCodeEquals(403);
  }

}
