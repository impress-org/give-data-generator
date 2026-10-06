<?php

namespace GiveDataGenerator\DataGenerator;

use Exception;
use Give\Campaigns\Models\Campaign;

/**
 * Page Generator: one page for every GiveWP block and shortcode.
 *
 * @package     GiveDataGenerator\DataGenerator
 * @unreleased
 */
class PageGenerator
{
    /**
     * Handle AJAX request for generating pages.
     *
     * @unreleased
     */
    public function handleAjaxRequest(): void
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'page_generator_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'give-data-generator')]);
        }

        if (!current_user_can('manage_give_settings')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'give-data-generator')]);
        }

        if (!empty($_POST['campaign_id'])) {
            $campaign = Campaign::find(absint($_POST['campaign_id']));
            if (!$campaign) {
                wp_send_json_error(['message' => __('Selected campaign not found.', 'give-data-generator')]);
            }
            $campaignId = $campaign->id;
            $formId = (int)$campaign->defaultFormId;
        } else {
            $campaignId = null;
            $formId = absint($_POST['form_id'] ?? 0);
            if (get_post_type($formId) !== 'give_forms') {
                wp_send_json_error(['message' => __('Selected donation form not found.', 'give-data-generator')]);
            }
        }

        $titles = array_map('sanitize_text_field', wp_unslash((array)($_POST['pages'] ?? [])));
        if (!$titles) {
            wp_send_json_error(['message' => __('Select at least one block or shortcode.', 'give-data-generator')]);
        }

        $layout = sanitize_key($_POST['layout'] ?? 'individual');

        try {
            $pageIds = $this->generatePages($titles, $formId, $campaignId, $layout);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }

        wp_send_json_success([
            'message' => sprintf(__('%d pages created. Find them under Pages.', 'give-data-generator'), count($pageIds)),
        ]);
    }

    /**
     * Create published pages for the selected blocks and shortcodes.
     *
     * @unreleased
     * @param string[] $titles Keys of getPageContents() to create; unknown titles are ignored.
     * @param int|null $campaignId Null for a standalone form, which skips the campaign blocks and shortcodes.
     * @param string $layout "individual" for a page each, "type" for a Blocks page and a Shortcodes page, "single" for one page.
     * @return int[] Page IDs.
     * @throws Exception
     */
    public function generatePages(array $titles, int $formId, ?int $campaignId = null, string $layout = 'individual'): array
    {
        if (!in_array($layout, ['individual', 'type', 'single'], true)) {
            throw new Exception(sprintf('Unknown layout "%s". Use individual, type or single.', $layout));
        }

        $pages = [];
        $contents = $this->getPageContents($formId, $campaignId);

        foreach (array_intersect_key($contents, array_flip($titles)) as $title => $content) {
            if ($layout === 'single') {
                $pages['GiveWP Blocks and Shortcodes'][] = $this->heading($title) . $content;
            } elseif ($layout === 'type') {
                $pages[strpos($title, 'Block: ') === 0 ? 'GiveWP Blocks' : 'GiveWP Shortcodes'][] = $this->heading($title) . $content;
            } else {
                $pages[$title][] = $content;
            }
        }

        $pageIds = [];

        foreach ($pages as $title => $parts) {
            $pageId = wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => $title,
                // wp_insert_post unslashes, which would break the escaped JSON in block comments.
                'post_content' => wp_slash(implode("\n\n", $parts)),
            ], true);

            if (is_wp_error($pageId)) {
                throw new Exception($pageId->get_error_message());
            }

            $pageIds[] = $pageId;
        }

        return $pageIds;
    }

    /**
     * Page title => page content for every block and shortcode. Without a campaign, only
     * the ones that don't need one.
     *
     * @unreleased
     */
    public function getPageContents(int $formId, ?int $campaignId = null): array
    {
        $blocks = [];
        $shortcodes = [];

        if ($campaignId !== null) {
            $blocks = [
                'givewp/campaign-block' => ['campaignId' => $campaignId],
                'givewp/campaign-title' => ['campaignId' => $campaignId],
                'givewp/campaign-cover-block' => ['campaignId' => $campaignId],
                'givewp/campaign-goal' => ['campaignId' => $campaignId],
                'givewp/campaign-stats-block' => ['campaignId' => $campaignId],
                'givewp/campaign-donors' => ['campaignId' => $campaignId],
                'givewp/campaign-donations' => ['campaignId' => $campaignId],
                'givewp/campaign-comments-block' => ['campaignId' => $campaignId],
                'givewp/campaign-donate-button' => ['campaignId' => $campaignId],
                'givewp/campaign-form' => ['campaignId' => $campaignId, 'id' => $formId],
            ];

            $shortcodes = [
                "[givewp_campaign campaign_id=\"$campaignId\"]",
                "[givewp_campaign_form campaign_id=\"$campaignId\"]",
                "[givewp_campaign_goal campaign_id=\"$campaignId\"]",
                "[givewp_campaign_stats campaign_id=\"$campaignId\"]",
                "[givewp_campaign_donors campaign_id=\"$campaignId\"]",
                "[givewp_campaign_donations campaign_id=\"$campaignId\"]",
                "[givewp_campaign_comments campaign_id=\"$campaignId\"]",
            ];
        }

        $blocks += [
            'givewp/campaign-grid' => [],
            'givewp/donation-form' => ['formId' => (string)$formId], // block.json types formId as a string
            'give/donation-form' => ['id' => $formId],
            'give/donation-form-grid' => [],
            'give/donor-wall' => [],
            'give/donor-dashboard' => [],
            'give/progress-bar' => ['ids' => [(string)$formId]],
        ];

        $shortcodes = array_merge($shortcodes, [
            '[givewp_campaign_grid]',
            "[give_form id=\"$formId\"]",
            "[give_goal id=\"$formId\"]",
            '[give_form_grid]',
            '[give_donor_wall]',
            "[give_totals ids=\"$formId\" total_goal=\"10000\"]",
            "[give_multi_form_goal ids=\"$formId\" goal=\"10000\"]",
            '[give_donor_dashboard]',
            '[donation_history]',
            '[give_receipt]',
            '[give_login]',
            '[give_register]',
            '[give_profile_editor]',
        ]);

        $contents = [];

        foreach ($blocks as $name => $attrs) {
            $contents["Block: $name"] = serialize_block($this->block($name, $attrs));
        }

        // Multi-form goal only renders its inner blocks, so give it a progress bar to show.
        $contents['Block: give/multi-form-goal'] = serialize_block([
            'blockName' => 'give/multi-form-goal',
            'attrs' => [],
            'innerBlocks' => [$this->block('give/progress-bar', $blocks['give/progress-bar'])],
            'innerHTML' => '<div class="give-multi-form-goal-block"></div>',
            'innerContent' => ['<div class="give-multi-form-goal-block">', null, '</div>'],
        ]);

        foreach ($shortcodes as $shortcode) {
            $tag = strtok(trim($shortcode, '[]'), ' ');
            $contents["Shortcode: [$tag]"] = serialize_block($this->block('core/shortcode', [], $shortcode));
        }

        return $contents;
    }

    /**
     * Heading block that labels each section of a grouped page.
     *
     * @unreleased
     */
    private function heading(string $title): string
    {
        // Encode brackets so the_content doesn't run the shortcode named in the heading.
        $text = str_replace(['[', ']'], ['&#91;', '&#93;'], esc_html($title));

        return serialize_block($this->block('core/heading', [], "<h2 class=\"wp-block-heading\">$text</h2>")) . "\n\n";
    }

    /**
     * Parsed block array in the shape serialize_block() expects.
     *
     * @unreleased
     */
    private function block(string $name, array $attrs, string $html = ''): array
    {
        return [
            'blockName' => $name,
            'attrs' => $attrs,
            'innerBlocks' => [],
            'innerHTML' => $html,
            'innerContent' => [$html],
        ];
    }
}
