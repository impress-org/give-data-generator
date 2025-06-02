import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import {
    Button,
    SelectControl,
    TextControl,
    Notice,
    Card,
    CardBody,
    CardHeader,
    Flex,
    FlexItem
} from '@wordpress/components';
import { useEntityRecords } from '@wordpress/core-data';
import { useForm, Controller, SubmitHandler } from 'react-hook-form';
import { ApiResponse, ResultState, Campaign } from '../../types';
import dataGenerator from '../../common/getWindowData';

interface SubscriptionFormData {
    campaign_id: string;
    subscription_count: number;
    date_range: 'custom' | 'last_30_days' | 'last_90_days' | 'last_year';
    start_date: string;
    end_date: string;
    subscription_mode: 'live' | 'test';
    subscription_status: 'active' | 'completed' | 'paused' | 'suspended' | 'pending' | 'expired' | 'refunded' | 'cancelled' | 'abandoned' | 'random' | 'failing';
    subscription_period: 'day' | 'month' | 'year' | 'quarter' | 'week';
    frequency: number;
    installments: number;
    renewals_count: number;
}

interface SelectOption {
    label: string;
    value: string;
}

const SubscriptionsTab: React.FC = () => {
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

    // Initialize react-hook-form
    const {
        control,
        handleSubmit,
        watch,
        formState: { errors },
        setError,
        clearErrors
    } = useForm<SubscriptionFormData>({
        defaultValues: {
            campaign_id: '',
            subscription_count: 10,
            date_range: 'last_30_days',
            start_date: '',
            end_date: '',
            subscription_mode: 'test',
            subscription_status: 'active',
            subscription_period: 'month',
            frequency: 1,
            installments: 0,
            renewals_count: 0
        },
        mode: 'onSubmit'
    });

    // Watch specific fields for conditional rendering
    const watchedDateRange = watch('date_range');

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

    const onSubmit: SubmitHandler<SubscriptionFormData> = async (formData): Promise<void> => {
        // Clear any previous errors
        clearErrors();

        // Custom validation
        if (!formData.campaign_id) {
            setError('campaign_id', {
                type: 'required',
                message: __('Please select a campaign', 'give-data-generator')
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
            const params = new URLSearchParams({
                action: 'generate_test_subscriptions',
                nonce: dataGenerator.subscriptionNonce,
                ...Object.fromEntries(Object.entries(formData).map(([key, value]) => [key, String(value)]))
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

    return (
        <Card>
            <CardHeader>
                <h2>{__('Generate Test Subscriptions', 'give-data-generator')}</h2>
            </CardHeader>
            <CardBody>
                <form onSubmit={handleSubmit(onSubmit)}>
                    <table className="form-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label>{__('Campaign', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <Controller
                                        name="campaign_id"
                                        control={control}
                                        rules={{ required: __('Please select a campaign', 'give-data-generator') }}
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
                                        {__('Choose which campaign the test subscriptions should be associated with.', 'give-data-generator')}
                                    </p>
                                    {isCampaignsResolved && campaigns.length === 0 && (
                                        <p className="description" style={{ color: '#d63638' }}>
                                            {__('No active campaigns found. Please create a campaign first.', 'give-data-generator')}
                                        </p>
                                    )}
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Number of Subscriptions', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <Controller
                                        name="subscription_count"
                                        control={control}
                                        rules={{
                                            required: __('Subscription count is required', 'give-data-generator'),
                                            min: { value: 1, message: __('Minimum 1 subscription required', 'give-data-generator') },
                                            max: { value: 1000, message: __('Maximum 1000 subscriptions allowed', 'give-data-generator') }
                                        }}
                                        render={({ field }) => (
                                            <TextControl
                                                type="number"
                                                value={String(field.value)}
                                                onChange={(value: string) => field.onChange(parseInt(value) || 1)}
                                                min={1}
                                                max={1000}
                                            />
                                        )}
                                    />
                                    {errors.subscription_count && (
                                        <p className="description" style={{ color: '#d63638' }}>
                                            {errors.subscription_count.message}
                                        </p>
                                    )}
                                    <p className="description">
                                        {__('How many test subscriptions to generate (1-1000).', 'give-data-generator')}
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
                                                    { label: __('Custom Range', 'give-data-generator'), value: 'custom' }
                                                ]}
                                            />
                                        )}
                                    />
                                    <p className="description">
                                        {__('Timeframe within which subscriptions should be created.', 'give-data-generator')}
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
                                            {__('Select the start and end dates for the subscription generation.', 'give-data-generator')}
                                        </p>
                                    </td>
                                </tr>
                            )}

                            <tr>
                                <th scope="row">
                                    <label>{__('Subscription Mode', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <Controller
                                        name="subscription_mode"
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
                                        {__('Choose whether subscriptions should be created in test or live mode.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Subscription Status', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <Controller
                                        name="subscription_status"
                                        control={control}
                                        render={({ field }) => (
                                            <SelectControl
                                                value={field.value}
                                                onChange={field.onChange}
                                                options={[
                                                    { label: __('Active', 'give-data-generator'), value: 'active' },
                                                    { label: __('Completed', 'give-data-generator'), value: 'completed' },
                                                    { label: __('Paused', 'give-data-generator'), value: 'paused' },
                                                    { label: __('Suspended', 'give-data-generator'), value: 'suspended' },
                                                    { label: __('Pending', 'give-data-generator'), value: 'pending' },
                                                    { label: __('Expired', 'give-data-generator'), value: 'expired' },
                                                    { label: __('Refunded', 'give-data-generator'), value: 'refunded' },
                                                    { label: __('Cancelled', 'give-data-generator'), value: 'cancelled' },
                                                    { label: __('Abandoned', 'give-data-generator'), value: 'abandoned' },
                                                    { label: __('Failing', 'give-data-generator'), value: 'failing' },
                                                    { label: __('Random Status', 'give-data-generator'), value: 'random' }
                                                ]}
                                            />
                                        )}
                                    />
                                    <p className="description">
                                        {__('Status for the generated subscriptions. Select "Random" to use a mix of statuses.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Billing Period', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <Controller
                                        name="subscription_period"
                                        control={control}
                                        render={({ field }) => (
                                            <SelectControl
                                                value={field.value}
                                                onChange={field.onChange}
                                                options={[
                                                    { label: __('Daily', 'give-data-generator'), value: 'day' },
                                                    { label: __('Weekly', 'give-data-generator'), value: 'week' },
                                                    { label: __('Monthly', 'give-data-generator'), value: 'month' },
                                                    { label: __('Quarterly', 'give-data-generator'), value: 'quarter' },
                                                    { label: __('Yearly', 'give-data-generator'), value: 'year' }
                                                ]}
                                            />
                                        )}
                                    />
                                    <p className="description">
                                        {__('The billing period for subscriptions.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Frequency', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <Controller
                                        name="frequency"
                                        control={control}
                                        rules={{
                                            required: __('Frequency is required', 'give-data-generator'),
                                            min: { value: 1, message: __('Frequency must be at least 1', 'give-data-generator') },
                                            max: { value: 12, message: __('Frequency cannot exceed 12', 'give-data-generator') }
                                        }}
                                        render={({ field }) => (
                                            <TextControl
                                                type="number"
                                                value={String(field.value)}
                                                onChange={(value: string) => field.onChange(parseInt(value) || 1)}
                                                min={1}
                                                max={12}
                                            />
                                        )}
                                    />
                                    {errors.frequency && (
                                        <p className="description" style={{ color: '#d63638' }}>
                                            {errors.frequency.message}
                                        </p>
                                    )}
                                    <p className="description">
                                        {__('How often the subscription should bill within the period (e.g., every 2 months = frequency 2 with monthly period).', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Installments', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <Controller
                                        name="installments"
                                        control={control}
                                        rules={{
                                            min: { value: 0, message: __('Installments cannot be negative', 'give-data-generator') },
                                            max: { value: 100, message: __('Installments cannot exceed 100', 'give-data-generator') }
                                        }}
                                        render={({ field }) => (
                                            <TextControl
                                                type="number"
                                                value={String(field.value)}
                                                onChange={(value: string) => field.onChange(parseInt(value) || 0)}
                                                min={0}
                                                max={100}
                                            />
                                        )}
                                    />
                                    {errors.installments && (
                                        <p className="description" style={{ color: '#d63638' }}>
                                            {errors.installments.message}
                                        </p>
                                    )}
                                    <p className="description">
                                        {__('Total number of payments before subscription ends. Set to 0 for unlimited/indefinite subscriptions.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Renewals per Subscription', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <Controller
                                        name="renewals_count"
                                        control={control}
                                        rules={{
                                            min: { value: 0, message: __('Renewals count cannot be negative', 'give-data-generator') },
                                            max: { value: 50, message: __('Renewals count cannot exceed 50', 'give-data-generator') }
                                        }}
                                        render={({ field }) => (
                                            <TextControl
                                                type="number"
                                                value={String(field.value)}
                                                onChange={(value: string) => field.onChange(parseInt(value) || 0)}
                                                min={0}
                                                max={50}
                                            />
                                        )}
                                    />
                                    {errors.renewals_count && (
                                        <p className="description" style={{ color: '#d63638' }}>
                                            {errors.renewals_count.message}
                                        </p>
                                    )}
                                    <p className="description">
                                        {__('Number of renewal payments to generate for each subscription (0-50). This creates historical renewal data.', 'give-data-generator')}
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
                            disabled={isSubmitting || (isCampaignsResolved && campaigns.length === 0)}
                        >
                            {isSubmitting
                                ? __('Generating...', 'give-data-generator')
                                : campaigns.length === 0
                                    ? __('No Campaigns Available', 'give-data-generator')
                                    : __('Generate Subscriptions', 'give-data-generator')
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

export default SubscriptionsTab;
