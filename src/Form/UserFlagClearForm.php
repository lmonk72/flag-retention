<?php

namespace Drupal\flag_retention\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\flag_retention\FlagClearer;
use Drupal\flag\FlagServiceInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for users to clear their own flags.
 */
class UserFlagClearForm extends ConfirmFormBase {

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
   * The user whose flags are being cleared.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * Constructs a new UserFlagClearForm object.
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
    return 'user_flag_clear_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, UserInterface $user = NULL) {
    $this->user = $user;

    if (!$user) {
      $form['error'] = [
        '#markup' => $this->t('Invalid user.'),
      ];
      return $form;
    }

    // Get user's flag counts.
    $flag_counts = $this->flagClearer->getUserFlagCount($user->id());
    
    if (empty($flag_counts)) {
      $form['no_flags'] = [
        '#markup' => '<p>' . $this->t('You have no flags to clear.') . '</p>',
      ];
      return $form;
    }

    $form['description'] = [
      '#markup' => '<p>' . $this->t('You can clear your flags by type. This action cannot be undone.') . '</p>',
    ];

    $form['flag_selection'] = [
      '#type' => 'details',
      '#title' => $this->t('Select flags to clear'),
      '#open' => TRUE,
    ];

    $flags = $this->flagService->getAllFlags();
    $options = [];
    $descriptions = [];

    foreach ($flag_counts as $flag_id => $data) {
      if (isset($flags[$flag_id])) {
        $flag = $flags[$flag_id];
        $count = $data->count;
        $options[$flag_id] = sprintf('%s (%d flags)', $flag->label(), $count);
        $descriptions[] = sprintf('<strong>%s:</strong> %d flags', $flag->label(), $count);
      }
    }

    $form['flag_selection']['clear_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Clear ALL my flags'),
      '#description' => $this->t('Check this to clear all your flags at once.'),
    ];

    $form['flag_selection']['selected_flags'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Or select specific flag types'),
      '#options' => $options,
      '#description' => $this->t('Select which flag types to clear.'),
      '#states' => [
        'visible' => [
          ':input[name="clear_all"]' => ['checked' => FALSE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to clear your flags?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.user.canonical', ['user' => $this->user->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone. Your selected flags will be permanently removed.');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $clear_all = $form_state->getValue('clear_all');
    $selected_flags = array_filter($form_state->getValue('selected_flags', []));
    
    $total_cleared = 0;

    if ($clear_all) {
      $total_cleared = $this->flagClearer->clearUserFlags($this->user->id());
      $this->messenger()->addMessage(
        $this->t('Cleared all @count of your flags.', ['@count' => $total_cleared])
      );
    }
    elseif (!empty($selected_flags)) {
      foreach ($selected_flags as $flag_id) {
        $cleared = $this->flagClearer->clearUserFlags($this->user->id(), $flag_id);
        $total_cleared += $cleared;
      }
      
      $this->messenger()->addMessage(
        $this->t('Cleared @count flags.', ['@count' => $total_cleared])
      );
    }
    else {
      $this->messenger()->addWarning($this->t('No flags were selected for clearing.'));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}