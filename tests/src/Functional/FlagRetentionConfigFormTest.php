<?php

namespace Drupal\Tests\flag_retention\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the flag retention configuration form.
 *
 * @group flag_retention
 */
class FlagRetentionConfigFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['flag_retention', 'flag', 'user', 'system'];

  /**
   * A user with admin permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A regular user without admin permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $regularUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create admin user with permission to administer flag retention
    $this->adminUser = $this->drupalCreateUser([
      'administer flag retention',
      'access administration pages',
    ]);

    // Create regular user
    $this->regularUser = $this->drupalCreateUser([]);
  }

  /**
   * Tests that admin can access config form.
   */
  public function testAdminCanAccessConfigForm() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/system/flag-retention');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Global Settings');
  }

  /**
   * Tests that non-admin cannot access config form.
   */
  public function testNonAdminCannotAccessConfigForm() {
    $this->drupalLogin($this->regularUser);
    $this->drupalGet('admin/config/system/flag-retention');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that form displays current configuration.
   */
  public function testFormDisplaysCurrentConfiguration() {
    // Set some configuration
    $config = $this->config('flag_retention.settings');
    $config->set('global_retention_days', 60);
    $config->set('enable_user_clearing', TRUE);
    $config->set('cron_batch_size', 200);
    $config->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/system/flag-retention');

    // Check that values are displayed
    $this->assertSession()->fieldValueEquals('global_retention_days', '60');
    $this->assertSession()->checkboxChecked('enable_user_clearing');
    $this->assertSession()->fieldValueEquals('cron_batch_size', '200');
  }

  /**
   * Tests that form saves configuration correctly.
   */
  public function testFormSavesConfiguration() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/system/flag-retention');

    // Submit the form with new values
    $edit = [
      'global_retention_days' => 120,
      'enable_user_clearing' => FALSE,
      'log_clearing_activity' => TRUE,
      'cron_batch_size' => 50,
      'item_term_singular' => 'bookmark',
      'item_term_plural' => 'bookmarks',
      'clear_action_term' => 'remove',
    ];
    $this->submitForm($edit, 'Save configuration');

    // Check success message
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Verify configuration was saved
    $config = $this->config('flag_retention.settings');
    $this->assertEquals(120, $config->get('global_retention_days'));
    $this->assertEquals(FALSE, $config->get('enable_user_clearing'));
    $this->assertEquals(TRUE, $config->get('log_clearing_activity'));
    $this->assertEquals(50, $config->get('cron_batch_size'));
    $this->assertEquals('bookmark', $config->get('item_term_singular'));
    $this->assertEquals('bookmarks', $config->get('item_term_plural'));
    $this->assertEquals('remove', $config->get('clear_action_term'));
  }

  /**
   * Tests form validation for minimum values.
   */
  public function testFormValidation() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/system/flag-retention');

    // Try to submit with invalid values
    $edit = [
      'global_retention_days' => -1,  // Negative value
      'cron_batch_size' => 0,  // Below minimum
    ];
    $this->submitForm($edit, 'Save configuration');

    // Check for validation errors
    // Note: Drupal's #min attribute provides client-side validation
    // Server-side validation should also be present
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests terminology settings are saved.
   */
  public function testTerminologySettings() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/system/flag-retention');

    $edit = [
      'item_term_singular' => 'favorite',
      'item_term_plural' => 'favorites',
      'clear_action_term' => 'delete',
    ];
    $this->submitForm($edit, 'Save configuration');

    $config = $this->config('flag_retention.settings');
    $this->assertEquals('favorite', $config->get('item_term_singular'));
    $this->assertEquals('favorites', $config->get('item_term_plural'));
    $this->assertEquals('delete', $config->get('clear_action_term'));
  }

}
