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
    CardHeader,
    Flex,
    FlexItem
} from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useForm, Controller, SubmitHandler } from 'react-hook-form';
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

    const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
    const [result, setResult] = useState<ResultState | null>(null);

    // Initialize react-hook-form
    const {
        control,
        handleSubmit,
        formState: { errors },
        clearErrors
    } = useForm<CampaignFormData>({
        defaultValues: {
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
        },
        mode: 'onSubmit'
    });

    const onSubmit: SubmitHandler<CampaignFormData> = async (formData): Promise<void> => {
        // Clear any previous errors
        clearErrors();

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

    return (
        <Card>
            <CardHeader>
                <h2>{__('Generate Test Campaigns', 'give-data-generator')}</h2>
            </CardHeader>
            <CardBody>
                <form onSubmit={handleSubmit(onSubmit)}>
                    <table className="form-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label>{__('Number of Campaigns', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <Controller
                                        name="campaign_count"
                                        control={control}
                                        rules={{
                                            required: __('Campaign count is required', 'give-data-generator'),
                                            min: { value: 1, message: __('Minimum 1 campaign required', 'give-data-generator') },
                                            max: { value: 50, message: __('Maximum 50 campaigns allowed', 'give-data-generator') }
                                        }}
                                        render={({ field }) => (
                                            <TextControl
                                                type="number"
                                                value={String(field.value)}
                                                onChange={(value: string) => field.onChange(parseInt(value) || 1)}
                                                min={1}
                                                max={50}
                                            />
                                        )}
                                    />
                                    {errors.campaign_count && (
                                        <p className="description" style={{ color: '#d63638' }}>
                                            {errors.campaign_count.message}
                                        </p>
                                    )}
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
                                    <Controller
                                        name="campaign_status"
                                        control={control}
                                        render={({ field }) => (
                                            <SelectControl
                                                value={field.value}
                                                onChange={field.onChange}
                                                options={[
                                                    { label: __('Active', 'give-data-generator'), value: 'active' },
                                                    { label: __('Draft', 'give-data-generator'), value: 'draft' },
                                                    { label: __('Inactive', 'give-data-generator'), value: 'inactive' },
                                                    { label: __('Pending', 'give-data-generator'), value: 'pending' },
                                                    { label: __('Archived', 'give-data-generator'), value: 'archived' }
                                                ]}
                                            />
                                        )}
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
                                    <Controller
                                        name="goal_type"
                                        control={control}
                                        render={({ field }) => (
                                            <SelectControl
                                                value={field.value}
                                                onChange={field.onChange}
                                                options={[
                                                    { label: __('Amount', 'give-data-generator'), value: 'amount' },
                                                    { label: __('Number of Donations', 'give-data-generator'), value: 'donations' },
                                                    { label: __('Number of Donors', 'give-data-generator'), value: 'donors' },
                                                    { label: __('Amount from Subscriptions', 'give-data-generator'), value: 'amountFromSubscriptions' },
                                                    { label: __('Number of Subscriptions', 'give-data-generator'), value: 'subscriptions' },
                                                    { label: __('Donors from Subscriptions', 'give-data-generator'), value: 'donorsFromSubscriptions' }
                                                ]}
                                            />
                                        )}
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
                                            <Controller
                                                name="goal_amount_min"
                                                control={control}
                                                rules={{
                                                    required: __('Minimum goal amount is required', 'give-data-generator'),
                                                    min: { value: 100, message: __('Minimum goal amount must be at least $100', 'give-data-generator') }
                                                }}
                                                render={({ field }) => (
                                                    <TextControl
                                                        type="number"
                                                        value={String(field.value)}
                                                        onChange={(value: string) => field.onChange(parseInt(value) || 100)}
                                                        min={100}
                                                    />
                                                )}
                                            />
                                        </FlexItem>
                                        <FlexItem>
                                            {__('to', 'give-data-generator')}
                                        </FlexItem>
                                        <FlexItem>
                                            <Controller
                                                name="goal_amount_max"
                                                control={control}
                                                rules={{
                                                    required: __('Maximum goal amount is required', 'give-data-generator'),
                                                    min: { value: 100, message: __('Maximum goal amount must be at least $100', 'give-data-generator') }
                                                }}
                                                render={({ field }) => (
                                                    <TextControl
                                                        type="number"
                                                        value={String(field.value)}
                                                        onChange={(value: string) => field.onChange(parseInt(value) || 100)}
                                                        min={100}
                                                    />
                                                )}
                                            />
                                        </FlexItem>
                                    </Flex>
                                    {(errors.goal_amount_min || errors.goal_amount_max) && (
                                        <p className="description" style={{ color: '#d63638' }}>
                                            {errors.goal_amount_min?.message || errors.goal_amount_max?.message}
                                        </p>
                                    )}
                                    <p className="description">
                                        {__('Random goal amounts will be generated within this range (minimum $100).', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Color Scheme', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <Controller
                                        name="color_scheme"
                                        control={control}
                                        render={({ field }) => (
                                            <SelectControl
                                                value={field.value}
                                                onChange={field.onChange}
                                                options={[
                                                    { label: __('Random Colors', 'give-data-generator'), value: 'random' },
                                                    { label: __('Blue Theme', 'give-data-generator'), value: 'blue_theme' },
                                                    { label: __('Green Theme', 'give-data-generator'), value: 'green_theme' },
                                                    { label: __('Red Theme', 'give-data-generator'), value: 'red_theme' },
                                                    { label: __('Purple Theme', 'give-data-generator'), value: 'purple_theme' },
                                                    { label: __('Orange Theme', 'give-data-generator'), value: 'orange_theme' }
                                                ]}
                                            />
                                        )}
                                    />
                                    <p className="description">
                                        {__('Color scheme for campaign branding. Choose a specific theme or random for variety.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Content Options', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <Controller
                                        name="include_short_desc"
                                        control={control}
                                        render={({ field }) => (
                                            <CheckboxControl
                                                checked={field.value}
                                                onChange={field.onChange}
                                                label={__('Include short descriptions', 'give-data-generator')}
                                            />
                                        )}
                                    />
                                    <Controller
                                        name="include_long_desc"
                                        control={control}
                                        render={({ field }) => (
                                            <CheckboxControl
                                                checked={field.value}
                                                onChange={field.onChange}
                                                label={__('Include long descriptions', 'give-data-generator')}
                                            />
                                        )}
                                    />
                                    <p className="description">
                                        {__('Generate campaign content to make them more realistic and visually appealing.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Campaign Duration', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <Controller
                                        name="campaign_duration"
                                        control={control}
                                        render={({ field }) => (
                                            <SelectControl
                                                value={field.value}
                                                onChange={field.onChange}
                                                options={[
                                                    { label: __('Ongoing (No End Date)', 'give-data-generator'), value: 'ongoing' },
                                                    { label: __('30 Days', 'give-data-generator'), value: '30_days' },
                                                    { label: __('60 Days', 'give-data-generator'), value: '60_days' },
                                                    { label: __('90 Days', 'give-data-generator'), value: '90_days' },
                                                    { label: __('6 Months', 'give-data-generator'), value: '6_months' },
                                                    { label: __('1 Year', 'give-data-generator'), value: '1_year' }
                                                ]}
                                            />
                                        )}
                                    />
                                    <p className="description">
                                        {__('How long campaigns should run. Ongoing campaigns have no end date.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Additional Options', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <Controller
                                        name="create_forms"
                                        control={control}
                                        render={({ field }) => (
                                            <CheckboxControl
                                                checked={field.value}
                                                onChange={field.onChange}
                                                label={__('Create donation forms for each campaign', 'give-data-generator')}
                                            />
                                        )}
                                    />
                                    <p className="description">
                                        {__('Automatically generate a donation form for each campaign to make them immediately usable.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Campaign Title Prefix', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <Controller
                                        name="campaign_title_prefix"
                                        control={control}
                                        render={({ field }) => (
                                            <TextControl
                                                value={field.value}
                                                onChange={field.onChange}
                                                placeholder={__('Enter prefix for campaign titles', 'give-data-generator')}
                                            />
                                        )}
                                    />
                                    <p className="description">
                                        {__('Prefix to add to generated campaign titles for easy identification.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Featured Images', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <Controller
                                        name="image_source"
                                        control={control}
                                        render={({ field }) => (
                                            <SelectControl
                                                value={field.value}
                                                onChange={field.onChange}
                                                options={[
                                                    { label: __('No Images', 'give-data-generator'), value: 'none' },
                                                    { label: __('Random Images', 'give-data-generator'), value: 'random' },
                                                    { label: __('Lorem Picsum', 'give-data-generator'), value: 'lorem_picsum' },
                                                    { label: __('Openverse', 'give-data-generator'), value: 'openverse' }
                                                ]}
                                            />
                                        )}
                                    />
                                    <p className="description">
                                        {__('Source for campaign featured images. External sources require internet connection.', 'give-data-generator')}
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
                                : __('Generate Campaigns', 'give-data-generator')
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
