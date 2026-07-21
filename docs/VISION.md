# ERP architecture and extension vision

## Purpose

ERP is an optional Laraplate domain module for accounting, tax, sales, purchasing, inventory, banking, returns, e-invoicing, and operational reporting. Core supplies shared framework capabilities; ERP must not require MES, AI, CMS, or another optional vertical module.

## Architectural rules

- Business state changes belong to transactional services, not Filament pages, controllers, observers, or export classes.
- Filament and console commands are adapters over the same services and state guards.
- Posted fiscal/accounting records are changed through explicit post, unpost, reverse, close, or reopen workflows.
- Quantities and money use decimal database columns and decimal-safe domain helpers; DDT lines remain logistics-only and do not become pricing sources.
- New schema uses `ERPTables` / `erp_*`; generic infrastructure belongs in Core only when it has no ERP dependency.
- Cross-system notifications use Core's transactional outbox. Dynamic CRUD/API exposure is governed separately and is not a substitute for domain actions.

## Supported extension points

| Contract | Default | Intended replacement |
| --- | --- | --- |
| `ChartOfAccountsProvider` | `ItalianCoaProvider` | Jurisdiction/company chart templates and account-role definitions |
| `CurrencyConverter` | `DatabaseCurrencyConverter` | External or cached FX source while preserving dated conversion semantics |
| `EInvoiceProvider` | Configured `stub`, `fatturapa`, or `aruba` adapter | Contracted e-invoice transport registered by the host application |
| Core `OutboxPublisher` | Core `StubOutboxPublisher` | Broker, webhook, stream, or other external event transport |

The host application can replace a binding from an application service provider loaded after the module. Replacements must preserve the published contract and ERP transaction/state invariants.

There are currently **no container-tagged ERP plugin hooks**. Bank statement parsers and bank-file exporters have focused interfaces/services but are selected by ERP workflows, not discovered through tags. Add a registry/tag only when multiple independently installed implementations require runtime discovery; do not advertise implicit plugin discovery before it exists.

## Stable integration events

ERP records these event names in `core_outbox_events`:

- `erp.invoice.posted`
- `erp.payment.matched`
- `erp.customer-return.completed`
- `erp.supplier-return.completed`

`event_id` is the external consumer idempotency key. Payloads are integration contracts and should be evolved additively or versioned before incompatible changes.

## Module boundaries

- Core may be used for generic ACL, CRUD, export, queue, locking, outbox, and framework utilities.
- ERP owns its models, migrations, policies, domain services, commands, Filament resources, reports, and provider-specific accounting/fiscal behavior.
- The base application may hold Superpowers planning documents, but final behavior must also be documented in the owning module README and user/developer RAG.
- ERP must not add direct dependencies from the base application to ERP classes because the module can be absent.

## Deliberately separate work

- External API/domain-action governance remains deferred until Core dynamic CRUD exposure and ERP stateful operations are reviewed together.
- MES production execution, Gantt, calendar/ICS, and legacy ETL are separate vertical or future scopes. Partner-pool settlement is an ERP internal subledger; external transfer execution remains separate.
- Production transports require tenant contracts, credentials, operational monitoring, replay/dead-letter procedures, and certification where applicable.

## Change checklist

When adding an ERP capability:

1. Put invariants in a domain service/model validation and keep the transaction boundary explicit.
2. Use an existing contract or add one only when there is a genuine replaceable implementation.
3. Add focused database tests for success, invalid state, rollback, and idempotency/concurrency where relevant.
4. Update README, user guide, developer RAG, glossary, and active plan/spec in the same task.
5. Run focused tests and `vendor/bin/pint --dirty` before committing the point separately.
