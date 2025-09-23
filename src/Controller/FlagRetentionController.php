<?php

namespace Drupal\flag_retention\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * Controller for flag retention functionality.
 */
class FlagRetentionController extends ControllerBase {

  /**
   * Access check for user flag clearing.
   */
  public function userClearAccess(AccountInterface $account, UserInterface $user) {
    // Users can only clear their own flags, or admins can clear any user's flags.
    if ($account->hasPermission('clear all flags')) {
      return AccessResult::allowed();
    }
    
    if ($account->hasPermission('clear own flags') && $account->id() == $user->id()) {
      return AccessResult::allowed();
    }
    
    return AccessResult::forbidden();
  }

}