# Changelog

All notable changes to the GiveWP Test Donation Generator will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2024-12-12

### Added
- Initial release of GiveWP Test Donation Generator
- Generate test donations with realistic donor data
- Campaign selection for associating donations
- Configurable donation count (1-1000)
- Date range options (Last 30/90 days, Last year, Custom range)
- Donation mode selection (Test/Live mode)
- Random donor information generation (names, emails, addresses)
- Realistic donation amounts ($5-$500)
- Fee recovery amount generation (70% no fees, 30% realistic processing fees)
- Admin interface under "Donations > Test Donation Generator"
- Comprehensive error handling and validation
- AJAX form submission with loading states
- WordPress security best practices (nonces, capability checks)

### Features
- **Donor Generation**: Creates realistic donors with random names, emails, and addresses
- **Amount Variety**: Random donation amounts between $5-$500
- **Date Distribution**: Spreads donations across selected timeframe
- **Anonymous Donations**: 10% chance of anonymous donations
- **Company Names**: 30% chance of including company names
- **Donation Comments**: 20% chance of including donation comments
- **Phone Numbers**: US-formatted phone numbers
- **Billing Addresses**: Complete US billing addresses
- **Transaction Data**: Random transaction IDs and purchase keys
- **Fee Recovery**: Realistic processing fee amounts

### Technical
- Built using GiveWP's domain-driven architecture
- Uses GiveWP Models for data creation
- PSR-4 autoloading with proper namespacing
- Follows WordPress coding standards
- Comprehensive input validation and sanitization
- Detailed error logging for debugging

## [Unreleased]

### Planned
- Additional donor data fields (occupation, age ranges)
- International address formats
- More payment gateway options
- Bulk deletion of test donations
- Export functionality for generated data
- Integration with other GiveWP addons
