# ERP Point 0 Status

Canonical date: **2026-08-03**. External-source importers are excluded and tracked separately.

## Current State

- Approved mandatory non-external-API ERP scope is complete.
- Phase 2A, 2B, 2C, Phase 5, and Phase 6 are complete.
- Phase 3 internal domain actions are implemented through `POST /app/crud/{action}/{module}/{entity}` with ERP policy authorization.
- Phase 4 required work is complete; Task ICS export is implemented.
- External `/api/v1` governance (`3-02`, `3-03`) is deferred pending a real consumer contract.
- Gantt (`4-08`) and mobile API (`4-13`) are optional and unapproved.
- Importers (`4-09`) are a separate workstream and do not affect ERP Point 0.

## Important Boundaries

- Core dynamic CRUD handles generic record access; stateful ERP transitions use registered domain actions.
- Aruba tenant verification, legal-retention governance, bank certification/direct submission, external FX feeds, realized FX automation, rich scheduled reports, legacy Money migration, analytic cubes, and production load tests are go-live or future-depth work.
- See `docs/STATUS.md` for the full status table and document authority.

