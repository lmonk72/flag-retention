/**
 * @file
 * Flag Retention JavaScript.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Flag retention behaviors.
   */
  Drupal.behaviors.flagRetention = {
    attach: function (context, settings) {
      // Confirmation for flag clearing actions
      $('.flag-retention-clear-link:not(.use-ajax)', context).once('flag-retention-confirm').click(function (e) {
        if (!confirm(Drupal.t('Are you sure you want to clear these flags? This action cannot be undone.'))) {
          e.preventDefault();
          return false;
        }
      });

      // Handle AJAX modal links
      $('.flag-retention-clear-link.use-ajax, .flag-retention-clear-button.use-ajax', context).once('flag-retention-modal').click(function (e) {
        // Let Drupal's AJAX system handle the modal
        $(this).addClass('flag-retention-modal-trigger');
      });

      // Style form elements consistently
      $('.flag-retention-clear-form input[type="submit"]', context).once('flag-retention-button').addClass('btn btn-primary');
      $('.flag-retention-clear-form input[type="button"], .flag-retention-clear-form .button', context).once('flag-retention-button').addClass('btn btn-secondary');
    }
  };

  /**
   * Theme function for flag retention confirmation dialogs.
   */
  Drupal.theme.flagRetentionConfirm = function (message) {
    return '<div class="flag-retention-confirm-dialog">' + message + '</div>';
  };

})(jQuery, Drupal);