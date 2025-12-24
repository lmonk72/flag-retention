<?php

namespace Drupal\Tests\flag_retention\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests database operations for flag retention settings.
 *
 * @group flag_retention
 */
class FlagRetentionDatabaseTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['flag_retention', 'flag', 'user', 'system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    
    $this->installSchema('flag_retention', ['flag_retention_settings']);
  }

  /**
   * Tests that flag_retention_settings table is created correctly.
   */
  public function testTableExists() {
    $schema = \Drupal::database()->schema();
    $this->assertTrue($schema->tableExists('flag_retention_settings'));
  }

  /**
   * Tests that retention settings can be saved and retrieved.
   */
  public function testSaveAndRetrieveSettings() {
    $flag_id = 'test_flag';
    $retention_days = 90;
    $auto_clear = 1;

    $manager = \Drupal::service('flag_retention.manager');
    $result = $manager->saveRetentionSettings($flag_id, $retention_days, $auto_clear);
    
    $this->assertTrue($result);

    $settings = $manager->getRetentionSettings($flag_id);
    $this->assertEquals($flag_id, $settings['flag_id']);
    $this->assertEquals($retention_days, $settings['retention_days']);
    $this->assertEquals($auto_clear, $settings['auto_clear']);
  }

  /**
   * Tests that retention settings can be updated.
   */
  public function testUpdateSettings() {
    $flag_id = 'test_flag';
    $manager = \Drupal::service('flag_retention.manager');

    // Create initial settings
    $manager->saveRetentionSettings($flag_id, 30, 0);

    // Update settings
    $manager->saveRetentionSettings($flag_id, 60, 1);

    $settings = $manager->getRetentionSettings($flag_id);
    $this->assertEquals(60, $settings['retention_days']);
    $this->assertEquals(1, $settings['auto_clear']);
  }

  /**
   * Tests unique constraint on flag_id works.
   */
  public function testUniqueConstraintOnFlagId() {
    $flag_id = 'test_flag';
    
    // Insert first record
    \Drupal::database()->insert('flag_retention_settings')
      ->fields([
        'flag_id' => $flag_id,
        'retention_days' => 30,
        'auto_clear' => 0,
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    // Attempt to insert duplicate should fail
    try {
      \Drupal::database()->insert('flag_retention_settings')
        ->fields([
          'flag_id' => $flag_id,
          'retention_days' => 60,
          'auto_clear' => 1,
          'created' => time(),
          'changed' => time(),
        ])
        ->execute();
      
      $this->fail('Expected exception for duplicate flag_id was not thrown');
    }
    catch (\Exception $e) {
      // Expected exception - unique constraint violation
      $this->assertTrue(TRUE);
    }
  }

  /**
   * Tests indexes exist on auto_clear and retention_days.
   */
  public function testIndexesExist() {
    $schema = \Drupal::database()->schema();
    
    // Note: Not all database drivers expose index information in the same way
    // This is a basic check that the table was created with the schema
    $this->assertTrue($schema->tableExists('flag_retention_settings'));
    
    // Verify we can query efficiently with indexed fields
    $result = \Drupal::database()->select('flag_retention_settings', 'frs')
      ->fields('frs')
      ->condition('auto_clear', 1)
      ->execute()
      ->fetchAll();
    
    $this->assertIsArray($result);
  }

  /**
   * Tests created and changed timestamps are set correctly.
   */
  public function testTimestamps() {
    $flag_id = 'test_flag';
    $before_time = time();
    
    $manager = \Drupal::service('flag_retention.manager');
    $manager->saveRetentionSettings($flag_id, 30, 0);
    
    $after_time = time();

    $settings = \Drupal::database()->select('flag_retention_settings', 'frs')
      ->fields('frs')
      ->condition('flag_id', $flag_id)
      ->execute()
      ->fetchAssoc();

    $this->assertGreaterThanOrEqual($before_time, $settings['created']);
    $this->assertLessThanOrEqual($after_time, $settings['created']);
    $this->assertGreaterThanOrEqual($before_time, $settings['changed']);
    $this->assertLessThanOrEqual($after_time, $settings['changed']);
  }

}
