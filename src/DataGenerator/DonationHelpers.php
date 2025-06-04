<?php

namespace GiveDataGenerator\DataGenerator;

use Give\Donations\Models\Donation;
use Give\Framework\Database\DB;
use Give\Donations\ValueObjects\DonationMetaKeys;

class DonationHelpers
{
    /**
     * @since 1.0.0
     *
     * @param Donation $donation
     * @return void
     */
    public static function addDonationAndDonorBackwardsCompatibility(Donation $donation)
    {
        $donor = $donation->donor;

        give()->donors->updateLegacyColumns($donor->id, [
            'purchase_value' => static::getDonorTotalAmountDonated($donor->id),
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
    }

    /**
     * Calculate total amount donated by a donor (intended amount after subtracting fees)
     *
     * @since 1.0.0
     *
     * @param int $donorId The donor ID
     * @return float The total intended amount donated
     */
    private static function getDonorTotalAmountDonated(int $donorId): float
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
