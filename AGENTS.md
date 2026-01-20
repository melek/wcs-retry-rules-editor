## Requirements
- Provide a GUI for editing Woo Subscriptions retry rules.
- Treat this as a potential upstream-worthy extension (WordPress.org/subscriptions-quality).
- Require Woo Subscriptions and WooCommerce; compatibility only needs to match the symlinked versions in `ref/`.
- Preserve safe defaults and fail-safe behavior; invalid inputs must not break retry processing.
- Favor safe, durable implementation choices and mirror Woo Subscriptions patterns.

## Operations
- Full read/write access; all development happens in this repo.

## Quality
- Mirror Woo Subscriptions testing conventions and admin UX expectations.

## References
- Woo Subscriptions failed payment retry docs: https://woocommerce.com/document/subscriptions/failed-payment-retry/
- Woo Subscriptions failed payment retry developer docs: https://woocommerce.com/document/subscriptions/develop/failed-payment-retry/
