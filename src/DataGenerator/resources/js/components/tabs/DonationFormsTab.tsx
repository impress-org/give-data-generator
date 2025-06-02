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
    CardHeader,
    Flex,
    FlexItem
} from '@wordpress/components';
import { useEntityRecords } from '@wordpress/core-data';
import { useForm, Controller, SubmitHandler } from 'react-hook-form';
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
    } = useForm<DonationFormFormData>({
        defaultValues: {
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
        },
        mode: 'onSubmit'
    });

    // Watch specific fields for conditional rendering
    const watchedEnableGoals = watch('enable_goals');

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

    const onSubmit: SubmitHandler<DonationFormFormData> = async (formData): Promise<void> => {
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

    return (
        <Card>
            <CardHeader>
                <h2>{__('Generate Test Donation Forms', 'give-data-generator')}</h2>
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
                                    <Controller
                                        name="form_count"
                                        control={control}
                                        rules={{
                                            required: __('Form count is required', 'give-data-generator'),
                                            min: { value: 1, message: __('Minimum 1 form required', 'give-data-generator') },
                                            max: { value: 20, message: __('Maximum 20 forms allowed', 'give-data-generator') }
                                        }}
                                        render={({ field }) => (
                                            <TextControl
                                                type="number"
                                                value={String(field.value)}
                                                onChange={(value: string) => field.onChange(parseInt(value) || 1)}
                                                min={1}
                                                max={20}
                                            />
                                        )}
                                    />
                                    {errors.form_count && (
                                        <p className="description" style={{ color: '#d63638' }}>
                                            {errors.form_count.message}
                                        </p>
                                    )}
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
                                    <Controller
                                        name="form_status"
                                        control={control}
                                        render={({ field }) => (
                                            <SelectControl
                                                value={field.value}
                                                onChange={field.onChange}
                                                options={[
                                                    { label: __('Published', 'give-data-generator'), value: 'published' },
                                                    { label: __('Draft', 'give-data-generator'), value: 'draft' },
                                                    { label: __('Private', 'give-data-generator'), value: 'private' }
                                                ]}
                                            />
                                        )}
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
                                    <Controller
                                        name="enable_goals"
                                        control={control}
                                        render={({ field }) => (
                                            <CheckboxControl
                                                checked={field.value}
                                                onChange={field.onChange}
                                                label={__('Enable donation goals for generated forms', 'give-data-generator')}
                                            />
                                        )}
                                    />
                                    <p className="description">
                                        {__('When enabled, each donation form will have a randomly generated goal within the specified range.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            {watchedEnableGoals && (
                                <>
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
                                                            { label: __('Campaign Amount', 'give-data-generator'), value: 'campaign' },
                                                            { label: __('Number of Donations', 'give-data-generator'), value: 'donations' },
                                                            { label: __('Number of Donors', 'give-data-generator'), value: 'donors' }
                                                        ]}
                                                    />
                                                )}
                                            />
                                            <p className="description">
                                                {__('Type of goal for the donation forms.', 'give-data-generator')}
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
                                                            required: watchedEnableGoals ? __('Minimum goal amount is required', 'give-data-generator') : false,
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
                                                            required: watchedEnableGoals ? __('Maximum goal amount is required', 'give-data-generator') : false,
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
                                </>
                            )}

                            <tr>
                                <th scope="row">
                                    <label>{__('Design Options', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <Controller
                                        name="random_designs"
                                        control={control}
                                        render={({ field }) => (
                                            <CheckboxControl
                                                checked={field.value}
                                                onChange={field.onChange}
                                                label={__('Use random form designs', 'give-data-generator')}
                                            />
                                        )}
                                    />
                                    <Controller
                                        name="inherit_campaign_colors"
                                        control={control}
                                        render={({ field }) => (
                                            <CheckboxControl
                                                checked={field.value}
                                                onChange={field.onChange}
                                                label={__('Inherit colors from campaign', 'give-data-generator')}
                                            />
                                        )}
                                    />
                                    <p className="description">
                                        {__('Customize the visual appearance of generated donation forms.', 'give-data-generator')}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label>{__('Form Title Prefix', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <Controller
                                        name="form_title_prefix"
                                        control={control}
                                        render={({ field }) => (
                                            <TextControl
                                                value={field.value}
                                                onChange={field.onChange}
                                                placeholder={__('Enter prefix for form titles (optional)', 'give-data-generator')}
                                            />
                                        )}
                                    />
                                    <p className="description">
                                        {__('Optional prefix to add to generated form titles. Leave blank for default naming.', 'give-data-generator')}
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
                                : (isCampaignsResolved && campaigns.length === 0)
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
