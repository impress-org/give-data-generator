import React, { useState, useEffect } from 'react';
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
import { useEntityRecords } from '@wordpress/core-data';
import { ApiResponse, ResultState, Campaign } from '../../types';
import dataGenerator from '../../common/getWindowData';

interface DonationFormFormData {
    campaign_id: string;
    form_count: number;
    form_status: 'private' | 'published' | 'draft';
    enable_goals: boolean;
    goal_type: 'amount' | 'campaign' | 'donations' | 'donors';
    goal_amount_min: number;
    goal_amount_max: number;
    random_designs: boolean;
    inherit_campaign_colors: boolean;
    form_title_prefix: string;
}

interface SelectOption {
    label: string;
    value: string;
}

const DonationFormsTab: React.FC = () => {
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

    const [formData, setFormData] = useState<DonationFormFormData>({
        campaign_id: '',
        form_count: 3,
        form_status: 'published',
        enable_goals: false,
        goal_type: 'campaign',
        goal_amount_min: 500,
        goal_amount_max: 5000,
        random_designs: true,
        inherit_campaign_colors: true,
        form_title_prefix: ''
    });

    const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
    const [result, setResult] = useState<ResultState | null>(null);

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

    const handleSubmit = async (e: React.FormEvent<HTMLFormElement>): Promise<void> => {
        e.preventDefault();

        if (!formData.campaign_id) {
            setResult({
                success: false,
                message: __('Please select a campaign', 'give-data-generator')
            });
            return;
        }

        setIsSubmitting(true);
        setResult(null);

        try {
            const params = new URLSearchParams({
                action: 'generate_test_donation_forms',
                nonce: dataGenerator.donationFormNonce,
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

    const handleFieldChange = (field: keyof DonationFormFormData, value: DonationFormFormData[keyof DonationFormFormData]): void => {
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
                                        onChange={(value: string) => handleFieldChange('campaign_id', value)}
                                        options={campaignOptions}
                                    />
                                    <p className="description">
                                        {__('Choose which campaign the donation forms should be associated with.', 'give-data-generator')}
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
                                    <label>{__('Number of Forms', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <TextControl
                                        type="number"
                                        value={formData.form_count}
                                        onChange={(value: string) => handleFieldChange('form_count', parseInt(value) || 1)}
                                        min={1}
                                        max={20}
                                    />
                                    <p className="description">
                                        {__('How many donation forms to generate for the selected campaign (1-20).', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Form Status', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <SelectControl
                                        value={formData.form_status}
                                        onChange={(value: string) => handleFieldChange('form_status', value)}
                                        options={[
                                            { label: __('Published', 'give-data-generator'), value: 'published' },
                                            { label: __('Draft', 'give-data-generator'), value: 'draft' },
                                            { label: __('Private', 'give-data-generator'), value: 'private' }
                                        ]}
                                    />
                                    <p className="description">
                                        {__('Status for the generated donation forms.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Enable Goals', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <CheckboxControl
                                        checked={formData.enable_goals}
                                        onChange={(value: boolean) => handleFieldChange('enable_goals', value)}
                                        label={__('Enable donation goals for generated forms', 'give-data-generator')}
                                    />
                                    <p className="description">
                                        {__('Whether to enable donation goals on the generated forms.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            {formData.enable_goals && (
                                <>
                                    <tr>
                                        <th scope="row">
                                            <label>{__('Goal Type', 'give-data-generator')}</label>
                                        </th>
                                        <td>
                                            <SelectControl
                                                value={formData.goal_type}
                                                onChange={(value: string) => handleFieldChange('goal_type', value)}
                                                options={[
                                                    { label: __('Inherit from Campaign', 'give-data-generator'), value: 'campaign' },
                                                    { label: __('Amount', 'give-data-generator'), value: 'amount' },
                                                    { label: __('Number of Donations', 'give-data-generator'), value: 'donations' },
                                                    { label: __('Number of Donors', 'give-data-generator'), value: 'donors' }
                                                ]}
                                            />
                                            <p className="description">
                                                {__('Type of goal for the forms. Campaign option inherits goal settings from the selected campaign.', 'give-data-generator')}
                                            </p>
                                        </td>
                                    </tr>

                                    {formData.goal_type !== 'campaign' && (
                                        <tr>
                                            <th scope="row">
                                                <label>{__('Goal Amount Range', 'give-data-generator')}</label>
                                            </th>
                                            <td>
                                                <Flex gap={2} align="center">
                                                    <FlexItem>
                                                        <TextControl
                                                            type="number"
                                                            value={formData.goal_amount_min}
                                                            onChange={(value: string) => handleFieldChange('goal_amount_min', parseInt(value) || 0)}
                                                            min={100}
                                                        />
                                                    </FlexItem>
                                                    <FlexItem>
                                                        {__('to', 'give-data-generator')}
                                                    </FlexItem>
                                                    <FlexItem>
                                                        <TextControl
                                                            type="number"
                                                            value={formData.goal_amount_max}
                                                            onChange={(value: string) => handleFieldChange('goal_amount_max', parseInt(value) || 0)}
                                                            min={100}
                                                        />
                                                    </FlexItem>
                                                </Flex>
                                                <p className="description">
                                                    {__('Random goal amounts will be generated within this range (only for custom goals).', 'give-data-generator')}
                                                </p>
                                            </td>
                                        </tr>
                                    )}
                                </>
                            )}

                            <tr>
                                <th scope="row">
                                    <label>{__('Form Options', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <CheckboxControl
                                        checked={formData.random_designs}
                                        onChange={(value: boolean) => handleFieldChange('random_designs', value)}
                                        label={__('Use random form designs', 'give-data-generator')}
                                    />
                                    <p className="description" style={{ marginTop: '5px', marginBottom: '10px' }}>
                                        {__('Randomly assign form designs (Multi-step, Classic, Two Panel) to generated forms.', 'give-data-generator')}
                                    </p>

                                    <CheckboxControl
                                        checked={formData.inherit_campaign_colors}
                                        onChange={(value: boolean) => handleFieldChange('inherit_campaign_colors', value)}
                                        label={__('Inherit colors from campaign', 'give-data-generator')}
                                    />
                                    <p className="description" style={{ marginTop: '5px' }}>
                                        {__('Use the primary and secondary colors from the selected campaign for the forms.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Title Prefix', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <TextControl
                                        value={formData.form_title_prefix}
                                        onChange={(value: string) => handleFieldChange('form_title_prefix', value)}
                                        placeholder={__('e.g., Test Form', 'give-data-generator')}
                                    />
                                    <p className="description">
                                        {__('Optional prefix for generated form titles. Leave blank to use default naming pattern.', 'give-data-generator')}
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
                                    : __('Generate Donation Forms', 'give-data-generator')
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

export default DonationFormsTab;
