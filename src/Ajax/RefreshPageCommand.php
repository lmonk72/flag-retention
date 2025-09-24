<?php

namespace Drupal\flag_retention\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * AJAX command to refresh the page after a delay.
 */
class RefreshPageCommand implements CommandInterface {

  /**
   * The delay in milliseconds.
   *
   * @var int
   */
  protected $delay;

  /**
   * Constructs a RefreshPageCommand object.
   *
   * @param int $delay
   *   The delay in milliseconds before refreshing.
   */
  public function __construct($delay = 1500) {
    $this->delay = $delay;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'flagRetentionRefreshPage',
      'delay' => $this->delay,
    ];
  }

}