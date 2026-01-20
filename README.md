# WCS Retry Rules Editor

Visual editor for WooCommerce Subscriptions failed payment retry rules.
<img width="990" height="652" alt="image" src="https://github.com/user-attachments/assets/88510bab-0056-4e90-8de7-8b23e63fe7f4" />

## Features
- Create, reorder, and delete retry rules.
- Configure retry interval, order status, and subscription status.
- Choose customer/admin retry emails per rule.
- Preview default retry emails in a modal.
- Optionally override email subject, heading, and additional content per rule.

## Requirements
- WordPress 6.0+
- WooCommerce 7.0+
- WooCommerce Subscriptions (version in `ref/` or a release package)
- PHP 7.4+

## Usage
1. Go to **WooCommerce → Settings → Subscriptions**.
2. Use the **General | Retry Rules** links to open the Retry Rules section.
3. Add or edit rules in the list.
4. Use **Email Content Overrides** in each rule to preview defaults.
5. Enable **Override email content for this rule** to customize the message.
6. Click **Preview** to open a popup preview for customer/admin emails.
7. Save rules when you are done.

## Notes
- Email overrides only apply when the override toggle is enabled.
- Override fields accept the same placeholders as the default retry emails.
- Preview uses dummy data from WooCommerce email preview tooling.

## Changelog
See `CHANGELOG.md`.
