# ERP Point 0 Status

Baseline date: **2026-08-03**

This document is the canonical implementation-status summary for the ERP module. It describes the ERP scope after reconciling the module code, Superpowers plans, README, user documentation, and RAG documentation.

External-source importers are a separate workstream and are intentionally excluded from this baseline.

## Baseline

- All approved mandatory ERP work that does not require external `/api/v1` exposure is implemented.
- Stateful ERP operations are available through Filament and the authenticated internal action route `POST /app/crud/{action}/{module}/{entity}` where an action is registered.
- Core dynamic CRUD remains the generic record-access surface. External ERP domain APIs are not approved by default.
- FatturaPA, Aruba transport, Italian bank files, report snapshots, dated FX conversion, unrealized revaluation, decimal-safe money, and analytic allocations have implemented first production-oriented slices.
- Remaining items are explicit deferred decisions, optional features, or deployment/go-live obligations. They are not hidden incomplete tasks in the current tranche.

## Phase Status

| Scope | Status | Boundary |
| --- | --- | --- |
| Phase 2A | Complete | Domain services, state-aware policies, and Filament actions. |
| Phase 2B | Complete | Tasks `2B-01` through `2B-13`. |
| Phase 2C | Complete | FatturaPA mapping/XSD, Aruba operations, polling, and extended permissions. |
| Phase 3 internal actions | Complete | Registered actions use Core's authenticated `/app` dispatcher and ERP policies. |
| Phase 3 external API | Deferred | `3-02` and `3-03` require an approved external consumer and governance design. |
| Phase 4 required scope | Complete | Commercial depth through `4-07` and `4-10`, including Task ICS export. |
| Phase 4 optional scope | Not approved | `4-08` Gantt and `4-13` mobile API remain optional. |
| Phase 5 | Complete | Safe return reversal, e-invoice operations, Italian bank formats, report snapshots, FX/revaluation, Money, and analytic dimensions. |
| Phase 6 | Complete | Commands, outbox integration, extension contracts, and architecture documentation. |

## Deliberately Deferred

| ID | Item | Decision needed before implementation |
| --- | --- | --- |
| `3-02` | External action/API authorization contract | Define consumers, credentials, scopes, idempotency, and which state transitions may be public. |
| `3-03` | ERP-specific external resources and requests | Add only where Core dynamic CRUD cannot safely represent an approved use case. |
| `4-08` | Project Gantt | Approve a scheduling use case and interaction requirements. |
| `4-13` | Mobile API | Approve a mobile client contract and offline/synchronization behavior. |

The former `4-09` importer work is maintained in its own plan and is not part of ERP Point 0.

## Go-Live Obligations And Future Depth

These items do not invalidate the implemented baseline:

- Verify Aruba endpoints, credentials, callbacks, polling behavior, and conservazione obligations against the contracted tenant.
- Certify generated bank files with each target bank and provide a separate transport when direct submission is required.
- Add rich paginated PDF layouts and scheduled operational snapshots only for approved reporting requirements.
- Integrate external FX feeds, realized FX settlement automation, and broader legacy-money migration when required.
- Add analytic reporting cubes only after reporting dimensions and aggregation requirements are agreed.
- Execute database-specific release, concurrency, and load tests in the target deployment environments.

## Documentation Authority

- `README.md`: module capabilities, configuration, and operational overview.
- `docs/ERP_GUIDA_SEMPLICE.md`: user-facing workflows and limitations.
- `docs/VISION.md`: architecture, boundaries, and extension contracts.
- `docs/GLOSSARY.md`: domain terminology.
- `docs/rag/`: concise retrieval documentation for users and developers.
- Root `docs/superpowers/`: implementation history and decision trace until the active workstream is formally closed.

