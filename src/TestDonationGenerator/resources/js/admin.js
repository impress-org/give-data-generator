/**
 * Test Donation Generator Admin JavaScript
 *
 * @package     GiveFaker\TestDonationGenerator
 * @since       1.0.0
 */

(function($) {
    'use strict';

    /**
     * Test Donation Generator Admin Object
     */
    var TestDonationGeneratorAdmin = {

        /**
         * Initialize the admin functionality
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Show/hide custom date range
            $('#date_range').on('change', this.toggleCustomDateRange);

            // Handle form submission
            $('#test-donation-generator-form').on('submit', this.handleFormSubmission);
        },

        /**
         * Toggle custom date range visibility
         */
        toggleCustomDateRange: function() {
            var $customDateRange = $('#custom-date-range');

            if ($(this).val() === 'custom') {
                $customDateRange.show();
            } else {
                $customDateRange.hide();
            }
        },

        /**
         * Handle form submission
         */
        handleFormSubmission: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $('#generate-donations');
            var $spinner = $('.spinner');
            var $results = $('#generation-results');

            // Disable form and show loading
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            $results.hide();

            // Prepare form data
            var formData = {
                action: 'generate_test_donations',
                nonce: testDonationGenerator.nonce,
                campaign_id: $('#campaign_id').val(),
                donation_count: $('#donation_count').val(),
                date_range: $('#date_range').val(),
                donation_mode: $('#donation_mode').val(),
                start_date: $('#start_date').val(),
                end_date: $('#end_date').val()
            };

            // Submit AJAX request
            $.ajax({
                url: testDonationGenerator.ajaxUrl,
                type: 'POST',
                data: formData,
                success: TestDonationGeneratorAdmin.handleSuccess,
                error: TestDonationGeneratorAdmin.handleError,
                complete: TestDonationGeneratorAdmin.handleComplete
            });
        },

        /**
         * Handle AJAX success response
         */
        handleSuccess: function(response) {
            var $results = $('#generation-results');
            var $resultsContent = $('#results-content');

            if (response.success) {
                $resultsContent.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
            } else {
                $resultsContent.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
            }

            $results.show();
        },

        /**
         * Handle AJAX error response
         */
        handleError: function() {
            var $results = $('#generation-results');
            var $resultsContent = $('#results-content');

            $resultsContent.html('<div class="notice notice-error"><p>' + testDonationGenerator.strings.errorMessage + '</p></div>');
            $results.show();
        },

        /**
         * Handle AJAX complete (always runs)
         */
        handleComplete: function() {
            var $button = $('#generate-donations');
            var $spinner = $('.spinner');

            // Re-enable form
            $button.prop('disabled', false);
            $spinner.removeClass('is-active');
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        // Only initialize if we're on the correct page
        if ($('#test-donation-generator-form').length) {
            TestDonationGeneratorAdmin.init();
        }
    });

})(jQuery);
