<?php

namespace GiveFaker\TestDonationGenerator;

use DateTime;
use Exception;
use Give\Campaigns\Models\Campaign;
use Give\Donations\Models\Donation;
use Give\Donations\Properties\BillingAddress;
use Give\Donations\ValueObjects\DonationMode;
use Give\Donations\ValueObjects\DonationStatus;
use Give\Donations\ValueObjects\DonationType;
use Give\Donors\Models\Donor;
use Give\Framework\Support\ValueObjects\Money;

/**
 * Data Generator.
 *
 * @package     GiveFaker\TestDonationGenerator
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

            // Get campaign
            $campaign = Campaign::find($campaignId);
            if (!$campaign) {
                wp_send_json_error(['message' => __('Invalid campaign selected.', 'give-data-generator')]);
                return;
            }

            // Generate donations
            $generated = $this->generateDonations($campaign, $donationCount, $dateRange, $donationMode, $startDate, $endDate);

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
     * Generate test donations.
     *
     * @since 1.0.0
     *
     * @param Campaign $campaign
     * @param int $count
     * @param string $dateRange
     * @param string $donationMode
     * @param string $startDate
     * @param string $endDate
     *
     * @return int Number of donations generated
     * @throws Exception
     */
    public function generateDonations(Campaign $campaign, int $count, string $dateRange, string $donationMode, string $startDate = '', string $endDate = ''): int
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
                $this->createTestDonation($campaign, $dateInfo, $donationMode);
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
     *
     * @throws Exception
     */
    private function createTestDonation(Campaign $campaign, array $dateInfo, string $donationMode)
    {
        // Generate random donor data
        $firstName = $this->getRandomItem($this->firstNames);
        $lastName = $this->getRandomItem($this->lastNames);
        $email = $this->generateRandomEmail($firstName, $lastName);

        // Create or get donor
        $donor = $this->createTestDonor($firstName, $lastName, $email);

        // Generate random donation data
        $amount = $this->generateRandomAmount();
        $createdAt = $this->generateRandomDate($dateInfo['start'], $dateInfo['end']);

        // Get default form from campaign or use form ID 1 as fallback
        $defaultForm = $campaign->defaultForm();
        $formId = $defaultForm ? $defaultForm->id : 1;
        $formTitle = $defaultForm ? $defaultForm->title : 'Test Form';

        // Simplified donation creation with minimal required fields
        $donationData = [
            'status' => DonationStatus::COMPLETE(),
            'gatewayId' => 'manual',
            'mode' => new DonationMode($donationMode),
            'type' => DonationType::SINGLE(),
            'amount' => new Money($amount * 100, give_get_currency()), // Convert to cents
            'donorId' => $donor->id,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'campaignId' => $campaign->id,
            'formId' => $formId,
            'formTitle' => $formTitle,
            'levelId' => 'custom',
            'anonymous' => $this->getRandomBoolean(0.1),
            'company' => $this->getRandomCompany(),
            'comment' => $this->getRandomComment(),
        ];

        // Add optional fields if they have values
        $phone = $this->generateRandomPhone();
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
     *
     * @return Donor
     * @throws Exception
     */
    private function createTestDonor(string $firstName, string $lastName, string $email): Donor
    {
        // Check if donor already exists
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
}


