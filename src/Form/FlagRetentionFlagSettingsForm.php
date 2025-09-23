<?php

namespace Drupal\flag_retention\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\flag_retention\FlagRetentionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for managing flag-specific retention settings.
 */
class FlagRetentionFlagSettingsForm extends FormBase {

  /**
   * The flag retention manager.
   *
   * @var \Drupal\flag_retention\FlagRetentionManager
   */
  protected $retentionManager;

  /**
   * Constructs a new FlagRetentionFlagSettingsForm object.
   */
  public function __construct(FlagRetentionManager $retention_manager) {
    $this->retentionManager = $retention_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('flag_retention.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'flag_retention_flag_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#markup' => '<p>' . $this->t('Configure retention settings for individual flags. These settings override the global defaults.') . '</p>',
    ];

    $flags_with_settings = $this->retentionManager->getAllFlagsWithSettings();

    if (empty($flags_with_settings)) {
      $form['no_flags'] = [
        '#markup' => '<p>' . $this->t('No flags are currently available.') . '</p>',
      ];
      return $form;
    }

    $form['flags'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Flag'),
        $this->t('Description'),
        $this->t('Retention (days)'),
        $this->t('Auto-clear'),
        $this->t('Current count'),
      ],
      '#empty' => $this->t('No flags available.'),
    ];

    foreach ($flags_with_settings as $flag_id => $data) {
      $flag = $data['flag'];
      $clearer = \Drupal::service('flag_retention.clearer');
      $stats = $clearer->getFlagStatistics($flag_id);
      $current_count = isset($stats[$flag_id]) ? $stats[$flag_id]->total_count : 0;

      $form['flags'][$flag_id]['name'] = [
        '#markup' => '<strong>' . $flag->label() . '</strong><br><small>' . $flag_id . '</small>',
      ];

      $form['flags'][$flag_id]['description'] = [
        '#markup' => $flag->get('flag_long') ?: $this->t('No description available'),
      ];

      $form['flags'][$flag_id]['retention_days'] = [
        '#type' => 'number',
        '#default_value' => $data['retention_days'],
        '#min' => 0,
        '#step' => 1,
        '#size' => 8,
        '#field_suffix' => $this->t('days (0 = keep forever)'),
      ];

      $form['flags'][$flag_id]['auto_clear'] = [
        '#type' => 'checkbox',
        '#default_value' => $data['auto_clear'],
        '#title' => $this->t('Enable automatic cleanup'),
        '#title_display' => 'invisible',
      ];

      $form['flags'][$flag_id]['current_count'] = [
        '#markup' => number_format($current_count),
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save settings'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $saved_count = 0;

    foreach ($values['flags'] as $flag_id => $settings) {
      if (is_array($settings)) {
        $retention_days = (int) $settings['retention_days'];
        $auto_clear = (int) $settings['auto_clear'];
        
        $this->retentionManager->saveRetentionSettings($flag_id, $retention_days, $auto_clear);
        $saved_count++;
      }
    }

    $this->messenger()->addMessage(
      $this->t('Saved retention settings for @count flags.', ['@count' => $saved_count])
    );
  }

}