/**
 * @file
 * Flag Retention JavaScript.
 */

(function ($, Drupal, once) {

  'use strict';

  /**
   * Flag retention behaviors.
   */
  Drupal.behaviors.flagRetention = {
    attach: function (context, settings) {
      // Confirmation for flag clearing actions
      once('flag-retention-confirm', '.flag-retention-clear-link:not(.use-ajax)', context).forEach(function (element) {
        $(element).click(function (e) {
          if (!confirm(Drupal.t('Are you sure you want to clear these flags? This action cannot be undone.'))) {
            e.preventDefault();
            return false;
          }
        });
      });

      // Handle AJAX modal links
      once('flag-retention-modal', '.flag-retention-clear-link.use-ajax, .flag-retention-clear-button.use-ajax', context).forEach(function (element) {
        $(element).click(function (e) {
          // Let Drupal's AJAX system handle the modal
        $(this).addClass('flag-retention-modal-trigger');
        });
      });

      // Style form elements consistently
      once('flag-retention-button', '.flag-retention-clear-form input[type="submit"]', context).forEach(function (element) {
        $(element).addClass('btn btn-primary');
      });
      once('flag-retention-button', '.flag-retention-clear-form input[type="button"], .flag-retention-clear-form .button', context).forEach(function (element) {
        $(element).addClass('btn btn-secondary');
      });
    }
  };

  /**
   * Theme function for flag retention confirmation dialogs.
   */
  Drupal.theme.flagRetentionConfirm = function (message) {
    return '<div class="flag-retention-confirm-dialog">' + message + '</div>';
  };

})(jQuery, Drupal, once);