<?php

namespace Drupal\flag_retention;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\flag\FlagServiceInterface;

/**
 * Flag clearer service.
 */
class FlagClearer {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

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
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a FlagClearer object.
   */
  public function __construct(Connection $database, FlagServiceInterface $flag_service, LoggerChannelFactoryInterface $logger_factory, TimeInterface $time, MessengerInterface $messenger) {
    $this->database = $database;
    $this->flagService = $flag_service;
    $this->loggerFactory = $logger_factory;
    $this->time = $time;
    $this->messenger = $messenger;
  }

  /**
   * Clear all flags of a specific type for a specific user.
   */
  public function clearUserFlags($user_id, $flag_id = NULL) {
    $query = $this->database->select('flagging', 'f')
      ->fields('f', ['id'])
      ->condition('uid', $user_id);

    if ($flag_id) {
      $query->condition('flag_id', $flag_id);
    }

    $flagging_ids = $query->execute()->fetchCol();

    if (!empty($flagging_ids)) {
      return $this->deleteFlaggingsByIds($flagging_ids);
    }

    return 0;
  }

  /**
   * Clear all flags of a specific type.
   */
  public function clearAllFlagsByType($flag_id) {
    $flagging_ids = $this->database->select('flagging', 'f')
      ->fields('f', ['id'])
      ->condition('flag_id', $flag_id)
      ->execute()
      ->fetchCol();

    if (!empty($flagging_ids)) {
      return $this->deleteFlaggingsByIds($flagging_ids);
    }

    return 0;
  }

  /**
   * Clear old flags based on age.
   */
  public function clearOldFlags($flag_id, $days_old) {
    $current_time = $this->time->getRequestTime();
    $cutoff_time = $current_time - ($days_old * 24 * 60 * 60);

    $flagging_ids = $this->database->select('flagging', 'f')
      ->fields('f', ['id'])
      ->condition('flag_id', $flag_id)
      ->condition('created', $cutoff_time, '<')
      ->execute()
      ->fetchCol();

    if (!empty($flagging_ids)) {
      return $this->deleteFlaggingsByIds($flagging_ids);
    }

    return 0;
  }

  /**
   * Delete flaggings by their IDs.
   */
  public function deleteFlaggingsByIds(array $flagging_ids) {
    if (empty($flagging_ids)) {
      return 0;
    }

    try {
      // Use Drupal's entity storage to properly delete flaggings
      // This ensures all hooks and events are triggered properly.
      $storage = \Drupal::entityTypeManager()->getStorage('flagging');
      $flaggings = $storage->loadMultiple($flagging_ids);
      
      if (!empty($flaggings)) {
        $storage->delete($flaggings);
        return count($flaggings);
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('flag_retention')->error(
        'Error deleting flaggings: @message',
        ['@message' => $e->getMessage()]
      );
      return 0;
    }

    return 0;
  }

  /**
   * Get flag statistics.
   */
  public function getFlagStatistics($flag_id = NULL) {
    $query = $this->database->select('flagging', 'f');
    $query->addExpression('COUNT(f.id)', 'total_count');
    $query->addExpression('COUNT(DISTINCT f.uid)', 'unique_users');

    if ($flag_id) {
      $query->condition('flag_id', $flag_id);
    }

    $query->groupBy('f.flag_id');
    $query->addField('f', 'flag_id');

    $results = $query->execute()->fetchAllAssoc('flag_id');

    if ($flag_id && isset($results[$flag_id])) {
      return $results[$flag_id];
    }

    return $results;
  }

  /**
   * Get user's flag count.
   */
  /**
   * Get user flag count for allowed flags only.
   */
  public function getUserFlagCount($user_id, $flag_id = NULL) {
    $query = $this->database->select('flagging', 'f')
      ->condition('uid', $user_id);

    // Apply flag access control
    $allowed_flags = $this->getAllowedFlags();
    if (!empty($allowed_flags)) {
      $query->condition('flag_id', $allowed_flags, 'IN');
    }

    $query->addExpression('COUNT(f.id)', 'count');

    if ($flag_id) {
      $query->condition('flag_id', $flag_id);
      $query->groupBy('f.flag_id');
      $query->addField('f', 'flag_id');
      $results = $query->execute()->fetchAllAssoc('flag_id');
      return isset($results[$flag_id]) ? $results[$flag_id]->count : 0;
    }
    else {
      $query->groupBy('f.flag_id');
      $query->addField('f', 'flag_id');
      return $query->execute()->fetchAllAssoc('flag_id');
    }
  }

  /**
   * Get list of allowed flags based on admin configuration.
   */
  public function getAllowedFlags() {
    $config = \Drupal::config('flag_retention.settings');
    $access_mode = $config->get('flag_access_mode') ?: 'allow_all';
    
    if ($access_mode === 'allow_all') {
      // Return empty array to indicate all flags are allowed
      return [];
    }
    
    // Only allow selected flags
    $enabled_flags = $config->get('enabled_flags') ?: [];
    return array_values($enabled_flags);
  }

  /**
   * Check if a specific flag is allowed to be cleared.
   */
  public function isFlagAllowed($flag_id) {
    $config = \Drupal::config('flag_retention.settings');
    $access_mode = $config->get('flag_access_mode') ?: 'allow_all';
    
    if ($access_mode === 'allow_all') {
      return TRUE;
    }
    
    $enabled_flags = $config->get('enabled_flags') ?: [];
    return in_array($flag_id, $enabled_flags);
  }

}