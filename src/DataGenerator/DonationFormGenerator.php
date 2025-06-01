<?php

namespace GiveDataGenerator\DataGenerator;

use DateTime;
use Exception;
use Give\Campaigns\Models\Campaign;
use Give\DonationForms\Models\DonationForm;
use Give\DonationForms\Properties\FormSettings;
use Give\DonationForms\ValueObjects\DonationFormStatus;
use Give\DonationForms\ValueObjects\GoalSource;
use Give\FormBuilder\Actions\GenerateDefaultDonationFormBlockCollection;
use Give\DonationForms\FormDesigns\MultiStepFormDesign\MultiStepFormDesign;
use Give\DonationForms\FormDesigns\ClassicFormDesign\ClassicFormDesign;
use Give\DonationForms\FormDesigns\TwoPanelStepsFormLayout\TwoPanelStepsFormLayout;

/**
 * Donation Form Generator.
 *
 * @package     GiveDataGenerator\DataGenerator
 * @since       1.0.0
 */
class DonationFormGenerator
{
    /**
     * Sample form titles for generating fake donation forms.
     *
     * @since 1.0.0
     * @var array
     */
    private array $formTitles = [
        'Emergency Relief Donation',
        'Monthly Support Form',
        'Quick Donation',
        'Sponsor a Child',
        'Build a School',
        'Medical Fund Donation',
        'Education Support',
        'Clean Water Project',
        'Food Bank Contribution',
        'Animal Rescue Support',
        'Community Garden Fund',
        'Senior Care Donation',
        'Youth Program Support',
        'Scholarship Fund',
        'Environmental Protection',
        'Disaster Response',
        'Healthcare Access',
        'Arts & Culture Fund',
        'Technology for Education',
        'Local Business Recovery',
        'Housing Initiative',
        'Mental Health Support',
        'Veterans Assistance',
        'Special Needs Program',
        'Rural Development',
        'Women\'s Empowerment',
        'Children\'s Safety Net',
        'Community Kitchen',
        'Mobile Health Clinic',
        'Job Training Support',
    ];

    /**
     * Available form designs.
     *
     * @since 1.0.0
     * @var array
     */
    private array $formDesigns = [
        MultiStepFormDesign::class,
        ClassicFormDesign::class,
        TwoPanelStepsFormLayout::class,
    ];

    /**
     * Handle AJAX request for generating test donation forms.
     *
     * @since 1.0.0
     */
    public function handleAjaxRequest(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'donation_form_generator_nonce')) {
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
            $formCount = intval($_POST['form_count']);
            $formStatus = sanitize_text_field($_POST['form_status']);
            $enableGoals = !empty($_POST['enable_goals']);
            $goalType = sanitize_text_field($_POST['goal_type']);
            $goalAmountMin = intval($_POST['goal_amount_min']);
            $goalAmountMax = intval($_POST['goal_amount_max']);
            $randomDesigns = !empty($_POST['random_designs']);
            $titlePrefix = sanitize_text_field($_POST['form_title_prefix']);
            $inheritCampaignColors = !empty($_POST['inherit_campaign_colors']);

            // Validate inputs
            if ($formCount < 1 || $formCount > 20) {
                wp_send_json_error(['message' => __('Number of forms must be between 1 and 20.', 'give-data-generator')]);
                return;
            }

            if (!$campaignId) {
                wp_send_json_error(['message' => __('Please select a campaign.', 'give-data-generator')]);
                return;
            }

            // Verify campaign exists
            $campaign = Campaign::find($campaignId);
            if (!$campaign) {
                wp_send_json_error(['message' => __('Selected campaign not found.', 'give-data-generator')]);
                return;
            }

            if ($enableGoals && $goalAmountMin >= $goalAmountMax) {
                wp_send_json_error(['message' => __('Minimum goal amount must be less than maximum goal amount.', 'give-data-generator')]);
                return;
            }

            // Generate donation forms
            $generated = $this->generateDonationForms(
                $campaign,
                $formCount,
                $formStatus,
                $enableGoals,
                $goalType,
                $goalAmountMin,
                $goalAmountMax,
                $randomDesigns,
                $titlePrefix,
                $inheritCampaignColors
            );

            wp_send_json_success([
                'message' => sprintf(
                    _n(
                        '%d donation form generated successfully for campaign "%s".',
                        '%d donation forms generated successfully for campaign "%s".',
                        $generated,
                        'give-data-generator'
                    ),
                    $generated,
                    $campaign->title
                ),
                'generated' => $generated,
                'campaign' => $campaign->title
            ]);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Error generating donation forms: %s', 'give-data-generator'),
                    $e->getMessage()
                )
            ]);
        }
    }

    /**
     * Generate test donation forms for a campaign.
     *
     * @since 1.0.0
     *
     * @param Campaign $campaign
     * @param int $count
     * @param string $status
     * @param bool $enableGoals
     * @param string $goalType
     * @param int $goalAmountMin
     * @param int $goalAmountMax
     * @param bool $randomDesigns
     * @param string $titlePrefix
     * @param bool $inheritCampaignColors
     *
     * @return int Number of forms generated
     * @throws Exception
     */
    public function generateDonationForms(
        Campaign $campaign,
        int $count,
        string $status,
        bool $enableGoals,
        string $goalType,
        int $goalAmountMin,
        int $goalAmountMax,
        bool $randomDesigns,
        string $titlePrefix,
        bool $inheritCampaignColors
    ): int {
        $generated = 0;

        for ($i = 0; $i < $count; $i++) {
            try {
                $this->createTestDonationForm(
                    $campaign,
                    $status,
                    $enableGoals,
                    $goalType,
                    $goalAmountMin,
                    $goalAmountMax,
                    $randomDesigns,
                    $titlePrefix,
                    $inheritCampaignColors
                );
                $generated++;
            } catch (Exception $e) {
                // Log error but continue generating other forms
                error_log(sprintf('Error generating donation form: %s', $e->getMessage()));
            }
        }

        return $generated;
    }

    /**
     * Create a single test donation form.
     *
     * @since 1.0.0
     *
     * @param Campaign $campaign
     * @param string $status
     * @param bool $enableGoals
     * @param string $goalType
     * @param int $goalAmountMin
     * @param int $goalAmountMax
     * @param bool $randomDesigns
     * @param string $titlePrefix
     * @param bool $inheritCampaignColors
     *
     * @throws Exception
     */
    private function createTestDonationForm(
        Campaign $campaign,
        string $status,
        bool $enableGoals,
        string $goalType,
        int $goalAmountMin,
        int $goalAmountMax,
        bool $randomDesigns,
        string $titlePrefix,
        bool $inheritCampaignColors
    ): void {
        // Generate form title
        $formTitle = $this->generateFormTitle($titlePrefix, $campaign->title);

        // Determine form status
        $formStatus = $this->getFormStatus($status);

        // Generate goal settings
        $goalSettings = $this->generateGoalSettings($enableGoals, $goalType, $goalAmountMin, $goalAmountMax, $campaign);

        // Generate form design
        $designId = $this->generateFormDesign($randomDesigns);

        // Create form settings
        $formSettings = FormSettings::fromArray([
            'showHeader' => true,
            'enableDonationGoal' => $goalSettings['enabled'],
            'goalAmount' => $goalSettings['amount'],
            'goalType' => $goalSettings['type'],
            'goalSource' => $goalSettings['source'],
            'designId' => $designId,
            'inheritCampaignColors' => $inheritCampaignColors,
            'primaryColor' => $inheritCampaignColors ? $campaign->primaryColor : '#28C77B',
            'secondaryColor' => $inheritCampaignColors ? $campaign->secondaryColor : '#FFA200',
        ]);

        // Create the donation form
        $donationForm = DonationForm::create([
            'title' => $formTitle,
            'status' => $formStatus,
            'settings' => $formSettings,
            'blocks' => (new GenerateDefaultDonationFormBlockCollection())(),
        ]);

        // Associate form with campaign
        $this->associateFormWithCampaign($campaign, $donationForm);
    }

    /**
     * Generate form title.
     *
     * @since 1.0.0
     *
     * @param string $prefix
     * @param string $campaignTitle
     *
     * @return string
     */
    private function generateFormTitle(string $prefix, string $campaignTitle): string
    {
        $baseTitle = $this->getRandomItem($this->formTitles);

        if (!empty($prefix)) {
            $title = $prefix . ' ' . $baseTitle;
        } else {
            $title = $baseTitle . ' - ' . $campaignTitle;
        }

        return $title;
    }

    /**
     * Get form status enum from string.
     *
     * @since 1.0.0
     *
     * @param string $status
     *
     * @return DonationFormStatus
     */
    private function getFormStatus(string $status): DonationFormStatus
    {
        switch ($status) {
            case 'published':
                return DonationFormStatus::PUBLISHED();
            case 'draft':
                return DonationFormStatus::DRAFT();
            case 'private':
                return DonationFormStatus::PRIVATE();
            default:
                return DonationFormStatus::PUBLISHED();
        }
    }

    /**
     * Generate goal settings.
     *
     * @since 1.0.0
     *
     * @param bool $enableGoals
     * @param string $goalType
     * @param int $goalAmountMin
     * @param int $goalAmountMax
     * @param Campaign $campaign
     *
     * @return array
     */
    private function generateGoalSettings(bool $enableGoals, string $goalType, int $goalAmountMin, int $goalAmountMax, Campaign $campaign): array
    {
        if (!$enableGoals) {
            return [
                'enabled' => false,
                'amount' => 0,
                'type' => 'amount',
                'source' => GoalSource::FORM()->getValue(),
            ];
        }

        // If using campaign goal, inherit from campaign
        if ($goalType === 'campaign') {
            return [
                'enabled' => true,
                'amount' => $campaign->goal,
                'type' => $campaign->goalType->getValue(),
                'source' => GoalSource::CAMPAIGN()->getValue(),
            ];
        }

        // Generate random goal amount
        $goalAmount = $this->generateRandomGoalAmount($goalAmountMin, $goalAmountMax);

        return [
            'enabled' => true,
            'amount' => $goalAmount,
            'type' => $goalType,
            'source' => GoalSource::FORM()->getValue(),
        ];
    }

    /**
     * Generate random goal amount.
     *
     * @since 1.0.0
     *
     * @param int $min
     * @param int $max
     *
     * @return int
     */
    private function generateRandomGoalAmount(int $min, int $max): int
    {
        return random_int($min, $max);
    }

    /**
     * Generate form design.
     *
     * @since 1.0.0
     *
     * @param bool $randomDesigns
     *
     * @return string
     */
    private function generateFormDesign(bool $randomDesigns): string
    {
        if (!$randomDesigns) {
            return MultiStepFormDesign::id();
        }

        $designClass = $this->getRandomItem($this->formDesigns);
        return $designClass::id();
    }

    /**
     * Associate form with campaign.
     *
     * @since 1.0.0
     *
     * @param Campaign $campaign
     * @param DonationForm $donationForm
     *
     * @throws Exception
     */
    private function associateFormWithCampaign(Campaign $campaign, DonationForm $donationForm): void
    {
        // Use GiveWP's campaign repository to associate the form
        give(\Give\Campaigns\Repositories\CampaignRepository::class)->addCampaignForm($campaign, $donationForm->id);
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
}
