declare const window: {
    dataGenerator: {
        ajaxUrl: string;
        nonce: string;
        campaignNonce: string;
        donationFormNonce: string;
        cleanupNonce: string;
    };
} & Window;

export default window.dataGenerator;
