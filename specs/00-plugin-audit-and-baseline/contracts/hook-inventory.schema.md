# Contract: `deliverables/hook-inventory.md`

**Entity**: [HookRegistration](../data-model.md#entity-hookregistration)

## Required structure

```markdown
# Hook Inventory — Disable Emails Per Product for WooCommerce

**Audit date**: YYYY-MM-DD
**Commit audited**: <full SHA>
**Files scanned**: <list of source paths>

## Summary

- Total registrations: <N>
- Action registrations: <N>
- Filter registrations: <N>
- Conditional registrations: <N>
- Registrations exercised under default config: <N>
- Registrations linked to a critical email flow: <N>

## Table

| # | hook_name | hook_type | callback | source_file | source_line | priority | accepted_args | registration_precondition | exercised_under_default_config | relates_to_critical_email_flow | notes |
|---|-----------|-----------|----------|-------------|-------------|----------|---------------|---------------------------|--------------------------------|--------------------------------|-------|
| 1 | … | action | … | includes/Core.php | 9 | 10 | 1 | always | yes | none | … |

## Per-file breakdown

### `<source_file>`

- Bullet list of registrations originating in this file, with one line per
  registration linking back to the table above.
```

## Required completeness checks

1. Every `add_action` / `add_filter` / `register_*` call in the source
   tree appears as exactly one row.
2. Every row's `source_file:source_line` resolves to the matching call
   site in the audited commit.
3. The `Summary` counts equal the actual counts derivable from the table.
4. Every row whose `relates_to_critical_email_flow != "none"` MUST appear
   in the `architecture-notes.md` deliverable's flow-trace section for
   that flow.
