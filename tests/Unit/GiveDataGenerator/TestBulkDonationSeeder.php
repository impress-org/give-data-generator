<?php

namespace GiveDataGenerator\Tests\Unit\GiveDataGenerator;

use Give\Campaigns\Models\Campaign;
use Give\Donations\Models\Donation;
use Give\Framework\Database\DB;
use Give\Tests\TestCase;
use Give\Tests\TestTraits\RefreshDatabase;
use GiveDataGenerator\DataGenerator\BulkDonationSeeder;

/**
 * @since 1.1.0
 */
class TestBulkDonationSeeder extends TestCase
{
    use RefreshDatabase;

    /** @var BulkDonationSeeder */
    private $seeder;

    public function setUp(): void
    {
        parent::setUp();
        Donation::query()->delete();
        DB::query('DELETE FROM ' . DB::prefix('give_donors'));
        DB::query('DELETE FROM ' . DB::prefix('give_donormeta'));
        DB::query('DELETE FROM ' . DB::prefix('give_revenue'));
        $this->seeder = give(BulkDonationSeeder::class);
    }

    /**
     * @since 1.1.0
     */
    public function testSeedsAcrossCampaignsTagsRowsAndKeepsDataListenersOn()
    {
        $campaigns = $this->seeder->ensureCampaigns(3);
        $this->assertCount(3, $campaigns);

        $created = $this->seeder->seed($campaigns, 30, 5, 'test', 'complete');

        $this->assertSame(30, $created);
        $this->assertSame(30, $this->seeder->countDonations());
        $this->assertSame(30, $this->seeder->countGenerated());
        $this->assertLessThanOrEqual(5, $this->seeder->countDonors(), 'stops creating donors at the donor target');
        $this->assertGreaterThan(1, $this->seeder->countDonors());
        $this->assertSame(30, (int)DB::table('give_revenue')->count(), 'revenue rows still written per donation');
        $this->assertSame(30, (int)DB::table('give_donationmeta')->where('meta_key', '_give_payment_mode')->where('meta_value', 'test')->count(), 'test mode by default');

        $perCampaign = array_map(static function (Campaign $campaign) {
            return Donation::query()->where('give_donationmeta_attach_meta_campaignId.meta_value', $campaign->id)->count();
        }, $campaigns);
        $this->assertSame(30, array_sum($perCampaign));
        $this->assertGreaterThan(0, min($perCampaign), 'every campaign received donations');

        // Additive: a second call adds more.
        $this->assertSame(10, $this->seeder->seed($campaigns, 10, 5, 'test', 'complete'));
        $this->assertSame(40, $this->seeder->countDonations());
    }

    /**
     * @since 1.1.0
     */
    public function testRecountsDonorTotalsAndCampaignCachesOnceAtTheEnd()
    {
        $campaigns = $this->seeder->ensureCampaigns(2);
        $this->seeder->seed($campaigns, 20, 4, 'test', 'complete');

        $donor = DB::table('give_donors')->orderBy('purchase_count', 'DESC')->get();
        $expectedCount = Donation::query()->where('give_donationmeta_attach_meta_donorId.meta_value', $donor->id)->count();
        $this->assertSame($expectedCount, (int)$donor->purchase_count);
        $this->assertGreaterThan(0, (float)$donor->purchase_value);
        $this->assertNotEmpty(get_option('give_campaigns_data'), 'campaign cache rebuilt');
    }

    /**
     * @since 1.1.0
     */
    public function testResetRemovesOnlyGeneratedData()
    {
        $campaigns = $this->seeder->ensureCampaigns(1);
        $real = Donation::factory()->create(['campaignId' => $campaigns[0]->id]);
        $realDonorId = $real->donorId;

        $this->seeder->seed($campaigns, 12, 3, 'test', 'complete');
        $this->assertSame(13, $this->seeder->countDonations());

        $result = $this->seeder->reset();

        $this->assertSame(12, $result['donations']);
        $this->assertSame(1, $this->seeder->countDonations());
        $this->assertSame(0, $this->seeder->countGenerated());
        $this->assertNotNull(Donation::find($real->id));
        $this->assertNotNull(DB::table('give_donors')->where('id', $realDonorId)->get(), 'real donor kept');
        $this->assertSame(1, (int)DB::table('give_revenue')->count());
    }
}
