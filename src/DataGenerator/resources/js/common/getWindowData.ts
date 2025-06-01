import { Donor } from '../types';

declare const window: {
    dataGenerator: {
        donors: Donor[];
        ajaxUrl: string;
        nonce: string;
        campaignNonce: string;
        donationFormNonce: string;
        cleanupNonce: string;
    };
} & Window;

export default window.dataGenerator;
