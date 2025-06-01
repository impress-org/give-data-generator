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
        'Music Education Fund',
        'Solar Energy Project',
        'Women\'s Empowerment',
        'Elder Care Support',
        'Refugee Assistance',
        'Local Business Recovery',
        'Tree Planting Initiative',
        'Special Needs Support',
        'Artisan Craft Program',
        'Community Kitchen',
        'Mobile Health Clinic',
        'Student Meal Program',
        'Digital Inclusion',
        'Addiction Recovery',
        'Homeless Shelter Fund',
        'After School Program',
        'Bike Share Initiative',
        'Maternal Health Care',
        'Job Training Center',
        'Community Safety Net',
        'Cultural Heritage Fund'
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
            $imageSource = sanitize_text_field($_POST['image_source'] ?? 'none');

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
                $titlePrefix,
                $imageSource
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
     * @param string $imageSource
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
        string $titlePrefix,
        string $imageSource = 'none'
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
                    $titlePrefix,
                    $imageSource
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
     * @param string $imageSource
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
        string $titlePrefix,
        string $imageSource = 'none'
    ): void {
        // Generate campaign data
        $title = $this->generateCampaignTitle($titlePrefix);
        $goal = $this->generateRandomGoalAmount($goalAmountMin, $goalAmountMax);
        $colors = $this->generateColors($colorScheme);
        $dates = $this->generateCampaignDates($duration);
        $imageUrl = $this->generateCampaignImage($title, $imageSource);

        // Create campaign
        $campaign = Campaign::create([
            'type' => CampaignType::CORE(),
            'title' => $title,
            'shortDescription' => $includeShortDesc ? $this->getRandomItem($this->shortDescriptions) : '',
            'longDescription' => $includeLongDesc ? $this->generateLongDescription() : '',
            'logo' => '',
            'image' => $imageUrl,
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

            // Set featured image if campaign has an image
            if (!has_post_thumbnail($page->id)) {
                if ($campaign->image && $imageId = attachment_url_to_postid($campaign->image)) {
                    set_post_thumbnail($page->id, $imageId);
                }
            }

            $page->save();
        }
    }

    /**
     * Generate and upload campaign image based on title and image source.
     *
     * @since 1.0.0
     *
     * @param string $campaignTitle
     * @param string $imageSource
     * @return string Image URL
     */
    private function generateCampaignImage(string $campaignTitle, string $imageSource): string
    {
        // Skip image generation if not enabled
        if ($imageSource === 'none') {
            return '';
        }

        try {
            // Create search terms from campaign title
            $searchTerms = $this->createSearchTermsFromTitle($campaignTitle);

            $imageData = null;

            // Try different image sources
            switch ($imageSource) {
                case 'openverse':
                    $imageData = $this->fetchImageFromOpenverse($searchTerms);
                    break;
                case 'lorem_picsum':
                    $imageData = $this->fetchImageFromLoremPicsum($searchTerms);
                    break;
                case 'random':
                    // Try sources in random order
                    $sources = ['openverse', 'lorem_picsum'];
                    shuffle($sources);
                    foreach ($sources as $source) {
                        switch ($source) {
                            case 'openverse':
                                $imageData = $this->fetchImageFromOpenverse($searchTerms);
                                break;
                            case 'lorem_picsum':
                                $imageData = $this->fetchImageFromLoremPicsum($searchTerms);
                                break;
                        }
                        if ($imageData) break;
                    }
                    break;
                default:
                    return '';
            }

            if (!$imageData) {
                return '';
            }

            // Download and upload the image
            return $this->downloadAndUploadImage($imageData, $campaignTitle);

        } catch (Exception $e) {
            error_log('Campaign image generation error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Create search terms from campaign title.
     *
     * @since 1.0.0
     *
     * @param string $title
     * @return string
     */
    private function createSearchTermsFromTitle(string $title): string
    {
        // Remove common words and clean up title for better search
        $commonWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'fund', 'initiative', 'program', 'project'];
        $words = explode(' ', strtolower($title));
        $filteredWords = array_filter($words, function($word) use ($commonWords) {
            return !in_array(trim($word), $commonWords) && strlen(trim($word)) > 2;
        });

        return implode(' ', array_slice($filteredWords, 0, 3)); // Use first 3 meaningful words
    }

    /**
     * Fetch image from Openverse API based on search term.
     *
     * @since 1.0.0
     *
     * @param string $searchTerm
     * @return array|null
     */
    private function fetchImageFromOpenverse(string $searchTerm): ?array
    {
        $searchQuery = urlencode($searchTerm);
        $apiUrl = "https://api.openverse.org/v1/images/?format=json&q={$searchQuery}&page_size=20&mature=false";

        $response = wp_remote_get($apiUrl, [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'GiveWP Campaign Generator/1.0 (WordPress Plugin)',
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('Openverse API Error: ' . $response->get_error_message());
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data['results'])) {
            return null;
        }

        // Filter out images that are too small or have issues
        $validImages = array_filter($data['results'], function($image) {
            return !empty($image['url']) &&
                   !empty($image['license']) &&
                   ($image['width'] ?? 0) >= 400 &&
                   ($image['height'] ?? 0) >= 300;
        });

        if (empty($validImages)) {
            return null;
        }

        // Get a random image from the results
        $selectedImage = $validImages[array_rand($validImages)];

        return [
            'url' => $selectedImage['url'],
            'title' => $selectedImage['title'] ?? '',
            'creator' => $selectedImage['creator'] ?? '',
            'license' => $selectedImage['license'] ?? '',
            'source' => 'Openverse (' . ($selectedImage['source'] ?? 'Unknown') . ')',
            'foreign_landing_url' => $selectedImage['foreign_landing_url'] ?? '',
            'attribution' => $this->buildOpenverseAttribution($selectedImage)
        ];
    }

    /**
     * Build proper attribution for Openverse images.
     *
     * @since 1.0.0
     *
     * @param array $imageData
     * @return string
     */
    private function buildOpenverseAttribution(array $imageData): string
    {
        $attribution = '';

        if (!empty($imageData['title'])) {
            $attribution .= '"' . $imageData['title'] . '"';
        }

        if (!empty($imageData['creator'])) {
            $attribution .= ' by ' . $imageData['creator'];
        }

        if (!empty($imageData['license'])) {
            $attribution .= ' is licensed under ' . $imageData['license'];
        }

        if (!empty($imageData['foreign_landing_url'])) {
            $attribution .= '. View original at: ' . $imageData['foreign_landing_url'];
        }

        $attribution .= ' (via Openverse)';

        return $attribution;
    }

    /**
     * Fetch image from Lorem Picsum service.
     *
     * @since 1.0.0
     *
     * @param string $searchTerm
     * @return array|null
     */
    private function fetchImageFromLoremPicsum(string $searchTerm): ?array
    {
        // Lorem Picsum provides random images, so we'll just get a random one
        $width = rand(800, 1200);
        $height = rand(600, 800);
        $imageId = rand(1, 1000);

        return [
            'url' => "https://picsum.photos/{$width}/{$height}?random={$imageId}",
            'title' => 'Random Image',
            'creator' => 'Lorem Picsum',
            'license' => 'Free to use',
            'source' => 'Lorem Picsum',
            'foreign_landing_url' => 'https://picsum.photos/',
            'attribution' => 'Image from Lorem Picsum (https://picsum.photos/)'
        ];
    }

    /**
     * Download image from URL and upload to WordPress media library.
     *
     * @since 1.0.0
     *
     * @param array $imageData
     * @param string $campaignTitle
     * @return string Uploaded image URL
     */
    private function downloadAndUploadImage(array $imageData, string $campaignTitle): string
    {
        try {
            // Download the image
            $response = wp_remote_get($imageData['url'], [
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => 'GiveWP Campaign Generator/1.0 (WordPress Plugin)',
                ]
            ]);

            if (is_wp_error($response)) {
                error_log('Image download error: ' . $response->get_error_message());
                return '';
            }

            $imageContent = wp_remote_retrieve_body($response);
            $contentType = wp_remote_retrieve_header($response, 'content-type');

            if (empty($imageContent)) {
                return '';
            }

            // Determine file extension from content type
            $extension = 'jpg'; // default
            if (strpos($contentType, 'png') !== false) {
                $extension = 'png';
            } elseif (strpos($contentType, 'webp') !== false) {
                $extension = 'webp';
            } elseif (strpos($contentType, 'gif') !== false) {
                $extension = 'gif';
            }

            // Create a safe filename
            $filename = sanitize_file_name(
                'campaign-' . sanitize_title($campaignTitle) . '-' . time() . '.' . $extension
            );

            // Upload the image using WordPress functions
            $upload = wp_upload_bits($filename, null, $imageContent);

            if ($upload['error']) {
                error_log('WordPress upload error: ' . $upload['error']);
                return '';
            }

            // Create attachment post
            $attachment = [
                'post_mime_type' => $contentType,
                'post_title' => $imageData['title'] ?: $campaignTitle . ' Image',
                'post_content' => '',
                'post_excerpt' => $imageData['attribution'] ?? '',
                'post_status' => 'inherit'
            ];

            $attachmentId = wp_insert_attachment($attachment, $upload['file']);

            if (is_wp_error($attachmentId)) {
                error_log('Attachment creation error: ' . $attachmentId->get_error_message());
                return '';
            }

            // Generate attachment metadata
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attachmentData = wp_generate_attachment_metadata($attachmentId, $upload['file']);
            wp_update_attachment_metadata($attachmentId, $attachmentData);

            // Add source information to attachment meta
            update_post_meta($attachmentId, '_campaign_image_source', $imageData['source']);
            update_post_meta($attachmentId, '_campaign_image_attribution', $imageData['attribution']);
            update_post_meta($attachmentId, '_campaign_image_license', $imageData['license']);
            if (!empty($imageData['foreign_landing_url'])) {
                update_post_meta($attachmentId, '_campaign_image_original_url', $imageData['foreign_landing_url']);
            }

            return $upload['url'];

        } catch (Exception $e) {
            error_log('Image processing error: ' . $e->getMessage());
            return '';
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
