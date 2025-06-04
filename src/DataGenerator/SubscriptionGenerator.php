<?php

namespace GiveDataGenerator\DataGenerator;

use DateTime;
use Exception;
use Give\Campaigns\Models\Campaign;
use Give\Donations\Models\Donation;
use Give\Donations\ValueObjects\DonationMode;
use Give\Donations\ValueObjects\DonationStatus;
use Give\Donations\ValueObjects\DonationType;
use Give\Donors\Models\Donor;
use Give\Framework\Support\ValueObjects\Money;
use Give\Subscriptions\Models\Subscription;
use Give\Subscriptions\ValueObjects\SubscriptionMode;
use Give\Subscriptions\ValueObjects\SubscriptionPeriod;
use Give\Subscriptions\ValueObjects\SubscriptionStatus;

/**
 * Subscription Data Generator.
 *
 * @package     GiveDataGenerator\DataGenerator
 * @since       1.0.0
 */
class SubscriptionGenerator
{
    /**
     * List of sample first names for generating fake donors.
     *
     * @since 1.0.0
     * @var array
     */
    private $firstNames = [
        'John', 'Jane', 'Michael', 'Sarah', 'David', 'Emily', 'Robert', 'Jessica',
        'William', 'Ashley', 'Richard', 'Amanda', 'Joseph', 'Jennifer', 'Thomas',
        'Elizabeth', 'Christopher', 'Melissa', 'Charles', 'Linda', 'Daniel', 'Mary',
        'Matthew', 'Patricia', 'Anthony', 'Susan', 'Mark', 'Nancy', 'Donald', 'Lisa'
    ];

    /**
     * List of sample last names for generating fake donors.
     *
     * @since 1.0.0
     * @var array
     */
    private $lastNames = [
        'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis',
        'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson',
        'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin', 'Lee', 'Perez', 'Thompson',
        'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson'
    ];

    /**
     * List of sample email domains for generating fake emails.
     *
     * @since 1.0.0
     * @var array
     */
    private $emailDomains = [
        'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'example.com',
        'test.com', 'demo.com', 'sample.org', 'mail.com', 'email.com'
    ];

    /**
     * Handle AJAX request for generating test subscriptions.
     *
     * @since 1.0.0
     */
    public function handleAjaxRequest()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subscription_generator_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'give-data-generator')]);
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_give_settings')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'give-data-generator')]);
            return;
        }

        try {
            $campaignId = intval($_POST['campaign_id']);
            $subscriptionCount = intval($_POST['subscription_count']);
            $dateRange = sanitize_text_field($_POST['date_range']);
            $subscriptionMode = sanitize_text_field($_POST['subscription_mode']);
            $subscriptionStatus = sanitize_text_field($_POST['subscription_status']);
            $subscriptionPeriod = sanitize_text_field($_POST['subscription_period']);
            $frequency = intval($_POST['frequency']);
            $installments = intval($_POST['installments']);
            $startDate = sanitize_text_field($_POST['start_date']);
            $endDate = sanitize_text_field($_POST['end_date']);
            $renewalsCount = intval($_POST['renewals_count']);

            // Validate inputs
            if (empty($campaignId)) {
                wp_send_json_error(['message' => __('Please select a campaign.', 'give-data-generator')]);
                return;
            }

            if ($subscriptionCount < 1 || $subscriptionCount > 1000) {
                wp_send_json_error(['message' => __('Number of subscriptions must be between 1 and 1000.', 'give-data-generator')]);
                return;
            }

            // Validate subscription mode
            if (!in_array($subscriptionMode, ['test', 'live'])) {
                $subscriptionMode = 'test'; // Default to test mode
            }

            // Validate subscription status
            $validStatuses = ['pending', 'active', 'expired', 'completed', 'refunded', 'failing', 'cancelled', 'abandoned', 'suspended', 'paused', 'random'];
            if (!in_array($subscriptionStatus, $validStatuses)) {
                $subscriptionStatus = 'active'; // Default to active
            }

            // Validate subscription period
            $validPeriods = ['day', 'week', 'month', 'quarter', 'year'];
            if (!in_array($subscriptionPeriod, $validPeriods)) {
                $subscriptionPeriod = 'month'; // Default to monthly
            }

            // Validate frequency
            if ($frequency < 1 || $frequency > 12) {
                $frequency = 1; // Default to 1
            }

            // Validate installments
            if ($installments < 0 || $installments > 100) {
                $installments = 0; // Default to indefinite (0)
            }

            // Validate renewals count
            if ($renewalsCount < 0 || $renewalsCount > 50) {
                $renewalsCount = 0; // Default to no renewals
            }

            // Get campaign
            $campaign = Campaign::find($campaignId);
            if (!$campaign) {
                wp_send_json_error(['message' => __('Invalid campaign selected.', 'give-data-generator')]);
                return;
            }

            // Generate subscriptions
            $generated = $this->generateSubscriptions(
                $campaign,
                $subscriptionCount,
                $dateRange,
                $subscriptionMode,
                $subscriptionStatus,
                $subscriptionPeriod,
                $frequency,
                $installments,
                $startDate,
                $endDate,
                $renewalsCount
            );

            wp_send_json_success([
                'message' => sprintf(
                    __('Successfully generated %d test subscriptions for campaign "%s".', 'give-data-generator'),
                    $generated,
                    $campaign->title
                )
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Generate test subscriptions.
     *
     * @since 1.0.0
     *
     * @param Campaign $campaign
     * @param int $count
     * @param string $dateRange
     * @param string $subscriptionMode
     * @param string $subscriptionStatus
     * @param string $subscriptionPeriod
     * @param int $frequency
     * @param int $installments
     * @param string $startDate
     * @param string $endDate
     * @param int $renewalsCount
     *
     * @return int Number of subscriptions generated
     * @throws Exception
     */
    public function generateSubscriptions(
        Campaign $campaign,
        int $count,
        string $dateRange,
        string $subscriptionMode,
        string $subscriptionStatus,
        string $subscriptionPeriod,
        int $frequency,
        int $installments,
        string $startDate = '',
        string $endDate = '',
        int $renewalsCount = 0
    ): int {
        $generated = 0;
        $errors = [];
        $consecutiveErrors = 0;

        try {
            $dateInfo = $this->getDateRange($dateRange, $startDate, $endDate);
        } catch (Exception $e) {
            error_log('Subscription Generator - Date Range Error: ' . $e->getMessage());
            throw $e;
        }

        for ($i = 0; $i < $count; $i++) {
            try {
                $this->createTestSubscription(
                    $campaign,
                    $dateInfo,
                    $subscriptionMode,
                    $subscriptionStatus,
                    $subscriptionPeriod,
                    $frequency,
                    $installments,
                    $renewalsCount
                );
                $generated++;
                $consecutiveErrors = 0; // Reset consecutive error counter on success
            } catch (Exception $e) {
                $consecutiveErrors++;
                $errorMessage = 'Subscription Generator Error (iteration ' . ($i + 1) . '): ' . $e->getMessage();
                error_log($errorMessage);
                $errors[] = $errorMessage;

                // Only stop if we have too many consecutive errors (indicates a systemic issue)
                if ($consecutiveErrors >= 10) {
                    error_log('Subscription Generator: Too many consecutive errors, stopping generation. Last error: ' . $e->getMessage());
                    break;
                }
            }
        }

        // If we generated some subscriptions but had errors, log them but don't fail
        if (!empty($errors) && $generated > 0) {
            error_log('Subscription Generator: Generated ' . $generated . ' subscriptions with ' . count($errors) . ' errors.');
        }

        // Only throw an exception if we couldn't generate any subscriptions at all
        if ($generated === 0 && !empty($errors)) {
            throw new Exception('No subscriptions were generated. Errors: ' . implode(', ', array_slice($errors, 0, 3)));
        }

        return $generated;
    }

    /**
     * Create a single test subscription.
     *
     * @since 1.0.0
     *
     * @param Campaign $campaign
     * @param array $dateInfo
     * @param string $subscriptionMode
     * @param string $subscriptionStatus
     * @param string $subscriptionPeriod
     * @param int $frequency
     * @param int $installments
     * @param int $renewalsCount
     *
     * @throws Exception
     */
    private function createTestSubscription(
        Campaign $campaign,
        array $dateInfo,
        string $subscriptionMode,
        string $subscriptionStatus,
        string $subscriptionPeriod,
        int $frequency,
        int $installments,
        int $renewalsCount = 0
    ) {
        // Generate random donor data
        $firstName = $this->getRandomItem($this->firstNames);
        $lastName = $this->getRandomItem($this->lastNames);
        $email = $this->generateRandomEmail($firstName, $lastName);

        // Create or get donor
        $donor = $this->createTestDonor($firstName, $lastName, $email);

        // Generate random subscription data
        $amount = $this->generateRandomAmount();
        $createdAt = $this->generateRandomDate($dateInfo['start'], $dateInfo['end']);

        // Get default form from campaign or use form ID 1 as fallback
        $defaultForm = $campaign->defaultForm();
        $formId = $defaultForm ? $defaultForm->id : 1;

        // Calculate renewal date based on period and frequency
        $renewsAt = $this->calculateRenewalDate($createdAt, $subscriptionPeriod, $frequency);

        // Create subscription data
        $subscriptionData = [
            'donorId' => $donor->id,
            'donationFormId' => $formId,
            'amount' => new Money($amount * 100, give_get_currency()), // Convert to cents
            'period' => new SubscriptionPeriod($subscriptionPeriod),
            'frequency' => $frequency,
            'installments' => $installments,
            'status' => $this->getSubscriptionStatus($subscriptionStatus),
            'mode' => new SubscriptionMode($subscriptionMode),
            'gatewayId' => 'manual',
            'gatewaySubscriptionId' => $this->generateRandomSubscriptionId(),
            'transactionId' => $this->generateRandomTransactionId(),
            'createdAt' => $createdAt,
            'renewsAt' => $renewsAt,
            'feeAmountRecovered' => new Money(0, give_get_currency()),
        ];

        // Create the subscription
        $subscription = Subscription::create($subscriptionData);

        // Create initial donation for the subscription
        $this->createInitialDonation($subscription, $donor, $campaign, $firstName, $lastName, $email, $subscriptionMode);

        // Create renewals if requested
        if ($renewalsCount > 0) {
            $this->createRenewalsForSubscription($subscription, $renewalsCount, $subscriptionPeriod, $frequency, $createdAt);
        }
    }

    /**
     * Create initial donation for subscription.
     *
     * @since 1.0.0
     *
     * @param Subscription $subscription
     * @param Donor $donor
     * @param Campaign $campaign
     * @param string $firstName
     * @param string $lastName
     * @param string $email
     * @param string $mode
     *
     * @throws Exception
     */
    private function createInitialDonation(
        Subscription $subscription,
        Donor $donor,
        Campaign $campaign,
        string $firstName,
        string $lastName,
        string $email,
        string $mode
    ) {
        $defaultForm = $campaign->defaultForm();
        $formId = $defaultForm ? $defaultForm->id : 1;
        $formTitle = $defaultForm ? $defaultForm->title : 'Test Form';

        $donationData = [
            'status' => DonationStatus::COMPLETE(),
            'gatewayId' => 'manual',
            'mode' => new DonationMode($mode),
            'type' => DonationType::SUBSCRIPTION(),
            'amount' => $subscription->amount,
            'donorId' => $donor->id,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'campaignId' => $campaign->id,
            'formId' => $formId,
            'formTitle' => $formTitle,
            'levelId' => 'custom',
            'anonymous' => $this->getRandomBoolean(0.1),
            'subscriptionId' => $subscription->id,
            'createdAt' => $subscription->createdAt,
            'gatewayTransactionId' => $this->generateRandomTransactionId(),
        ];

        $donation = Donation::create($donationData);

        DonationHelpers::addDonationAndDonorBackwardsCompatibility($donation);

        // Update subscription with parent payment ID
        give()->subscriptions->updateLegacyParentPaymentId($subscription->id, $donation->id);
    }

    /**
     * Create or get a test donor.
     *
     * @since 1.0.0
     *
     * @param string $firstName
     * @param string $lastName
     * @param string $email
     *
     * @return Donor
     * @throws Exception
     */
    private function createTestDonor(string $firstName, string $lastName, string $email): Donor
    {
        // Check if donor already exists by email
        $existingDonor = Donor::query()->where('email', $email)->get();
        if ($existingDonor) {
            return $existingDonor;
        }

        // Create new donor
        return Donor::create([
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'name' => $firstName . ' ' . $lastName,
        ]);
    }

    /**
     * Get date range for subscriptions.
     *
     * @since 1.0.0
     *
     * @param string $dateRange
     * @param string $startDate
     * @param string $endDate
     *
     * @return array
     * @throws Exception
     */
    private function getDateRange(string $dateRange, string $startDate = '', string $endDate = ''): array
    {
        $now = new DateTime();

        switch ($dateRange) {
            case 'last_30_days':
                $start = (clone $now)->modify('-30 days');
                $end = $now;
                break;

            case 'last_90_days':
                $start = (clone $now)->modify('-90 days');
                $end = $now;
                break;

            case 'last_year':
                $start = (clone $now)->modify('-1 year');
                $end = $now;
                break;

            case 'custom':
                if (empty($startDate) || empty($endDate)) {
                    throw new Exception(__('Custom date range requires both start and end dates.', 'give-data-generator'));
                }

                try {
                    $start = new DateTime($startDate);
                    $end = new DateTime($endDate);
                } catch (Exception $e) {
                    throw new Exception(__('Invalid date format provided.', 'give-data-generator'));
                }

                if ($start >= $end) {
                    throw new Exception(__('Start date must be before end date.', 'give-data-generator'));
                }
                break;

            default:
                throw new Exception(__('Invalid date range specified.', 'give-data-generator'));
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * Generate random subscription amount.
     *
     * @since 1.0.0
     *
     * @return int Amount in dollars
     */
    private function generateRandomAmount(): int
    {
        // Common subscription amounts: $5, $10, $25, $50, $100, etc.
        $commonAmounts = [5, 10, 15, 20, 25, 30, 40, 50, 75, 100, 150, 200, 250, 500];

        // 70% chance of using a common amount, 30% chance of random amount
        if ($this->getRandomBoolean(0.7)) {
            return $this->getRandomItem($commonAmounts);
        }

        return rand(5, 1000);
    }

    /**
     * Generate random date within range.
     *
     * @since 1.0.0
     *
     * @param DateTime $start
     * @param DateTime $end
     *
     * @return DateTime
     */
    private function generateRandomDate(DateTime $start, DateTime $end): DateTime
    {
        $startTimestamp = $start->getTimestamp();
        $endTimestamp = $end->getTimestamp();
        $randomTimestamp = rand($startTimestamp, $endTimestamp);

        return (new DateTime())->setTimestamp($randomTimestamp);
    }

    /**
     * Generate random email address.
     *
     * @since 1.0.0
     *
     * @param string $firstName
     * @param string $lastName
     *
     * @return string
     */
    private function generateRandomEmail(string $firstName, string $lastName): string
    {
        $domain = $this->getRandomItem($this->emailDomains);
        $username = strtolower($firstName . '.' . $lastName . rand(100, 999));

        return $username . '@' . $domain;
    }

    /**
     * Calculate renewal date based on period and frequency.
     *
     * @since 1.0.0
     *
     * @param DateTime $startDate
     * @param string $period
     * @param int $frequency
     *
     * @return DateTime
     */
    private function calculateRenewalDate(DateTime $startDate, string $period, int $frequency): DateTime
    {
        $renewalDate = clone $startDate;

        switch ($period) {
            case 'day':
                $renewalDate->modify('+' . $frequency . ' days');
                break;
            case 'week':
                $renewalDate->modify('+' . ($frequency * 7) . ' days');
                break;
            case 'month':
                $renewalDate->modify('+' . $frequency . ' months');
                break;
            case 'quarter':
                $renewalDate->modify('+' . ($frequency * 3) . ' months');
                break;
            case 'year':
                $renewalDate->modify('+' . $frequency . ' years');
                break;
        }

        return $renewalDate;
    }

    /**
     * Generate random transaction ID.
     *
     * @since 1.0.0
     *
     * @return string
     */
    private function generateRandomTransactionId(): string
    {
        return 'txn_' . uniqid() . '_' . rand(1000, 9999);
    }

    /**
     * Generate random subscription ID.
     *
     * @since 1.0.0
     *
     * @return string
     */
    private function generateRandomSubscriptionId(): string
    {
        return 'sub_' . uniqid() . '_' . rand(1000, 9999);
    }

    /**
     * Get random item from array.
     *
     * @since 1.0.0
     *
     * @param array $items
     *
     * @return mixed
     */
    private function getRandomItem(array $items)
    {
        if (empty($items)) {
            return '';
        }

        return $items[array_rand($items)];
    }

    /**
     * Get random boolean based on probability.
     *
     * @since 1.0.0
     *
     * @param float $probability Probability of returning true (0.0 to 1.0)
     *
     * @return bool
     */
    private function getRandomBoolean(float $probability = 0.5): bool
    {
        return (rand() / getrandmax()) < $probability;
    }

    /**
     * Get subscription status based on string.
     *
     * @since 1.0.0
     *
     * @param string $status
     *
     * @return SubscriptionStatus
     */
    private function getSubscriptionStatus(string $status): SubscriptionStatus
    {
        if ($status === 'random') {
            return $this->getRandomSubscriptionStatus();
        }

        switch ($status) {
            case 'pending':
                return SubscriptionStatus::PENDING();
            case 'active':
                return SubscriptionStatus::ACTIVE();
            case 'expired':
                return SubscriptionStatus::EXPIRED();
            case 'completed':
                return SubscriptionStatus::COMPLETED();
            case 'refunded':
                return SubscriptionStatus::REFUNDED();
            case 'failing':
                return SubscriptionStatus::FAILING();
            case 'cancelled':
                return SubscriptionStatus::CANCELLED();
            case 'abandoned':
                return SubscriptionStatus::ABANDONED();
            case 'suspended':
                return SubscriptionStatus::SUSPENDED();
            case 'paused':
                return SubscriptionStatus::PAUSED();
            default:
                return SubscriptionStatus::ACTIVE();
        }
    }

    /**
     * Get random subscription status.
     *
     * @since 1.0.0
     *
     * @return SubscriptionStatus
     */
    private function getRandomSubscriptionStatus(): SubscriptionStatus
    {
        $statuses = [
            SubscriptionStatus::PENDING(),
            SubscriptionStatus::ACTIVE(),
            SubscriptionStatus::ACTIVE(), // Make active more likely
            SubscriptionStatus::ACTIVE(), // Make active more likely
            SubscriptionStatus::EXPIRED(),
            SubscriptionStatus::COMPLETED(),
            SubscriptionStatus::CANCELLED(),
        ];

        return $this->getRandomItem($statuses);
    }

    /**
     * Create renewals for the subscription.
     *
     * @since 1.0.0
     *
     * @param Subscription $subscription
     * @param int $count
     * @param string $period
     * @param int $frequency
     * @param DateTime $createdAt
     *
     * @throws Exception
     */
    private function createRenewalsForSubscription(
        Subscription $subscription,
        int $count,
        string $period,
        int $frequency,
        DateTime $createdAt
    ) {
        $renewalDate = clone $createdAt;

        for ($i = 0; $i < $count; $i++) {
            // Calculate the date for this renewal
            $renewalDate = $this->calculateRenewalDate($renewalDate, $period, $frequency);

            // Create renewal using the Subscription model's createRenewal method
            $donation = $subscription->createRenewal([
                'createdAt' => $renewalDate,
                'gatewayTransactionId' => $this->generateRandomTransactionId(),
            ]);

            DonationHelpers::addDonationAndDonorBackwardsCompatibility($donation);
        }
    }
}
