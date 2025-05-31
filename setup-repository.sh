#!/bin/bash

# GiveWP Test Donation Generator - Repository Setup Script
# This script helps initialize the Git repository for the addon

echo "🚀 Setting up GiveWP Test Donation Generator repository..."

# Check if we're in the right directory
if [ ! -f "give-test-donation-generator.php" ]; then
    echo "❌ Error: Please run this script from the plugin directory"
    exit 1
fi

# Initialize git repository
echo "📂 Initializing Git repository..."
git init

# Add all files
echo "📁 Adding files to Git..."
git add .

# Create initial commit
echo "💾 Creating initial commit..."
git commit -m "feat: Initial release of GiveWP Test Donation Generator v1.0.0

- Generate test donations with realistic donor data
- Campaign selection and donation count configuration
- Date range options (Last 30/90 days, Last year, Custom range)
- Donation mode selection (Test/Live mode)
- Random donor information with realistic amounts
- Fee recovery amount generation
- Admin interface under Donations menu
- Comprehensive error handling and validation
- AJAX form submission with loading states
- WordPress security best practices"

# Set main branch
echo "🌿 Setting main branch..."
git branch -M main

# Instructions for remote setup
echo ""
echo "✅ Repository initialized successfully!"
echo ""
echo "📋 Next steps:"
echo "1. Create the repository on GitHub at: https://github.com/impress-org"
echo "2. Use repository name: givewp-test-donation-generator"
echo "3. Add the remote origin:"
echo "   git remote add origin https://github.com/impress-org/givewp-test-donation-generator.git"
echo "4. Push to GitHub:"
echo "   git push -u origin main"
echo ""
echo "🎉 Your GiveWP Test Donation Generator is ready for GitHub!"
