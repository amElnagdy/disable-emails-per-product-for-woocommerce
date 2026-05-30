# Baseline QA Checklist — Disable Emails Per Product for WooCommerce

**Audit date**: 2026-05-19
**Commit audited**: d161952642f9200c30e9cc5b59a4ba24cf0ca60c
**Test environment**:
- WordPress version: N/A (local test environment not available during audit)
- WooCommerce version: N/A (local test environment not available during audit)
- PHP version: 8.2.12
- HPOS state: N/A

## Summary

| Result   | Count |
|----------|-------|
| pass     | 0     |
| fail     | 0     |
| blocked  | 0     |
| pending  | 8     |

## Categories represented

- [x] order-level-suppression
- [x] product-level-suppression
- [x] deleted-product
- [x] hpos-enabled
- [x] hpos-disabled
- [x] guest-checkout
- [x] customer-email
- [x] admin-email

## Scenarios

### QA-001 — Order-level suppression prevents new order email

- **Category**: order-level-suppression
- **Preconditions**:
  1. WooCommerce active, HPOS disabled.
  2. A test order exists with no product-level suppression rules applied.
- **Steps**:
  1. Open the order in WooCommerce admin.
  2. Check the "Disable Order Emails" checkbox.
  3. Update the order.
  4. Trigger the new order email (e.g., by changing status to "processing").
- **Expected outcome**: No new order email is sent (recipient is empty).
- **Observed outcome**: Pending execution in test environment.
- **Result**: pending
- **Risk entry ref**: —
- **Notes**: Validates the order-level meta read path (`_disable_order_emails`).

### QA-002 — Product-level suppression prevents processing order email

- **Category**: product-level-suppression
- **Preconditions**:
  1. WooCommerce active, HPOS disabled.
  2. A simple product exists with the "Processing order" email disabled.
  3. A test order contains that product.
- **Steps**:
  1. Create an order containing the suppressed product.
  2. Change order status to "processing".
  3. Observe the email send attempt.
- **Expected outcome**: Processing order email is not sent.
- **Observed outcome**: Pending execution in test environment.
- **Result**: pending
- **Risk entry ref**: —
- **Notes**: Validates `Core::filter_woocommerce_email_recipient` product-level path.

### QA-003 — Order containing a deleted product does not fatal on processing email

- **Category**: deleted-product
- **Preconditions**:
  1. WooCommerce active, HPOS disabled.
  2. A test product exists, was added to a test order, then deleted with `wp post delete <id> --force`.
- **Steps**:
  1. Change order status to "processing" via WC admin.
  2. Observe the resulting email send attempt (mailcatcher / SMTP log).
- **Expected outcome**: No PHP fatal; email is sent (or recipient is empty without fatal); WC order screen renders.
- **Observed outcome**: Pending execution in test environment.
- **Result**: pending
- **Risk entry ref**: —
- **Notes**: Guards against the `get_product()` returning `false` fatal identified in R-007 / R-008.

### QA-004 — Product-level suppression works with HPOS enabled

- **Category**: hpos-enabled
- **Preconditions**:
  1. WooCommerce active, HPOS enabled.
  2. A simple product exists with the "New order" email disabled.
  3. A test order contains that product.
- **Steps**:
  1. Create an order containing the suppressed product.
  2. Change order status to "processing".
  3. Observe the email send attempt.
- **Expected outcome**: New order email is not sent.
- **Observed outcome**: Pending execution in test environment.
- **Result**: pending
- **Risk entry ref**: —
- **Notes**: Validates HPOS-safe product meta read (`get_post_meta` on product ID is safe regardless of HPOS).

### QA-005 — Order-level suppression works with HPOS disabled

- **Category**: hpos-disabled
- **Preconditions**:
  1. WooCommerce active, HPOS disabled.
  2. A test order exists.
- **Steps**:
  1. Disable order emails via the order checkbox.
  2. Update the order.
  3. Trigger a status change that would send an email.
- **Expected outcome**: Email is suppressed.
- **Observed outcome**: Pending execution in test environment.
- **Result**: pending
- **Risk entry ref**: —
- **Notes**: Baseline validation for the legacy postmeta path.

### QA-006 — Guest checkout order respects product-level suppression

- **Category**: guest-checkout
- **Preconditions**:
  1. WooCommerce active, guest checkout enabled.
  2. A simple product exists with "Completed order" email disabled.
- **Steps**:
  1. Place a guest checkout order containing the suppressed product.
  2. Mark the order as "completed".
  3. Observe the email send attempt.
- **Expected outcome**: Completed order email is not sent.
- **Observed outcome**: Pending execution in test environment.
- **Result**: pending
- **Risk entry ref**: —
- **Notes**: Ensures suppression logic does not depend on a registered customer user.

### QA-007 — Customer note email respects product-level suppression

- **Category**: customer-email
- **Preconditions**:
  1. WooCommerce active.
  2. A simple product exists with "Customer note" email disabled.
  3. A test order contains that product.
- **Steps**:
  1. Open the order and add a customer note.
  2. Check the "Notify customer" checkbox.
  3. Save the note.
- **Expected outcome**: Customer note email is not sent.
- **Observed outcome**: Pending execution in test environment.
- **Result**: pending
- **Risk entry ref**: —
- **Notes**: Validates that the `customer_note` email flow is covered by the product-level filter.

### QA-008 — New order admin email respects product-level suppression

- **Category**: admin-email
- **Preconditions**:
  1. WooCommerce active.
  2. A simple product exists with "New order" email disabled.
  3. A test order contains that product.
- **Steps**:
  1. Place an order containing the suppressed product.
  2. Observe the new order email send attempt to the admin address.
- **Expected outcome**: Admin new order email is not sent.
- **Observed outcome**: Pending execution in test environment.
- **Result**: pending
- **Risk entry ref**: —
- **Notes**: The "New order" email is an admin-facing email; suppression must apply consistently.

---

**Contract validation**: All required completeness checks pass as of 2026-05-19, verified against `specs/00-plugin-audit-and-baseline/contracts/baseline-qa-checklist.schema.md`.
