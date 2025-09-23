<?php

namespace Drupal\flag_retention\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\flag_retention\FlagClearer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Clear My Flags' Block.
 *
 * @Block(
 *   id = "flag_retention_clear_block",
 *   admin_label = @Translation("Clear My Flags"),
 *   category = @Translation("Flag Retention")
 * )
 */
class FlagRetentionClearBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The flag clearer service.
   *
   * @var \Drupal\flag_retention\FlagClearer
   */
  protected $flagClearer;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a new FlagRetentionClearBlock.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FlagClearer $flag_clearer, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->flagClearer = $flag_clearer;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('flag_retention.clearer'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'button_text' => 'Clear My Flags',
      'show_count' => TRUE,
      'show_summary' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['button_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button text'),
      '#default_value' => $this->configuration['button_text'],
      '#description' => $this->t('The text to display on the clear button.'),
    ];

    $form['show_count'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show flag count'),
      '#default_value' => $this->configuration['show_count'],
      '#description' => $this->t('Display the total number of flags the user has.'),
    ];

    $form['show_summary'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show flag summary'),
      '#default_value' => $this->configuration['show_summary'],
      '#description' => $this->t('Display a breakdown of flags by type.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['button_text'] = $form_state->getValue('button_text');
    $this->configuration['show_count'] = $form_state->getValue('show_count');
    $this->configuration['show_summary'] = $form_state->getValue('show_summary');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    
    // Get user's flag counts.
    $flag_counts = $this->flagClearer->getUserFlagCount($this->currentUser->id());
    $total_flags = 0;
    
    foreach ($flag_counts as $data) {
      $total_flags += $data->count;
    }

    if ($total_flags === 0) {
      $build['no_flags'] = [
        '#markup' => '<p>' . $this->t('You have no flags to clear.') . '</p>',
      ];
      return $build;
    }

    // Show flag summary if enabled.
    if ($this->configuration['show_summary'] && !empty($flag_counts)) {
      $flag_service = \Drupal::service('flag');
      $summary_items = [];
      
      foreach ($flag_counts as $flag_id => $data) {
        $flag = $flag_service->getFlagById($flag_id);
        if ($flag) {
          $summary_items[] = $flag->label() . ': ' . $data->count;
        }
      }
      
      if (!empty($summary_items)) {
        $build['summary'] = [
          '#theme' => 'item_list',
          '#title' => $this->t('Your flags:'),
          '#items' => $summary_items,
          '#attributes' => ['class' => ['flag-retention-summary']],
        ];
      }
    }

    // Show total count if enabled.
    if ($this->configuration['show_count']) {
      $build['count'] = [
        '#markup' => '<p><strong>' . $this->t('Total flags: @count', ['@count' => $total_flags]) . '</strong></p>',
      ];
    }

    // Add the clear button.
    $button_text = $this->configuration['button_text'];
    $url = Url::fromRoute('flag_retention.user_clear', ['user' => $this->currentUser->id()]);
    
    $build['clear_button'] = [
      '#type' => 'link',
      '#title' => $button_text,
      '#url' => $url,
      '#attributes' => [
        'class' => ['button', 'button--primary', 'flag-retention-clear-button'],
        'title' => $this->t('Clear all your flags'),
      ],
    ];

    $build['#cache']['contexts'][] = 'user';
    $build['#cache']['tags'][] = 'flagging_list:' . $this->currentUser->id();
    $build['#attached']['library'][] = 'flag_retention/flag_retention';

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    $config = \Drupal::config('flag_retention.settings');
    
    if (!$config->get('enable_user_clearing') || !$account->hasPermission('clear own flags')) {
      return AccessResult::forbidden();
    }
    
    return AccessResult::allowed();
  }

}