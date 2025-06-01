/**
 * Test Donation Generator Admin JavaScript
 *
 * @package     GiveFaker\TestDonationGenerator
 * @since       1.0.0
 */

class TestDonationGeneratorAdmin {
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
        // Show/hide custom date range
        document.getElementById('date_range').addEventListener('change', this.toggleCustomDateRange);

        // Handle form submission
        document.getElementById('test-donation-generator-form').addEventListener('submit', this.handleFormSubmission);
    }

    /**
     * Toggle custom date range visibility
     */
    toggleCustomDateRange(event) {
        const customDateRange = document.getElementById('custom-date-range');
        customDateRange.style.display = event.target.value === 'custom' ? 'table-row' : 'none';
    }

    /**
     * Handle form submission
     */
    async handleFormSubmission(event) {
        event.preventDefault();

        const button = document.getElementById('generate-donations');
        const spinner = document.querySelector('.spinner');
        const results = document.getElementById('generation-results');

        // Disable form and show loading
        button.disabled = true;
        spinner.classList.add('is-active');
        results.style.display = 'none';

        // Prepare form data
        const formData = new URLSearchParams({
            action: 'generate_test_donations',
            nonce: testDonationGenerator.nonce,
            campaign_id: document.getElementById('campaign_id').value,
            donation_count: document.getElementById('donation_count').value,
            date_range: document.getElementById('date_range').value,
            donation_mode: document.getElementById('donation_mode').value,
            start_date: document.getElementById('start_date').value,
            end_date: document.getElementById('end_date').value
        });

        try {
            // Submit fetch request
            const response = await fetch(testDonationGenerator.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formData
            });

            const data = await response.json();
            TestDonationGeneratorAdmin.handleSuccess(data);
        } catch (error) {
            TestDonationGeneratorAdmin.handleError();
        } finally {
            TestDonationGeneratorAdmin.handleComplete();
        }
    }

    /**
     * Handle fetch success response
     */
    static handleSuccess(response) {
        const results = document.getElementById('generation-results');
        const resultsContent = document.getElementById('results-content');

        resultsContent.innerHTML = response.success
            ? `<div class="notice notice-success"><p>${response.data.message}</p></div>`
            : `<div class="notice notice-error"><p>${response.data.message}</p></div>`;

        results.style.display = 'block';
    }

    /**
     * Handle fetch error response
     */
    static handleError() {
        const results = document.getElementById('generation-results');
        const resultsContent = document.getElementById('results-content');

        resultsContent.innerHTML = `<div class="notice notice-error"><p>${testDonationGenerator.strings.errorMessage}</p></div>`;
        results.style.display = 'block';
    }

    /**
     * Handle fetch complete (always runs)
     */
    static handleComplete() {
        const button = document.getElementById('generate-donations');
        const spinner = document.querySelector('.spinner');

        // Re-enable form
        button.disabled = false;
        spinner.classList.remove('is-active');
    }
}

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', () => {
    // Only initialize if we're on the correct page
    if (document.getElementById('test-donation-generator-form')) {
        const admin = new TestDonationGeneratorAdmin();
        admin.init();
    }
});
