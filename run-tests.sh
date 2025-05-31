#!/bin/bash

# GiveWP Test Donation Generator - Test Runner Script
# This script runs the PHPUnit tests for the addon

echo "🧪 Running GiveWP Test Donation Generator Tests..."

# Check if we're in the right directory
if [ ! -f "give-test-donation-generator.php" ]; then
    echo "❌ Error: Please run this script from the plugin directory"
    exit 1
fi

# Check if GiveWP is installed
if [ ! -d "../give" ]; then
    echo "❌ Error: GiveWP plugin not found. Please ensure GiveWP is installed in the adjacent 'give' directory"
    exit 1
fi

# Check if PHPUnit is available via Composer
if [ -f "../give/vendor/bin/phpunit" ]; then
    PHPUNIT="../give/vendor/bin/phpunit"
elif command -v phpunit &> /dev/null; then
    PHPUNIT="phpunit"
else
    echo "❌ Error: PHPUnit not found. Please install via Composer or globally"
    exit 1
fi

echo "📁 Running tests from: $(pwd)"
echo "🔧 Using PHPUnit: $PHPUNIT"

# Run specific test groups if provided
if [ "$1" != "" ]; then
    echo "🎯 Running specific test: $1"
    $PHPUNIT --configuration phpunit.xml --filter "$1"
else
    echo "🚀 Running all tests..."
    $PHPUNIT --configuration phpunit.xml
fi

echo ""
echo "✅ Test run complete!"

# Check test results
RESULT=$?
if [ $RESULT -eq 0 ]; then
    echo "🎉 All tests passed!"
else
    echo "💥 Some tests failed (exit code: $RESULT)"
fi

exit $RESULT
