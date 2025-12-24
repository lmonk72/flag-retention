<?php

namespace Drupal\flag_retention;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\flag\FlagServiceInterface;

/**
 * Flag retention manager service.
 */
class FlagRetentionManager {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a FlagRetentionManager object.
   */
  public function __construct(Connection $database, ConfigFactoryInterface $config_factory, FlagServiceInterface $flag_service, LoggerChannelFactoryInterface $logger_factory, TimeInterface $time) {
    $this->database = $database;
    $this->configFactory = $config_factory;
    $this->flagService = $flag_service;
    $this->loggerFactory = $logger_factory;
    $this->time = $time;
  }

  /**
   * Get retention settings for a specific flag.
   */
  public function getRetentionSettings($flag_id) {
    $result = $this->database->select('flag_retention_settings', 'frs')
      ->fields('frs')
      ->condition('flag_id', $flag_id)
      ->execute()
      ->fetchAssoc();

    if (!$result) {
      // Return default settings (disabled by default).
      return [
        'flag_id' => $flag_id,
        'retention_days' => 0,
        'auto_clear' => 0,
      ];
    }

    return $result;
  }

  /**
   * Save retention settings for a flag.
   *
   * @param string $flag_id
   *   The flag ID (must be a valid Drupal machine name).
   * @param int $retention_days
   *   Number of days to retain flags (0-3650).
   * @param int $auto_clear
   *   Whether to automatically clear expired flags (0 or 1).
   *
   * @return bool
   *   TRUE if settings were saved successfully.
   *
   * @throws \InvalidArgumentException
   *   If parameters are invalid.
   */
  public function saveRetentionSettings($flag_id, $retention_days, $auto_clear = 0) {
    // Validate flag_id format (must be a valid Drupal machine name)
    if (!$this->isValidFlagId($flag_id)) {
      throw new \InvalidArgumentException(
        sprintf('Invalid flag_id format: %s. Must be lowercase alphanumeric with underscores.', $flag_id)
      );
    }

    // Validate retention_days is within acceptable range
    if (!is_numeric($retention_days) || $retention_days < 0 || $retention_days > 3650) {
      throw new \InvalidArgumentException(
        sprintf('Invalid retention_days: %s. Must be between 0 and 3650.', $retention_days)
      );
    }
    $retention_days = (int) $retention_days;

    // Validate auto_clear is boolean-like
    if (!in_array($auto_clear, [0, 1])) {
      throw new \InvalidArgumentException(
        sprintf('Invalid auto_clear value: %s. Must be 0 or 1.', $auto_clear)
      );
    }
    $auto_clear = (int) $auto_clear;

    $current_time = $this->time->getRequestTime();

    // Check if settings already exist.
    $existing = $this->database->select('flag_retention_settings', 'frs')
      ->fields('frs', ['id'])
      ->condition('flag_id', $flag_id)
      ->execute()
      ->fetchField();

    if ($existing) {
      // Update existing settings.
      $this->database->update('flag_retention_settings')
        ->fields([
          'retention_days' => $retention_days,
          'auto_clear' => $auto_clear,
          'changed' => $current_time,
        ])
        ->condition('flag_id', $flag_id)
        ->execute();
    }
    else {
      // Insert new settings.
      $this->database->insert('flag_retention_settings')
        ->fields([
          'flag_id' => $flag_id,
          'retention_days' => $retention_days,
          'auto_clear' => $auto_clear,
          'created' => $current_time,
          'changed' => $current_time,
        ])
        ->execute();
    }

    return TRUE;
  }

  /**
   * Validates if a flag_id is in a valid Drupal machine name format.
   *
   * @param string $flag_id
   *   The flag ID to validate.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  protected function isValidFlagId($flag_id) {
    // Drupal machine names: lowercase alphanumeric and underscore, 1-255 chars
    if (!is_string($flag_id) || empty($flag_id) || strlen($flag_id) > 255) {
      return FALSE;
    }

    // Match Drupal machine name pattern
    return preg_match('/^[a-z0-9_]+$/', $flag_id) === 1;
  }

  /**
   * Get flags that are eligible for automatic cleanup.
   */
  public function getExpiredFlags($limit = 100) {
    $current_time = $this->time->getRequestTime();

    // Get all flags with auto-clear enabled and retention period > 0.
    $flags_with_settings = $this->database->select('flag_retention_settings', 'frs')
      ->fields('frs', ['flag_id', 'retention_days'])
      ->condition('auto_clear', 1)
      ->condition('retention_days', 0, '>')
      ->execute()
      ->fetchAllKeyed();

    if (empty($flags_with_settings)) {
      return [];
    }

    // Get all valid flags for validation.
    $all_flags = $this->flagService->getAllFlags();
    $valid_flag_ids = array_keys($all_flags);

    $expired_flaggings = [];

    foreach ($flags_with_settings as $flag_id => $retention_days) {
      // Validate flag_id to ensure it's a known flag before querying.
      if (!in_array($flag_id, $valid_flag_ids)) {
        $this->loggerFactory->get('flag_retention')->warning(
          'Skipping expired flags query for unknown flag: @flag_id',
          ['@flag_id' => $flag_id]
        );
        continue;
      }

      $cutoff_time = $current_time - ($retention_days * 24 * 60 * 60);

      $flaggings = $this->database->select('flagging', 'f')
        ->fields('f', ['id'])
        ->condition('flag_id', $flag_id)
        ->condition('created', $cutoff_time, '<')
        ->range(0, $limit)
        ->execute()
        ->fetchCol();

      $expired_flaggings = array_merge($expired_flaggings, $flaggings);

      // Respect the batch limit.
      if (count($expired_flaggings) >= $limit) {
        $expired_flaggings = array_slice($expired_flaggings, 0, $limit);
        break;
      }
    }

    return $expired_flaggings;
  }

  /**
   * Process cron cleanup.
   */
  public function processCronCleanup() {
    $config = $this->configFactory->get('flag_retention.settings');
    $batch_size = $config->get('cron_batch_size') ?: 100;

    $expired_flagging_ids = $this->getExpiredFlags($batch_size);

    if (!empty($expired_flagging_ids)) {
      $clearer = \Drupal::service('flag_retention.clearer');
      $deleted_count = $clearer->deleteFlaggingsByIds($expired_flagging_ids);

      if ($config->get('log_clearing_activity')) {
        $this->loggerFactory->get('flag_retention')->info(
          'Cron cleanup deleted @count expired flaggings.',
          ['@count' => $deleted_count]
        );
      }
    }
  }

  /**
   * Get all flags with their retention settings.
   */
  public function getAllFlagsWithSettings() {
    $flags = $this->flagService->getAllFlags();
    $result = [];

    foreach ($flags as $flag) {
      $flag_id = $flag->id();
      $settings = $this->getRetentionSettings($flag_id);

      $result[$flag_id] = [
        'flag' => $flag,
        'retention_days' => $settings['retention_days'] ?? 0,
        'auto_clear' => $settings['auto_clear'] ?? 0,
      ];
    }

    return $result;
  }

}