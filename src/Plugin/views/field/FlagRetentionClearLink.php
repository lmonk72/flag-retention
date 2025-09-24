<?php

namespace Drupal\flag_retention\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A handler to provide a clear flags link.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("flag_retention_clear_link")
 */
class FlagRetentionClearLink extends FieldPluginBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a new FlagRetentionClearLink.
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
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // This field doesn't need to add anything to the query.
    // This is a global field.
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['link_type'] = ['default' => 'user_clear'];
    $options['link_text'] = ['default' => 'Clear my flags'];
    $options['show_count'] = ['default' => TRUE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['link_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Link type'),
      '#options' => [
        'user_clear' => $this->t('User clear own flags'),
        'admin_clear' => $this->t('Admin clear all flags (requires admin permission)'),
      ],
      '#default_value' => $this->options['link_type'],
      '#description' => $this->t('Choose what type of clear link to display.'),
    ];

    $form['link_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link text'),
      '#default_value' => $this->options['link_text'],
      '#description' => $this->t('The text to display for the link.'),
    ];

    $form['show_count'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show flag count'),
      '#default_value' => $this->options['show_count'],
      '#description' => $this->t('Display the number of flags the user has.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    // Try to get user from the row entity first
    $entity = $this->getEntity($values);
    $user = NULL;
    
    if ($entity && $entity->getEntityTypeId() === 'user') {
      $user = $entity;
    } elseif (isset($values->users_field_data_uid)) {
      // Try to load user from uid field in the row
      $user = \Drupal::entityTypeManager()->getStorage('user')->load($values->users_field_data_uid);
    } elseif (isset($values->uid)) {
      // Try to load user from uid field
      $user = \Drupal::entityTypeManager()->getStorage('user')->load($values->uid);
    } else {
      // Fall back to current user for global field usage
      $user = \Drupal::entityTypeManager()->getStorage('user')->load($this->currentUser->id());
    }

    if (!$user || $user->id() == 0) {
      return '';
    }
    $link_type = $this->options['link_type'];
    $config = \Drupal::config('flag_retention.settings');

    // Check permissions and access.
    $has_clear_own = $this->currentUser->hasPermission('clear own flags');
    $has_clear_all = $this->currentUser->hasPermission('clear all flags');
    $is_own_profile = $this->currentUser->id() == $user->id();

    if ($link_type === 'user_clear') {
      // For user clear, check if user clearing is enabled and user has permission.
      if (!$config->get('enable_user_clearing') || !$has_clear_own) {
        return '';
      }
      
      // Only show on own profile unless user has admin permission.
      if (!$is_own_profile && !$has_clear_all) {
        return '';
      }
    }
    elseif ($link_type === 'admin_clear') {
      // For admin clear, user must have admin permission.
      if (!$has_clear_all) {
        return '';
      }
    }

    // Get user's flag count if requested.
    $count_text = '';
    if ($this->options['show_count']) {
      $flag_clearer = \Drupal::service('flag_retention.clearer');
      $flag_counts = $flag_clearer->getUserFlagCount($user->id());
      $total_flags = 0;
      
      foreach ($flag_counts as $data) {
        $total_flags += $data->count;
      }
      
      if ($total_flags > 0) {
        $count_text = ' (' . $total_flags . ')';
      }
      else {
        // Don't show link if user has no flags.
        return '';
      }
    }

    // Build the link.
    $link_text = $this->options['link_text'] . $count_text;
    
    if ($link_type === 'user_clear') {
      $url = Url::fromRoute('flag_retention.user_clear', ['user' => $user->id()]);
    }
    else {
      // For admin clear, we'll link to a bulk clear form.
      $url = Url::fromRoute('flag_retention.bulk_clear');
    }

    return [
      '#type' => 'link',
      '#title' => $link_text,
      '#url' => $url,
      '#attributes' => [
        'class' => ['flag-retention-clear-link', 'flag-retention-' . $link_type],
        'title' => $this->t('Clear flags for @user', ['@user' => $user->label()]),
      ],
      '#attached' => ['library' => ['flag_retention/flag_retention']],
    ];
  }

}