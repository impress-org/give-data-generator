/**
 * Data Generator Admin JavaScript
 *
 * @package     GiveFaker\TestDonationGenerator
 * @since       1.0.0
 */

class DataGeneratorAdmin {
    /**
     * Initialize the admin functionality
     */
    init() {
        this.bindEvents();
    }

    /**
     * Bind event handlers
     */
    bindEvents() {
        // Show/hide custom date range for donations tab
        const dateRangeSelect = document.getElementById('date_range');
        if (dateRangeSelect) {
            dateRangeSelect.addEventListener('change', this.toggleCustomDateRange);
        }

        // Handle donation form submission
        const donationForm = document.getElementById('donation-generator-form');
        if (donationForm) {
            donationForm.addEventListener('submit', this.handleDonationFormSubmission);
        }

        // Handle campaign form submission
        const campaignForm = document.getElementById('campaign-generator-form');
        if (campaignForm) {
            campaignForm.addEventListener('submit', this.handleCampaignFormSubmission);
        }
    }

    /**
     * Toggle custom date range visibility
     */
    toggleCustomDateRange(event) {
        const customDateRange = document.getElementById('custom-date-range');
        customDateRange.style.display = event.target.value === 'custom' ? 'table-row' : 'none';
    }

    /**
     * Handle donation form submission
     */
    async handleDonationFormSubmission(event) {
        event.preventDefault();

        const button = document.getElementById('generate-donations');
        const spinner = document.querySelector('#donations-panel .spinner');
        const results = document.getElementById('generation-results');

        // Disable form and show loading
        button.disabled = true;
        spinner.classList.add('is-active');
        results.style.display = 'none';

        // Prepare form data
        const formData = new URLSearchParams({
            action: 'generate_test_donations',
            nonce: dataGenerator.nonce,
            campaign_id: document.getElementById('campaign_id').value,
            donation_count: document.getElementById('donation_count').value,
            date_range: document.getElementById('date_range').value,
            donation_mode: document.getElementById('donation_mode').value,
            donation_status: document.getElementById('donation_status').value,
            start_date: document.getElementById('start_date').value,
            end_date: document.getElementById('end_date').value
        });

        try {
            // Submit fetch request
            const response = await fetch(dataGenerator.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formData
            });

            const data = await response.json();
            DataGeneratorAdmin.handleSuccess(data, 'generation-results', 'results-content');
        } catch (error) {
            DataGeneratorAdmin.handleError('generation-results', 'results-content');
        } finally {
            DataGeneratorAdmin.handleComplete('generate-donations', '#donations-panel .spinner');
        }
    }

    /**
     * Handle campaign form submission
     */
    async handleCampaignFormSubmission(event) {
        event.preventDefault();

        const button = document.getElementById('generate-campaigns');
        const spinner = document.querySelector('#campaigns-panel .spinner');
        const results = document.getElementById('campaign-generation-results');

        // Disable form and show loading
        button.disabled = true;
        spinner.classList.add('is-active');
        results.style.display = 'none';

        // Prepare form data
        const formData = new URLSearchParams({
            action: 'generate_test_campaigns',
            nonce: dataGenerator.campaignNonce,
            campaign_count: document.getElementById('campaign_count').value,
            campaign_status: document.getElementById('campaign_status').value,
            goal_type: document.getElementById('goal_type').value,
            goal_amount_min: document.getElementById('goal_amount_min').value,
            goal_amount_max: document.getElementById('goal_amount_max').value,
            color_scheme: document.getElementById('color_scheme').value,
            include_short_desc: document.getElementById('include_short_desc').checked ? '1' : '',
            include_long_desc: document.getElementById('include_long_desc').checked ? '1' : '',
            campaign_duration: document.getElementById('campaign_duration').value,
            create_forms: document.getElementById('create_forms').checked ? '1' : '',
            campaign_title_prefix: document.getElementById('campaign_title_prefix').value
        });

        try {
            // Submit fetch request
            const response = await fetch(dataGenerator.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formData
            });

            const data = await response.json();
            DataGeneratorAdmin.handleSuccess(data, 'campaign-generation-results', 'campaign-results-content');
        } catch (error) {
            DataGeneratorAdmin.handleError('campaign-generation-results', 'campaign-results-content');
        } finally {
            DataGeneratorAdmin.handleComplete('generate-campaigns', '#campaigns-panel .spinner');
        }
    }

    /**
     * Handle fetch success response
     */
    static handleSuccess(response, resultsId, contentId) {
        const results = document.getElementById(resultsId);
        const resultsContent = document.getElementById(contentId);

        resultsContent.innerHTML = response.success
            ? `<div class="notice notice-success"><p>${response.data.message}</p></div>`
            : `<div class="notice notice-error"><p>${response.data.message}</p></div>`;

        results.style.display = 'block';
    }

    /**
     * Handle fetch error response
     */
    static handleError(resultsId, contentId) {
        const results = document.getElementById(resultsId);
        const resultsContent = document.getElementById(contentId);

        resultsContent.innerHTML = `<div class="notice notice-error"><p>${dataGenerator.strings.errorMessage}</p></div>`;
        results.style.display = 'block';
    }

    /**
     * Handle fetch complete (always runs)
     */
    static handleComplete(buttonId, spinnerSelector) {
        const button = document.getElementById(buttonId);
        const spinner = document.querySelector(spinnerSelector);

        // Re-enable form
        button.disabled = false;
        spinner.classList.remove('is-active');
    }
}

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', () => {
    // Only initialize if we're on the correct page
    if (document.getElementById('donation-generator-form') || document.getElementById('campaign-generator-form')) {
        const admin = new DataGeneratorAdmin();
        admin.init();
    }
});
