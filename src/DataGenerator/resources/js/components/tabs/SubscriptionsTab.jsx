import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
    Button,
    SelectControl,
    TextControl,
    Notice,
    Card,
    CardBody,
    Flex,
    FlexItem
} from '@wordpress/components';
import {useEntityRecords} from '@wordpress/core-data';

const SubscriptionsTab = () => {
    const [campaigns, setCampaigns] = useState([]);
    const {hasResolved: isCampaignsResolved, records: campaignRecords} = useEntityRecords('givewp', 'campaign', {
        status: ['active'],
        per_page: 100,
        orderby: 'date',
        order: 'desc',
    });

    useEffect(() => {
        if (isCampaignsResolved && campaignRecords !== null) {
            setCampaigns(campaignRecords);
        }
    }, [campaignRecords, isCampaignsResolved]);

    const [formData, setFormData] = useState({
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
    });

    const [isSubmitting, setIsSubmitting] = useState(false);
    const [result, setResult] = useState(null);

    // Prepare campaign options for SelectControl
    const campaignOptions = !isCampaignsResolved ? [
        { label: __('Loading campaigns...', 'give-data-generator'), value: '' },
    ] : [
        { label: __('Select a Campaign', 'give-data-generator'), value: '' },
        ...campaigns.map(campaign => ({
            label: campaign.title,
            value: campaign.id
        }))
    ];

    const handleSubmit = async (e) => {
        e.preventDefault();

        if (!formData.campaign_id) {
            setResult({
                success: false,
                message: __('Please select a campaign', 'give-data-generator')
            });
            return;
        }

        if (formData.date_range === 'custom' && (!formData.start_date || !formData.end_date)) {
            setResult({
                success: false,
                message: __('Please select both start and end dates for custom range', 'give-data-generator')
            });
            return;
        }

        setIsSubmitting(true);
        setResult(null);

        try {
            const params = new URLSearchParams({
                action: 'generate_test_subscriptions',
                nonce: dataGenerator.subscriptionNonce,
                ...formData
            });

            const response = await fetch(dataGenerator.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: params
            });

            const data = await response.json();

            setResult({
                success: data.success,
                message: data.data?.message || 'Operation completed'
            });
        } catch (error) {
            console.error('Form submission error:', error);
            setResult({
                success: false,
                message: error.message || 'An error occurred'
            });
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleFieldChange = (field, value) => {
        setFormData(prev => ({ ...prev, [field]: value }));
    };

    return (
        <Card>
            <CardBody>
                <form onSubmit={handleSubmit}>
                    <table className="form-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label>{__('Campaign', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <SelectControl
                                        value={formData.campaign_id}
                                        onChange={(value) => handleFieldChange('campaign_id', value)}
                                        options={campaignOptions}
                                    />
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
                                    <TextControl
                                        type="number"
                                        value={formData.subscription_count}
                                        onChange={(value) => handleFieldChange('subscription_count', parseInt(value) || 1)}
                                        min={1}
                                        max={1000}
                                    />
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
                                    <SelectControl
                                        value={formData.date_range}
                                        onChange={(value) => handleFieldChange('date_range', value)}
                                        options={[
                                            { label: __('Last 30 Days', 'give-data-generator'), value: 'last_30_days' },
                                            { label: __('Last 90 Days', 'give-data-generator'), value: 'last_90_days' },
                                            { label: __('Last Year', 'give-data-generator'), value: 'last_year' },
                                            { label: __('Custom Range', 'give-data-generator'), value: 'custom' }
                                        ]}
                                    />
                                    <p className="description">
                                        {__('Timeframe within which subscriptions should be created.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            {formData.date_range === 'custom' && (
                                <tr>
                                    <th scope="row">
                                        <label>{__('Custom Date Range', 'give-data-generator')}</label>
                                    </th>
                                    <td>
                                        <Flex gap={2} align="center">
                                            <FlexItem>
                                                <TextControl
                                                    type="date"
                                                    value={formData.start_date}
                                                    onChange={(value) => handleFieldChange('start_date', value)}
                                                />
                                            </FlexItem>
                                            <FlexItem>
                                                {__('to', 'give-data-generator')}
                                            </FlexItem>
                                            <FlexItem>
                                                <TextControl
                                                    type="date"
                                                    value={formData.end_date}
                                                    onChange={(value) => handleFieldChange('end_date', value)}
                                                />
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
                                    <SelectControl
                                        value={formData.subscription_mode}
                                        onChange={(value) => handleFieldChange('subscription_mode', value)}
                                        options={[
                                            { label: __('Test Mode', 'give-data-generator'), value: 'test' },
                                            { label: __('Live Mode', 'give-data-generator'), value: 'live' }
                                        ]}
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
                                    <SelectControl
                                        value={formData.subscription_status}
                                        onChange={(value) => handleFieldChange('subscription_status', value)}
                                        options={[
                                            { label: __('Pending', 'give-data-generator'), value: 'pending' },
                                            { label: __('Active', 'give-data-generator'), value: 'active' },
                                            { label: __('Expired', 'give-data-generator'), value: 'expired' },
                                            { label: __('Completed', 'give-data-generator'), value: 'completed' },
                                            { label: __('Refunded', 'give-data-generator'), value: 'refunded' },
                                            { label: __('Failing', 'give-data-generator'), value: 'failing' },
                                            { label: __('Cancelled', 'give-data-generator'), value: 'cancelled' },
                                            { label: __('Abandoned', 'give-data-generator'), value: 'abandoned' },
                                            { label: __('Suspended', 'give-data-generator'), value: 'suspended' },
                                            { label: __('Paused', 'give-data-generator'), value: 'paused' },
                                            { label: __('Random', 'give-data-generator'), value: 'random' }
                                        ]}
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
                                    <SelectControl
                                        value={formData.subscription_period}
                                        onChange={(value) => handleFieldChange('subscription_period', value)}
                                        options={[
                                            { label: __('Daily', 'give-data-generator'), value: 'day' },
                                            { label: __('Weekly', 'give-data-generator'), value: 'week' },
                                            { label: __('Monthly', 'give-data-generator'), value: 'month' },
                                            { label: __('Quarterly', 'give-data-generator'), value: 'quarter' },
                                            { label: __('Yearly', 'give-data-generator'), value: 'year' }
                                        ]}
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
                                    <TextControl
                                        type="number"
                                        value={formData.frequency}
                                        onChange={(value) => handleFieldChange('frequency', parseInt(value) || 1)}
                                        min={1}
                                        max={12}
                                    />
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
                                    <TextControl
                                        type="number"
                                        value={formData.installments}
                                        onChange={(value) => handleFieldChange('installments', parseInt(value) || 0)}
                                        min={0}
                                        max={100}
                                    />
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
                                    <TextControl
                                        type="number"
                                        value={formData.renewals_count}
                                        onChange={(value) => handleFieldChange('renewals_count', parseInt(value) || 0)}
                                        min={0}
                                        max={50}
                                    />
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
                            disabled={!formData.campaign_id || isSubmitting || campaigns.length === 0}
                        >
                            {isSubmitting
                                ? __('Generating...', 'give-data-generator')
                                : campaigns.length === 0
                                    ? __('No Campaigns Available', 'give-data-generator')
                                    : __('Generate Test Subscriptions', 'give-data-generator')
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
