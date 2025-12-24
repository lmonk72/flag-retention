/**
 * @file
 * Flag Retention modal JavaScript.
 */

(function ($, Drupal, once) {

  'use strict';

  /**
   * Custom AJAX command to refresh the page.
   */
  Drupal.AjaxCommands.prototype.flagRetentionRefreshPage = function (ajax, response, status) {
    var delay = response.delay || 1500;
    setTimeout(function() {
      window.location.reload();
    }, delay);
  };

  /**
   * Helper namespace for flag retention utilities.
   */
  Drupal.flagRetention = Drupal.flagRetention || {};

  /**
   * Initialize keyboard navigation for modal dialogs.
   * Implements focus trapping and Escape key handling.
   */
  Drupal.flagRetention.initKeyboardNavigation = function($dialog) {
    if (!$dialog || !$dialog.length) {
      return;
    }

    // Store the element that triggered the modal
    var $triggerElement = $(document.activeElement);
    $dialog.data('trigger-element', $triggerElement);

    // Get all focusable elements within the dialog
    var focusableSelectors = 'a[href], area[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])';
    
    var getFocusableElements = function() {
      return $dialog.find(focusableSelectors).filter(':visible');
    };

    // Handle Escape key to close modal
    var escapeHandler = function(e) {
      if (e.key === 'Escape' || e.keyCode === 27) {
        var $closeButton = $dialog.find('.ui-dialog-titlebar-close');
        if ($closeButton.length) {
          $closeButton.trigger('click');
        }
        e.preventDefault();
      }
    };

    // Handle Tab key for focus trapping
    var tabHandler = function(e) {
      if (e.key !== 'Tab' && e.keyCode !== 9) {
        return;
      }

      var $focusableElements = getFocusableElements();
      
      if ($focusableElements.length === 0) {
        return;
      }

      var firstElement = $focusableElements.first()[0];
      var lastElement = $focusableElements.last()[0];

      // Shift + Tab on first element: go to last
      if (e.shiftKey && document.activeElement === firstElement) {
        lastElement.focus();
        e.preventDefault();
      }
      // Tab on last element: go to first
      else if (!e.shiftKey && document.activeElement === lastElement) {
        firstElement.focus();
        e.preventDefault();
      }
    };

    // Attach keyboard handlers
    $dialog.on('keydown.flagRetentionModal', function(e) {
      escapeHandler(e);
      tabHandler(e);
    });

    // Return focus when dialog closes
    $dialog.on('dialogclose', function() {
      var $trigger = $dialog.data('trigger-element');
      if ($trigger && $trigger.length) {
        setTimeout(function() {
          $trigger.focus();
        }, 100);
      }
      // Clean up event handlers
      $dialog.off('keydown.flagRetentionModal');
      $dialog.off('dialogclose');
    });
  };

  /**
   * Modal-specific behaviors for flag retention.
   */
  Drupal.behaviors.flagRetentionModal = {
    attach: function (context, settings) {
      // Handle page refresh trigger from AJAX response
      if (settings.flagRetention && settings.flagRetention.refreshPage) {
        setTimeout(function() {
          window.location.reload();
        }, 1500);
        // Clear the setting to prevent multiple refreshes
        delete settings.flagRetention.refreshPage;
      }

      // Custom modal dialog settings for flag retention
      if (typeof Drupal.ajax !== 'undefined') {
        // Override default modal settings for flag retention dialogs
        $(document).on('ajaxStart.flagRetentionModal', function (event) {
          var $trigger = $(event.target);
          if ($trigger.hasClass('flag-retention-modal-trigger') || $trigger.closest('.flag-retention-clear-area, .flag-retention-clear-block').length) {
            // Add custom class to identify flag retention modals
            setTimeout(function () {
              $('.ui-dialog:last').addClass('flag-retention-modal');
              // Initialize keyboard navigation for the modal
              Drupal.flagRetention.initKeyboardNavigation($('.ui-dialog:last'));
            }, 50);
          }
        });

        // Handle form submission feedback
        $(document).on('ajaxSuccess.flagRetentionModal', function (event, xhr, settings) {
          if (settings.url && settings.url.includes('flag-clear')) {
            // Custom success handling for flag clearing
            var $dialog = $('.ui-dialog.flag-retention-modal:last');
            if ($dialog.length) {
              $dialog.find('.ui-dialog-content').addClass('flag-retention-success');
            }
            // Announce success to screen readers (Issue #5)
            if (typeof Drupal.flagRetention !== 'undefined' && typeof Drupal.flagRetention.announce === 'function') {
              Drupal.flagRetention.announce(Drupal.t('Flags successfully cleared'));
            }
          }
        });

        // Handle errors gracefully
        $(document).on('ajaxError.flagRetentionModal', function (event, xhr, settings) {
          if (settings.url && settings.url.includes('flag-clear')) {
            console.warn('Flag retention AJAX error:', xhr);
            var errorMessage;
            // Show fallback message
            if (xhr.status === 403) {
              errorMessage = Drupal.t('You do not have permission to clear flags.');
              alert(errorMessage);
            } else {
              errorMessage = Drupal.t('An error occurred while clearing flags. Please try again.');
              alert(errorMessage);
            }
            // Announce error to screen readers (Issue #5)
            if (typeof Drupal.flagRetention !== 'undefined' && typeof Drupal.flagRetention.announce === 'function') {
              Drupal.flagRetention.announce(errorMessage, 'assertive');
            }
          }
        });
      }

      // Enhance form elements within modals
      once('flag-retention-modal-enhance', '.ui-dialog.flag-retention-modal .form-item', context).forEach(function (element) {
        var $item = $(element);
        
        // Add better styling to checkboxes
        $item.find('input[type="checkbox"]').each(function () {
          $(this).wrap('<label class="flag-retention-checkbox-wrapper"></label>');
        });

        // Enhance required field indicators
        if ($item.find('.form-required').length) {
          $item.addClass('required-field');
        }
      });

      // Auto-focus first form element in flag retention modals
      once('flag-retention-focus', '.ui-dialog.flag-retention-modal', context).forEach(function (dialogElement) {
        var $dialog = $(dialogElement);
        var firstFormElement = $dialog.find('.form-element, input, button, select, textarea').filter(':visible').first();
        if (firstFormElement.length > 0) {
          // Delay focus slightly to ensure modal is fully rendered
          setTimeout(function() {
            firstFormElement.focus();
          }, 100);
        }
      });

      // Handle "Select All" functionality for checkboxes
      once('flag-retention-select-all', '.flag-retention-modal-form', context).forEach(function (element) {
        var $form = $(element);
        var $checkboxes = $form.find('input[type="checkbox"][name^="flags["]');
        
        if ($checkboxes.length > 3) {
          // Add select all/none buttons if there are many flags
          var $selectAllContainer = $('<div class="flag-retention-select-all-container"></div>');
          var $selectAll = $('<button type="button" class="btn btn-sm btn-secondary">' + Drupal.t('Select All') + '</button>');
          var $selectNone = $('<button type="button" class="btn btn-sm btn-secondary">' + Drupal.t('Select None') + '</button>');
          
          $selectAllContainer.append($selectAll, ' ', $selectNone);
          $checkboxes.first().closest('.form-checkboxes').before($selectAllContainer);
          
          $selectAll.click(function (e) {
            e.preventDefault();
            $checkboxes.prop('checked', true);
          });
          
          $selectNone.click(function (e) {
            e.preventDefault();
            $checkboxes.prop('checked', false);
          });
        }
      });
    }
  };

  // Override Drupal's dialog default settings for flag retention
  if (typeof Drupal.dialog !== 'undefined') {
    Drupal.dialog.originalDialog = Drupal.dialog.dialog;
    Drupal.dialog.dialog = function (element, options) {
      if ($(element).closest('.flag-retention-modal-form').length || 
          (options && options.title && options.title.includes('Flag'))) {
        // Apply flag retention specific settings
        options = $.extend({
          width: 600,
          height: 'auto',
          maxHeight: $(window).height() * 0.9,
          resizable: true,
          draggable: true,
          modal: true,
          closeOnEscape: true,
          dialogClass: 'flag-retention-modal'
        }, options);
      }
      return Drupal.dialog.originalDialog.call(this, element, options);
    };
  }

})(jQuery, Drupal, once);