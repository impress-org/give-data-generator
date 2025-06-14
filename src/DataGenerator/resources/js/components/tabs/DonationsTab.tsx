import React, { useState, useEffect } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import {
    Button,
    SelectControl,
    TextControl,
    CheckboxControl,
    Notice,
    Card,
    CardBody,
    Flex,
    FlexItem,
    CardHeader,
    RadioControl
} from '@wordpress/components';
import { useEntityRecords } from '@wordpress/core-data';
import { useForm, Controller, SubmitHandler } from 'react-hook-form';
import { ApiResponse, ResultState, Campaign, Donor } from '../../types';
import dataGenerator from '../../common/getWindowData';

interface DonationFormData {
    generation_type: 'specific_campaign' | 'all_campaigns';
    campaign_id: string;
    donor_creation_method: 'mixed' | 'create_new' | 'select_specific' | 'use_existing';
    selected_donor_id: string;
    donation_count: number;
    date_range: 'custom' | 'last_30_days' | 'last_90_days' | 'last_year';
    start_date: string;
    end_date: string;
    donation_mode: 'live' | 'test';
    donation_status: 'failed' | 'complete' | 'pending' | 'processing' | 'refunded' | 'cancelled' | 'abandoned' | 'preapproval' | 'revoked' | 'random';
}

interface SelectOption {
    label: string;
    value: string;
}

const DonationsTab: React.FC = () => {
    const [campaigns, setCampaigns] = useState<Campaign[]>([]);
    const { hasResolved: isCampaignsResolved, records: campaignRecords } = useEntityRecords('givewp', 'campaign', {
        status: ['active'],
        per_page: 100,
        orderby: 'date',
        order: 'desc',
    });

    useEffect(() => {
        if (isCampaignsResolved && campaignRecords !== null) {
            setCampaigns(campaignRecords as Campaign[]);
        }
    }, [campaignRecords, isCampaignsResolved]);

    const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
    const [result, setResult] = useState<ResultState | null>(null);

    // Get campaigns and donors from the global dataGenerator object
    const donors: Donor[] = dataGenerator?.donors || [];

    // Initialize react-hook-form
    const {
        control,
        handleSubmit,
        watch,
        formState: { errors },
        setError,
        clearErrors
    } = useForm<DonationFormData>({
        defaultValues: {
            generation_type: 'specific_campaign',
            campaign_id: '',
            donor_creation_method: 'create_new',
            selected_donor_id: '',
            donation_count: 10,
            date_range: 'last_30_days',
            start_date: '',
            end_date: '',
            donation_mode: 'test',
            donation_status: 'complete'
        },
        mode: 'onSubmit'
    });

    // Watch specific fields for conditional rendering
    const watchedGenerationType = watch('generation_type');
    const watchedDonorMethod = watch('donor_creation_method');
    const watchedDateRange = watch('date_range');
    const watchedDonationCount = watch('donation_count');

    // Prepare campaign options for SelectControl
    const campaignOptions: SelectOption[] = !isCampaignsResolved ? [
        { label: __('Loading campaigns...', 'give-data-generator'), value: '' },
    ] : [
        { label: __('Select a Campaign', 'give-data-generator'), value: '' },
        ...campaigns.map((campaign: Campaign) => ({
            label: campaign.title,
            value: campaign.id
        }))
    ];

    // Prepare donor options for SelectControl
    const donorOptions: SelectOption[] = [
        { label: __('Choose a donor...', 'give-data-generator'), value: '' },
        ...donors.map((donor: Donor) => ({
            label: `${donor.name} (${donor.email})`,
            value: donor.id
        }))
    ];

    const onSubmit: SubmitHandler<DonationFormData> = async (formData): Promise<void> => {
        // Clear any previous errors
        clearErrors();

        // Custom validation
        if (formData.generation_type === 'specific_campaign' && !formData.campaign_id) {
            setError('campaign_id', {
                type: 'required',
                message: __('Please select a campaign', 'give-data-generator')
            });
            return;
        }

        if (formData.donor_creation_method === 'select_specific' && !formData.selected_donor_id) {
            setError('selected_donor_id', {
                type: 'required',
                message: __('Please select a specific donor', 'give-data-generator')
            });
            return;
        }

        if (formData.date_range === 'custom' && (!formData.start_date || !formData.end_date)) {
            if (!formData.start_date) {
                setError('start_date', {
                    type: 'required',
                    message: __('Please select a start date', 'give-data-generator')
                });
            }
            if (!formData.end_date) {
                setError('end_date', {
                    type: 'required',
                    message: __('Please select an end date', 'give-data-generator')
                });
            }
            return;
        }

        setIsSubmitting(true);
        setResult(null);

        try {
            // Determine which AJAX action to use based on generation type
            const action = formData.generation_type === 'all_campaigns'
                ? 'generate_bulk_test_donations'
                : 'generate_test_donations';

            // Prepare parameters - for bulk, we use donations_per_campaign instead of donation_count
            const baseParams = {
                action,
                nonce: dataGenerator.nonce,
                donor_creation_method: formData.donor_creation_method,
                selected_donor_id: formData.selected_donor_id,
                date_range: formData.date_range,
                start_date: formData.start_date,
                end_date: formData.end_date,
                donation_mode: formData.donation_mode,
                donation_status: formData.donation_status
            };

            const params = new URLSearchParams({
                ...baseParams,
                ...(formData.generation_type === 'all_campaigns'
                    ? { donations_per_campaign: String(formData.donation_count) }
                    : {
                        campaign_id: formData.campaign_id,
                        donation_count: String(formData.donation_count)
                    }
                )
            });

            const response = await fetch(dataGenerator.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: params
            });

            const data: ApiResponse = await response.json();

            setResult({
                success: data.success,
                message: data.data?.message || 'Operation completed'
            });
        } catch (error) {
            console.error('Form submission error:', error);
            setResult({
                success: false,
                message: error instanceof Error ? error.message : 'An error occurred'
            });
        } finally {
            setIsSubmitting(false);
        }
    };

    // Calculate total donations for display
    const getTotalDonationsDisplay = () => {
        if (watchedGenerationType === 'all_campaigns' && isCampaignsResolved && campaigns.length > 0) {
            const total = campaigns.length * (watchedDonationCount || 0);
            return sprintf(
                __('This will generate %d total donations across %d active campaigns', 'give-data-generator'),
                total,
                campaigns.length
            );
        }
        return '';
    };

    return (
        <Card>
            <CardHeader>
                <h2>{__('Generate Test Donations', 'give-data-generator')}</h2>
            </CardHeader>
            <CardBody>
                <form onSubmit={handleSubmit(onSubmit)}>
                    <table className="form-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label>{__('Generation Type', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <Controller
                                        name="generation_type"
                                        control={control}
                                        render={({ field }) => (
                                            <RadioControl
                                                selected={field.value}
                                                options={[
                                                    {
                                                        label: __('Generate for specific campaign', 'give-data-generator'),
                                                        value: 'specific_campaign'
                                                    },
                                                    {
                                                        label: __('Generate for all active campaigns', 'give-data-generator'),
                                                        value: 'all_campaigns'
                                                    }
                                                ]}
                                                onChange={field.onChange}
                                            />
                                        )}
                                    />
                                    <p className="description">
                                        {watchedGenerationType === 'specific_campaign'
                                            ? __('Generate donations for a single campaign of your choice.', 'give-data-generator')
                                            : __('Generate donations for every active campaign automatically.', 'give-data-generator')
                                        }
                                        {watchedGenerationType === 'all_campaigns' && isCampaignsResolved && (
                                            <>
                                                {' '}
                                                {sprintf(
                                                    __('Currently there are %d active campaigns.', 'give-data-generator'),
                                                    campaigns.length
                                                )}
                                            </>
                                        )}
                                    </p>
                                </td>
                            </tr>

                            {watchedGenerationType === 'specific_campaign' && (
                                <tr>
                                    <th scope="row">
                                        <label>{__('Campaign', 'give-data-generator')}</label>
                                    </th>
                                    <td>
                                        <Controller
                                            name="campaign_id"
                                            control={control}
                                            rules={{
                                                required: watchedGenerationType === 'specific_campaign'
                                                    ? __('Please select a campaign', 'give-data-generator')
                                                    : false
                                            }}
                                            render={({ field }) => (
                                                <SelectControl
                                                    value={field.value}
                                                    onChange={field.onChange}
                                                    options={campaignOptions}
                                                />
                                            )}
                                        />
                                        {errors.campaign_id && (
                                            <p className="description" style={{ color: '#d63638' }}>
                                                {errors.campaign_id.message}
                                            </p>
                                        )}
                                        <p className="description">
                                            {__('Choose which campaign the test donations should be associated with.', 'give-data-generator')}
                                        </p>
                                        {isCampaignsResolved && campaigns.length === 0 && (
                                            <p className="description" style={{ color: '#d63638' }}>
                                                {__('No active campaigns found. Please create a campaign first.', 'give-data-generator')}
                                            </p>
                                        )}
                                    </td>
                                </tr>
                            )}

                            <tr>
                                <th scope="row">
                                    <label>{__('Donor Selection', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <Controller
                                        name="donor_creation_method"
                                        control={control}
                                        render={({ field }) => (
                                            <SelectControl
                                                value={field.value}
                                                onChange={field.onChange}
                                                options={[
                                                    { label: __('Create New Donors', 'give-data-generator'), value: 'create_new' },
                                                    { label: __('Use Existing Donors', 'give-data-generator'), value: 'use_existing' },
                                                    { label: __('Mix of New and Existing', 'give-data-generator'), value: 'mixed' },
                                                    { label: __('Select Specific Donor', 'give-data-generator'), value: 'select_specific' }
                                                ]}
                                            />
                                        )}
                                    />
                                    <p className="description">
                                        {__('Choose whether to create new donors for each donation or use existing donors from your database.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            {watchedDonorMethod === 'select_specific' && (
                                <tr>
                                    <th scope="row">
                                        <label>{__('Select Donor', 'give-data-generator')}</label>
                                    </th>
                                    <td>
                                        <Controller
                                            name="selected_donor_id"
                                            control={control}
                                            rules={{
                                                required: watchedDonorMethod === 'select_specific'
                                                    ? __('Please select a specific donor', 'give-data-generator')
                                                    : false
                                            }}
                                            render={({ field }) => (
                                                <SelectControl
                                                    value={field.value}
                                                    onChange={field.onChange}
                                                    options={donorOptions}
                                                />
                                            )}
                                        />
                                        {errors.selected_donor_id && (
                                            <p className="description" style={{ color: '#d63638' }}>
                                                {errors.selected_donor_id.message}
                                            </p>
                                        )}
                                        <p className="description">
                                            {__('Select a specific donor to use for all generated donations.', 'give-data-generator')}
                                        </p>
                                        {donors.length === 0 && (
                                            <p className="description" style={{ color: '#d63638' }}>
                                                {__('No existing donors found. Please select a different donor creation method.', 'give-data-generator')}
                                            </p>
                                        )}
                                    </td>
                                </tr>
                            )}

                            <tr>
                                <th scope="row">
                                    <label>
                                        {watchedGenerationType === 'all_campaigns'
                                            ? __('Donations per Campaign', 'give-data-generator')
                                            : __('Number of Donations', 'give-data-generator')
                                        }
                                    </label>
                                </th>
                                <td>
                                    <Controller
                                        name="donation_count"
                                        control={control}
                                        rules={{
                                            required: watchedGenerationType === 'all_campaigns'
                                                ? __('Please enter the number of donations per campaign', 'give-data-generator')
                                                : __('Donation count is required', 'give-data-generator'),
                                            min: { value: 1, message: __('Minimum 1 donation required', 'give-data-generator') },
                                            max: {
                                                value: watchedGenerationType === 'all_campaigns' ? 100 : 1000,
                                                message: watchedGenerationType === 'all_campaigns'
                                                    ? __('Maximum 100 donations per campaign', 'give-data-generator')
                                                    : __('Maximum 1000 donations allowed', 'give-data-generator')
                                            }
                                        }}
                                        render={({ field }) => (
                                            <TextControl
                                                type="number"
                                                value={String(field.value)}
                                                onChange={(value: string) => field.onChange(parseInt(value) || 1)}
                                                min={1}
                                                max={watchedGenerationType === 'all_campaigns' ? 100 : 1000}
                                            />
                                        )}
                                    />
                                    {errors.donation_count && (
                                        <p className="description" style={{ color: '#d63638' }}>
                                            {errors.donation_count.message}
                                        </p>
                                    )}
                                    <p className="description">
                                        {watchedGenerationType === 'all_campaigns'
                                            ? __('How many donations to generate for each active campaign (1-100).', 'give-data-generator')
                                            : __('How many test donations to generate (1-1000).', 'give-data-generator')
                                        }
                                        {getTotalDonationsDisplay() && (
                                            <>
                                                <br />
                                                <strong>{getTotalDonationsDisplay()}</strong>
                                            </>
                                        )}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Date Range', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <Controller
                                        name="date_range"
                                        control={control}
                                        render={({ field }) => (
                                            <SelectControl
                                                value={field.value}
                                                onChange={field.onChange}
                                                options={[
                                                    { label: __('Last 30 Days', 'give-data-generator'), value: 'last_30_days' },
                                                    { label: __('Last 90 Days', 'give-data-generator'), value: 'last_90_days' },
                                                    { label: __('Last Year', 'give-data-generator'), value: 'last_year' },
                                                    { label: __('Custom Date Range', 'give-data-generator'), value: 'custom' }
                                                ]}
                                            />
                                        )}
                                    />
                                    <p className="description">
                                        {__('Timeframe within which donations should be created.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            {watchedDateRange === 'custom' && (
                                <tr>
                                    <th scope="row">
                                        <label>{__('Custom Date Range', 'give-data-generator')}</label>
                                    </th>
                                    <td>
                                        <Flex gap={2} align="center">
                                            <FlexItem>
                                                <Controller
                                                    name="start_date"
                                                    control={control}
                                                    rules={{
                                                        required: watchedDateRange === 'custom'
                                                            ? __('Please select a start date', 'give-data-generator')
                                                            : false
                                                    }}
                                                    render={({ field }) => (
                                                        <TextControl
                                                            type="date"
                                                            value={field.value}
                                                            onChange={field.onChange}
                                                        />
                                                    )}
                                                />
                                                {errors.start_date && (
                                                    <p className="description" style={{ color: '#d63638', fontSize: '12px' }}>
                                                        {errors.start_date.message}
                                                    </p>
                                                )}
                                            </FlexItem>
                                            <FlexItem>
                                                {__('to', 'give-data-generator')}
                                            </FlexItem>
                                            <FlexItem>
                                                <Controller
                                                    name="end_date"
                                                    control={control}
                                                    rules={{
                                                        required: watchedDateRange === 'custom'
                                                            ? __('Please select an end date', 'give-data-generator')
                                                            : false
                                                    }}
                                                    render={({ field }) => (
                                                        <TextControl
                                                            type="date"
                                                            value={field.value}
                                                            onChange={field.onChange}
                                                        />
                                                    )}
                                                />
                                                {errors.end_date && (
                                                    <p className="description" style={{ color: '#d63638', fontSize: '12px' }}>
                                                        {errors.end_date.message}
                                                    </p>
                                                )}
                                            </FlexItem>
                                        </Flex>
                                        <p className="description">
                                            {__('Select the start and end dates for the donation generation.', 'give-data-generator')}
                                        </p>
                                    </td>
                                </tr>
                            )}

                            <tr>
                                <th scope="row">
                                    <label>{__('Donation Mode', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <Controller
                                        name="donation_mode"
                                        control={control}
                                        render={({ field }) => (
                                            <SelectControl
                                                value={field.value}
                                                onChange={field.onChange}
                                                options={[
                                                    { label: __('Test Mode', 'give-data-generator'), value: 'test' },
                                                    { label: __('Live Mode', 'give-data-generator'), value: 'live' }
                                                ]}
                                            />
                                        )}
                                    />
                                    <p className="description">
                                        {__('Choose whether donations should be created in test or live mode.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Donation Status', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <Controller
                                        name="donation_status"
                                        control={control}
                                        render={({ field }) => (
                                            <SelectControl
                                                value={field.value}
                                                onChange={field.onChange}
                                                options={[
                                                    { label: __('Complete', 'give-data-generator'), value: 'complete' },
                                                    { label: __('Pending', 'give-data-generator'), value: 'pending' },
                                                    { label: __('Processing', 'give-data-generator'), value: 'processing' },
                                                    { label: __('Failed', 'give-data-generator'), value: 'failed' },
                                                    { label: __('Cancelled', 'give-data-generator'), value: 'cancelled' },
                                                    { label: __('Refunded', 'give-data-generator'), value: 'refunded' },
                                                    { label: __('Abandoned', 'give-data-generator'), value: 'abandoned' },
                                                    { label: __('Preapproval', 'give-data-generator'), value: 'preapproval' },
                                                    { label: __('Revoked', 'give-data-generator'), value: 'revoked' },
                                                    { label: __('Random Status', 'give-data-generator'), value: 'random' }
                                                ]}
                                            />
                                        )}
                                    />
                                    <p className="description">
                                        {__('Status for the generated donations. Select "Random" to use a mix of statuses.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p className="submit">
                        <Button
                            type="submit"
                            variant="primary"
                            isBusy={isSubmitting}
                            disabled={isSubmitting || (watchedGenerationType === 'all_campaigns' && isCampaignsResolved && campaigns.length === 0) || (watchedGenerationType === 'specific_campaign' && isCampaignsResolved && campaigns.length === 0)}
                        >
                            {isSubmitting
                                ? __('Generating...', 'give-data-generator')
                                : (isCampaignsResolved && campaigns.length === 0)
                                    ? __('No Active Campaigns Available', 'give-data-generator')
                                    : watchedGenerationType === 'all_campaigns'
                                        ? __('Generate Donations for All Campaigns', 'give-data-generator')
                                        : __('Generate Donations', 'give-data-generator')
                            }
                        </Button>
                    </p>

                    {result && (
                        <Notice
                            status={result.success ? 'success' : 'error'}
                            isDismissible={true}
                            onRemove={() => setResult(null)}
                        >
                            {result.message}
                        </Notice>
                    )}
                </form>
            </CardBody>
        </Card>
    );
};

export default DonationsTab;
