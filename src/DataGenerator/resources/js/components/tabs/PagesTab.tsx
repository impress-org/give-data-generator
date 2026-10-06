import React, {useState} from 'react';
import {__} from '@wordpress/i18n';
import {Button, CheckboxControl, SelectControl, Notice, Card, CardBody, CardHeader} from '@wordpress/components';
import {useEntityRecords} from '@wordpress/core-data';
import {ApiResponse, ResultState, Campaign} from '../../types';
import dataGenerator from '../../common/getWindowData';

const PagesTab: React.FC = () => {
    const {hasResolved, records} = useEntityRecords('givewp', 'campaign', {
        status: ['active'],
        per_page: 100,
        orderby: 'date',
        order: 'desc',
    });
    const campaigns = (records ?? []) as Campaign[];

    const [source, setSource] = useState<'campaign' | 'form'>('campaign');
    const [campaignId, setCampaignId] = useState<string>('');
    const [formId, setFormId] = useState<string>('');
    const [selected, setSelected] = useState<string[]>(dataGenerator.pageTitles);
    const [layout, setLayout] = useState<'individual' | 'type' | 'single'>('individual');

    // A standalone form has no campaign, so only list the pages that don't need one.
    const available = source === 'form' ? dataGenerator.formPageTitles : dataGenerator.pageTitles;
    const pages = selected.filter((title) => available.includes(title));
    const sourceId = source === 'form' ? formId : campaignId;
    const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
    const [result, setResult] = useState<ResultState | null>(null);

    const onSubmit = async (event: React.FormEvent): Promise<void> => {
        event.preventDefault();
        setIsSubmitting(true);
        setResult(null);

        try {
            const response = await fetch(dataGenerator.ajaxUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams([
                    ['action', 'generate_test_pages'],
                    ['nonce', dataGenerator.pageNonce],
                    [source === 'form' ? 'form_id' : 'campaign_id', sourceId],
                    ['layout', layout],
                    ...pages.map((title) => ['pages[]', title]),
                ]),
            });

            const data: ApiResponse = await response.json();

            setResult({
                success: data.success,
                message: data.data?.message || 'Operation completed',
            });
        } catch (error) {
            setResult({
                success: false,
                message: error instanceof Error ? error.message : 'An error occurred',
            });
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <Card>
            <CardHeader>
                <h2>{__('Generate Block & Shortcode Pages', 'give-data-generator')}</h2>
            </CardHeader>
            <CardBody>
                <form onSubmit={onSubmit}>
                    <table className="form-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label>{__('Generate For', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <SelectControl
                                        value={source}
                                        onChange={(value: string) => setSource(value as 'campaign' | 'form')}
                                        options={[
                                            {label: __('Campaign', 'give-data-generator'), value: 'campaign'},
                                            {
                                                label: __('Donation form (standalone)', 'give-data-generator'),
                                                value: 'form',
                                            },
                                        ]}
                                    />
                                    <p className="description">
                                        {__(
                                            'A standalone form skips the campaign blocks and shortcodes.',
                                            'give-data-generator'
                                        )}
                                    </p>
                                </td>
                            </tr>
                            {source === 'form' ? (
                                <tr>
                                    <th scope="row">
                                        <label>{__('Donation Form', 'give-data-generator')}</label>
                                    </th>
                                    <td>
                                        <SelectControl
                                            value={formId}
                                            onChange={setFormId}
                                            required
                                            options={[
                                                {label: __('Select a Donation Form', 'give-data-generator'), value: ''},
                                                ...dataGenerator.forms.map((form) => ({
                                                    label: form.title,
                                                    value: String(form.id),
                                                })),
                                            ]}
                                        />
                                        <p className="description">
                                            {__('Form blocks and shortcodes use this form.', 'give-data-generator')}
                                        </p>
                                        {dataGenerator.forms.length === 0 && (
                                            <p className="description" style={{color: '#d63638'}}>
                                                {__(
                                                    'No published donation forms found. Please create a form first.',
                                                    'give-data-generator'
                                                )}
                                            </p>
                                        )}
                                    </td>
                                </tr>
                            ) : (
                                <tr>
                                    <th scope="row">
                                        <label>{__('Campaign', 'give-data-generator')}</label>
                                    </th>
                                    <td>
                                        <SelectControl
                                            value={campaignId}
                                            onChange={setCampaignId}
                                            required
                                            options={[
                                                {
                                                    label: hasResolved
                                                        ? __('Select a Campaign', 'give-data-generator')
                                                        : __('Loading campaigns...', 'give-data-generator'),
                                                    value: '',
                                                },
                                                ...campaigns.map((campaign) => ({
                                                    label: campaign.title,
                                                    value: String(campaign.id),
                                                })),
                                            ]}
                                        />
                                        <p className="description">
                                            {__(
                                                'Campaign blocks use this campaign, and form blocks use its default form.',
                                                'give-data-generator'
                                            )}
                                        </p>
                                        {hasResolved && campaigns.length === 0 && (
                                            <p className="description" style={{color: '#d63638'}}>
                                                {__(
                                                    'No active campaigns found. Please create a campaign first.',
                                                    'give-data-generator'
                                                )}
                                            </p>
                                        )}
                                    </td>
                                </tr>
                            )}
                            <tr>
                                <th scope="row">
                                    <label>{__('Page Layout', 'give-data-generator')}</label>
                                </th>
                                <td>
                                    <SelectControl
                                        value={layout}
                                        onChange={setLayout}
                                        options={[
                                            {
                                                label: __('One page per block or shortcode', 'give-data-generator'),
                                                value: 'individual',
                                            },
                                            {
                                                label: __(
                                                    'One page for blocks, one for shortcodes',
                                                    'give-data-generator'
                                                ),
                                                value: 'type',
                                            },
                                            {
                                                label: __('Everything on one page', 'give-data-generator'),
                                                value: 'single',
                                            },
                                        ]}
                                    />
                                    <p className="description">
                                        {__(
                                            'Grouped pages label each block and shortcode with a heading.',
                                            'give-data-generator'
                                        )}
                                    </p>
                                </td>
                            </tr>
                            {[
                                {label: __('Blocks', 'give-data-generator'), prefix: 'Block: '},
                                {label: __('Shortcodes', 'give-data-generator'), prefix: 'Shortcode: '},
                            ].map(({label, prefix}) => {
                                const titles = available.filter((title) => title.startsWith(prefix));
                                const others = selected.filter((title) => !title.startsWith(prefix));

                                return (
                                    <tr key={prefix}>
                                        <th scope="row">
                                            <label>{label}</label>
                                        </th>
                                        <td>
                                            <p>
                                                <Button
                                                    variant="link"
                                                    onClick={() => setSelected([...others, ...titles])}
                                                >
                                                    {__('Select all', 'give-data-generator')}
                                                </Button>
                                                {' | '}
                                                <Button variant="link" onClick={() => setSelected(others)}>
                                                    {__('Select none', 'give-data-generator')}
                                                </Button>
                                            </p>
                                            <div
                                                style={{
                                                    display: 'grid',
                                                    gridTemplateColumns: 'repeat(auto-fill, minmax(260px, 1fr))',
                                                    gap: '8px',
                                                }}
                                            >
                                                {titles.map((title) => (
                                                    <CheckboxControl
                                                        key={title}
                                                        __nextHasNoMarginBottom
                                                        label={title.slice(prefix.length)}
                                                        checked={selected.includes(title)}
                                                        onChange={(checked: boolean) =>
                                                            setSelected(
                                                                checked
                                                                    ? [...selected, title]
                                                                    : selected.filter((t) => t !== title)
                                                            )
                                                        }
                                                    />
                                                ))}
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>

                    <p className="submit">
                        <Button
                            type="submit"
                            variant="primary"
                            isBusy={isSubmitting}
                            disabled={isSubmitting || !sourceId || pages.length === 0}
                        >
                            {isSubmitting
                                ? __('Generating...', 'give-data-generator')
                                : __('Generate Pages', 'give-data-generator')}
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

export default PagesTab;
