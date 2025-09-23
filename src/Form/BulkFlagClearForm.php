<?php

namespace Drupal\flag_retention\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\flag_retention\FlagClearer;
use Drupal\flag\FlagServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for bulk clearing flags.
 */
class BulkFlagClearForm extends FormBase {

  /**
   * The flag clearer service.
   *
   * @var \Drupal\flag_retention\FlagClearer
   */
  protected $flagClearer;

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * Constructs a new BulkFlagClearForm object.
   */
  public function __construct(FlagClearer $flag_clearer, FlagServiceInterface $flag_service) {
    $this->flagClearer = $flag_clearer;
    $this->flagService = $flag_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('flag_retention.clearer'),
      $container->get('flag')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bulk_flag_clear_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#markup' => '<p>' . $this->t('Use this form to perform bulk flag clearing operations.') . '</p>',
    ];

    $flags = $this->flagService->getAllFlags();
    $flag_options = [];
    
    foreach ($flags as $flag) {
      $stats = $this->flagClearer->getFlagStatistics($flag->id());
      $count = isset($stats[$flag->id()]) ? $stats[$flag->id()]->total_count : 0;
      $flag_options[$flag->id()] = sprintf('%s (%s flags)', $flag->label(), number_format($count));
    }

    $form['operation'] = [
      '#type' => 'radios',
      '#title' => $this->t('Clear operation'),
      '#options' => [
        'by_flag' => $this->t('Clear all flags of specific types'),
        'by_age' => $this->t('Clear flags older than specified days'),
      ],
      '#default_value' => 'by_flag',
      '#required' => TRUE,
    ];

    $form['flag_selection'] = [
      '#type' => 'details',
      '#title' => $this->t('Flag selection'),
      '#open' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="operation"]' => ['value' => 'by_flag'],
        ],
      ],
    ];

    $form['flag_selection']['selected_flags'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select flags to clear'),
      '#options' => $flag_options,
      '#description' => $this->t('Select which flag types to clear completely.'),
    ];

    $form['age_selection'] = [
      '#type' => 'details',
      '#title' => $this->t('Age-based clearing'),
      '#open' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="operation"]' => ['value' => 'by_age'],
        ],
      ],
    ];

    $form['age_selection']['flag_for_age'] = [
      '#type' => 'select',
      '#title' => $this->t('Flag type'),
      '#options' => $flag_options,
      '#description' => $this->t('Select the flag type to clear by age.'),
      '#empty_option' => $this->t('- Select a flag type -'),
    ];

    $form['age_selection']['days_old'] = [
      '#type' => 'number',
      '#title' => $this->t('Days old'),
      '#description' => $this->t('Clear flags older than this many days.'),
      '#min' => 1,
      '#step' => 1,
      '#default_value' => 30,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear flags'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $operation = $form_state->getValue('operation');
    
    if ($operation === 'by_flag') {
      $selected_flags = array_filter($form_state->getValue('selected_flags', []));
      if (empty($selected_flags)) {
        $form_state->setErrorByName('selected_flags', $this->t('Please select at least one flag type to clear.'));
      }
    }
    elseif ($operation === 'by_age') {
      $flag_for_age = $form_state->getValue('flag_for_age');
      if (empty($flag_for_age)) {
        $form_state->setErrorByName('flag_for_age', $this->t('Please select a flag type for age-based clearing.'));
      }
      
      $days_old = $form_state->getValue('days_old');
      if (!$days_old || $days_old < 1) {
        $form_state->setErrorByName('days_old', $this->t('Please enter a valid number of days (minimum 1).'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $operation = $form_state->getValue('operation');
    $total_cleared = 0;

    if ($operation === 'by_flag') {
      $selected_flags = array_filter($form_state->getValue('selected_flags', []));
      
      foreach ($selected_flags as $flag_id) {
        $cleared = $this->flagClearer->clearAllFlagsByType($flag_id);
        $total_cleared += $cleared;
      }
      
      $this->messenger()->addMessage(
        $this->t('Cleared @count flags across @flag_count flag types.', [
          '@count' => $total_cleared,
          '@flag_count' => count($selected_flags),
        ])
      );
    }
    elseif ($operation === 'by_age') {
      $flag_id = $form_state->getValue('flag_for_age');
      $days_old = $form_state->getValue('days_old');
      
      $cleared = $this->flagClearer->clearOldFlags($flag_id, $days_old);
      $total_cleared = $cleared;
      
      $flag = $this->flagService->getFlagById($flag_id);
      $flag_name = $flag ? $flag->label() : $flag_id;
      
      $this->messenger()->addMessage(
        $this->t('Cleared @count flags of type "@flag_name" older than @days days.', [
          '@count' => $cleared,
          '@flag_name' => $flag_name,
          '@days' => $days_old,
        ])
      );
    }

    if ($total_cleared === 0) {
      $this->messenger()->addWarning($this->t('No flags were found matching the specified criteria.'));
    }
  }

}