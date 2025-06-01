import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
    Button,
    SelectControl,
    TextControl,
    CheckboxControl,
    Notice,
    Card,
    CardBody,
    Flex,
    FlexItem
} from '@wordpress/components';

const DonationsTab = () => {
    const [formData, setFormData] = useState({
        campaign_id: '',
        donor_creation_method: 'create_new',
        selected_donor_id: '',
        donation_count: 10,
        date_range: 'last_30_days',
        start_date: '',
        end_date: '',
        donation_mode: 'test',
        donation_status: 'complete'
    });

    const [isSubmitting, setIsSubmitting] = useState(false);
    const [result, setResult] = useState(null);

    // Get campaigns and donors from the global dataGenerator object
    const campaigns = dataGenerator?.campaigns || [];
    const donors = dataGenerator?.donors || [];

    // Prepare campaign options for SelectControl
    const campaignOptions = [
        { label: __('Select a Campaign', 'give-data-generator'), value: '' },
        ...campaigns.map(campaign => ({
            label: campaign.title,
            value: campaign.id
        }))
    ];

    // Prepare donor options for SelectControl
    const donorOptions = [
        { label: __('Choose a donor...', 'give-data-generator'), value: '' },
        ...donors.map(donor => ({
            label: `${donor.name} (${donor.email})`,
            value: donor.id
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

        if (formData.donor_creation_method === 'select_specific' && !formData.selected_donor_id) {
            setResult({
                success: false,
                message: __('Please select a specific donor', 'give-data-generator')
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
                action: 'generate_test_donations',
                nonce: dataGenerator.nonce,
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
                                        {__('Choose which campaign the test donations should be associated with.', 'give-data-generator')}
                                    </p>
                                    {campaigns.length === 0 && (
                                        <p className="description" style={{ color: '#d63638' }}>
                                            {__('No active campaigns found. Please create a campaign first.', 'give-data-generator')}
                                        </p>
                                    )}
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Donor Selection', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <SelectControl
                                        value={formData.donor_creation_method}
                                        onChange={(value) => handleFieldChange('donor_creation_method', value)}
                                        options={[
                                            { label: __('Create New Donors', 'give-data-generator'), value: 'create_new' },
                                            { label: __('Use Existing Donors', 'give-data-generator'), value: 'use_existing' },
                                            { label: __('Mix of New and Existing', 'give-data-generator'), value: 'mixed' },
                                            { label: __('Select Specific Donor', 'give-data-generator'), value: 'select_specific' }
                                        ]}
                                    />
                                    <p className="description">
                                        {__('Choose whether to create new donors for each donation or use existing donors from your database.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            {formData.donor_creation_method === 'select_specific' && (
                                <tr>
                                    <th scope="row">
                                        <label>{__('Select Donor', 'give-data-generator')}</label>
                                    </th>
                                    <td>
                                        <SelectControl
                                            value={formData.selected_donor_id}
                                            onChange={(value) => handleFieldChange('selected_donor_id', value)}
                                            options={donorOptions}
                                        />
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
                                    <label>{__('Number of Donations', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <TextControl
                                        type="number"
                                        value={formData.donation_count}
                                        onChange={(value) => handleFieldChange('donation_count', parseInt(value) || 1)}
                                        min={1}
                                        max={1000}
                                    />
                                    <p className="description">
                                        {__('How many test donations to generate (1-1000).', 'give-data-generator')}
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
                                        {__('Timeframe within which donations should be created.', 'give-data-generator')}
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
                                    <SelectControl
                                        value={formData.donation_mode}
                                        onChange={(value) => handleFieldChange('donation_mode', value)}
                                        options={[
                                            { label: __('Test Mode', 'give-data-generator'), value: 'test' },
                                            { label: __('Live Mode', 'give-data-generator'), value: 'live' }
                                        ]}
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
                                    <SelectControl
                                        value={formData.donation_status}
                                        onChange={(value) => handleFieldChange('donation_status', value)}
                                        options={[
                                            { label: __('Complete', 'give-data-generator'), value: 'complete' },
                                            { label: __('Pending', 'give-data-generator'), value: 'pending' },
                                            { label: __('Processing', 'give-data-generator'), value: 'processing' },
                                            { label: __('Refunded', 'give-data-generator'), value: 'refunded' },
                                            { label: __('Failed', 'give-data-generator'), value: 'failed' },
                                            { label: __('Cancelled', 'give-data-generator'), value: 'cancelled' },
                                            { label: __('Abandoned', 'give-data-generator'), value: 'abandoned' },
                                            { label: __('Preapproval', 'give-data-generator'), value: 'preapproval' },
                                            { label: __('Revoked', 'give-data-generator'), value: 'revoked' },
                                            { label: __('Random', 'give-data-generator'), value: 'random' }
                                        ]}
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
                            disabled={!formData.campaign_id || isSubmitting || campaigns.length === 0}
                        >
                            {isSubmitting
                                ? __('Generating...', 'give-data-generator')
                                : campaigns.length === 0
                                    ? __('No Campaigns Available', 'give-data-generator')
                                    : __('Generate Test Data', 'give-data-generator')
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
