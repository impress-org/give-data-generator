import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TabPanel } from '@wordpress/components';
import DonationFormsTab from './tabs/DonationFormsTab';
import DonationsTab from './tabs/DonationsTab';
import CampaignsTab from './tabs/CampaignsTab';
import SubscriptionsTab from './tabs/SubscriptionsTab';
import CleanupTab from './tabs/CleanupTab';

const DataGeneratorApp = () => {
    const tabs = [
        {
            name: 'campaigns',
            title: __('Campaigns', 'give-data-generator'),
            content: <CampaignsTab />
        },
        {
            name: 'donations',
            title: __('Donations', 'give-data-generator'),
            content: <DonationsTab />
        },
        {
            name: 'donation-forms',
            title: __('Donation Forms', 'give-data-generator'),
            content: <DonationFormsTab />
        },
        {
            name: 'subscriptions',
            title: __('Subscriptions', 'give-data-generator'),
            content: <SubscriptionsTab />
        },
        {
            name: 'cleanup',
            title: __('Cleanup', 'give-data-generator'),
            content: <CleanupTab />
        }
    ];

    return (
        <div className="wrap">
            <h1>{__('Data Generator (React)', 'give-data-generator')}</h1>

            <div className="notice notice-info">
                <p>{__('This is the React version of the Data Generator. You can switch back to the vanilla version by removing "?react=1" from the URL.', 'give-data-generator')}</p>
            </div>

            <TabPanel
                className="data-generator-tabs"
                activeClass="is-active"
                orientation="vertical"
                tabs={tabs}
            >
                {(tab) => tab.content}
            </TabPanel>
        </div>
    );
};

export default DataGeneratorApp;
