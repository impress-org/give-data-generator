<?php

namespace GiveDataGenerator\Tests\Unit\GiveDataGenerator;

use Give\Campaigns\Models\Campaign;
use Give\Tests\TestCase;
use Give\Tests\TestTraits\RefreshDatabase;
use GiveDataGenerator\DataGenerator\PageGenerator;

class TestPageGenerator extends TestCase
{
    use RefreshDatabase;

    /**
     * @unreleased
     */
    public function testGeneratePagesCreatesParsablePageForEveryBlockAndShortcode()
    {
        // Refetch so defaultFormId is set by the campaign-created hook.
        $campaign = Campaign::find(Campaign::factory()->create()->id);
        $generator = new PageGenerator();

        $contents = $generator->getPageContents($campaign->defaultFormId, $campaign->id);
        $pageIds = $generator->generatePages(array_keys($contents), $campaign->defaultFormId, $campaign->id);

        $this->assertCount(count($contents), $pageIds);

        $blockPage = get_post($pageIds[0]);
        $this->assertSame('Block: givewp/campaign-block', $blockPage->post_title);
        $this->assertSame('publish', $blockPage->post_status);
        $this->assertSame(['campaignId' => $campaign->id], parse_blocks($blockPage->post_content)[0]['attrs']);

        $shortcodePage = get_post(end($pageIds));
        $this->assertSame('Shortcode: [give_profile_editor]', $shortcodePage->post_title);
        $this->assertTrue(has_shortcode($shortcodePage->post_content, 'give_profile_editor'));

        $multiFormGoal = parse_blocks($contents['Block: give/multi-form-goal'])[0];
        $this->assertSame('give/progress-bar', $multiFormGoal['innerBlocks'][0]['blockName']);

        // WordPress drops attributes that don't match the block.json type, which renders nothing.
        $this->assertNotEmpty(do_blocks($contents['Block: givewp/donation-form']));
    }

    /**
     * @unreleased
     */
    public function testGeneratePagesOnlyCreatesSelectedTitles()
    {
        $pageIds = (new PageGenerator())->generatePages(['Shortcode: [give_form]', 'Not a real page'], 1, 1);

        $this->assertCount(1, $pageIds);
        $this->assertSame('Shortcode: [give_form]', get_post($pageIds[0])->post_title);
    }

    /**
     * @unreleased
     */
    public function testGeneratePagesGroupsByLayout()
    {
        $generator = new PageGenerator();
        $titles = ['Block: give/donor-wall', 'Block: give/donation-form-grid', 'Shortcode: [give_form]'];

        $this->assertCount(2, $generator->generatePages($titles, 1, 1, 'type'));

        $pageIds = $generator->generatePages($titles, 1, 1, 'single');
        $this->assertCount(1, $pageIds);

        $page = get_post($pageIds[0]);
        $this->assertSame('GiveWP Blocks and Shortcodes', $page->post_title);
        $this->assertSame(
            ['core/heading', 'give/donation-form-grid', 'core/heading', 'give/donor-wall', 'core/heading', 'core/shortcode'],
            array_values(array_filter(array_column(parse_blocks($page->post_content), 'blockName')))
        );
        // The heading names the shortcode without running it.
        $this->assertStringNotContainsString('Shortcode: [give_form]', $page->post_content);
    }

    /**
     * @unreleased
     */
    public function testGeneratePagesRejectsUnknownLayout()
    {
        $this->expectException(\Exception::class);

        (new PageGenerator())->generatePages(['Shortcode: [give_form]'], 1, 1, '1');
    }

    /**
     * @unreleased
     */
    public function testStandaloneFormSkipsCampaignPages()
    {
        $contents = (new PageGenerator())->getPageContents(7);

        $this->assertArrayHasKey('Shortcode: [give_form]', $contents);
        $this->assertStringContainsString('id="7"', $contents['Shortcode: [give_form]']);
        $this->assertArrayNotHasKey('Block: givewp/campaign-block', $contents);
        $this->assertArrayNotHasKey('Shortcode: [givewp_campaign]', $contents);
    }
}
