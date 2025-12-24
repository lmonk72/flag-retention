<?php

namespace Drupal\flag_retention\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Controller for flag retention functionality.
 */
class FlagRetentionController extends ControllerBase {

  /**
   * Access check for user flag clearing.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check access for.
   * @param string|int $user
   *   The user ID from the route parameter.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function userClearAccess(AccountInterface $account, $user = NULL) {
    // Check if user clearing is enabled.
    $config = \Drupal::config('flag_retention.settings');
    if (!$config->get('enable_user_clearing')) {
      return AccessResult::forbidden('User clearing is disabled.');
    }

    // If no user specified, allow (form will handle default).
    if (!$user) {
      return AccessResult::allowed();
    }

    // Convert to integer for comparison.
    $user_id = is_numeric($user) ? (int) $user : NULL;
    if (!$user_id) {
      return AccessResult::forbidden('Invalid user ID.');
    }

    // Users can clear their own flags, admins can clear any flags.
    if ($user_id == $account->id() || $account->hasPermission('clear all flags')) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden('User can only clear their own flags.');
  }

}