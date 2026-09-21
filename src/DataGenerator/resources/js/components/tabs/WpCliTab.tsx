import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import {
    Button,
    Card,
    CardBody,
    CardHeader,
    CheckboxControl,
    SelectControl,
    TextControl,
} from '@wordpress/components';

const DEFAULTS = { campaigns: '10', mode: 'test', status: 'random' };

type Option = { label: string; value: string };

const MODES: Option[] = [
    { label: __('Test', 'give-data-generator'), value: 'test' },
    { label: __('Live', 'give-data-generator'), value: 'live' },
];

const STATUSES: Option[] = [
    { label: __('Random', 'give-data-generator'), value: 'random' },
    { label: __('Complete', 'give-data-generator'), value: 'complete' },
    { label: __('Pending', 'give-data-generator'), value: 'pending' },
    { label: __('Processing', 'give-data-generator'), value: 'processing' },
    { label: __('Failed', 'give-data-generator'), value: 'failed' },
    { label: __('Cancelled', 'give-data-generator'), value: 'cancelled' },
    { label: __('Refunded', 'give-data-generator'), value: 'refunded' },
    { label: __('Abandoned', 'give-data-generator'), value: 'abandoned' },
    { label: __('Preapproval', 'give-data-generator'), value: 'preapproval' },
    { label: __('Revoked', 'give-data-generator'), value: 'revoked' },
];

const CommandBlock: React.FC<{ command: string }> = ({ command }) => {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        await navigator.clipboard.writeText(command);
        setCopied(true);
        setTimeout(() => setCopied(false), 1500);
    };

    return (
        <div className="givewp-cli-command">
            <code>{command}</code>
            <Button variant="secondary" size="small" onClick={copy}>
                {copied ? __('Copied', 'give-data-generator') : __('Copy', 'give-data-generator')}
            </Button>
        </div>
    );
};

const WpCliTab: React.FC = () => {
    const [count, setCount] = useState('100000');
    const [campaigns, setCampaigns] = useState(DEFAULTS.campaigns);
    const [donors, setDonors] = useState('');
    const [mode, setMode] = useState(DEFAULTS.mode);
    const [status, setStatus] = useState(DEFAULTS.status);
    const [yes, setYes] = useState(true);

    // Only flags that differ from the command's defaults are printed.
    const donationsCommand = [
        'wp give-data donations',
        count || '<count>',
        campaigns !== DEFAULTS.campaigns && campaigns ? `--campaigns=${campaigns}` : '',
        donors ? `--donors=${donors}` : '',
        mode !== DEFAULTS.mode ? `--mode=${mode}` : '',
        status !== DEFAULTS.status ? `--status=${status}` : '',
    ].filter(Boolean).join(' ');

    const resetCommand = `wp give-data reset${yes ? ' --yes' : ''}`;

    const minutes = Math.round((parseInt(count, 10) || 0) / 100000 * 2.5);

    return (
        <>
            <Card>
                <CardHeader>
                    <h2>{__('Generate donations at scale', 'give-data-generator')}</h2>
                </CardHeader>
                <CardBody>
                    <p className="description">
                        {__('The admin screen caps each request at 1,000 donations. For load testing, use WP-CLI from the site root. Every donation goes through the Donation model, so rows look like production writes. About 2.5 minutes per 100,000. The count is additive: run it twice, get twice as many.', 'give-data-generator')}
                    </p>

                    <div className="givewp-cli-args">
                        <TextControl
                            label={__('Donations to add', 'give-data-generator')}
                            type="number"
                            min={1}
                            value={count}
                            onChange={setCount}
                            help={minutes > 0 ? `≈ ${minutes} min` : undefined}
                        />
                        <TextControl
                            label={__('Campaigns', 'give-data-generator')}
                            type="number"
                            min={1}
                            value={campaigns}
                            onChange={setCampaigns}
                            help={__('Spread across this many campaigns. Missing ones are created with a default form.', 'give-data-generator')}
                        />
                        <TextControl
                            label={__('Donors', 'give-data-generator')}
                            type="number"
                            min={1}
                            value={donors}
                            onChange={setDonors}
                            placeholder={count ? String(Math.max(1, Math.floor((parseInt(count, 10) || 0) / 10))) : ''}
                            help={__('New donors are created until the site holds this many, then existing donors are reused. Default: count / 10.', 'give-data-generator')}
                        />
                        <SelectControl
                            label={__('Mode', 'give-data-generator')}
                            value={mode}
                            onChange={setMode}
                            options={MODES}
                        />
                        <SelectControl
                            label={__('Status', 'give-data-generator')}
                            value={status}
                            onChange={setStatus}
                            help={__('Random is a realistic mix, 70% complete.', 'give-data-generator')}
                            options={STATUSES}
                        />
                    </div>

                    <CommandBlock command={donationsCommand} />

                    <p className="description">
                        {__('While generating, email and the per-donation campaign cache job are suspended. Everything that writes data still runs. Donor totals and campaign caches are rebuilt once at the end.', 'give-data-generator')}
                    </p>
                </CardBody>
            </Card>

            <Card>
                <CardHeader>
                    <h2>{__('Remove generated data', 'give-data-generator')}</h2>
                </CardHeader>
                <CardBody>
                    <p className="description">
                        {__('Deletes every donation and donor the command generated. Rows are tagged with _give_data_generator meta, so real data is untouched. Donations created from this admin screen are not tagged; use the Cleanup tab for those.', 'give-data-generator')}
                    </p>
                    <CheckboxControl
                        label={__('Skip the confirmation prompt (--yes)', 'give-data-generator')}
                        checked={yes}
                        onChange={setYes}
                    />
                    <CommandBlock command={resetCommand} />
                </CardBody>
            </Card>
        </>
    );
};

export default WpCliTab;
