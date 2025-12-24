<?php

namespace Drupal\Tests\flag_retention\Unit;

use Drupal\flag_retention\FlagRetentionManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Select;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\flag\FlagInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests for FlagRetentionManager service.
 *
 * @group flag_retention
 * @coversDefaultClass \Drupal\flag_retention\FlagRetentionManager
 */
class FlagRetentionManagerTest extends TestCase {

  /**
   * The mocked database connection.
   *
   * @var \Drupal\Core\Database\Connection|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $database;

  /**
   * The mocked config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The mocked flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $flagService;

  /**
   * The mocked logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $loggerFactory;

  /**
   * The mocked time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $time;

  /**
   * The flag retention manager under test.
   *
   * @var \Drupal\flag_retention\FlagRetentionManager
   */
  protected $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->database = $this->createMock(Connection::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->flagService = $this->createMock(FlagServiceInterface::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->time = $this->createMock(TimeInterface::class);

    $this->manager = new FlagRetentionManager(
      $this->database,
      $this->configFactory,
      $this->flagService,
      $this->loggerFactory,
      $this->time
    );
  }

  /**
   * Tests getRetentionSettings with existing settings.
   *
   * @covers ::getRetentionSettings
   */
  public function testGetRetentionSettingsExisting() {
    $flag_id = 'bookmark';
    $expected = [
      'flag_id' => 'bookmark',
      'retention_days' => 90,
      'auto_clear' => 1,
    ];

    $statement = $this->createMock(StatementInterface::class);
    $statement->expects($this->once())
      ->method('fetchAssoc')
      ->willReturn($expected);

    $query = $this->createMock(Select::class);
    $query->expects($this->once())
      ->method('fields')
      ->with('frs')
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('condition')
      ->with('flag_id', $flag_id)
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('execute')
      ->willReturn($statement);

    $this->database->expects($this->once())
      ->method('select')
      ->with('flag_retention_settings', 'frs')
      ->willReturn($query);

    $result = $this->manager->getRetentionSettings($flag_id);
    $this->assertEquals($expected, $result);
  }

  /**
   * Tests getRetentionSettings with non-existing settings.
   *
   * @covers ::getRetentionSettings
   */
  public function testGetRetentionSettingsNonExisting() {
    $flag_id = 'bookmark';

    $statement = $this->createMock(StatementInterface::class);
    $statement->expects($this->once())
      ->method('fetchAssoc')
      ->willReturn(FALSE);

    $query = $this->createMock(Select::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn($statement);

    $this->database->method('select')->willReturn($query);

    $result = $this->manager->getRetentionSettings($flag_id);

    $expected = [
      'flag_id' => $flag_id,
      'retention_days' => 0,
      'auto_clear' => 0,
    ];

    $this->assertEquals($expected, $result);
  }

  /**
   * Tests getAllFlagsWithSettings combines data correctly.
   *
   * @covers ::getAllFlagsWithSettings
   */
  public function testGetAllFlagsWithSettings() {
    $flag1 = $this->createMock(FlagInterface::class);
    $flag1->method('id')->willReturn('bookmark');
    $flag1->method('label')->willReturn('Bookmark');

    $flag2 = $this->createMock(FlagInterface::class);
    $flag2->method('id')->willReturn('favorite');
    $flag2->method('label')->willReturn('Favorite');

    $this->flagService->expects($this->once())
      ->method('getAllFlags')
      ->willReturn([
        'bookmark' => $flag1,
        'favorite' => $flag2,
      ]);

    // Mock database calls for getRetentionSettings
    $statement1 = $this->createMock(StatementInterface::class);
    $statement1->method('fetchAssoc')
      ->willReturn(['flag_id' => 'bookmark', 'retention_days' => 90, 'auto_clear' => 1]);

    $statement2 = $this->createMock(StatementInterface::class);
    $statement2->method('fetchAssoc')
      ->willReturn(FALSE);  // favorite has no settings

    $query = $this->createMock(Select::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturnOnConsecutiveCalls($statement1, $statement2);

    $this->database->method('select')->willReturn($query);

    $result = $this->manager->getAllFlagsWithSettings();

    $this->assertArrayHasKey('bookmark', $result);
    $this->assertArrayHasKey('favorite', $result);
    $this->assertEquals(90, $result['bookmark']['retention_days']);
    $this->assertEquals(1, $result['bookmark']['auto_clear']);
    $this->assertEquals(0, $result['favorite']['retention_days']);
    $this->assertEquals(0, $result['favorite']['auto_clear']);
  }

  /**
   * Tests getExpiredFlags respects batch limit.
   *
   * @covers ::getExpiredFlags
   */
  public function testGetExpiredFlagsRespectsLimit() {
    $current_time = 1000000;
    $this->time->method('getRequestTime')->willReturn($current_time);

    // Mock flags with retention settings
    $statement1 = $this->createMock(StatementInterface::class);
    $statement1->method('fetchAllKeyed')
      ->willReturn(['bookmark' => 30, 'favorite' => 60]);

    $query1 = $this->createMock(Select::class);
    $query1->method('fields')->willReturnSelf();
    $query1->method('condition')->willReturnSelf();
    $query1->method('execute')->willReturn($statement1);

    // Mock flagging queries
    $statement2 = $this->createMock(StatementInterface::class);
    $statement2->method('fetchCol')->willReturn([1, 2, 3, 4, 5]);

    $query2 = $this->createMock(Select::class);
    $query2->method('fields')->willReturnSelf();
    $query2->method('condition')->willReturnSelf();
    $query2->method('range')->willReturnSelf();
    $query2->method('execute')->willReturn($statement2);

    $this->database->method('select')
      ->willReturnOnConsecutiveCalls($query1, $query2);

    $result = $this->manager->getExpiredFlags(10);

    $this->assertIsArray($result);
    $this->assertLessThanOrEqual(10, count($result));
  }

  /**
   * Tests getExpiredFlags validates flag_id exists.
   *
   * @covers ::getExpiredFlags
   */
  public function testGetExpiredFlagsValidatesFlagId() {
    $current_time = 1000000;
    $this->time->method('getRequestTime')->willReturn($current_time);

    // Mock settings query to return flags with retention settings
    $statement1 = $this->createMock(StatementInterface::class);
    $statement1->method('fetchAllKeyed')
      ->willReturn(['nonexistent_flag' => 30]);

    $query1 = $this->createMock(Select::class);
    $query1->method('fields')->willReturnSelf();
    $query1->method('condition')->willReturnSelf();
    $query1->method('execute')->willReturn($statement1);

    // Mock getAllFlags to return empty list (nonexistent flag)
    $this->flagService->method('getAllFlags')
      ->willReturn([]);

    // Mock logger to verify warning is logged
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())
      ->method('warning')
      ->with('Skipping expired flags query for unknown flag: @flag_id', ['@flag_id' => 'nonexistent_flag']);

    $this->loggerFactory->expects($this->once())
      ->method('get')
      ->with('flag_retention')
      ->willReturn($logger);

    $this->database->expects($this->once())
      ->method('select')
      ->willReturn($query1);

    $result = $this->manager->getExpiredFlags(100);

    // Should return empty array since the flag was invalid
    $this->assertEquals([], $result);
  }

}
