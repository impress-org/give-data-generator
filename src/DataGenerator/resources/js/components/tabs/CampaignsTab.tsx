import React, { useState } from 'react';
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
import { useDispatch } from '@wordpress/data';
import { ApiResponse, ResultState } from '../../types';
import dataGenerator from '../../common/getWindowData';

interface CampaignFormData {
    campaign_count: number;
    campaign_status: 'active' | 'inactive' | 'pending' | 'draft' | 'archived';
    goal_type: 'amount' | 'donations' | 'donors' | 'amountFromSubscriptions' | 'subscriptions' | 'donorsFromSubscriptions';
    goal_amount_min: number;
    goal_amount_max: number;
    color_scheme: 'random' | 'blue_theme' | 'green_theme' | 'red_theme' | 'purple_theme' | 'orange_theme';
    include_short_desc: boolean;
    include_long_desc: boolean;
    campaign_duration: 'ongoing' | '30_days' | '60_days' | '90_days' | '6_months' | '1_year';
    create_forms: boolean;
    campaign_title_prefix: string;
    image_source: 'none' | 'random' | 'lorem_picsum' | 'openverse';
}

const CampaignsTab: React.FC = () => {
    const { invalidateResolution, invalidateResolutionForStore } = useDispatch('core');

    const [formData, setFormData] = useState<CampaignFormData>({
        campaign_count: 5,
        campaign_status: 'active',
        goal_type: 'amount',
        goal_amount_min: 1000,
        goal_amount_max: 10000,
        color_scheme: 'random',
        include_short_desc: true,
        include_long_desc: true,
        campaign_duration: 'ongoing',
        create_forms: true,
        campaign_title_prefix: 'Test Campaign',
        image_source: 'lorem_picsum'
    });

    const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
    const [result, setResult] = useState<ResultState | null>(null);

    const handleSubmit = async (e: React.FormEvent<HTMLFormElement>): Promise<void> => {
        e.preventDefault();

        setIsSubmitting(true);
        setResult(null);

        try {
            const params = new URLSearchParams({
                action: 'generate_test_campaigns',
                nonce: dataGenerator.campaignNonce,
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

            if (data.success) {
                // Invalidate campaign cache so other tabs will refetch updated data
                invalidateResolution('getEntityRecords', ['givewp', 'campaign']);
                invalidateResolution('getEntityRecords', [
                    'givewp',
                    'campaign',
                    {
                        status: ['active'],
                        per_page: 100,
                        orderby: 'date',
                        order: 'desc',
                    }
                ]);
                invalidateResolutionForStore();
            }
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

    const handleFieldChange = (field: keyof CampaignFormData, value: CampaignFormData[keyof CampaignFormData]): void => {
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
                                    <label>{__('Number of Campaigns', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <TextControl
                                        type="number"
                                        value={formData.campaign_count}
                                        onChange={(value: string) => handleFieldChange('campaign_count', parseInt(value) || 1)}
                                        min={1}
                                        max={50}
                                    />
                                    <p className="description">
                                        {__('How many test campaigns to generate (1-50).', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Campaign Status', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <SelectControl
                                        value={formData.campaign_status}
                                        onChange={(value: string) => handleFieldChange('campaign_status', value)}
                                        options={[
                                            { label: __('Active', 'give-data-generator'), value: 'active' },
                                            { label: __('Draft', 'give-data-generator'), value: 'draft' },
                                            { label: __('Inactive', 'give-data-generator'), value: 'inactive' },
                                            { label: __('Pending', 'give-data-generator'), value: 'pending' },
                                            { label: __('Archived', 'give-data-generator'), value: 'archived' }
                                        ]}
                                    />
                                    <p className="description">
                                        {__('Status for the generated campaigns.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Goal Type', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <SelectControl
                                        value={formData.goal_type}
                                        onChange={(value: string) => handleFieldChange('goal_type', value)}
                                        options={[
                                            { label: __('Amount', 'give-data-generator'), value: 'amount' },
                                            { label: __('Number of Donations', 'give-data-generator'), value: 'donations' },
                                            { label: __('Number of Donors', 'give-data-generator'), value: 'donors' },
                                            { label: __('Amount from Subscriptions', 'give-data-generator'), value: 'amountFromSubscriptions' },
                                            { label: __('Number of Subscriptions', 'give-data-generator'), value: 'subscriptions' },
                                            { label: __('Donors from Subscriptions', 'give-data-generator'), value: 'donorsFromSubscriptions' }
                                        ]}
                                    />
                                    <p className="description">
                                        {__('Type of goal for the campaigns.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Goal Amount Range', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <Flex gap={2} align="center" justify="flex-start">
                                        <FlexItem>
                                            <TextControl
                                                type="number"
                                                value={formData.goal_amount_min}
                                                onChange={(value: string) => handleFieldChange('goal_amount_min', parseInt(value) || 100)}
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
                                                onChange={(value: string) => handleFieldChange('goal_amount_max', parseInt(value) || 100)}
                                                min={100}
                                            />
                                        </FlexItem>
                                    </Flex>
                                    <p className="description">
                                        {__('Random goal amounts will be generated within this range.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Primary Color Range', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <SelectControl
                                        value={formData.color_scheme}
                                        onChange={(value: string) => handleFieldChange('color_scheme', value)}
                                        options={[
                                            { label: __('Random Colors', 'give-data-generator'), value: 'random' },
                                            { label: __('Blue Theme', 'give-data-generator'), value: 'blue_theme' },
                                            { label: __('Green Theme', 'give-data-generator'), value: 'green_theme' },
                                            { label: __('Red Theme', 'give-data-generator'), value: 'red_theme' },
                                            { label: __('Purple Theme', 'give-data-generator'), value: 'purple_theme' },
                                            { label: __('Orange Theme', 'give-data-generator'), value: 'orange_theme' }
                                        ]}
                                    />
                                    <p className="description">
                                        {__('Color scheme for generated campaigns.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Include Descriptions', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <CheckboxControl
                                        checked={formData.include_short_desc}
                                        onChange={(value: boolean) => handleFieldChange('include_short_desc', value)}
                                        label={__('Generate short descriptions', 'give-data-generator')}
                                    />
                                    <p className="description" style={{ marginTop: '5px', marginBottom: '10px' }}>
                                        {__('Include brief campaign summaries and taglines.', 'give-data-generator')}
                                    </p>

                                    <CheckboxControl
                                        checked={formData.include_long_desc}
                                        onChange={(value: boolean) => handleFieldChange('include_long_desc', value)}
                                        label={__('Generate long descriptions', 'give-data-generator')}
                                    />
                                    <p className="description" style={{ marginTop: '5px' }}>
                                        {__('Include detailed campaign stories and call-to-action content.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Campaign Duration', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <SelectControl
                                        value={formData.campaign_duration}
                                        onChange={(value: string) => handleFieldChange('campaign_duration', value)}
                                        options={[
                                            { label: __('Ongoing (No End Date)', 'give-data-generator'), value: 'ongoing' },
                                            { label: __('30 Days', 'give-data-generator'), value: '30_days' },
                                            { label: __('60 Days', 'give-data-generator'), value: '60_days' },
                                            { label: __('90 Days', 'give-data-generator'), value: '90_days' },
                                            { label: __('6 Months', 'give-data-generator'), value: '6_months' },
                                            { label: __('1 Year', 'give-data-generator'), value: '1_year' }
                                        ]}
                                    />
                                    <p className="description">
                                        {__('Duration for the generated campaigns.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Create Associated Forms', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <CheckboxControl
                                        checked={formData.create_forms}
                                        onChange={(value: boolean) => handleFieldChange('create_forms', value)}
                                        label={__('Create default donation forms for each campaign', 'give-data-generator')}
                                    />
                                    <p className="description">
                                        {__('Automatically create and associate donation forms with each campaign.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Title Prefix', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <TextControl
                                        value={formData.campaign_title_prefix}
                                        onChange={(value: string) => handleFieldChange('campaign_title_prefix', value)}
                                        placeholder={__('e.g., Test Campaign', 'give-data-generator')}
                                    />
                                    <p className="description">
                                        {__('Prefix for generated campaign titles (e.g., "Test Campaign - Save the Whales").', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Campaign Images', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <SelectControl
                                        value={formData.image_source}
                                        onChange={(value: string) => handleFieldChange('image_source', value)}
                                        options={[
                                            { label: __('Lorem Picsum (Random)', 'give-data-generator'), value: 'lorem_picsum' },
                                            { label: __('Openverse (Open Licensed)', 'give-data-generator'), value: 'openverse' },
                                            { label: __('Random (All Sources)', 'give-data-generator'), value: 'random' },
                                            { label: __('No Images', 'give-data-generator'), value: 'none' }
                                        ]}
                                    />
                                    <p className="description">
                                        {__('Choose where to fetch campaign images from. Images will be downloaded and uploaded to your WordPress media library.', 'give-data-generator')}
                                        <br />
                                        <strong>{__('Openverse:', 'give-data-generator')}</strong> {__('Provides properly licensed images with attribution from sources like Flickr, Wikimedia Commons, etc.', 'give-data-generator')}
                                        <br />
                                        <strong>{__('Lorem Picsum:', 'give-data-generator')}</strong> {__('Beautiful random placeholder images for testing purposes.', 'give-data-generator')}
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
                            disabled={isSubmitting}
                        >
                            {isSubmitting
                                ? __('Generating...', 'give-data-generator')
                                : __('Generate Test Campaigns', 'give-data-generator')
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

export default CampaignsTab;
