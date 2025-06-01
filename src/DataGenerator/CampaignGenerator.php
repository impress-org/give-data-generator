<?php

namespace GiveDataGenerator\DataGenerator;

use DateTime;
use Exception;
use Give\Campaigns\Models\Campaign;
use Give\Campaigns\ValueObjects\CampaignGoalType;
use Give\Campaigns\ValueObjects\CampaignStatus;
use Give\Campaigns\ValueObjects\CampaignType;
use Give\Campaigns\Actions\CreateDefaultCampaignForm;
use Give\Campaigns\ValueObjects\CampaignPageStatus;

/**
 * Campaign Generator.
 *
 * @package     GiveDataGenerator\DataGenerator
 * @since       1.0.0
 */
class CampaignGenerator
{
    /**
     * Sample campaign titles for generating fake campaigns.
     *
     * @since 1.0.0
     * @var array
     */
    private array $campaignTitles = [
        'Save the Ocean',
        'Help Local Families',
        'Emergency Relief Fund',
        'Build a Better Tomorrow',
        'Education for All',
        'Clean Water Initiative',
        'Feed the Hungry',
        'Medical Research Fund',
        'Animal Rescue Mission',
        'Climate Action Now',
        'Youth Development Program',
        'Veterans Support Fund',
        'Community Garden Project',
        'Disaster Relief Effort',
        'Housing for the Homeless',
        'Arts & Culture Preservation',
        'Senior Care Services',
        'Mental Health Awareness',
        'Environmental Protection',
        'Children\'s Hospital Fund',
        'Scholarship Program',
        'Technology for Schools',
        'Public Park Restoration',
        'Cancer Research Initiative',
        'Rural Healthcare Access',
        'Food Bank Support',
        'Wildlife Conservation',
        'Literacy Program',
        'Sports for Youth',
        'Music Education Fund'
    ];

    /**
     * Sample short descriptions for campaigns.
     *
     * @since 1.0.0
     * @var array
     */
    private array $shortDescriptions = [
        'Make a difference in your community today.',
        'Every donation counts toward our goal.',
        'Join us in creating positive change.',
        'Your support helps those in need.',
        'Together we can achieve great things.',
        'Be part of something meaningful.',
        'Help us reach our fundraising goal.',
        'Support a cause that matters.',
        'Your generosity changes lives.',
        'Making the world a better place.',
        'Stand with us for this important cause.',
        'Your contribution makes an impact.',
        'Join our mission to help others.',
        'Together we can make it happen.',
        'Every dollar brings us closer.',
        'Be the change you want to see.',
        'Help us create lasting impact.',
        'Your support is greatly appreciated.',
        'Making dreams become reality.',
        'Building hope for the future.',
    ];

    /**
     * Color schemes for campaigns.
     *
     * @since 1.0.0
     * @var array
     */
    private array $colorSchemes = [
        'blue_theme' => [
            'primary' => ['#1e40af', '#3b82f6', '#60a5fa', '#93c5fd'],
            'secondary' => ['#f59e0b', '#fbbf24', '#fcd34d', '#fde68a']
        ],
        'green_theme' => [
            'primary' => ['#059669', '#10b981', '#34d399', '#6ee7b7'],
            'secondary' => ['#dc2626', '#ef4444', '#f87171', '#fca5a5']
        ],
        'red_theme' => [
            'primary' => ['#dc2626', '#ef4444', '#f87171', '#fca5a5'],
            'secondary' => ['#059669', '#10b981', '#34d399', '#6ee7b7']
        ],
        'purple_theme' => [
            'primary' => ['#7c3aed', '#8b5cf6', '#a78bfa', '#c4b5fd'],
            'secondary' => ['#f59e0b', '#fbbf24', '#fcd34d', '#fde68a']
        ],
        'orange_theme' => [
            'primary' => ['#ea580c', '#f97316', '#fb923c', '#fdba74'],
            'secondary' => ['#1e40af', '#3b82f6', '#60a5fa', '#93c5fd']
        ]
    ];

    /**
     * Handle AJAX request for generating test campaigns.
     *
     * @since 1.0.0
     */
    public function handleAjaxRequest(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'campaign_generator_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'give-data-generator')]);
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_give_settings')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'give-data-generator')]);
            return;
        }

        try {
            $campaignCount = intval($_POST['campaign_count']);
            $campaignStatus = sanitize_text_field($_POST['campaign_status']);
            $goalType = sanitize_text_field($_POST['goal_type']);
            $goalAmountMin = intval($_POST['goal_amount_min']);
            $goalAmountMax = intval($_POST['goal_amount_max']);
            $colorScheme = sanitize_text_field($_POST['color_scheme']);
            $includeShortDesc = !empty($_POST['include_short_desc']);
            $includeLongDesc = !empty($_POST['include_long_desc']);
            $campaignDuration = sanitize_text_field($_POST['campaign_duration']);
            $createForms = !empty($_POST['create_forms']);
            $titlePrefix = sanitize_text_field($_POST['campaign_title_prefix']);

            // Validate inputs
            if ($campaignCount < 1 || $campaignCount > 50) {
                wp_send_json_error(['message' => __('Number of campaigns must be between 1 and 50.', 'give-data-generator')]);
                return;
            }

            if ($goalAmountMin >= $goalAmountMax) {
                wp_send_json_error(['message' => __('Minimum goal amount must be less than maximum goal amount.', 'give-data-generator')]);
                return;
            }

            // Generate campaigns
            $generated = $this->generateCampaigns(
                $campaignCount,
                $campaignStatus,
                $goalType,
                $goalAmountMin,
                $goalAmountMax,
                $colorScheme,
                $includeShortDesc,
                $includeLongDesc,
                $campaignDuration,
                $createForms,
                $titlePrefix
            );

            wp_send_json_success([
                'message' => sprintf(
                    __('Successfully generated %d test campaigns.', 'give-data-generator'),
                    $generated
                )
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Generate test campaigns.
     *
     * @since 1.0.0
     *
     * @param int $count
     * @param string $status
     * @param string $goalType
     * @param int $goalAmountMin
     * @param int $goalAmountMax
     * @param string $colorScheme
     * @param bool $includeShortDesc
     * @param bool $includeLongDesc
     * @param string $duration
     * @param bool $createForms
     * @param string $titlePrefix
     *
     * @return int Number of campaigns generated
     * @throws Exception
     */
    public function generateCampaigns(
        int $count,
        string $status,
        string $goalType,
        int $goalAmountMin,
        int $goalAmountMax,
        string $colorScheme,
        bool $includeShortDesc,
        bool $includeLongDesc,
        string $duration,
        bool $createForms,
        string $titlePrefix
    ): int {
        $generated = 0;
        $errors = [];
        $consecutiveErrors = 0;

        for ($i = 0; $i < $count; $i++) {
            try {
                $this->createTestCampaign(
                    $status,
                    $goalType,
                    $goalAmountMin,
                    $goalAmountMax,
                    $colorScheme,
                    $includeShortDesc,
                    $includeLongDesc,
                    $duration,
                    $createForms,
                    $titlePrefix
                );
                $generated++;
                $consecutiveErrors = 0;
            } catch (Exception $e) {
                $consecutiveErrors++;
                $errorMessage = 'Campaign Generator Error (iteration ' . ($i + 1) . '): ' . $e->getMessage();
                error_log($errorMessage);
                $errors[] = $errorMessage;

                if ($consecutiveErrors >= 5) {
                    error_log('Campaign Generator: Too many consecutive errors, stopping generation. Last error: ' . $e->getMessage());
                    break;
                }
            }
        }

        if (!empty($errors) && $generated > 0) {
            error_log('Campaign Generator: Generated ' . $generated . ' campaigns with ' . count($errors) . ' errors.');
        }

        if ($generated === 0 && !empty($errors)) {
            throw new Exception('No campaigns were generated. Errors: ' . implode(', ', array_slice($errors, 0, 3)));
        }

        return $generated;
    }

    /**
     * Create a single test campaign.
     *
     * @since 1.0.0
     *
     * @param string $status
     * @param string $goalType
     * @param int $goalAmountMin
     * @param int $goalAmountMax
     * @param string $colorScheme
     * @param bool $includeShortDesc
     * @param bool $includeLongDesc
     * @param string $duration
     * @param bool $createForms
     * @param string $titlePrefix
     *
     * @throws Exception
     */
    private function createTestCampaign(
        string $status,
        string $goalType,
        int $goalAmountMin,
        int $goalAmountMax,
        string $colorScheme,
        bool $includeShortDesc,
        bool $includeLongDesc,
        string $duration,
        bool $createForms,
        string $titlePrefix
    ): void {
        // Generate campaign data
        $title = $this->generateCampaignTitle($titlePrefix);
        $goal = $this->generateRandomGoalAmount($goalAmountMin, $goalAmountMax);
        $colors = $this->generateColors($colorScheme);
        $dates = $this->generateCampaignDates($duration);

        // Create campaign
        $campaign = Campaign::create([
            'type' => CampaignType::CORE(),
            'title' => $title,
            'shortDescription' => $includeShortDesc ? $this->getRandomItem($this->shortDescriptions) : '',
            'longDescription' => $includeLongDesc ? $this->generateLongDescription() : '',
            'logo' => '',
            'image' => '',
            'goal' => $goal,
            'goalType' => new CampaignGoalType($goalType),
            'status' => new CampaignStatus($status),
            'primaryColor' => $colors['primary'],
            'secondaryColor' => $colors['secondary'],
            'startDate' => $dates['start'],
            'endDate' => $dates['end'],
        ]);

        // Create associated donation form if requested
        if ($createForms && $campaign->id) {
            try {
                give(CreateDefaultCampaignForm::class)($campaign);
            } catch (Exception $e) {
                error_log('Campaign Generator: Failed to create form for campaign ' . $campaign->id . ': ' . $e->getMessage());
            }
        }

        // Set campaign page status to publish
        if ($campaign->id && $page = $campaign->page()) {
            $page->status = CampaignPageStatus::PUBLISH();
            $page->save();
        }
    }

    /**
     * Generate a campaign title.
     *
     * @since 1.0.0
     *
     * @param string $prefix
     * @return string
     */
    private function generateCampaignTitle(string $prefix): string
    {
        $baseTitle = $this->getRandomItem($this->campaignTitles);

        if (empty($prefix)) {
            return $baseTitle;
        }

        return $prefix . ' - ' . $baseTitle;
    }

    /**
     * Generate random goal amount within range.
     *
     * @since 1.0.0
     *
     * @param int $min
     * @param int $max
     * @return int
     */
    private function generateRandomGoalAmount(int $min, int $max): int
    {
        return rand($min, $max);
    }

    /**
     * Generate campaign colors based on scheme.
     *
     * @since 1.0.0
     *
     * @param string $colorScheme
     * @return array
     */
    private function generateColors(string $colorScheme): array
    {
        if ($colorScheme === 'random' || !isset($this->colorSchemes[$colorScheme])) {
            return [
                'primary' => $this->generateRandomColor(),
                'secondary' => $this->generateRandomColor()
            ];
        }

        $scheme = $this->colorSchemes[$colorScheme];
        return [
            'primary' => $this->getRandomItem($scheme['primary']),
            'secondary' => $this->getRandomItem($scheme['secondary'])
        ];
    }

    /**
     * Generate a random hex color.
     *
     * @since 1.0.0
     *
     * @return string
     */
    private function generateRandomColor(): string
    {
        return sprintf('#%06x', rand(0, 0xFFFFFF));
    }

    /**
     * Generate campaign start and end dates.
     *
     * @since 1.0.0
     *
     * @param string $duration
     * @return array
     */
    private function generateCampaignDates(string $duration): array
    {
        $startDate = new DateTime();

        // Randomly start campaigns up to 30 days ago or up to 7 days in the future
        $startOffset = rand(-30, 7);
        $startDate->modify($startOffset . ' days');

        $endDate = null;

        switch ($duration) {
            case '30_days':
                $endDate = clone $startDate;
                $endDate->modify('+30 days');
                break;
            case '60_days':
                $endDate = clone $startDate;
                $endDate->modify('+60 days');
                break;
            case '90_days':
                $endDate = clone $startDate;
                $endDate->modify('+90 days');
                break;
            case '6_months':
                $endDate = clone $startDate;
                $endDate->modify('+6 months');
                break;
            case '1_year':
                $endDate = clone $startDate;
                $endDate->modify('+1 year');
                break;
            case 'ongoing':
            default:
                // No end date for ongoing campaigns
                break;
        }

        return [
            'start' => $startDate,
            'end' => $endDate
        ];
    }

    /**
     * Generate a long description.
     *
     * @since 1.0.0
     *
     * @return string
     */
    private function generateLongDescription(): string
    {
        $templates = [
            'Our organization is dedicated to making a positive impact in the community. Through your generous support, we can continue our important work and reach even more people in need. Every donation, no matter the size, helps us move closer to our goal.',
            'This campaign represents hope for countless individuals and families. Your contribution will directly support our mission and help us create lasting change. Together, we can build a brighter future for everyone.',
            'We believe that when people come together for a common cause, amazing things can happen. Your donation will help fund essential programs and services that make a real difference in people\'s lives.',
            'Thank you for considering a donation to our cause. Your support helps us continue our vital work in the community and ensures that we can keep providing the services that people depend on.',
            'Every dollar donated goes directly toward supporting our mission. We are committed to transparency and making sure your contribution has the maximum possible impact. Join us in making a difference today.',
        ];

        return $this->getRandomItem($templates);
    }

    /**
     * Get a random item from an array.
     *
     * @since 1.0.0
     *
     * @param array $items
     * @return mixed
     */
    private function getRandomItem(array $items)
    {
        if (empty($items)) {
            return '';
        }

        return $items[array_rand($items)];
    }
}
