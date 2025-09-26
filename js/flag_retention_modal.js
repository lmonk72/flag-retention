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
          }
        });

        // Handle errors gracefully
        $(document).on('ajaxError.flagRetentionModal', function (event, xhr, settings) {
          if (settings.url && settings.url.includes('flag-clear')) {
            console.warn('Flag retention AJAX error:', xhr);
            // Show fallback message
            if (xhr.status === 403) {
              alert(Drupal.t('You do not have permission to clear flags.'));
            } else {
              alert(Drupal.t('An error occurred while clearing flags. Please try again.'));
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
        var firstFormElement = $(dialogElement).find('.form-element').first();
        if (firstFormElement.length > 0) {
          firstFormElement.focus();
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