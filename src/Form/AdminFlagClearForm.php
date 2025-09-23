<?php

namespace Drupal\flag_retention\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\flag_retention\FlagClearer;
use Drupal\flag\FlagServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for admins to clear all flags of a specific type.
 */
class AdminFlagClearForm extends ConfirmFormBase {

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
   * The flag ID being cleared.
   *
   * @var string
   */
  protected $flagId;

  /**
   * Constructs a new AdminFlagClearForm object.
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
    return 'admin_flag_clear_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $flag_id = NULL) {
    $this->flagId = $flag_id;

    if (!$flag_id) {
      $form['error'] = [
        '#markup' => $this->t('Invalid flag ID.'),
      ];
      return $form;
    }

    $flag = $this->flagService->getFlagById($flag_id);
    if (!$flag) {
      $form['error'] = [
        '#markup' => $this->t('Flag not found.'),
      ];
      return $form;
    }

    // Get current flag statistics.
    $stats = $this->flagClearer->getFlagStatistics($flag_id);
    $current_count = isset($stats[$flag_id]) ? $stats[$flag_id]->total_count : 0;
    $unique_users = isset($stats[$flag_id]) ? $stats[$flag_id]->unique_users : 0;

    $form['info'] = [
      '#type' => 'item',
      '#markup' => '<div class="messages messages--warning">' . 
        $this->t('You are about to clear <strong>@count flags</strong> of type "<strong>@flag_name</strong>" affecting <strong>@users users</strong>.', [
          '@count' => number_format($current_count),
          '@flag_name' => $flag->label(),
          '@users' => number_format($unique_users),
        ]) . '</div>',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $flag = $this->flagService->getFlagById($this->flagId);
    return $this->t('Are you sure you want to clear all flags of type "@flag_name"?', [
      '@flag_name' => $flag ? $flag->label() : $this->flagId,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('flag_retention.flag_settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone. All flags of this type will be permanently removed from the system.');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $cleared_count = $this->flagClearer->clearAllFlagsByType($this->flagId);
    
    $flag = $this->flagService->getFlagById($this->flagId);
    $flag_name = $flag ? $flag->label() : $this->flagId;

    if ($cleared_count > 0) {
      $this->messenger()->addMessage(
        $this->t('Successfully cleared @count flags of type "@flag_name".', [
          '@count' => $cleared_count,
          '@flag_name' => $flag_name,
        ])
      );
    }
    else {
      $this->messenger()->addMessage($this->t('No flags were found to clear.'));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}