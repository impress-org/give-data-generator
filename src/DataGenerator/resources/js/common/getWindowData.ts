import { Donor } from '../types';

declare const window: {
    dataGenerator: {
        donors: Donor[];
        ajaxUrl: string;
        nonce: string;
        campaignNonce: string;
        donationFormNonce: string;
        subscriptionNonce: string;
        cleanupNonce: string;
        pageNonce: string;
        pageTitles: string[];
        formPageTitles: string[];
        forms: { id: number; title: string }[];
    };
} & Window;

export default window.dataGenerator;
