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
            <h1>{__('Data Generator', 'give-data-generator')}</h1>

            <div className="notice notice-info">
                <p>
                    {__(
                        'This tool generates test data for GiveWP including campaigns, donations, donors, subscriptions, donation forms, and more. Use only for testing purposes. ',
                        'give-data-generator'
                    )}
                </p>
            </div>

            <TabPanel className="data-generator-tabs" activeClass="is-active" orientation="vertical" tabs={tabs}>
                {(tab) => tab.content}
            </TabPanel>
        </div>
    );
};

export default DataGeneratorApp;
