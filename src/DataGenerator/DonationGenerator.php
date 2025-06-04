<?php

namespace GiveDataGenerator\DataGenerator;

use DateTime;
use Exception;
use Give\Campaigns\Models\Campaign;
use Give\Campaigns\ValueObjects\CampaignStatus;
use Give\Donations\LegacyListeners\UpdateDonorPurchaseValueAndCount;
use Give\Donations\Models\Donation;
use Give\Donations\Properties\BillingAddress;
use Give\Donations\ValueObjects\DonationMetaKeys;
use Give\Donations\ValueObjects\DonationMode;
use Give\Donations\ValueObjects\DonationStatus;
use Give\Donations\ValueObjects\DonationType;
use Give\Donors\Models\Donor;
use Give\Framework\Database\DB;
use Give\Framework\Support\ValueObjects\Money;
use GiveFeeRecovery\FormExtension\Hooks\UpdateDonationWithFeeAmountRecovered;

/**
 * Data Generator.
 *
 * @package     GiveDataGenerator\DataGenerator
 * @since       1.0.0
 */
class DonationGenerator
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
     * Handle AJAX request for generating test donations.
     *
     * @since 1.0.0
     */
    public function handleAjaxRequest()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'data_generator_nonce')) {
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
            $donationCount = intval($_POST['donation_count']);
            $dateRange = sanitize_text_field($_POST['date_range']);
            $donationMode = sanitize_text_field($_POST['donation_mode']);
            $donationStatus = sanitize_text_field($_POST['donation_status']);
            $donorCreationMethod = sanitize_text_field($_POST['donor_creation_method'] ?? 'create_new');
            $selectedDonorId = intval($_POST['selected_donor_id'] ?? 0);
            $startDate = sanitize_text_field($_POST['start_date']);
            $endDate = sanitize_text_field($_POST['end_date']);

            // Validate inputs
            if (empty($campaignId)) {
                wp_send_json_error(['message' => __('Please select a campaign.', 'give-data-generator')]);
                return;
            }

            if ($donationCount < 1 || $donationCount > 1000) {
                wp_send_json_error(['message' => __('Number of donations must be between 1 and 1000.', 'give-data-generator')]);
                return;
            }

            // Validate donation mode
            if (!in_array($donationMode, ['test', 'live'])) {
                $donationMode = 'test'; // Default to test mode
            }

            // Validate donation status
            $validStatuses = ['complete', 'pending', 'processing', 'refunded', 'failed', 'cancelled', 'abandoned', 'preapproval', 'revoked', 'random'];
            if (!in_array($donationStatus, $validStatuses)) {
                $donationStatus = 'complete'; // Default to complete
            }

            // Validate donor creation method
            if (!in_array($donorCreationMethod, ['create_new', 'use_existing', 'mixed', 'select_specific'])) {
                $donorCreationMethod = 'create_new'; // Default to create new
            }

            // Validate selected donor if using specific selection
            if ($donorCreationMethod === 'select_specific') {
                if (empty($selectedDonorId)) {
                    wp_send_json_error(['message' => __('Please select a specific donor.', 'give-data-generator')]);
                    return;
                }

                // Verify the donor exists
                $selectedDonor = Donor::find($selectedDonorId);
                if (!$selectedDonor) {
                    wp_send_json_error(['message' => __('The selected donor does not exist.', 'give-data-generator')]);
                    return;
                }
            }

            // Get campaign
            $campaign = Campaign::find($campaignId);
            if (!$campaign) {
                wp_send_json_error(['message' => __('Invalid campaign selected.', 'give-data-generator')]);
                return;
            }

            // Generate donations
            $generated = $this->generateDonations($campaign, $donationCount, $dateRange, $donationMode, $donationStatus, $donorCreationMethod, $selectedDonorId, $startDate, $endDate);

            wp_send_json_success([
                'message' => sprintf(
                    __('Successfully generated %d test donations for campaign "%s".', 'give-data-generator'),
                    $generated,
                    $campaign->title
                )
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Handle AJAX request for generating bulk test donations for all active campaigns.
     *
     * @since 1.0.0
     */
    public function handleBulkAjaxRequest()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'data_generator_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'give-data-generator')]);
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_give_settings')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'give-data-generator')]);
            return;
        }

        try {
            $donationsPerCampaign = intval($_POST['donations_per_campaign']);
            $dateRange = sanitize_text_field($_POST['date_range']);
            $donationMode = sanitize_text_field($_POST['donation_mode']);
            $donationStatus = sanitize_text_field($_POST['donation_status']);
            $donorCreationMethod = sanitize_text_field($_POST['donor_creation_method'] ?? 'create_new');
            $selectedDonorId = intval($_POST['selected_donor_id'] ?? 0);
            $startDate = sanitize_text_field($_POST['start_date']);
            $endDate = sanitize_text_field($_POST['end_date']);

            // Validate inputs
            if ($donationsPerCampaign < 1 || $donationsPerCampaign > 100) {
                wp_send_json_error(['message' => __('Number of donations per campaign must be between 1 and 100.', 'give-data-generator')]);
                return;
            }

            // Validate donation mode
            if (!in_array($donationMode, ['test', 'live'])) {
                $donationMode = 'test'; // Default to test mode
            }

            // Validate donation status
            $validStatuses = ['complete', 'pending', 'processing', 'refunded', 'failed', 'cancelled', 'abandoned', 'preapproval', 'revoked', 'random'];
            if (!in_array($donationStatus, $validStatuses)) {
                $donationStatus = 'complete'; // Default to complete
            }

            // Validate donor creation method
            if (!in_array($donorCreationMethod, ['create_new', 'use_existing', 'mixed', 'select_specific'])) {
                $donorCreationMethod = 'create_new'; // Default to create new
            }

            // Validate selected donor if using specific selection
            if ($donorCreationMethod === 'select_specific') {
                if (empty($selectedDonorId)) {
                    wp_send_json_error(['message' => __('Please select a specific donor.', 'give-data-generator')]);
                    return;
                }

                // Verify the donor exists
                $selectedDonor = Donor::find($selectedDonorId);
                if (!$selectedDonor) {
                    wp_send_json_error(['message' => __('The selected donor does not exist.', 'give-data-generator')]);
                    return;
                }
            }

            // Get all active campaigns
            $campaigns = Campaign::query()
                ->where('status', CampaignStatus::ACTIVE()->getValue())
                ->getAll();

            if (empty($campaigns)) {
                wp_send_json_error(['message' => __('No active campaigns found.', 'give-data-generator')]);
                return;
            }

            // Generate donations for all campaigns
            $totalGenerated = $this->generateBulkDonations($campaigns, $donationsPerCampaign, $dateRange, $donationMode, $donationStatus, $donorCreationMethod, $selectedDonorId, $startDate, $endDate);

            wp_send_json_success([
                'message' => sprintf(
                    __('Successfully generated %d test donations across %d active campaigns (%d donations per campaign).', 'give-data-generator'),
                    $totalGenerated,
                    count($campaigns),
                    $donationsPerCampaign
                )
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Generate test donations.
     *
     * @since 1.0.0
     *
     * @param Campaign $campaign
     * @param int $count
     * @param string $dateRange
     * @param string $donationMode
     * @param string $donationStatus
     * @param string $donorCreationMethod
     * @param int $selectedDonorId
     * @param string $startDate
     * @param string $endDate
     *
     * @return int Number of donations generated
     * @throws Exception
     */
    public function generateDonations(Campaign $campaign, int $count, string $dateRange, string $donationMode, string $donationStatus, string $donorCreationMethod, int $selectedDonorId, string $startDate = '', string $endDate = ''): int
    {
        $generated = 0;
        $errors = [];
        $consecutiveErrors = 0;

        try {
            $dateInfo = $this->getDateRange($dateRange, $startDate, $endDate);
        } catch (Exception $e) {
            error_log('Data Generator - Date Range Error: ' . $e->getMessage());
            throw $e;
        }

        for ($i = 0; $i < $count; $i++) {
            try {
                $this->createTestDonation($campaign, $dateInfo, $donationMode, $donationStatus, $donorCreationMethod, $selectedDonorId);
                $generated++;
                $consecutiveErrors = 0; // Reset consecutive error counter on success
            } catch (Exception $e) {
                $consecutiveErrors++;
                $errorMessage = 'Data Generator Error (iteration ' . ($i + 1) . '): ' . $e->getMessage();
                error_log($errorMessage);
                $errors[] = $errorMessage;

                // Only stop if we have too many consecutive errors (indicates a systemic issue)
                if ($consecutiveErrors >= 10) {
                    error_log('Data Generator: Too many consecutive errors, stopping generation. Last error: ' . $e->getMessage());
                    break;
                }
            }
        }

        // If we generated some donations but had errors, log them but don't fail
        if (!empty($errors) && $generated > 0) {
            error_log('Data Generator: Generated ' . $generated . ' donations with ' . count($errors) . ' errors.');
        }

        // Only throw an exception if we couldn't generate any donations at all
        if ($generated === 0 && !empty($errors)) {
            throw new Exception('No donations were generated. Errors: ' . implode(', ', array_slice($errors, 0, 3)));
        }

        return $generated;
    }

    /**
     * Create a single test donation.
     *
     * @since 1.0.0
     *
     * @param Campaign $campaign
     * @param array $dateInfo
     * @param string $donationMode
     * @param string $donationStatus
     * @param string $donorCreationMethod
     * @param int $selectedDonorId
     *
     * @throws Exception
     */
    private function createTestDonation(Campaign $campaign, array $dateInfo, string $donationMode, string $donationStatus, string $donorCreationMethod, int $selectedDonorId)
    {
        // Generate random donor data for new donors
        $firstName = $this->getRandomItem($this->firstNames);
        $lastName = $this->getRandomItem($this->lastNames);
        $email = $this->generateRandomEmail($firstName, $lastName);

        // Create or get donor
        $donor = $this->createTestDonor($firstName, $lastName, $email, $donorCreationMethod, $selectedDonorId);

        // Use actual donor information if we're using an existing donor
        $donorFirstName = $donor->firstName ?? $firstName;
        $donorLastName = $donor->lastName ?? $lastName;
        $donorEmail = $donor->email ?? $email;

        // Generate random donation data
        $amount = $this->generateRandomAmount();
        $createdAt = $this->generateRandomDate($dateInfo['start'], $dateInfo['end']);

        // Get default form from campaign or use form ID 1 as fallback
        $defaultForm = $campaign->defaultForm();
        $formId = $defaultForm ? $defaultForm->id : 1;
        $formTitle = $defaultForm ? $defaultForm->title : 'Test Form';

        // Simplified donation creation with minimal required fields
        $donationData = [
            'status' => $this->getDonationStatus($donationStatus),
            'gatewayId' => 'manual',
            'mode' => new DonationMode($donationMode),
            'type' => DonationType::SINGLE(),
            'amount' => new Money($amount * 100, give_get_currency()), // Convert to cents
            'donorId' => $donor->id,
            'firstName' => $donorFirstName,
            'lastName' => $donorLastName,
            'email' => $donorEmail,
            'campaignId' => $campaign->id,
            'formId' => $formId,
            'formTitle' => $formTitle,
            'levelId' => 'custom',
            'anonymous' => $this->getRandomBoolean(0.1),
            'company' => $this->getRandomCompany(),
            'comment' => $this->getRandomComment(),
        ];

        // Add optional fields if they have values
        $phone = $donor->phone ?? $this->generateRandomPhone();
        if ($phone) {
            $donationData['phone'] = $phone;
        }

        $billingAddress = $this->generateRandomBillingAddress();
        if ($billingAddress) {
            $donationData['billingAddress'] = $billingAddress;
        }

        // Always add these fields
        $donationData['feeAmountRecovered'] = $this->generateRandomFeeAmount($amount);
        $donationData['exchangeRate'] = '1';
        $donationData['gatewayTransactionId'] = $this->generateRandomTransactionId();
        $donationData['purchaseKey'] = $this->generateRandomPurchaseKey();
        $donationData['donorIp'] = $this->generateRandomIp();
        $donationData['createdAt'] = $createdAt;
        $donationData['updatedAt'] = $createdAt;

        // Create donation
        try {
            $donation = Donation::create($donationData);

            $donor = $donation->donor;

            give()->donors->updateLegacyColumns($donor->id, [
                'purchase_value' => $this->getDonorTotalAmountDonated($donor->id),
                'purchase_count' => $donor->totalDonations()
            ]);

            if ($donation->feeAmountRecovered !== null) {
                give()->payment_meta->update_meta(
                    $donation->id,
                    '_give_fee_donation_amount',
                    give_sanitize_amount_for_db(
                        $donation->intendedAmount()->formatToDecimal(),
                        ['currency' => $donation->amount->getCurrency()]
                    )
                );
            }

            // Log success for debugging
            error_log('Data Generator: Successfully created donation ID ' . $donation->id . ' for campaign ' . $campaign->id);

        } catch (Exception $e) {
            error_log('Data Generator: Failed to create donation. Error: ' . $e->getMessage());
            error_log('Data Generator: Donation data: ' . print_r($donationData, true));
            throw $e;
        }
    }

    /**
     * Create or get a test donor.
     *
     * @since 1.0.0
     *
     * @param string $firstName
     * @param string $lastName
     * @param string $email
     * @param string $donorCreationMethod
     * @param int $selectedDonorId
     *
     * @return Donor
     * @throws Exception
     */
    private function createTestDonor(string $firstName, string $lastName, string $email, string $donorCreationMethod, int $selectedDonorId): Donor
    {
        if ($donorCreationMethod === 'select_specific') {
            // Use the specifically selected donor
            $selectedDonor = Donor::find($selectedDonorId);
            if ($selectedDonor) {
                return $selectedDonor;
            }
            // If selected donor not found, fall back to creating a new one
            error_log('Data Generator: Selected donor not found, falling back to create new');
        }

        if ($donorCreationMethod === 'use_existing') {
            // Try to get a random existing donor
            $existingDonor = $this->getRandomExistingDonor();
            if ($existingDonor) {
                return $existingDonor;
            }
            // If no existing donors found, fall back to creating a new one
        }

        if ($donorCreationMethod === 'mixed') {
            // 50% chance to use existing donor, 50% chance to create new
            if ($this->getRandomBoolean(0.5)) {
                $existingDonor = $this->getRandomExistingDonor();
                if ($existingDonor) {
                    return $existingDonor;
                }
            }
            // Fall through to create new donor
        }

        // For 'create_new' method or fallback, check if donor already exists by email
        $existingDonor = Donor::whereEmail($email);

        if ($existingDonor) {
            return $existingDonor;
        }

        // Create new donor
        return Donor::create([
            'firstName' => $firstName,
            'lastName' => $lastName,
            'name' => trim("$firstName $lastName"),
            'email' => $email,
            'phone' => $this->generateRandomPhone(),
        ]);
    }

    /**
     * Get a random existing donor from the database.
     *
     * @since 1.0.0
     *
     * @return Donor|null
     */
    private function getRandomExistingDonor(): ?Donor
    {
        global $wpdb;

        // Get a random donor ID from the database
        $donorId = $wpdb->get_var("
            SELECT id
            FROM {$wpdb->prefix}give_donors
            ORDER BY RAND()
            LIMIT 1
        ");

        if ($donorId) {
            return Donor::find($donorId);
        }

        return null;
    }

    /**
     * Get date range based on selection.
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
                    throw new Exception(__('Start and end dates are required for custom range.', 'give-data-generator'));
                }
                $start = new DateTime($startDate);
                $end = new DateTime($endDate);
                break;
            default:
                $start = (clone $now)->modify('-30 days');
                $end = $now;
        }

        return [
            'start' => $start,
            'end' => $end
        ];
    }

    /**
     * Generate random amount between $5 and $500.
     *
     * @since 1.0.0
     *
     * @return int
     */
    private function generateRandomAmount(): int
    {
        $amounts = [5, 10, 15, 20, 25, 30, 50, 75, 100, 150, 200, 250, 500];
        return $this->getRandomItem($amounts);
    }

    /**
     * Generate random date between start and end dates.
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
        $timestamp = mt_rand($start->getTimestamp(), $end->getTimestamp());
        return (new DateTime())->setTimestamp($timestamp);
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
        $username = strtolower($firstName . '.' . $lastName . mt_rand(100, 999));
        return $username . '@' . $domain;
    }

    /**
     * Generate random phone number.
     *
     * @since 1.0.0
     *
     * @return string
     */
    private function generateRandomPhone(): string
    {
        return sprintf('(%03d) %03d-%04d',
            mt_rand(200, 999),
            mt_rand(200, 999),
            mt_rand(1000, 9999)
        );
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
        return 'test_' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 10));
    }

    /**
     * Generate random purchase key.
     *
     * @since 1.0.0
     *
     * @return string
     */
    private function generateRandomPurchaseKey(): string
    {
        return wp_generate_uuid4();
    }

    /**
     * Generate random IP address.
     *
     * @since 1.0.0
     *
     * @return string
     */
    private function generateRandomIp(): string
    {
        return sprintf('%d.%d.%d.%d',
            mt_rand(1, 255),
            mt_rand(1, 255),
            mt_rand(1, 255),
            mt_rand(1, 255)
        );
    }

    /**
     * Generate random billing address.
     *
     * @since 1.0.0
     *
     * @return BillingAddress
     */
    private function generateRandomBillingAddress(): BillingAddress
    {
        $states = ['CA', 'NY', 'TX', 'FL', 'IL', 'PA', 'OH', 'GA', 'NC', 'MI'];
        $cities = ['Los Angeles', 'New York', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'San Jose'];

        return BillingAddress::fromArray([
            'address1' => mt_rand(100, 9999) . ' ' . $this->getRandomItem(['Main St', 'Oak Ave', 'First St', 'Second St', 'Park Ave', 'Maple Dr']),
            'address2' => $this->getRandomBoolean(0.3) ? 'Apt ' . mt_rand(1, 999) : '',
            'city' => $this->getRandomItem($cities),
            'state' => $this->getRandomItem($states),
            'zip' => sprintf('%05d', mt_rand(10000, 99999)),
            'country' => 'US'
        ]);
    }

    /**
     * Get random company name (sometimes empty).
     *
     * @since 1.0.0
     *
     * @return string
     */
    private function getRandomCompany(): string
    {
        if ($this->getRandomBoolean(0.7)) { // 70% chance of no company
            return '';
        }

        $companies = [
            'ABC Corporation', 'XYZ Industries', 'Tech Solutions Inc', 'Global Services LLC',
            'Innovation Partners', 'Digital Dynamics', 'Future Systems', 'Premier Group',
            'Elite Enterprises', 'Synergy Solutions'
        ];

        return $this->getRandomItem($companies);
    }

    /**
     * Get random comment (sometimes empty).
     *
     * @since 1.0.0
     *
     * @return string
     */
    private function getRandomComment(): string
    {
        if ($this->getRandomBoolean(0.8)) { // 80% chance of no comment
            return '';
        }

        $comments = [
            'Happy to support this cause!',
            'Keep up the great work!',
            'This is for a great cause.',
            'Thank you for all you do.',
            'In memory of a loved one.',
            'Proud to donate to this campaign.',
            'Every little bit helps!',
            'Making a difference together.'
        ];

        return $this->getRandomItem($comments);
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
        return $items[array_rand($items)];
    }

    /**
     * Get random boolean with probability.
     *
     * @since 1.0.0
     *
     * @param float $probability Probability of true (0.0 to 1.0)
     *
     * @return bool
     */
    private function getRandomBoolean(float $probability = 0.5): bool
    {
        return mt_rand() / mt_getrandmax() < $probability;
    }

    /**
     * Generate random fee amount based on donation amount.
     * Some donations will have no fees (70% chance), others will have realistic processing fees.
     *
     * @since 1.0.0
     *
     * @param int $amount Donation amount in dollars
     *
     * @return Money
     */
    private function generateRandomFeeAmount(int $amount): Money
    {
        // 70% chance of no fee recovery
        if ($this->getRandomBoolean(0.7)) {
            return new Money(0, give_get_currency());
        }

        // Generate realistic processing fee (2.9% + $0.30 for credit cards, or flat 2-3%)
        $feeOptions = [
            // Credit card processing fee: 2.9% + $0.30
            ($amount * 100 * 0.029) + 30,
            // Flat percentage fees
            $amount * 100 * 0.02,  // 2%
            $amount * 100 * 0.025, // 2.5%
            $amount * 100 * 0.03,  // 3%
        ];

        $feeAmountCents = $this->getRandomItem($feeOptions);

        // Round to nearest cent and ensure it's not more than 10% of donation
        $feeAmountCents = min(round($feeAmountCents), $amount * 100 * 0.1);

        return new Money($feeAmountCents, give_get_currency());
    }

    /**
     * Get donation status based on input.
     *
     * @since 1.0.0
     *
     * @param string $status Input status
     *
     * @return DonationStatus
     */
    private function getDonationStatus(string $status): DonationStatus
    {
        if ($status === 'random') {
            return $this->getRandomDonationStatus();
        }

        $statuses = [
            'complete' => DonationStatus::COMPLETE(),
            'pending' => DonationStatus::PENDING(),
            'processing' => DonationStatus::PROCESSING(),
            'refunded' => DonationStatus::REFUNDED(),
            'failed' => DonationStatus::FAILED(),
            'cancelled' => DonationStatus::CANCELLED(),
            'abandoned' => DonationStatus::ABANDONED(),
            'preapproval' => DonationStatus::PREAPPROVAL(),
            'revoked' => DonationStatus::REVOKED(),
        ];

        return $statuses[$status] ?? DonationStatus::COMPLETE();
    }

    /**
     * Get random donation status with weighted distribution.
     *
     * @since 1.0.0
     *
     * @return DonationStatus
     */
    private function getRandomDonationStatus(): DonationStatus
    {
        // Weighted distribution - completed donations are most common
        $weightedStatuses = [
            ['status' => DonationStatus::COMPLETE(), 'weight' => 70],    // 70% chance
            ['status' => DonationStatus::PENDING(), 'weight' => 15],     // 15% chance
            ['status' => DonationStatus::PROCESSING(), 'weight' => 5],   // 5% chance
            ['status' => DonationStatus::FAILED(), 'weight' => 4],       // 4% chance
            ['status' => DonationStatus::CANCELLED(), 'weight' => 2],    // 2% chance
            ['status' => DonationStatus::REFUNDED(), 'weight' => 2],     // 2% chance
            ['status' => DonationStatus::ABANDONED(), 'weight' => 1],    // 1% chance
            ['status' => DonationStatus::PREAPPROVAL(), 'weight' => 1],  // 1% chance
        ];

        $rand = mt_rand(1, 100);
        $cumulative = 0;

        foreach ($weightedStatuses as $item) {
            $cumulative += $item['weight'];
            if ($rand <= $cumulative) {
                return $item['status'];
            }
        }

        // Fallback
        return DonationStatus::COMPLETE();
    }

    /**
     * Generate test donations for multiple campaigns (bulk generation).
     *
     * @since 1.0.0
     *
     * @param Campaign[] $campaigns
     * @param int $donationsPerCampaign
     * @param string $dateRange
     * @param string $donationMode
     * @param string $donationStatus
     * @param string $donorCreationMethod
     * @param int $selectedDonorId
     * @param string $startDate
     * @param string $endDate
     *
     * @return int Total number of donations generated across all campaigns
     * @throws Exception
     */
    public function generateBulkDonations(array $campaigns, int $donationsPerCampaign, string $dateRange, string $donationMode, string $donationStatus, string $donorCreationMethod, int $selectedDonorId, string $startDate = '', string $endDate = ''): int
    {
        $totalGenerated = 0;
        $errors = [];
        $campaignErrors = [];

        try {
            $dateInfo = $this->getDateRange($dateRange, $startDate, $endDate);
        } catch (Exception $e) {
            error_log('Data Generator - Bulk Date Range Error: ' . $e->getMessage());
            throw $e;
        }

        foreach ($campaigns as $campaign) {
            try {
                $generatedForCampaign = 0;
                $consecutiveErrors = 0;

                for ($i = 0; $i < $donationsPerCampaign; $i++) {
                    try {
                        $this->createTestDonation($campaign, $dateInfo, $donationMode, $donationStatus, $donorCreationMethod, $selectedDonorId);
                        $generatedForCampaign++;
                        $totalGenerated++;
                        $consecutiveErrors = 0; // Reset consecutive error counter on success
                    } catch (Exception $e) {
                        $consecutiveErrors++;
                        $errorMessage = 'Data Generator Bulk Error (campaign: ' . $campaign->title . ', iteration ' . ($i + 1) . '): ' . $e->getMessage();
                        error_log($errorMessage);
                        $errors[] = $errorMessage;

                        // Stop this campaign if we have too many consecutive errors
                        if ($consecutiveErrors >= 5) {
                            error_log('Data Generator Bulk: Too many consecutive errors for campaign "' . $campaign->title . '", skipping to next campaign. Last error: ' . $e->getMessage());
                            $campaignErrors[] = sprintf('Skipped campaign "%s" due to repeated errors', $campaign->title);
                            break;
                        }
                    }
                }

                if ($generatedForCampaign > 0) {
                    error_log('Data Generator Bulk: Generated ' . $generatedForCampaign . ' donations for campaign "' . $campaign->title . '"');
                }

            } catch (Exception $e) {
                $errorMessage = 'Data Generator Bulk: Fatal error processing campaign "' . $campaign->title . '": ' . $e->getMessage();
                error_log($errorMessage);
                $campaignErrors[] = sprintf('Failed to process campaign "%s": %s', $campaign->title, $e->getMessage());
                continue; // Continue with next campaign
            }
        }

        // Log summary of bulk generation
        if (!empty($campaignErrors)) {
            error_log('Data Generator Bulk: Encountered issues with ' . count($campaignErrors) . ' campaigns: ' . implode('; ', $campaignErrors));
        }

        if (!empty($errors)) {
            error_log('Data Generator Bulk: Encountered ' . count($errors) . ' total errors during bulk generation: ' . implode('; ', array_slice($errors, -5))); // Log last 5 errors
        }

        error_log('Data Generator Bulk: Successfully generated ' . $totalGenerated . ' donations across ' . count($campaigns) . ' campaigns');

        return $totalGenerated;
    }

    /**
     * Calculate total amount donated by a donor (intended amount after subtracting fees)
     *
     * @since 1.0.0
     *
     * @param int $donorId The donor ID
     * @return float The total intended amount donated
     */
    private function getDonorTotalAmountDonated(int $donorId): float
    {
        return (float) DB::table('posts', 'posts')
            ->join(function ($join) {
                $join->leftJoin('give_donationmeta', 'donor_meta')
                    ->on('posts.ID', 'donor_meta.donation_id')
                    ->andOn('donor_meta.meta_key', DonationMetaKeys::DONOR_ID, true);
            })
            ->join(function ($join) {
                $join->leftJoin('give_donationmeta', 'amount_meta')
                    ->on('posts.ID', 'amount_meta.donation_id')
                    ->andOn('amount_meta.meta_key', DonationMetaKeys::AMOUNT, true);
            })
            ->join(function ($join) {
                $join->leftJoin('give_donationmeta', 'fee_meta')
                    ->on('posts.ID', 'fee_meta.donation_id')
                    ->andOn('fee_meta.meta_key', DonationMetaKeys::FEE_AMOUNT_RECOVERED, true);
            })
            ->where('posts.post_type', 'give_payment')
            ->where('donor_meta.meta_value', $donorId)
            ->whereIn('posts.post_status', ['publish', 'give_subscription'])
            ->sum('IFNULL(amount_meta.meta_value, 0) - IFNULL(fee_meta.meta_value, 0)');
    }
}


