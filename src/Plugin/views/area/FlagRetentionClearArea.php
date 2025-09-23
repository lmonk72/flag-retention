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
    $options['button_text'] = ['default' => 'Clear My Flags'];
    $options['show_count'] = ['default' => TRUE];
    $options['button_class'] = ['default' => 'button button--primary'];
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
      '#description' => $this->t('The text to display on the button.'),
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

    // Get user's flag count if requested.
    $button_text = $this->options['button_text'];
    if ($this->options['show_count']) {
      $flag_clearer = \Drupal::service('flag_retention.clearer');
      $flag_counts = $flag_clearer->getUserFlagCount($this->currentUser->id());
      $total_flags = 0;
      
      foreach ($flag_counts as $data) {
        $total_flags += $data->count;
      }
      
      if ($total_flags > 0) {
        $button_text .= ' (' . $total_flags . ')';
      }
      else {
        // Don't show button if user has no flags.
        return [];
      }
    }

    $url = Url::fromRoute('flag_retention.user_clear', ['user' => $this->currentUser->id()]);
    
    return [
      '#type' => 'link',
      '#title' => $button_text,
      '#url' => $url,
      '#attributes' => [
        'class' => explode(' ', $this->options['button_class']),
        'title' => $this->t('Clear all your flags'),
      ],
      '#prefix' => '<div class="flag-retention-clear-area">',
      '#suffix' => '</div>',
      '#attached' => ['library' => ['flag_retention/flag_retention']],
    ];
  }

}