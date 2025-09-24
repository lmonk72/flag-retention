<?php

namespace Drupal\flag_retention\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\SettingsCommand;
use Drupal\Core\Form\FormBase;
use Drupal\flag_retention\Ajax\RefreshPageCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\flag_retention\FlagClearer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Form for users to clear their own flags.
 */
class UserClearForm extends FormBase {

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
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new UserClearForm.
   */
  public function __construct(FlagClearer $flag_clearer, AccountInterface $current_user, MessengerInterface $messenger, RequestStack $request_stack) {
    $this->flagClearer = $flag_clearer;
    $this->currentUser = $current_user;
    $this->messenger = $messenger;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('flag_retention.clearer'),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'flag_retention_user_clear_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $user = NULL) {
    // If no user specified, use current user.
    if (!$user) {
      $user = $this->currentUser->id();
    }

    // Ensure user can only clear their own flags (unless admin).
    if ($user != $this->currentUser->id() && !$this->currentUser->hasPermission('clear all flags')) {
      $this->messenger->addError($this->t('You can only clear your own flags.'));
      return $form;
    }

    // Get user's flag counts.
    $flag_counts = $this->flagClearer->getUserFlagCount($user);
    $total_flags = 0;
    
    if (empty($flag_counts)) {
      $form['no_flags'] = [
        '#markup' => '<p>' . $this->t('You have no items to clear.') . '</p>',
      ];
      
      if ($this->isAjaxRequest()) {
        $form['close'] = [
          '#type' => 'button',
          '#value' => $this->t('Close'),
          '#ajax' => [
            'callback' => '::closeModal',
          ],
        ];
      }
      
      return $form;
    }

    $flag_service = \Drupal::service('flag');
    $options = [];
    $flag_labels = [];
    
    foreach ($flag_counts as $flag_id => $data) {
      $flag = $flag_service->getFlagById($flag_id);
      if ($flag) {
        $flag_labels[$flag_id] = $flag->label();
        $options[$flag_id] = $flag->label() . ' (' . $data->count . ' items)';
        $total_flags += $data->count;
      }
    }

    // Get custom terminology from configuration
    $config = \Drupal::config('flag_retention.settings');
    $item_term_singular = $config->get('item_term_singular') ?: 'item';
    $item_term_plural = $config->get('item_term_plural') ?: 'items';
    $clear_action_term = $config->get('clear_action_term') ?: 'clear';

    // If only one flag type, skip selection and show summary
    if (count($options) === 1) {
      $flag_id = key($options);
      $flag_label = $flag_labels[$flag_id];
      $count = array_values($flag_counts)[0]->count;
      
      $form['description'] = [
        '#markup' => '<p>' . $this->t('You are about to @action @count @items from @label.', [
          '@action' => $clear_action_term,
          '@count' => $count,
          '@items' => $count == 1 ? $item_term_singular : $item_term_plural,
          '@label' => $flag_label,
        ]) . '</p>',
      ];

      $form['single_flag'] = [
        '#type' => 'hidden',
        '#value' => $flag_id,
      ];
    } else {
      // Multiple flag types - show selection
      $form['description'] = [
        '#markup' => '<p>' . $this->t('You have @count total @items. Select which types to @action:', [
          '@count' => $total_flags,
          '@items' => $total_flags == 1 ? $item_term_singular : $item_term_plural,
          '@action' => $clear_action_term,
        ]) . '</p>',
      ];

      // Update options to use custom terminology
      foreach ($options as $flag_id => &$option) {
        $count = $flag_counts[$flag_id]->count;
        $option = $flag_labels[$flag_id] . ' (' . $count . ' ' . ($count == 1 ? $item_term_singular : $item_term_plural) . ')';
      }

      $form['flags'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Types to @action', ['@action' => $clear_action_term]),
        '#options' => $options,
        '#default_value' => array_keys($options),
        '#required' => TRUE,
      ];
    }

    $form['confirm'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I understand that this action cannot be undone'),
      '#required' => TRUE,
    ];

    $form['user_id'] = [
      '#type' => 'hidden',
      '#value' => $user,
    ];

    // Get custom terminology for button text
    $button_text = ucfirst($clear_action_term);
    
    if (count($options) === 1) {
      $button_text .= ' ' . ucfirst($item_term_plural);
    } else {
      $button_text .= ' Selected';
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $button_text,
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('<front>'),
      '#attributes' => ['class' => ['button']],
    ];

    // Add AJAX support if this is a modal request.
    if ($this->isAjaxRequest()) {
      $form['actions']['submit']['#ajax'] = [
        'callback' => '::submitAjaxForm',
        'wrapper' => 'flag-retention-user-clear-form',
      ];
      
      $form['actions']['cancel'] = [
        '#type' => 'button',
        '#value' => $this->t('Cancel'),
        '#ajax' => [
          'callback' => '::closeModal',
        ],
      ];
    }

    $form['#prefix'] = '<div id="flag-retention-user-clear-form" class="flag-retention-modal-form">';
    $form['#suffix'] = '</div>';

    if ($this->isAjaxRequest()) {
      $form['#attached']['library'][] = 'flag_retention/flag_retention_modal';
    } else {
      $form['#attached']['library'][] = 'flag_retention/flag_retention';
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Handle single flag case
    if ($single_flag = $form_state->getValue('single_flag')) {
      // Check if single flag is allowed
      if (!$this->flagClearer->isFlagAllowed($single_flag)) {
        $form_state->setErrorByName('single_flag', $this->t('The selected flag type is not available for clearing.'));
      }
      return;
    }
    
    $selected_flags = array_filter($form_state->getValue('flags', []));
    
    if (empty($selected_flags)) {
      $config = \Drupal::config('flag_retention.settings');
      $clear_action_term = $config->get('clear_action_term') ?: 'clear';
      $form_state->setErrorByName('flags', $this->t('Please select at least one type to @action.', ['@action' => $clear_action_term]));
      return;
    }

    // Validate that all selected flags are allowed
    foreach ($selected_flags as $flag_id) {
      if (!$this->flagClearer->isFlagAllowed($flag_id)) {
        $form_state->setErrorByName('flags', $this->t('One or more selected flag types are not available for clearing.'));
        break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Skip processing if this is an AJAX request - let the AJAX handler do the work
    if ($this->isAjaxRequest()) {
      return;
    }
    
    $user_id = $form_state->getValue('user_id');
    $total_cleared = 0;
    
    // Handle single flag case
    if ($single_flag = $form_state->getValue('single_flag')) {
      $selected_flags = [$single_flag];
    } else {
      $selected_flags = array_filter($form_state->getValue('flags', []));
    }

    foreach ($selected_flags as $flag_id) {
      $cleared = $this->flagClearer->clearUserFlags($user_id, $flag_id);
      $total_cleared += $cleared;
    }

    // Get custom terminology for success message
    $config = \Drupal::config('flag_retention.settings');
    $item_term_plural = $config->get('item_term_plural') ?: 'items';
    $clear_action_term = $config->get('clear_action_term') ?: 'cleared';

    if ($total_cleared > 0) {
      $this->messenger->addStatus($this->t('Successfully @action @count @items.', [
        '@action' => $clear_action_term,
        '@count' => $total_cleared,
        '@items' => $total_cleared == 1 ? ($config->get('item_term_singular') ?: 'item') : $item_term_plural,
      ]));
    } else {
      $this->messenger->addWarning($this->t('No @items were @action.', [
        '@items' => $item_term_plural,
        '@action' => $clear_action_term,
      ]));
    }

    // Don't redirect if this is an AJAX request - let the AJAX handler manage it.
    if (!$this->isAjaxRequest()) {
      // Stay on the current page instead of redirecting to homepage
      $request = $this->requestStack->getCurrentRequest();
      if ($request && $request->headers->get('referer')) {
        $form_state->setRedirectUrl(Url::fromUri($request->headers->get('referer')));
      }
      // If no referer, just don't set any redirect (stay on current page)
    }
  }

  /**
   * AJAX callback for form submission.
   */
  public function submitAjaxForm(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    
    if ($form_state->hasAnyErrors()) {
      // Return form with errors.
      $response->addCommand(new HtmlCommand('#flag-retention-user-clear-form', $form));
      return $response;
    }
    
    // Process the clearing logic directly here
    $user_id = $form_state->getValue('user_id');
    $total_cleared = 0;
    
    // Handle single flag case
    if ($single_flag = $form_state->getValue('single_flag')) {
      $selected_flags = [$single_flag];
    } else {
      $selected_flags = array_filter($form_state->getValue('flags', []));
    }

    foreach ($selected_flags as $flag_id) {
      $cleared = $this->flagClearer->clearUserFlags($user_id, $flag_id);
      $total_cleared += $cleared;
    }

    // Get custom terminology for success message
    $config = \Drupal::config('flag_retention.settings');
    $item_term_plural = $config->get('item_term_plural') ?: 'items';
    $item_term_singular = $config->get('item_term_singular') ?: 'item';
    $clear_action_term = $config->get('clear_action_term') ?: 'cleared';

    // Create success/warning message
    if ($total_cleared > 0) {
      $message = $this->t('Successfully @action @count @items.', [
        '@action' => $clear_action_term,
        '@count' => $total_cleared,
        '@items' => $total_cleared == 1 ? $item_term_singular : $item_term_plural,
      ]);
      $message_type = 'status';
    } else {
      $message = $this->t('No @items were @action.', [
        '@items' => $item_term_plural,
        '@action' => $clear_action_term,
      ]);
      $message_type = 'warning';
    }
    
    // Close modal and add message
    $response->addCommand(new CloseModalDialogCommand());
    $response->addCommand(new MessageCommand($message, null, ['type' => $message_type]));
    
    // Add a JavaScript command to refresh the page after showing the message
    if ($total_cleared > 0) {
      // Only refresh if items were actually cleared
      $response->addCommand(new RefreshPageCommand(1500));
    }
    
    return $response;
  }

  /**
   * AJAX callback to close modal.
   */
  public function closeModal(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * Check if this is an AJAX request.
   */
  protected function isAjaxRequest() {
    $request = $this->requestStack->getCurrentRequest();
    return $request && $request->isXmlHttpRequest();
  }

}