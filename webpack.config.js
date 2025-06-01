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
        admin: srcPath('DataGenerator/resources/js/admin.js'),
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
