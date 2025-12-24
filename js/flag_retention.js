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

      // Handle AJAX modal links with CSRF token validation
      once('flag-retention-modal', '.flag-retention-clear-link.use-ajax, .flag-retention-clear-button.use-ajax', context).forEach(function (element) {
        $(element).click(function (e) {
          // Drupal's AJAX system automatically handles CSRF tokens for form submissions.
          // The token is included in the form's hidden field via the CSRF token service.
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

      // Ensure CSRF token is present in form submissions
      once('flag-retention-csrf', '.flag-retention-clear-form', context).forEach(function (element) {
        var $form = $(element);
        // Verify the form has the required CSRF token field
        if ($form.find('input[name="_token"]').length === 0) {
          console.warn('Flag retention form may be missing CSRF token protection.');
        }
      });
    }
  };

  /**
   * Theme function for flag retention confirmation dialogs.
   */
  Drupal.theme.flagRetentionConfirm = function (message) {
    var div = document.createElement('div');
    div.className = 'flag-retention-confirm-dialog';
    div.textContent = message;  // Safely escape message via textContent
    return div;
  };

  /**
   * Helper to announce messages to screen readers (Issue #5).
   */
  Drupal.flagRetention = Drupal.flagRetention || {};
  
  Drupal.flagRetention.announce = function(message, priority) {
    if (typeof Drupal.announce === 'function') {
      Drupal.announce(message, priority);
    } else {
      // Fallback for older Drupal versions
      var $liveRegion = $('#drupal-live-announce');
      if (!$liveRegion.length) {
        $liveRegion = $('<div id="drupal-live-announce" aria-live="polite" aria-atomic="true" class="visually-hidden"></div>');
        $('body').append($liveRegion);
      }
      $liveRegion.text(message);
    }
  };

})(jQuery, Drupal, once);