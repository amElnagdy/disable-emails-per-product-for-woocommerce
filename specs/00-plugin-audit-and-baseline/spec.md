# Feature Specification: Plugin Audit & Baseline

**Feature Branch**: `00-plugin-audit-and-baseline`

**Created**: 2026-05-18

**Status**: Draft

**Input**: User description: "Read plan.md and create a specification for the Phase 0 — Discovery & Baseline Audit ONLY"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Maintainer can locate every hook and entry point the plugin registers (Priority: P1)

A plugin maintainer preparing to make a stabilization change needs to know every
WordPress / WooCommerce hook the plugin registers, with each hook's callback and
source location, so they can predict the blast radius of any modification before
touching code.

**Why this priority**: Without a hook inventory, stabilization work in later
phases (runtime safety, HPOS, admin) is guesswork. This is the foundational
deliverable that every subsequent phase depends on.

**Independent Test**: A maintainer opens the hook inventory deliverable and,
for any of the six critical email flows (new order, processing, completed,
customer note, new account, password reset), can identify within minutes which
hooks the plugin attaches to and what callbacks run.

**Acceptance Scenarios**:

1. **Given** the hook inventory deliverable, **When** a maintainer looks up
   "new order email", **Then** the inventory lists every plugin-registered
   hook that participates in that flow, including hook name, callback name,
   and source file/line reference.
2. **Given** the hook inventory deliverable, **When** a maintainer searches
   for any plugin source file, **Then** every `add_action` / `add_filter` /
   `register_*` call in that file is represented in the inventory.

---

### User Story 2 - Maintainer has a written risk inventory ranking what could break in production (Priority: P1)

A maintainer needs a single document that enumerates the concrete risks the
plugin carries today (fatal-error sources, HPOS divergences, admin
instability, hidden suppression behavior), each with severity, likelihood,
and the stabilization phase that will address it.

**Why this priority**: The risk inventory drives prioritization of Phases 1–5
and gives the project a defensible answer to "what could go wrong if we ship
nothing this quarter". Without it, the project cannot demonstrate that later
phases targeted the right problems.

**Independent Test**: A maintainer reviewing the risk inventory can, for every
risk listed, point to the phase in `plan.md` that owns its mitigation, and
can sort the inventory by severity to identify the top 5 risks.

**Acceptance Scenarios**:

1. **Given** the risk inventory, **When** a maintainer filters by severity =
   high, **Then** every entry has an assigned mitigation phase from `plan.md`
   (Phases 1–6) and a one-line description of the failure mode.
2. **Given** the risk inventory, **When** a new risk is discovered later,
   **Then** the document's format makes it obvious where and how to append
   the new entry without restructuring existing entries.

---

### User Story 3 - Maintainer has a compatibility matrix covering supported environments (Priority: P2)

A maintainer needs a documented matrix of supported PHP versions × supported
WooCommerce versions × HPOS state, with a recorded result (works / partial /
broken / untested) for each cell, so subsequent stabilization phases know
exactly which environments must be exercised before release.

**Why this priority**: The Constitution (principles IV, V) requires
WooCommerce and HPOS compatibility to be validated, not assumed. The matrix
is the single source of truth for what "supported" means in this project.

**Independent Test**: A reviewer can open the matrix, pick any cell, and see
either a result with evidence reference, or an explicit "untested" marker.
No cell is silently empty.

**Acceptance Scenarios**:

1. **Given** the compatibility matrix, **When** a reviewer selects HPOS =
   enabled, **Then** every PHP × WooCommerce combination listed has a
   recorded status.
2. **Given** the compatibility matrix, **When** any cell is marked "broken"
   or "partial", **Then** there is a reference to a risk inventory entry
   describing the failure and its owning phase.

---

### User Story 4 - Maintainer has a baseline QA checklist and lint baseline for regression detection (Priority: P3)

A maintainer needs a reusable QA checklist of verifiable scenarios (covering
order-level suppression, product-level suppression, deleted-product orders,
HPOS on/off, guest checkout, customer emails, admin emails) and a recorded
baseline of PHP lint / WordPress coding standards output, so future
stabilization phases can demonstrate they have not introduced regressions.

**Why this priority**: Regression detection is required by Constitution
principle VIII but is only useful once Phases 1–5 begin producing changes.
The baseline must exist before those changes land, but it is not on the
critical path for understanding the codebase.

**Independent Test**: A maintainer running the QA checklist against the
current plugin records pass/fail for every scenario, and the lint baseline
file contains the exact tool output produced against the current code.

**Acceptance Scenarios**:

1. **Given** the baseline QA checklist, **When** a maintainer executes it
   against the current plugin, **Then** every listed scenario has an
   unambiguous pass/fail criterion and recorded result.
2. **Given** the lint baseline, **When** a later phase introduces changes,
   **Then** the diff between the new lint output and the baseline can be
   computed without ambiguity.

---

### Edge Cases

- The plugin registers hooks conditionally based on the presence of optional
  third-party plugins (e.g., another email extension). The inventory must
  record the precondition for any conditional registration, not just the
  hook itself.
- A hook is registered in source code but never actually fires under default
  WooCommerce configuration (dead code). The inventory must distinguish
  "registered" from "exercised" and flag suspected dead registrations as
  risk inventory entries.
- The current HPOS compatibility declaration may not match observed
  behavior. The audit must explicitly compare the declaration against
  observed behavior and treat any mismatch as a high-severity risk entry.
- Settings page registration may rely on hook ordering relative to
  WooCommerce core. The architecture notes must capture any ordering
  dependency so later phases do not silently break the admin UI.
- The plugin may read or write order/product metadata in ways that assume
  the post-table storage backend. Every such site must be enumerated for
  Phase 2 (HPOS) to address.
- Public filter prefix in use is `dwepp_` (per `README.md`), while
  `const.md` proposed `depp_`. The audit must record the actual prefix
  in use so later phases preserve the public API.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The audit MUST produce a hook inventory enumerating every
  WordPress / WooCommerce action or filter the plugin registers, with hook
  name, callback identifier, source file, source line, and any registration
  preconditions.
- **FR-002**: The audit MUST produce architecture notes describing the
  Core, Admin, and GlobalView components, their responsibilities, and the
  data/control flow between them.
- **FR-003**: The audit MUST document the end-to-end email suppression
  flow, from the moment WooCommerce attempts to send an email to the
  point the plugin's decision is applied.
- **FR-004**: The audit MUST document how plugin settings are registered,
  rendered, and persisted, including any reliance on WooCommerce settings
  hooks or tab registration.
- **FR-005**: The audit MUST document every place the plugin reads or
  writes order data or product data, identifying whether each access path
  is HPOS-safe.
- **FR-006**: The audit MUST produce a compatibility matrix covering the
  supported PHP versions, the supported WooCommerce versions, and HPOS
  enabled / HPOS disabled. Every cell MUST have a recorded status:
  works, partial, broken, or untested.
- **FR-007**: The audit MUST record the result of running PHP lint against
  the current source tree and store the output as the baseline.
- **FR-008**: The audit MUST record the result of running WordPress coding
  standards (WPCS) against the current source tree and store the output
  as the baseline.
- **FR-009**: The audit MUST attempt to run static analysis where the
  tooling is reasonably available; if static analysis is not run, the
  reason MUST be documented.
- **FR-010**: The audit MUST produce a risk inventory in which every entry
  has: short description, observed/expected impact, severity, likelihood,
  and the owning phase from `plan.md` that will address the risk.
- **FR-011**: The audit MUST produce a baseline QA checklist enumerating
  verifiable scenarios covering at minimum: order-level suppression,
  product-level suppression, orders containing deleted products, HPOS
  enabled, HPOS disabled, guest checkout, customer-facing emails, and
  admin-facing emails.
- **FR-012**: The audit MUST produce a known-regression list of any
  historical defects, observed misbehaviors, or open issues that future
  phases must explicitly confirm are not reintroduced.
- **FR-013**: The audit MUST NOT modify production plugin code. Any
  defects discovered are recorded as risk inventory entries and routed
  to the appropriate later phase.
- **FR-014**: The audit MUST verify whether the plugin's declared HPOS
  compatibility matches its observed behavior, and explicitly record
  either confirmation or a high-severity risk inventory entry if it does
  not.

### Key Entities

- **Hook Registration**: A single hook the plugin registers. Attributes:
  hook name, hook type (action/filter), callback identifier, source file,
  source line, registration precondition (always / conditional with
  description), exercised-under-default-config flag.
- **Component**: One of Core, Admin, GlobalView. Attributes: name,
  responsibility summary, public surface (hooks/filters it owns),
  dependencies on other components, dependencies on WooCommerce APIs.
- **Compatibility Datapoint**: A single cell in the compatibility matrix.
  Attributes: PHP version, WooCommerce version, HPOS state, status
  (works/partial/broken/untested), evidence reference.
- **Risk Entry**: A single identified risk. Attributes: short
  description, failure mode, severity (high/medium/low), likelihood
  (high/medium/low), owning phase (1–6), proposed mitigation summary.
- **Baseline QA Scenario**: A single verifiable scenario in the baseline
  QA checklist. Attributes: scenario name, preconditions, steps, expected
  outcome, current observed outcome, pass/fail.
- **Known Regression**: A historical defect or observed misbehavior.
  Attributes: short description, first observed (if known), current
  status, watching-phase that must confirm non-reintroduction.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A maintainer unfamiliar with the codebase can, using only
  the audit deliverables, identify the affected files and components for
  any of the six critical email flows from Constitution principle II in
  under 10 minutes.
- **SC-002**: 100% of `add_action` / `add_filter` / hook-registering
  calls in the plugin source tree are represented in the hook inventory.
- **SC-003**: 100% of compatibility matrix cells have a recorded status
  (works / partial / broken / untested); no cell is silently empty.
- **SC-004**: 100% of risk inventory entries have a severity, a
  likelihood, and an owning phase from `plan.md` (Phases 1–6).
- **SC-005**: The baseline QA checklist contains scenarios covering all
  minimum validation cases enumerated in Constitution principle VIII.
- **SC-006**: The lint and WPCS baseline output is stored verbatim such
  that a future phase can produce a deterministic diff against it.
- **SC-007**: The audit conclusively answers whether the plugin's current
  HPOS compatibility declaration is accurate, with either confirming
  evidence or a high-severity risk entry.
- **SC-008**: The audit deliverables are sufficient for Phase 1
  (Runtime Safety Stabilization) to begin without further discovery
  work; specifically, every fatal-error candidate listed in `plan.md`
  Phase 1 has a corresponding risk inventory entry with a source-file
  reference.

## Assumptions

- The audit produces documentation only; no plugin source code is
  modified in this phase. Defects discovered are routed to Phase 1+ via
  the risk inventory.
- Supported PHP versions for the compatibility matrix default to those
  currently supported by WordPress core at the time of the audit
  (assumed: 7.4, 8.0, 8.1, 8.2, 8.3). The maintainer may narrow or
  widen this range in the plan phase.
- Supported WooCommerce versions for the compatibility matrix default to
  the two most recent minor release lines available at the time of the
  audit. The maintainer may adjust in the plan phase.
- Local environments capable of running PHP lint, WPCS, and at least one
  WooCommerce-equipped test site (with HPOS toggleable) are available to
  the maintainer performing the audit.
- The public filter prefix `dwepp_` (as used in `README.md`) is the
  current production-facing API and is preserved by this audit; any
  rename is out of scope for Phase 0.
- Phase 0 is bounded to discovery and baseline establishment. Phases 1–6
  are explicitly out of scope and only referenced as the destination for
  risk inventory routing.
