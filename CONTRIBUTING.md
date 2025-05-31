# Contributing to GiveWP Test Donation Generator

Thank you for considering contributing to the GiveWP Test Donation Generator! This document outlines the process for contributing to this project.

## Development Setup

### Prerequisites

- WordPress development environment
- GiveWP plugin installed and activated
- PHP 7.4 or higher
- Composer (optional, for development dependencies)

### Installation

1. Clone the repository into your WordPress plugins directory:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/impress-org/givewp-test-donation-generator.git
   ```

2. Activate the plugin in your WordPress admin panel.

## Code Standards

This project follows the same coding standards as the main GiveWP plugin:

- **PHP**: WordPress Coding Standards (WPCS)
- **JavaScript**: WordPress JavaScript Coding Standards
- **CSS**: WordPress CSS Coding Standards

### PHP Code Standards

- Use PSR-4 autoloading
- Follow WordPress naming conventions
- Include proper DocBlocks for all functions and classes
- Use type hints where appropriate

### Namespace Convention

All PHP classes should use the `GiveFaker\TestDonationGenerator` namespace.

## Submitting Changes

### Pull Request Process

1. Fork the repository
2. Create a feature branch from `develop`
3. Make your changes
4. Test your changes thoroughly
5. Submit a pull request to the `develop` branch

### Commit Messages

Use clear, descriptive commit messages:

```
feat: Add donation mode selection setting
fix: Resolve issue with fee amount calculation
docs: Update README with new features
```

### Testing

Before submitting a pull request:

1. Test the addon with various GiveWP configurations
2. Verify that test donations are created correctly
3. Check that the admin interface works as expected
4. Ensure no PHP errors or warnings are generated

## Reporting Issues

When reporting issues, please include:

- WordPress version
- GiveWP version
- PHP version
- Steps to reproduce the issue
- Expected vs. actual behavior
- Any error messages

## Feature Requests

We welcome feature requests! Please:

1. Check if the feature has already been requested
2. Provide a clear description of the proposed feature
3. Explain the use case and benefits
4. Consider if this fits within the scope of a test data generator

## Code of Conduct

This project follows the [WordPress Community Code of Conduct](https://make.wordpress.org/handbook/community-code-of-conduct/).

## Questions?

If you have questions about contributing, feel free to:

- Open an issue for discussion
- Contact the GiveWP team
- Check the [GiveWP documentation](https://givewp.com/documentation/)

Thank you for helping improve the GiveWP Test Donation Generator!
