<?php

namespace Drupal\flag_retention\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class FlagRetentionConfigForm.
 */
class FlagRetentionConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'flag_retention.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'flag_retention_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('flag_retention.settings');

    $form['global_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Global Settings'),
      '#open' => TRUE,
    ];

    $form['global_settings']['global_retention_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Default retention period (days)'),
      '#description' => $this->t('Default number of days to keep flags. Set to 0 to keep forever. Valid range: 0-3650 days. This applies to flags without specific retention settings.'),
      '#default_value' => $config->get('global_retention_days'),
      '#min' => 0,
      '#max' => 3650,
      '#step' => 1,
    ];

    $form['global_settings']['enable_user_clearing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow users to clear their own flags'),
      '#description' => $this->t('If enabled, users with appropriate permissions can clear their own flags.'),
      '#default_value' => $config->get('enable_user_clearing'),
    ];

    $form['global_settings']['log_clearing_activity'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log flag clearing activity'),
      '#description' => $this->t('Log when flags are cleared for auditing purposes.'),
      '#default_value' => $config->get('log_clearing_activity'),
    ];

    $form['cron_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Automated Cleanup Settings'),
      '#open' => TRUE,
    ];

    $form['cron_settings']['cron_batch_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Cron batch size'),
      '#description' => $this->t('Number of expired flags to process per cron run. Lower values reduce server load but take longer to clean up.'),
      '#default_value' => $config->get('cron_batch_size'),
      '#min' => 1,
      '#max' => 1000,
      '#step' => 1,
    ];

    $form['terminology'] = [
      '#type' => 'details',
      '#title' => $this->t('User Interface Terminology'),
      '#description' => $this->t('Customize the terminology shown to users to better match your site\'s context.'),
      '#open' => FALSE,
    ];

    $form['terminology']['item_term_singular'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Singular term for items'),
      '#description' => $this->t('What to call a single flagged item (e.g., "item", "bookmark", "favorite"). Default: "item". Max 100 characters.'),
      '#default_value' => $config->get('item_term_singular') ?: 'item',
      '#required' => TRUE,
      '#size' => 30,
      '#maxlength' => 100,
    ];

    $form['terminology']['item_term_plural'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Plural term for items'),
      '#description' => $this->t('What to call multiple flagged items (e.g., "items", "bookmarks", "favorites"). Default: "items". Max 100 characters.'),
      '#default_value' => $config->get('item_term_plural') ?: 'items',
      '#required' => TRUE,
      '#size' => 30,
      '#maxlength' => 100,
    ];

    $form['terminology']['clear_action_term'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Action term for clearing'),
      '#description' => $this->t('The action word for clearing items (e.g., "clear", "remove", "delete"). Default: "clear". Max 100 characters.'),
      '#default_value' => $config->get('clear_action_term') ?: 'clear',
      '#required' => TRUE,
      '#size' => 30,
      '#maxlength' => 100,
    ];

    // Flag Access Control section
    $form['flag_access'] = [
      '#type' => 'details',
      '#title' => $this->t('Flag Access Control'),
      '#description' => $this->t('Control which flags users can discover and clear through the flag retention system.'),
      '#open' => FALSE,
    ];

    // Get all available flags
    $flag_service = \Drupal::service('flag');
    $all_flags = $flag_service->getAllFlags();
    $enabled_flags = $config->get('enabled_flags') ?: [];

    if (empty($all_flags)) {
      $form['flag_access']['no_flags'] = [
        '#markup' => '<p><em>' . $this->t('No flags are currently defined on this site. Create some flags first before configuring access controls.') . '</em></p>',
      ];
    } else {
      $flag_options = [];
      foreach ($all_flags as $flag_id => $flag) {
        $flag_options[$flag_id] = $this->t('@flag_name (@flag_id)', [
          '@flag_name' => $flag->label(),
          '@flag_id' => $flag_id,
        ]);
      }

      $form['flag_access']['enabled_flags'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Enabled flags'),
        '#description' => $this->t('Select which flags users can discover and clear. Unchecked flags will be hidden from users entirely.'),
        '#options' => $flag_options,
        '#default_value' => $enabled_flags,
      ];

      $form['flag_access']['flag_access_mode'] = [
        '#type' => 'radios',
        '#title' => $this->t('Access control mode'),
        '#description' => $this->t('How to handle flag access control.'),
        '#options' => [
          'allow_all' => $this->t('Allow all flags (ignore selections above)'),
          'allow_selected' => $this->t('Only allow selected flags'),
        ],
        '#default_value' => $config->get('flag_access_mode') ?: 'allow_all',
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Validates form input for retention configuration.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate retention_days is within acceptable range
    $global_retention_days = $form_state->getValue('global_retention_days');
    if ($global_retention_days !== null && ($global_retention_days < 0 || $global_retention_days > 3650)) {
      $form_state->setErrorByName(
        'global_retention_days',
        $this->t('Retention days must be between 0 and 3650. Provided value: @value', ['@value' => $global_retention_days])
      );
    }

    // Validate cron batch size
    $cron_batch_size = $form_state->getValue('cron_batch_size');
    if ($cron_batch_size !== null && ($cron_batch_size < 1 || $cron_batch_size > 1000)) {
      $form_state->setErrorByName(
        'cron_batch_size',
        $this->t('Cron batch size must be between 1 and 1000. Provided value: @value', ['@value' => $cron_batch_size])
      );
    }

    // Validate terminology fields are not empty and have reasonable length
    $terminology_fields = [
      'item_term_singular' => 'Singular term',
      'item_term_plural' => 'Plural term',
      'clear_action_term' => 'Action term',
    ];

    foreach ($terminology_fields as $field_name => $field_label) {
      $value = trim($form_state->getValue($field_name, ''));

      // Check not empty
      if (empty($value)) {
        $form_state->setErrorByName(
          $field_name,
          $this->t('@label cannot be empty.', ['@label' => $field_label])
        );
        continue;
      }

      // Check length
      if (strlen($value) > 100) {
        $form_state->setErrorByName(
          $field_name,
          $this->t('@label cannot exceed 100 characters. Current length: @length', [
            '@label' => $field_label,
            '@length' => strlen($value),
          ])
        );
      }

      // Check for valid characters (alphanumeric, spaces, hyphens, underscores)
      if (!preg_match('/^[a-zA-Z0-9\s\-_\.]+$/', $value)) {
        $form_state->setErrorByName(
          $field_name,
          $this->t('@label can only contain letters, numbers, spaces, hyphens, underscores, and periods.', ['@label' => $field_label])
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Process enabled_flags to remove unchecked values
    $enabled_flags = array_filter($form_state->getValue('enabled_flags', []));

    $this->config('flag_retention.settings')
      ->set('global_retention_days', $form_state->getValue('global_retention_days'))
      ->set('enable_user_clearing', $form_state->getValue('enable_user_clearing'))
      ->set('log_clearing_activity', $form_state->getValue('log_clearing_activity'))
      ->set('cron_batch_size', $form_state->getValue('cron_batch_size'))
      ->set('item_term_singular', $form_state->getValue('item_term_singular'))
      ->set('item_term_plural', $form_state->getValue('item_term_plural'))
      ->set('clear_action_term', $form_state->getValue('clear_action_term'))
      ->set('enabled_flags', $enabled_flags)
      ->set('flag_access_mode', $form_state->getValue('flag_access_mode'))
      ->save();
  }

}