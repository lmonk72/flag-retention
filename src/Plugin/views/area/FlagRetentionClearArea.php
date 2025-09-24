<?php

namespace Drupal\flag_retention\Plugin\views\area;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\area\AreaPluginBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Views area plugin to display a "Clear My Flags" button.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("flag_retention_clear_area")
 */
class FlagRetentionClearArea extends AreaPluginBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a new FlagRetentionClearArea.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['button_text'] = ['default' => 'Clear My Items'];
    $options['show_count'] = ['default' => TRUE];
    $options['button_class'] = ['default' => 'button button--primary'];
    $options['use_modal'] = ['default' => TRUE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['button_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button text'),
      '#default_value' => $this->options['button_text'],
      '#description' => $this->t('The text to display on the clear flags button.'),
      '#required' => TRUE,
    ];

    $form['show_count'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show flag count in button'),
      '#default_value' => $this->options['show_count'],
      '#description' => $this->t('Display the number of flags the user has in the button text.'),
    ];

    $form['button_class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button CSS classes'),
      '#default_value' => $this->options['button_class'],
      '#description' => $this->t('CSS classes to apply to the button.'),
    ];

    $form['use_modal'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open in modal'),
      '#default_value' => $this->options['use_modal'],
      '#description' => $this->t('Open the clear form in a modal dialog instead of navigating to a new page.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    // Only show to logged-in users.
    if ($this->currentUser->isAnonymous()) {
      return [];
    }

    // Check if user clearing is enabled and user has permission.
    $config = \Drupal::config('flag_retention.settings');
    if (!$config->get('enable_user_clearing') || !$this->currentUser->hasPermission('clear own flags')) {
      return [];
    }

    // Always check if user has flags, regardless of show_count setting
    $flag_clearer = \Drupal::service('flag_retention.clearer');
    $flag_counts = $flag_clearer->getUserFlagCount($this->currentUser->id());
    $total_flags = 0;
    
    foreach ($flag_counts as $data) {
      $total_flags += $data->count;
    }
    
    // Handle display logic based on flags and view results
    if ($total_flags === 0) {
      // User has no flags - only show button if "Display even if view has no result" is enabled
      if ($empty && empty($this->options['empty'])) {
        // View is empty and "Display even if view has no result" is disabled - hide button
        return [];
      }
      // Either view has results or "Display even if view has no result" is enabled - show button
    } else {
      // User has flags - respect the "Display even if view has no result" setting
      if ($empty && empty($this->options['empty'])) {
        return [];
      }
    }

    // Build button text, adding count if requested
    $button_text = $this->options['button_text'];
    if ($this->options['show_count'] && $total_flags > 0) {
      $button_text .= ' (' . $total_flags . ')';
    }

    $url = Url::fromRoute('flag_retention.user_clear', ['user' => $this->currentUser->id()]);
    
    $attributes = [
      'class' => explode(' ', $this->options['button_class']),
      'title' => $this->t('Clear all your items'),
    ];

    $libraries = ['flag_retention/flag_retention'];

    // Add modal support if enabled
    if ($this->options['use_modal']) {
      // Get custom terminology for modal title
      $config = \Drupal::config('flag_retention.settings');
      $item_term_plural = $config->get('item_term_plural') ?: 'items';
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
    
    return [
      '#type' => 'link',
      '#title' => $button_text,
      '#url' => $url,
      '#attributes' => $attributes,
      '#prefix' => '<div class="flag-retention-clear-area">',
      '#suffix' => '</div>',
      '#attached' => ['library' => $libraries],
    ];
  }

}