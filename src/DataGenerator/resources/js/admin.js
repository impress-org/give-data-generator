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

        // Show/hide custom date range for subscriptions tab
        const subscriptionDateRangeSelect = document.getElementById('subscription_date_range');
        if (subscriptionDateRangeSelect) {
            subscriptionDateRangeSelect.addEventListener('change', this.toggleSubscriptionCustomDateRange);
        }

        // Show/hide specific donor selection for donations tab
        const donorCreationMethodSelect = document.getElementById('donor_creation_method');
        if (donorCreationMethodSelect) {
            donorCreationMethodSelect.addEventListener('change', this.toggleSpecificDonorSelection);
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

        // Handle subscription form submission
        const subscriptionForm = document.getElementById('subscription-generator-form');
        if (subscriptionForm) {
            subscriptionForm.addEventListener('submit', this.handleSubscriptionFormSubmission);
        }
    }

    /**
     * Toggle custom date range visibility for donations
     */
    toggleCustomDateRange(event) {
        const customDateRange = document.getElementById('custom-date-range');
        customDateRange.style.display = event.target.value === 'custom' ? 'table-row' : 'none';
    }

    /**
     * Toggle custom date range visibility for subscriptions
     */
    toggleSubscriptionCustomDateRange(event) {
        const customDateRange = document.getElementById('subscription-custom-date-range');
        customDateRange.style.display = event.target.value === 'custom' ? 'table-row' : 'none';
    }

    /**
     * Toggle specific donor selection visibility
     */
    toggleSpecificDonorSelection(event) {
        const specificDonorSelection = document.getElementById('specific-donor-selection');
        specificDonorSelection.style.display = event.target.value === 'select_specific' ? 'table-row' : 'none';
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
            donor_creation_method: document.getElementById('donor_creation_method').value,
            selected_donor_id: document.getElementById('selected_donor_id').value,
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
            campaign_title_prefix: document.getElementById('campaign_title_prefix').value,
            image_source: document.getElementById('image_source').value
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
     * Handle subscription form submission
     */
    async handleSubscriptionFormSubmission(event) {
        event.preventDefault();

        const button = document.getElementById('generate-subscriptions');
        const spinner = document.querySelector('#subscriptions-panel .spinner');
        const results = document.getElementById('subscription-generation-results');

        // Disable form and show loading
        button.disabled = true;
        spinner.classList.add('is-active');
        results.style.display = 'none';

        // Prepare form data
        const formData = new URLSearchParams({
            action: 'generate_test_subscriptions',
            nonce: dataGenerator.subscriptionNonce,
            campaign_id: document.getElementById('subscription_campaign_id').value,
            subscription_count: document.getElementById('subscription_count').value,
            date_range: document.getElementById('subscription_date_range').value,
            subscription_mode: document.getElementById('subscription_mode').value,
            subscription_status: document.getElementById('subscription_status').value,
            subscription_period: document.getElementById('subscription_period').value,
            frequency: document.getElementById('subscription_frequency').value,
            installments: document.getElementById('subscription_installments').value,
            renewals_count: document.getElementById('subscription_renewals_count').value,
            start_date: document.getElementById('subscription_start_date').value,
            end_date: document.getElementById('subscription_end_date').value
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
            DataGeneratorAdmin.handleSuccess(data, 'subscription-generation-results', 'subscription-results-content');
        } catch (error) {
            DataGeneratorAdmin.handleError('subscription-generation-results', 'subscription-results-content');
        } finally {
            DataGeneratorAdmin.handleComplete('generate-subscriptions', '#subscriptions-panel .spinner');
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
    if (document.getElementById('donation-generator-form') ||
        document.getElementById('campaign-generator-form') ||
        document.getElementById('subscription-generator-form')) {
        const admin = new DataGeneratorAdmin();
        admin.init();
    }
});
