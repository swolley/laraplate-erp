<p>&nbsp;</p>
<p align="center">
	<a href="https://github.com/swolley" target="_blank">
		<img src="https://raw.githubusercontent.com/swolley/images/refs/heads/master/logo_laraplate.png?raw=true" width="400" alt="Laraplate Logo" />
    </a>
</p>
<p>&nbsp;</p>

> ⚠️ **Caution**: This package is a **work in progress**. **Don't use this in production or use at your own risk**—no guarantees are provided... or better yet, collaborate with me to create the definitive Laravel boilerplate; that's the right place to introduce your ideas. Let me know your ideas...

## Table of Contents

-   [Description](#description)
-   [Installation](#installation)
-   [Configuration](#configuration)
-   [Features](#features)
-   [Scripts](#scripts)
-   [Contributing](#contributing)
-   [License](#license)

## Description

The **ERP** module provides Laraplate’s **accounting and operations** domain: multi-company, chart of accounts, journal entries, fiscal calendar, tax codes, commercial scaffolding (customers, quotations, projects), and related Filament admin resources. The module is **optional** and can be enabled or disabled via `modules_statuses.json` like other Laraplate modules.

The package evolves with product requirements; treat public APIs as unstable until a stable release is declared.

## Architecture and Extension Points

ERP is optional and depends on Core, never on other optional vertical modules. Business mutations remain in transactional services. Supported replaceable bindings are `ChartOfAccountsProvider`, `CurrencyConverter`, `EInvoiceProvider`, and Core `OutboxPublisher`. No container-tagged ERP plugin hooks currently exist. See `Modules/ERP/docs/VISION.md` for invariants, module boundaries, stable outbox event names, deferred API governance, and the implementation checklist.

## Installation

If you want to add this module to your project, you can use the `joshbrw/laravel-module-installer` package.

In a full Laraplate application you typically depend on **Core** first, then add **ERP**. Add the repositories to your `composer.json` file (adjust URLs if you use forks or private registries):

```json
"repositories": [
    {
        "type": "composer",
        "url": "https://github.com/swolley/laraplate-core.git"
    },
    {
        "type": "composer",
        "url": "https://github.com/swolley/laraplate-erp.git"
    }
]
```

```bash
composer require joshbrw/laravel-module-installer swolley/laraplate-core swolley/laraplate-erp
```

Then install and activate the modules:

```bash
php artisan module:install Core
php artisan module:install ERP
```

Ensure `modules_statuses.json` lists `ERP` as enabled when you want the module loaded.

## Configuration

When the module is active, configuration is published under the **`erp`** key (file: `Modules/ERP/config/config.php`).

```php
// Example
config('erp.name'); // "ERP"
config('erp.einvoice.driver'); // "stub", "fatturapa", or "aruba"
```

E-invoice provider configuration is read through Laravel config/env:

-   `ERP_EINVOICE_DRIVER`: `stub` by default; supported values are `stub`, `fatturapa`, `aruba`
-   `ERP_EINVOICE_ARUBA_BASE_URL`: Aruba API base URL, for example the contracted demo/production web-service host
-   `ERP_EINVOICE_ARUBA_AUTH_BASE_URL`: optional Aruba auth base URL when using username/password token acquisition
-   `ERP_EINVOICE_ARUBA_UPLOAD_PATH`: upload endpoint path, default `/services/invoice/upload`
-   `ERP_EINVOICE_ARUBA_NOTIFICATIONS_PATH`: notifications polling endpoint path, default `/api/v2/invoices-out/notifications`
-   `ERP_EINVOICE_ARUBA_TOKEN`: bearer token used by the Aruba adapter; if missing, username/password auth is attempted
-   `ERP_EINVOICE_ARUBA_USERNAME` / `ERP_EINVOICE_ARUBA_PASSWORD`: optional credentials for Aruba `/auth/signin`
-   `ERP_EINVOICE_ARUBA_CALLBACK_API_KEY`: optional static API key expected on Aruba callback requests
-   `ERP_EINVOICE_ARUBA_SIGNATURE_CREDENTIAL` / `ERP_EINVOICE_ARUBA_SIGNATURE_DOMAIN`: optional Aruba signature parameters
-   `ERP_EINVOICE_ARUBA_SENDER_PIVA`: optional sender VAT override; defaults to company fiscal country + tax id
-   `ERP_EINVOICE_ARUBA_SKIP_EXTRA_SCHEMA` / `ERP_EINVOICE_ARUBA_DRY_RUN`: optional upload flags

Per-company ERP settings are stored in `companies.settings` (JSON). Use `ErpCompanySettings` to read dotted keys (e.g. `erp.three_way_match.price_tolerance_percent`).

## Features

### Requirements

-   PHP >= 8.5
-   Laravel 12.0+
-   **Recommended:** Laraplate **Core** for users, permissions, and shared infrastructure (typical production setup)

### Installed Packages (development)

The ERP module aligns with the same quality toolchain as **Cms** and **Core**:

-   [pestphp/pest](https://github.com/pestphp/pest) and Laravel / type-coverage plugins
-   [laravel/pint](https://github.com/laravel/pint)
-   [larastan/larastan](https://github.com/larastan/larastan)
-   [rector/rector](https://github.com/rectorphp/rector) and [driftingly/rector-laravel](https://github.com/driftingly/rector-laravel)
-   [filament/filament](https://github.com/filamentphp/filament) (v5) for admin UI
-   [nunomaduro/phpinsights](https://github.com/nunomaduro/phpinsights), [peckphp/peck](https://github.com/peckphp/peck)

### Module metadata

-   **Priority:** `999` in `module.json` (load order consistent with other feature modules)
-   **Namespace:** `Modules\ERP\...`
-   **Composer package:** `swolley/laraplate-erp`
-   **Web / API routes:** `Modules/ERP/routes/web.php`, `Modules/ERP/routes/api.php`

### M0 — Multi-tenancy & Multi-currency Foundation

-   `companies` table with functional currency
-   `BelongsToCompany` trait + global scope for tenant isolation
-   Dual-currency columns via `ERPMigrateUtils::moneyColumns()` (amount_doc / currency_doc / amount_local / fx_rate)
-   `CurrencyConverter` service (no-op in M0, pluggable for live FX)

### M0 — Versioning

-   `HasVersions` trait with `VersionStrategy::DIFF` on accounting models
-   Immutable version strategy on contabile models (cannot be overridden via settings)

### M1 — Accounting Backbone

-   Chart of Accounts (`accounts` table, `Account` model, `ChartOfAccountsProvider` interface, Italian PDC default)
-   Journal Entries (`JournalEntry` / `JournalEntryLine`, `JournalPostingService::post/reverse`, immutability after posting)
-   Fiscal Calendar (`FiscalYear`, `FiscalPeriod`, `FiscalPeriodCloser` with re-open audit)
-   Document Sequences (`DocumentSequence`, `DocumentNumberAllocator`, `DocumentType` enum with gap policy)

### M2 — Tax & Invoice Foundation

-   Tax Codes (`TaxCode`, `TaxKind` enum, immutable fiscal snapshot, `TaxLineCalculator`, `TaxCodeSupersessionService`)
-   `ItalianTaxCodesSeeder` baseline
-   Invoice / InvoiceLine stub with tax snapshot columns

### M3.1 — CRM

-   Leads (`Lead` model, `LeadStatus` enum)
-   Opportunities (`Opportunity`, `OpportunityStatus`, `OpportunityStage` taxonomy)
-   `OpportunityLifecycleService` + `QuotationObserver` (auto-win on quotation accepted)

### M3.2 — Sales Order Processing

-   `SalesOrder` / `SalesOrderLine` with lock-chain progression
-   `SalesOrderEvasionService` (delivery + invoice qty tracking, status management)
-   `SalesOrderAmendmentService` (creates amendment SO from confirmed / partially evaded orders)
-   Lock propagation: confirming SO locks linked Quotation

### M3.3 — Inventory Management

-   Items, Warehouses, StockLevels
-   `StockMovement` / `stock_cost_layers` tables
-   `StockMovementService` with FIFO and weighted-average costing
-   COGS calculation integrated with delivery posting

### M3.4 — Delivery Notes

-   `DeliveryNote` / `DeliveryNoteLine` with SO line linking
-   `DeliveryNoteInventoryService` (stock posting + rollback)
-   `DeliveryNoteCogsJournalService` (COGS journal on posting, full reversal on unpost)
-   Automatic SO qty_delivered updates

### M3.5 — Invoice Posting & DDT Integration

-   `InvoicePostingService` with full journal post / unpost cycle (observer-driven via `posted_at`)
-   `DocumentNumberAllocator` integration at posting time (reference assigned on post, cleared on unpost)
-   Tax snapshot on invoice lines (tax_code, tax_rate, tax_label frozen at posting)
-   SO qty_invoiced tracking via `SalesOrderEvasionService`
-   Pivot `erp_invoice_line_delivery_note_line` for flexible Invoice↔DDT linking, mapped by `Models\Pivot\InvoiceLineHasDeliveryNoteLine` with covered quantity and timestamps
-   `InvoiceDeliveryNoteValidationService` — optional DDT linkage rules at posting (posted DDT, qty caps, SO line consistency)
-   `InvoiceCompactionService` (compact / expand invoice lines by item)
-   Filament **Post** / **Unpost** actions on invoice edit page and list (no manual `posted_at` editing)
-   See `docs/ERP_GUIDA_SEMPLICE.md` §4.7 and `docs/rag/MODULE.md` § Invoice posting workflow for end-to-end flows

### M3.6 — Purchasing Cycle

-   Unified `Party` entity (replaces `Customer`) with `is_customer` / `is_supplier` boolean flags
-   Party type validation on all models (saving callback) + filtered Filament dropdowns
-   `PurchaseOrder` / `PurchaseOrderLine`, `GoodsReceipt` / `GoodsReceiptLine`
-   `ThreeWayMatchService` with configurable price / quantity tolerances (wired in `InvoicePostingService` on purchase posting)
-   `MatchStatus` enum (matched, tolerance, forced, unmatched) persisted on `invoice_lines` with `match_discrepancy` JSON
-   Filament **Force three-way match** checkbox on purchase invoice Post action
-   Per-company settings via `ErpCompanySettings` on `companies.settings` JSON (`erp.three_way_match.*`, default 0%)

### M3+ — Database Lock Triggers

-   MySQL `BEFORE UPDATE` / `BEFORE DELETE` triggers on `quotations` and `sales_orders`
-   Safety net preventing modification of locked records at DB level
-   Coexists with application-level observer locks

### M5.1 — Payment Schedule & Receivables

-   `PaymentTerm` model (rate_lines JSON with days / percent / payment_method)
-   `PaymentScheduleLine` (auto-generated at invoice posting)
-   `Payment` / `PaymentAllocation` (allocation to schedule lines)
-   `PaymentScheduleGeneratorService` (integrated in InvoicePostingService)
-   `PaymentAllocationService` (allocate / deallocate with status tracking)
-   `AgingReportService` (AR / AP aging by 30 / 60 / 90 / 120+ day buckets)
-   `PartyBankAccount`, `PaymentRun`, and `PaymentRunLine` for supplier payment runs
-   `PaymentRunBuilderService` builds draft outbound supplier payment batches from open purchase schedule lines
-   `SepaPain001Exporter` exports approved supplier payment runs as auditable SEPA SCT `pain.001` XML files
-   Payment execution is file generation only: no direct bank/API submission, no CBI/Ri.Ba/SDD in v1

### M5.2 — Credit & Debit Notes

-   `InvoiceType` enum (invoice, credit_note, debit_note)
-   Separate document numbering (SalesCreditNote, PurchaseCreditNote, SalesDebitNote, PurchaseDebitNote)
-   `CreditNoteService` (create CN from posted invoice, line copying, total validation)
-   Inverted journal entries on credit note posting
-   Filament "Create Credit Note" action on invoice page
-   Credit/debit notes are fiscal corrections: natural prices come from source invoice lines, not from orders.

### M5.3 — VAT Registers & Settlement (Italian Compliance)

-   `VatRegisterEntry` with progressive protocol numbers per register / year
-   `VatRegisterService` (auto-registration at invoice posting, one entry per tax code)
-   `VatSettlement` (IVA debito − IVA credito − credito precedente)
-   `VatSettlementService` (compute + confirm with carry-forward)
-   Filament read-only register and settlement pages

### M5.4 — Financial Statements

-   `TrialBalanceService` (debit / credit balance per account at date)
-   `BalanceSheetService` (assets = liabilities + equity + net income)
-   `IncomeStatementService` (revenue − expenses for date range)
-   Filament pages with Blade templates for tabular reports

### E-Invoice Interface

-   `EInvoiceProvider` contract (prepare, submit, validateXml, remoteStatus)
-   `EInvoiceSubmission` model + `EInvoiceSubmissionStatus` enum
-   Stub provider binding and deterministic local submission workflow
-   Filament actions for posted sale invoice submit / status refresh
-   Phase 2C FatturaPA readiness: fiscal schema fields on company/party/invoice, `FatturaPaAnagraphicMapper`, `FatturaPaXmlBuilder`, vendored official FPR12 v1.2.3 XSD resources, and local `fatturapa` driver validation
-   `aruba` driver: production-oriented adapter for Aruba v2-style upload (`dataFile`), notifications polling, optional auth signin, provider callbacks, and conservation availability metadata in `response_payload`
-   `POST /api/v1/erp/einvoice/aruba/callbacks` applies signed provider callbacks when `ERP_EINVOICE_ARUBA_CALLBACK_API_KEY` is configured
-   `erp:einvoice:refresh-statuses` polls open submissions for the configured provider and supports `--company`, `--provider`, `--limit`, and `--dry-run`
-   `erp:health-check --format=table|json` performs read-only installation and operational checks for the default company, chart of accounts, fiscal calendar, domain permissions, current-year sequences, and e-invoice configuration
-   `erp:sequences:audit --company=ID --year=YYYY --format=table|json` cross-checks persisted invoice/order references against sequence formatters, counters, duplicates, and gap policy without repairing data
-   `erp:bank-statements:import --bank-account=ID --path=FILE_OR_DIR --format=auto --dry-run` validates or imports CSV, CAMT.053, and MT940 files idempotently by SHA-256 checksum; `--archive-path` explicitly enables post-import file moves
-   `erp:vat-settlements:compute --company=ID --year=YYYY --period=YYYY-N --dry-run` previews or computes draft VAT settlements for open fiscal periods; confirmed settlements and closed periods are never modified
-   Current runtime is still local by default: the `stub` driver remains default; `fatturapa` generates and validates XML without delivery
-   Remaining go-live work: verify the configured Aruba tenant/contract, callback accreditation/IP allowlist, production credentials, and legal retention obligations with the provider

### M6.1 — Bank Reconciliation

-   `BankAccount`, `BankStatement`, and `BankStatementLine` models
-   Bank statement import from CSV, CAMT.053, and a minimal MT940 transaction subset
-   Manual reconciliation service with ranked payment suggestions
-   Match-with-difference service and Filament workflow for fees, rounding, and write-offs
-   `BankDifferenceJournalService` posts balanced journals against `bank_cash` and the selected difference account
-   `BankStatementLine.difference_journal_entry_id` keeps audit linkage to the accounting adjustment

### M6.2 — Returns Management

-   `ReturnOrder` for customer returns and `SupplierReturn` for supplier returns
-   Customer returns generate/link inbound DDTs for physical stock receipts
-   Supplier returns generate/link outbound DDTs for physical stock shipments
-   DDT lines remain quantity/source-link only; no prices or costs on bolle
-   Return completion tracks returned quantities on source invoice / sales order / purchase order / goods receipt lines
-   Manual follow-up actions create and link credit/debit note drafts; optional setting `erp.returns.auto_create_notes_on_complete` creates NC/ND drafts during return completion
-   Customer return credit notes use `ReturnOrderLine.invoice_line_id` and the source sales `InvoiceLine.unit_price`; `ReturnOrderLine.unit_price` is an optional manual override.
-   Supplier return debit notes use `SupplierReturnLine.invoice_line_id` and the source purchase `InvoiceLine.unit_price`; `purchase_order_line_id` and `goods_receipt_line_id` remain logistics references.
-   Supplier return debit-note creation is blocked when the source purchase invoice line is missing. Returning goods physically can happen from PO/GR, but fiscal correction must reference the purchase invoice.

### M7.1 — Advanced Pricelists & Party Rules

-   `PriceList` / `PriceListItem` with validity windows and Filament resource
-   Each price-list row targets exactly one direct `Item` or one `Activity` taxonomy; direct item prices take precedence and taxonomy prices are the fallback
-   `PartyPriceRule` with percent, fixed, and override discounts
-   `Party::price_rules()` relation and Party Filament relation manager
-   `PriceResolverService` and `InvoiceLinePricingService` apply commercial pricing to quotation / sales order / invoice lines
-   `Activity` is the concrete ERP taxonomy used for activity-based price rules; do not point UI directly at abstract Core `Taxonomy`.

### Integration Outbox

-   Posted invoices, exact/difference payment matches, completed customer returns, and completed supplier returns write durable Core outbox events in the same database transaction.
-   Event types are `erp.invoice.posted`, `erp.payment.matched`, `erp.customer-return.completed`, and `erp.supplier-return.completed`.
-   Core queues publication after commit. Its default publisher is a no-I/O stub; an application transport binding is required for external delivery.

### Spec 2 Phase 2A/2B — Domain Actions & Commercial UX

-   State-aware `ERPModelPolicy` guards domain actions on top of Core CRUD permissions.
-   Domain abilities seeded in `ERPDatabaseSeeder` include posting/unposting, forced posting, e-invoice submit/refresh, quotation unlock, document-sequence reset/reserve, tax-code supersession, and company context switch.
-   Filament edit/list actions call services; they do not implement business mutations inline.
-   Implemented Phase 2B items: Party price rules UI, PriceList resource, quotation unlock, document sequence reset, return line fiscal override contract, and optional auto NC/ND on return complete.
-   Implemented Phase 2B items also include supplier payment runs with SEPA `pain.001` and CBI bonifici exports.
-   Implemented Phase 2B banking items also include bank difference journals, match-with-difference UI, and CAMT.053 / MT940 statement import.
-   Implemented Phase 2B reporting items also include CSV export actions and immutable CSV/PDF snapshot archives for trial balance, balance sheet, and income statement.
-   Implemented Phase 2B operational dashboard items also include Sales Pipeline filters/KPIs/CSV export and Stock Valuation warehouse filter/KPIs/CSV export.

### Supplier Payment Runs

-   Supplier bank coordinates live on `PartyBankAccount`, not on invoices or payment lines.
-   `PaymentRunBuilderService` selects open/partial purchase invoice schedule lines and creates a draft payment run.
-   `PaymentRunLine` stores a beneficiary snapshot: name, IBAN, BIC, amount, due date, and remittance text.
-   Payment runs move through `draft -> approved -> exported`; `cancelled` is available before export.
-   Exported payment runs are immutable and store file name, export timestamp, and SHA-256 checksum.
-   Supported supplier payment file formats are SEPA Credit Transfer `pain.001` and CBI bonifici text export; bank submission remains outside ERP.

### Filament Admin UI

-   **Resources:** Party, Contact, Quotation, Project, Lead, Opportunity, SalesOrder, DeliveryNote, Invoice, PurchaseOrder, GoodsReceipt, ReturnOrder, SupplierReturn, PaymentTerm, Payment, PaymentRun, BankAccount, BankStatement, VatRegister (read-only), VatSettlement (read-only)
-   **Core accounting:** Company, Account, JournalEntry (with view page), FiscalYear, FiscalPeriod, DocumentSequence, TaxCode
-   **Report pages:** Trial Balance, Balance Sheet, Income Statement, Sales Pipeline, Stock Valuation. Financial and operational report pages expose CSV export actions; financial reports can be archived through immutable CSV/PDF snapshots.

### Current ERP Status

| Area | Status | Notes |
| --- | --- | --- |
| M3.6 Purchasing | Implemented / cleanup only | Purchase invoice posting and 3-way match are present; keep regression coverage focused. |
| M4 Permissions & reporting | Implemented v1 + CSV/PDF snapshots | Domain permissions, invoice action auth, accounting/operational reports, financial/operational report CSV exports, immutable report snapshots, and read-only report pages are present; explicit DDT/fiscal-period/journal page actions remain follow-up. |
| M5.1 Payment execution | Implemented v1 + Italian bank exports | Supplier bank coordinates, payment runs, SEPA `pain.001` XML export, CBI bonifici export, checksum metadata, and Filament resource are present. Ri.Ba/SDD generators cover customer receivable schedules; direct bank submission remains backlog. |
| M6.1 Bank reconciliation | Implemented v1 + differences + bank formats | CSV, CAMT.053, and minimal MT940 import, manual match, suggestions, match-with-difference UI, and difference journal entries are present. |
| M6.2 Returns management | Implemented v1 + fiscal override + optional auto notes | Customer/supplier returns, DDT integration, returned-quantity tracking, manual NC/ND follow-up actions, optional auto NC/ND on completion, and invoice-line-based fiscal pricing are present. |
| M6.3 E-invoice stub | Implemented v1 + Phase 2C + Aruba operations | Provider binding, deterministic stub submission workflow, invoice actions, FatturaPA readiness fields, local XML/XSD validation, Aruba upload/polling/callback adapter, and polling command are present. |
| M7.1 Advanced pricelists | Implemented v1 + UI | Direct item and taxonomy fallback prices, validity windows, party rules, percent/fixed/override discounts, resolver, document-line integrations, PriceList resource, and Party price-rule UI are present. |
| Spec 2 Phase 2A | Implemented | Domain actions, state-aware policies, and Filament service-backed actions are present. |
| Spec 2 Phase 2B | Implemented | 2B-01/02/03/04/05/06/07/08/09/10/11/12/13 are done. |
| Spec 2 Phase 2C | Implemented | FatturaPA schema/readiness fields (`2C-05`), SDI/FatturaPA mapping (`2C-02`), FPR12 XML/XSD validation (`2C-01`), Aruba upload/polling/callback adapter (`2C-03`), polling command (`6-03`), and extended admin permissions (`2C-04`) are present. |

### Known Limitations After Phase 2C

-   E-invoice defaults to the deterministic `stub` workflow. The optional `fatturapa` driver generates and XSD-validates ordinary FPR12 XML locally, but it does not deliver to SDI.
-   The optional `aruba` driver now follows the Aruba v2-style upload and notifications polling shape, supports optional auth signin, exposes a signed callback route, and records polling/callback/conservation metadata in `EInvoiceSubmission.response_payload`. It still needs verification against the contracted Aruba tenant before production go-live.
-   FatturaPA / SDI readiness fields now exist on company, party, and invoice records, sale e-invoice submit validates mandatory data, `FatturaPaAnagraphicMapper` maps those fields into a FatturaPA-shaped neutral payload, and `FatturaPaXmlBuilder` generates XML from it. Edge-case fiscal mappings still need dedicated fields and rules.
-   Legal retention is tracked only as provider metadata such as `pddAvailable`; full conservazione governance, retrieval/esibizione workflows, and contract-specific retention checks remain go-live/backlog work.
-   Supplier payment execution exports SEPA SCT `pain.001` and CBI bonifici files. Ri.Ba and SDD CORE generators exist for customer receivable schedule lines, with SDD mandate validation on `PartyBankAccount`. Direct bank/API submission and bank-specific certification remain outside ERP.
-   Bank import supports CSV, CAMT.053, and a minimal MT940 subset; it is not full bank API sync and it does not auto-confirm matches without the reconciliation workflow.
-   Financial report snapshots store immutable payload, CSV, PDF content, hash, and parameters through `erp_report_snapshots`; the built-in PDF renderer is intentionally simple and dependency-free.
-   Multi-currency now has `ExchangeRate`, `DatabaseCurrencyConverter`, and `FxRevaluationService` for historical/inverse FX rates and unrealized revaluation journals on open foreign-currency schedules. External FX feed imports and realized FX settlement automation remain later enhancements.
-   Money math now has a `Money` value object built on `Decimal`; analytic accounting has company dimensions, dimension values, and a journal-line pivot with allocation percentage.
-   Generic domain HTTP actions, opt-in external APIs, and API exposure governance are Phase 3 work.
-   Processed returns can be safely reversed before any linked credit/debit note exists: the generated DDT is unposted, returned quantities are restored, and the return goes back to approved. Linked fiscal notes must be handled explicitly first.
-   DDT/bolle lines intentionally do not carry prices or costs. Fiscal corrections price from source invoice lines, not from DDTs, orders, goods receipts, or current price lists.
-   Reports remain live-query in Filament, but financial report snapshots now archive immutable payload/CSV/simple-PDF rows. Rich paginated PDF design and operational report snapshot scheduling remain enhancements.
-   Multi-currency has database FX rates, direct/inverse conversion, and unrealized revaluation journals for open schedules. External FX feed imports and realized FX automation remain future work.
-   `Money` exists for decimal-safe amount/currency arithmetic, and journal lines support analytic dimensions. Full refactoring of all legacy money helpers and analytic reporting cubes remains future work.
-   Application locks are portable; MySQL DB triggers are an extra safety net for selected lock chains and should not be treated as cross-database enforcement.
-   MES, ETL, calendar/ICS, Gantt planning, mobile API, and Tricount refactor are outside the current ERP slice.

### Roadmap

-   Phase 2C: FatturaPA / SDI production-readiness and extended admin permissions are implemented.
-   Phase 3+: domain HTTP actions, API exposure governance, and later accounting architecture improvements. Safe processed-return reverse is implemented in ERP services/UI before API exposure.

## Scripts

The ERP module exposes the same Composer script conventions as **Cms** and **Core**:

### Code quality and testing

```bash
composer test                 # Full test and quality pipeline
composer test:standalone      # Unit-focused Pest run
composer test:type-coverage   # Type coverage (target: 100%)
composer test:typos           # Peck typo check
composer test:lint            # Pint + Rector dry-run
composer test:types           # PHPStan
composer test:refactor        # Rector
```

### Maintenance

```bash
composer lint                 # Rector, Pint, IDE helpers
composer check                # PHPStan
composer fix                  # PHPStan with fix
composer refactor             # Rector
composer update:requirements  # composer bump + npm-check-updates
```

### Versioning

```bash
composer version:major
composer version:minor
composer version:patch
```

### Hooks

```bash
composer setup:hooks
```

## Repository rename (from laraplate-business)

If you still have a clone using the old remote:

```bash
git remote set-url origin git@github.com:swolley/laraplate-erp.git
# or HTTPS: https://github.com/swolley/laraplate-erp.git
```

In the parent Laraplate app, update `.gitmodules` to `path = Modules/ERP` and `url` / `pushurl` for `laraplate-erp`, then run `git submodule sync` and `composer update`.

## Contributing

If you want to contribute to this project, follow these steps:

1. Fork the repository.
2. Create a new branch for your feature or fix.
3. Open a pull request.

## License

ERP module is open-sourced software licensed under the [GNU AGPL v3](https://www.gnu.org/licenses/agpl-3.0.html).

## TODO

- [x] M6.1 — Bank reconciliation and statement import
- [x] M6.2 — Returns management (customer and supplier)
- [x] M6.2/2B-07 — Invoice-line-based return credit/debit note pricing contract
- [x] M6.2/2B-06 — Optional automatic NC/ND creation on return completion
- [x] M6.3 — E-invoice stub workflow
- [x] M7.1 — Advanced pricelists with party-specific pricing
- [x] M4 — Policies, permissions, and reporting pages
- [x] Spec 2 Phase 2A — State-aware policies and Filament domain actions
- [x] Spec 2 Phase 2B — Party pricing UI, PriceList UI, quotation unlock, document sequence reset, return fiscal override contract, optional auto NC/ND, banking depth, financial CSV export, operational dashboard polish
- [x] Spec 2 Phase 2C — FatturaPA / SDI schema, mapper, XML/XSD validation, configurable Aruba adapter, and extended admin permissions
- [ ] ERP-specific API resources/form requests only where domain invariants cannot be represented safely by Core's existing dynamic CRUD/API; design deliberately deferred
- [x] Comprehensive accounting golden-master tests
- [x] Export CSV for financial reports
