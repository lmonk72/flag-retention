<?php

namespace Drupal\flag_retention;

use Drupal\Core\Database\Connection;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\flag\FlagServiceInterface;

/**
 * Flag clearer service.
 */
class FlagClearer {

  use StringTranslationTrait;

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
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a FlagClearer object.
   */
  public function __construct(Connection $database, FlagServiceInterface $flag_service, LoggerChannelFactoryInterface $logger_factory, TimeInterface $time, MessengerInterface $messenger, AccountProxyInterface $current_user, EntityTypeManagerInterface $entity_type_manager, CacheTagsInvalidatorInterface $cache_tags_invalidator, ConfigFactoryInterface $config_factory) {
    $this->database = $database;
    $this->flagService = $flag_service;
    $this->loggerFactory = $logger_factory;
    $this->time = $time;
    $this->messenger = $messenger;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
    $this->configFactory = $config_factory;
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
      $deleted = $this->deleteFlaggingsByIds($flagging_ids);
      if ($deleted > 0) {
        $this->logAudit($flag_id, $deleted, 'user_clear', $this->currentUser->id(), $user_id);
        $this->invalidateFlagCaches($flag_id);
      }
      return $deleted;
    }

    return 0;
  }

  /**
   * Clear all flags of a specific type.
   */
  public function clearAllFlagsByType($flag_id) {
    // Validate flag_id to ensure it exists and is valid.
    $flag = $this->flagService->getFlagById($flag_id);
    if (!$flag) {
      $this->loggerFactory->get('flag_retention')->warning(
        'Attempted to clear flags for unknown flag_id: @flag_id',
        ['@flag_id' => $flag_id]
      );
      return 0;
    }

    $flagging_ids = $this->database->select('flagging', 'f')
      ->fields('f', ['id'])
      ->condition('flag_id', $flag_id)
      ->execute()
      ->fetchCol();

    if (!empty($flagging_ids)) {
      $deleted = $this->deleteFlaggingsByIds($flagging_ids);
      if ($deleted > 0) {
        $this->logAudit($flag_id, $deleted, 'admin_clear_all', $this->currentUser->id());
        $this->invalidateFlagCaches($flag_id);
      }
      return $deleted;
    }

    return 0;
  }

  /**
   * Clear old flags based on age.
   */
  public function clearOldFlags($flag_id, $days_old) {
    // Validate flag_id to ensure it exists and is valid.
    $flag = $this->flagService->getFlagById($flag_id);
    if (!$flag) {
      $this->loggerFactory->get('flag_retention')->warning(
        'Attempted to clear old flags for unknown flag_id: @flag_id',
        ['@flag_id' => $flag_id]
      );
      return 0;
    }

    $current_time = $this->time->getRequestTime();
    $cutoff_time = $current_time - ($days_old * 24 * 60 * 60);

    $flagging_ids = $this->database->select('flagging', 'f')
      ->fields('f', ['id'])
      ->condition('flag_id', $flag_id)
      ->condition('created', $cutoff_time, '<')
      ->execute()
      ->fetchCol();

    if (!empty($flagging_ids)) {
      $deleted = $this->deleteFlaggingsByIds($flagging_ids);
      if ($deleted > 0) {
        $this->logAudit($flag_id, $deleted, 'age_clear', $this->currentUser->id());
        $this->invalidateFlagCaches($flag_id);
      }
      return $deleted;
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
      $storage = $this->entityTypeManager->getStorage('flagging');
      $flaggings = $storage->loadMultiple($flagging_ids);

      if (!empty($flaggings)) {
        $storage->delete($flaggings);
        return count($flaggings);
      }
    }
    catch (\Exception $e) {
      if ($this->currentUser->hasPermission('administer flags')) {
        $this->loggerFactory->get('flag_retention')->error(
          'Error deleting flaggings: @message',
          ['@message' => $e->getMessage()]
        );
      }
      else {
        $this->loggerFactory->get('flag_retention')->error('Error deleting flaggings.');
      }
      // Provide a generic message to the end user without leaking details.
      $this->messenger->addError($this->t('An unexpected error occurred while deleting flags. Please try again later.'));
      return 0;
    }

    return 0;
  }

  /**
   * Record an audit entry for flag deletion operations.
   *
   * @param string|null $flag_id
   *   The flag ID affected (if applicable).
   * @param int $count
   *   Number of flaggings deleted.
   * @param string $operation
   *   Operation code (e.g., user_clear, admin_clear_all, age_clear).
   * @param int $performed_by
   *   User ID who initiated the operation (0 for system).
   * @param int|null $target_uid
   *   User ID whose flags were affected, if applicable.
   */
  protected function logAudit($flag_id, $count, $operation, $performed_by, $target_uid = NULL) {
    try {
      $this->database->insert('flag_retention_audit')
        ->fields([
          'flag_id' => $flag_id,
          'count' => (int) $count,
          'operation' => $operation,
          'performed_by' => (int) $performed_by,
          'target_uid' => isset($target_uid) ? (int) $target_uid : NULL,
          'created' => $this->time->getRequestTime(),
        ])
        ->execute();
    }
    catch (\Exception $e) {
      // Silently ignore audit write failures to avoid blocking clears.
      $this->loggerFactory->get('flag_retention')->warning('Failed to write flag retention audit record: @message', ['@message' => $e->getMessage()]);
    }
  }

  /**
   * Get flag statistics.
   */
  public function getFlagStatistics($flag_id = NULL) {
    // Validate flag_id if provided.
    if ($flag_id) {
      $flag = $this->flagService->getFlagById($flag_id);
      if (!$flag) {
        $this->loggerFactory->get('flag_retention')->warning(
          'Attempted to get statistics for unknown flag_id: @flag_id',
          ['@flag_id' => $flag_id]
        );
        return [];
      }
    }

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
    $config = $this->configFactory->get('flag_retention.settings');
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
    $config = $this->configFactory->get('flag_retention.settings');
    $access_mode = $config->get('flag_access_mode') ?: 'allow_all';

    if ($access_mode === 'allow_all') {
      return TRUE;
    }

    $enabled_flags = $config->get('enabled_flags') ?: [];
    return in_array($flag_id, $enabled_flags);
  }

  /**
   * Invalidate cache tags for flag data after deletions.
   *
   * @param string|null $flag_id
   *   Flag ID to target cache tags for, if available.
   */
  protected function invalidateFlagCaches($flag_id = NULL) {
    $tags = ['flag_retention'];

    if ($flag_id) {
      $flag = $this->flagService->getFlagById($flag_id);
      if ($flag) {
        $tags = array_merge($tags, $flag->getCacheTags());
      }
      $tags[] = 'flag_retention:' . $flag_id;
    }

    $this->cacheTagsInvalidator->invalidateTags(array_unique($tags));
  }

}