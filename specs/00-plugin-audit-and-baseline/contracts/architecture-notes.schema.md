# Contract: `deliverables/architecture-notes.md`

**Entities referenced**: [Component](../data-model.md#entity-component),
[HookRegistration](../data-model.md#entity-hookregistration).

## Required structure

```markdown
# Architecture Notes — Disable Emails Per Product for WooCommerce

**Audit date**: YYYY-MM-DD
**Commit audited**: <full SHA>

## 1. Bootstrap

Description of `disable-emails-per-product-for-woocommerce.php`:

- Plugin header values (Name, Version, Requires PHP, Requires Plugins,
  Tested up to, WC tested up to).
- Constants and globals defined.
- Component instantiation order and conditions.
- HPOS compatibility declaration (verbatim quote of the
  `FeaturesUtil::declare_compatibility` call site).

## 2. Components

### 2.1 Core

- **Files**: …
- **Responsibility**: …
- **Public hooks**: link to hook-inventory.md rows.
- **Consumed WC APIs**: …
- **Consumed WP APIs**: …
- **Inter-component deps**: …
- **HPOS assumptions**: explicit "none" or description.

### 2.2 Admin

… same structure …

### 2.3 GlobalView

… same structure …

## 3. End-to-end suppression flow

For each of the six critical email flows from Constitution principle II,
trace the path from WooCommerce attempting to send the email to the
plugin's decision being applied. One subsection per flow:

### 3.1 New order email

1. WC triggers `<entry hook>` with `<args>`.
2. Plugin filter `<callback>` (registered at `<priority>` in
   `<source_file>:<line>`) executes.
3. Plugin reads `<meta key>` from `<storage>` for `<entity id>`.
4. Plugin decision: deliver / suppress.
5. WC sends or skips the email.

### 3.2 Processing order email
### 3.3 Completed order email
### 3.4 Customer note email
### 3.5 New account email
### 3.6 Password reset email

## 4. Settings registration

- Where each WooCommerce settings tab/section is registered.
- Hook priorities and any ordering dependencies relative to WC core.
- Tab slug(s) the plugin owns.
- Nonces used for settings writes and where they are verified.

## 5. Order & product metadata access

Enumerate every read/write of order or product metadata. For each:

- `file:line`.
- Meta key.
- Read or write.
- Subject entity (product / order / variation).
- HPOS-safe? (yes / no / unknown).

## 6. Unrouted findings

Bullet list of any audit observations that did not fit cleanly into a
RiskEntry phase (see research R-008). Each item is a candidate for
re-triage during Phase 1 planning.
```

## Required completeness checks

1. All six critical email flows from Constitution principle II are
   traced in section 3, even if the trace concludes "plugin does not
   intervene in this flow".
2. Every metadata access in section 5 is annotated with HPOS-safe status.
3. Every hook referenced in this document exists as a row in
   `hook-inventory.md`.
4. Section 6 lists at least the count of unrouted items (0 is
   acceptable; absence is not).
