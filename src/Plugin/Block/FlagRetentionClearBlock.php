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
      'button_text' => 'Clear My Items',
      'show_count' => TRUE,
      'show_summary' => TRUE,
      'use_modal' => TRUE,
      'button_class' => 'btn btn-secondary',
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
      '#description' => $this->t('The text to display on the clear flags button.'),
      '#required' => TRUE,
    ];

    $form['show_count'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show flag count'),
      '#default_value' => $this->configuration['show_count'],
      '#description' => $this->t('Display the total number of flags the user has in the button text.'),
    ];

    $form['show_summary'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show flag summary'),
      '#default_value' => $this->configuration['show_summary'],
      '#description' => $this->t('Display a breakdown of flags by type.'),
    ];

    $form['button_class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button CSS classes'),
      '#default_value' => $this->configuration['button_class'],
      '#description' => $this->t('CSS classes to apply to the button.'),
    ];

    $form['use_modal'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open in modal'),
      '#default_value' => $this->configuration['use_modal'],
      '#description' => $this->t('Open the clear form in a modal dialog instead of navigating to a new page.'),
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
    $this->configuration['button_class'] = $form_state->getValue('button_class');
    $this->configuration['use_modal'] = $form_state->getValue('use_modal');
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

    // Get custom terminology from configuration
    $config = \Drupal::config('flag_retention.settings');
    $item_term_singular = $config->get('item_term_singular') ?: 'item';
    $item_term_plural = $config->get('item_term_plural') ?: 'items';

    // Show flag summary if enabled.
    if ($this->configuration['show_summary'] && !empty($flag_counts)) {
      $flag_service = \Drupal::service('flag');
      $summary_items = [];
      
      foreach ($flag_counts as $flag_id => $data) {
        $flag = $flag_service->getFlagById($flag_id);
        if ($flag) {
          $count = $data->count;
          $summary_items[] = $flag->label() . ': ' . $count . ' ' . ($count == 1 ? $item_term_singular : $item_term_plural);
        }
      }
      
      if (!empty($summary_items)) {
        $build['summary'] = [
          '#theme' => 'item_list',
          '#title' => $this->t('Your @items:', ['@items' => $item_term_plural]),
          '#items' => $summary_items,
          '#attributes' => ['class' => ['flag-retention-summary']],
        ];
      }
    }

    // Show total count if enabled.
    if ($this->configuration['show_count']) {
      $build['count'] = [
        '#markup' => '<p><strong>' . $this->t('Total @items: @count', [
          '@items' => $total_flags == 1 ? $item_term_singular : $item_term_plural,
          '@count' => $total_flags,
        ]) . '</strong></p>',
      ];
    }

    // Add the clear button.
    $button_text = $this->configuration['button_text'];
    $url = Url::fromRoute('flag_retention.user_clear', ['user' => $this->currentUser->id()]);
    
    $attributes = [
      'class' => explode(' ', $this->configuration['button_class']),
      'title' => $this->t('Clear all your @items', ['@items' => $item_term_plural]),
    ];

    $libraries = ['flag_retention/flag_retention'];

    // Add modal support if enabled
    if ($this->configuration['use_modal']) {
      $clear_action_term = $config->get('clear_action_term') ?: 'Clear';
      
      $attributes['class'][] = 'use-ajax';
      $attributes['data-dialog-type'] = 'modal';
      $attributes['data-dialog-options'] = json_encode([
        'width' => 600,
        'height' => 400,
        'title' => $this->t('@action Your @items', [
          '@action' => ucfirst($clear_action_term),
          '@items' => ucfirst($item_term_plural),
        ]),
      ]);
      $libraries[] = 'core/drupal.dialog.ajax';
      $libraries[] = 'flag_retention/flag_retention_modal';
    }
    
    $build['clear_button'] = [
      '#type' => 'link',
      '#title' => $button_text,
      '#url' => $url,
      '#attributes' => $attributes,
    ];

    $build['#cache']['contexts'][] = 'user';
    $build['#cache']['tags'][] = 'flagging_list:' . $this->currentUser->id();
    $build['#attached']['library'] = $libraries;

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