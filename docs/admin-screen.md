# The admin screen

**Donations > Data Generator** in wp-admin. One tab per kind of data, each capped at a size that
finishes within one request. For larger sets use the [command line](cli.md).

## Campaigns

Creates campaigns with a default form each: goal type (amount, donations, donors, subscriptions),
random or themed colours, descriptions and images, various durations.

## Donations

1. Pick a campaign.
2. Pick where donors come from: new donors for every donation, existing donors, a 50/50 mix, or
   one specific donor for all of them.
3. Pick how many donations, 1 to 1,000.
4. Pick a date range: last 30 days, last 90 days, last year, or a custom range.
5. Generate.

Each donation gets a realistic donor (name, unique email, US phone and billing address), a random
amount between $5 and $500, and optionally a company and a comment.

## Donation forms

Creates 1 to 20 forms for a campaign: published, draft or private; goals inherited from the
campaign or set to an amount, a donation count or a donor count; multi-step, classic or two-panel
design, random or fixed; campaign colours inherited or not; an optional title prefix.

## Subscriptions

Creates subscriptions with daily, weekly, monthly, quarterly or yearly billing, a mix of statuses,
custom frequency and installment settings, and renewal payments.

## Pages

Pick a campaign and create one published page for every GiveWP block and shortcode: campaign
blocks, v3 and legacy form blocks, donor wall, donor dashboard, multi-form goal, and the rest.
Campaign blocks use the selected campaign and form blocks use its default form. Each block and
shortcode has a checkbox, all checked by default. Switch "Generate for" to a donation form to test
a standalone form, which skips the campaign blocks and shortcodes. "Page layout" creates a page per
block or shortcode, one page for blocks and one for shortcodes, or everything on one page.

## WP-CLI

Documents the commands and builds one from form fields, with a copy button. It also lists the
ready-made datasets (100k, 400k and 1M donations) with the `wp give-data restore` command that
loads one in minutes, for QA on a throwaway site. See [benchmarking.md](benchmarking.md).

## Clean up

Deletes all test mode donations, deletes all test mode subscriptions, or archives all active
campaigns. The CLI's `wp give-data reset` is narrower: it removes only rows the CLI generated.
