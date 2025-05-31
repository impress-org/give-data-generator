/**
 * External Dependencies
 */
const path = require('path');

/**
 * WordPress Dependencies
 */
const defaultConfig = require('@wordpress/scripts/config/webpack.config.js');

/**
 * Custom config
 */
module.exports = {
    ...defaultConfig,
    entry: {
        GiveFakerFormBuilderExtension: srcPath('FormExtension/FormBuilder/resources/js/index.tsx'),
        GiveFakerFormBuilderExtensionGlobalStyle: srcPath('FormExtension/FormBuilder/resources/css/index.scss'),
        GiveFakerOffSiteGateway: srcPath('OffSiteGateway/Gateway/resources/OffSiteGateway.tsx'),
        settings: srcPath('Settings/resources/js/index.tsx'),
    },
};

/**
 * Helper for getting the path to the src directory.
 *
 * @param {string} relativePath
 * @returns {string}
 */
function srcPath(relativePath) {
    return path.resolve(process.cwd(), 'src', relativePath);
}
