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
-   `ERP_EINVOICE_ARUBA_BASE_URL`: base URL for the contracted Aruba/sandbox API
-   `ERP_EINVOICE_ARUBA_SUBMIT_PATH`: submit endpoint path, default `/einvoices`
-   `ERP_EINVOICE_ARUBA_STATUS_PATH`: status endpoint template, default `/einvoices/{external_id}`
-   `ERP_EINVOICE_ARUBA_TOKEN`: bearer token used by the Aruba adapter

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
-   Pivot `invoice_line_delivery_note_line` for flexible Invoice↔DDT linking
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
-   `aruba` driver: configurable HTTP adapter that sends validated FatturaPA XML to configured endpoints, maps submit ids/status responses, and keeps credentials in Laravel config/env only
-   Current runtime is still local by default: the `stub` driver remains default; `fatturapa` generates and validates XML without delivery
-   Remaining production work: verify the adapter against the contracted Aruba API/environment, advanced SDI statuses/callbacks, legal retention, and extended admin permissions

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
-   `PartyPriceRule` with percent, fixed, and override discounts
-   `Party::price_rules()` relation and Party Filament relation manager
-   `PriceResolverService` and `InvoiceLinePricingService` apply commercial pricing to quotation / sales order / invoice lines
-   `Activity` is the concrete ERP taxonomy used for activity-based price rules; do not point UI directly at abstract Core `Taxonomy`.

### Spec 2 Phase 2A/2B — Domain Actions & Commercial UX

-   State-aware `ERPModelPolicy` guards domain actions on top of Core CRUD permissions.
-   Domain abilities seeded in `ERPDatabaseSeeder` include posting/unposting, forced posting, e-invoice submit/refresh, quotation unlock, and document-sequence reset.
-   Filament edit/list actions call services; they do not implement business mutations inline.
-   Implemented Phase 2B items: Party price rules UI, PriceList resource, quotation unlock, document sequence reset, return line fiscal override contract, and optional auto NC/ND on return complete.
-   Implemented Phase 2B items also include supplier payment runs with SEPA `pain.001` export.
-   Implemented Phase 2B banking items also include bank difference journals, match-with-difference UI, and CAMT.053 / MT940 statement import.
-   Implemented Phase 2B reporting items also include CSV export actions for trial balance, balance sheet, and income statement.
-   Implemented Phase 2B operational dashboard items also include Sales Pipeline filters/KPIs/CSV export and Stock Valuation warehouse filter/KPIs/CSV export.

### Supplier Payment Runs

-   Supplier bank coordinates live on `PartyBankAccount`, not on invoices or payment lines.
-   `PaymentRunBuilderService` selects open/partial purchase invoice schedule lines and creates a draft payment run.
-   `PaymentRunLine` stores a beneficiary snapshot: name, IBAN, BIC, amount, due date, and remittance text.
-   Payment runs move through `draft -> approved -> exported`; `cancelled` is available before export.
-   Exported payment runs are immutable and store file name, export timestamp, and SHA-256 checksum.
-   The first supported bank format is SEPA Credit Transfer `pain.001`; bank submission and Italian/proprietary formats remain backlog.

### Filament Admin UI

-   **Resources:** Party, Contact, Quotation, Project, Lead, Opportunity, SalesOrder, DeliveryNote, Invoice, PurchaseOrder, GoodsReceipt, ReturnOrder, SupplierReturn, PaymentTerm, Payment, PaymentRun, BankAccount, BankStatement, VatRegister (read-only), VatSettlement (read-only)
-   **Core accounting:** Company, Account, JournalEntry (with view page), FiscalYear, FiscalPeriod, DocumentSequence, TaxCode
-   **Report pages:** Trial Balance, Balance Sheet, Income Statement, Sales Pipeline, Stock Valuation. Financial and operational report pages expose CSV export actions.

### Current ERP Status

| Area | Status | Notes |
| --- | --- | --- |
| M3.6 Purchasing | Implemented / cleanup only | Purchase invoice posting and 3-way match are present; keep regression coverage focused. |
| M4 Permissions & reporting | Implemented v1 + CSV export | Domain permissions, invoice action auth, accounting/operational reports, financial/operational report CSV exports, and read-only report pages are present; explicit DDT/fiscal-period/journal page actions remain follow-up. |
| M5.1 Payment execution | Implemented v1 | Supplier bank coordinates, payment runs, SEPA `pain.001` XML export, checksum metadata, and Filament resource are present. Direct bank submission and CBI/Ri.Ba/SDD remain backlog. |
| M6.1 Bank reconciliation | Implemented v1 + differences + bank formats | CSV, CAMT.053, and minimal MT940 import, manual match, suggestions, match-with-difference UI, and difference journal entries are present. |
| M6.2 Returns management | Implemented v1 + fiscal override + optional auto notes | Customer/supplier returns, DDT integration, returned-quantity tracking, manual NC/ND follow-up actions, optional auto NC/ND on completion, and invoice-line-based fiscal pricing are present. |
| M6.3 E-invoice stub | Implemented v1; Phase 2C active | Provider binding, deterministic stub submission workflow, and minimal invoice actions are present. Phase 2C now adds the production FatturaPA / SDI path. |
| M7.1 Advanced pricelists | Implemented v1 + UI | Validity windows, party rules, percent/fixed/override discounts, resolver, document-line integrations, PriceList resource, and Party price-rule UI are present. |
| Spec 2 Phase 2A | Implemented | Domain actions, state-aware policies, and Filament service-backed actions are present. |
| Spec 2 Phase 2B | Implemented | 2B-01/02/03/04/05/06/07/08/09/10/11/12/13 are done. |
| Spec 2 Phase 2C | Active | FatturaPA schema/readiness fields (`2C-05`), SDI/FatturaPA mapping (`2C-02`), FPR12 XML/XSD validation (`2C-01`), and configurable Aruba adapter (`2C-03`) are present. Next: extended permissions. |

### Known Limitations During Phase 2C

-   E-invoice defaults to the deterministic `stub` workflow. The optional `fatturapa` driver generates and XSD-validates ordinary FPR12 XML locally, but it does not deliver to SDI.
-   The optional `aruba` driver is an HTTP adapter with configurable endpoints/token and tested request/status mapping. It still needs verification against the real contracted Aruba API before production use.
-   FatturaPA / SDI readiness fields now exist on company, party, and invoice records, sale e-invoice submit validates mandatory data, `FatturaPaAnagraphicMapper` maps those fields into a FatturaPA-shaped neutral payload, and `FatturaPaXmlBuilder` generates XML from it. Edge-case fiscal mappings still need dedicated fields and rules.
-   Advanced SDI statuses, provider callbacks/polling beyond the basic status endpoint, and legal retention are not implemented yet.
-   Supplier payment execution exports SEPA SCT `pain.001` files only; direct bank submission, CBI, Ri.Ba, SDD, and proprietary Italian tracks are not implemented.
-   Bank import supports CSV, CAMT.053, and a minimal MT940 subset; it is not full bank API sync and it does not auto-confirm matches without the reconciliation workflow.
-   Generic domain HTTP actions, opt-in external APIs, and API exposure governance are Phase 3 work.
-   Processed returns cannot yet be safely reverted; only draft/approved return cancellation is supported.
-   DDT/bolle lines intentionally do not carry prices or costs. Fiscal corrections price from source invoice lines, not from DDTs, orders, goods receipts, or current price lists.
-   Reports are live-query Filament pages with CSV export; PDF export, scheduled snapshots, and immutable report archives are not implemented.
-   Multi-currency is a schema/converter foundation only; real FX rates, revaluation, and realized/unrealized exchange journals remain Phase 5 work.
-   A full domain-wide `Money` value object and analytic journal dimensions are not implemented.
-   Application locks are portable; MySQL DB triggers are an extra safety net for selected lock chains and should not be treated as cross-database enforcement.
-   MES, ETL, calendar/ICS, Gantt planning, mobile API, and Tricount refactor are outside the current ERP slice.

### Roadmap

-   Phase 2C: FatturaPA / SDI production-readiness work is the current implementation slice. Schema/readiness fields, mapper, FPR12 XML/XSD validation, and configurable Aruba adapter are done; next order is extended permissions.
-   Phase 3+: domain HTTP actions, API exposure governance, reverse processed returns, and later accounting architecture improvements

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
- [ ] Spec 2 Phase 2C — FatturaPA / SDI schema, mapper, XML/XSD validation, and configurable Aruba adapter done; extended permissions remain
- [ ] API resources and form requests
- [ ] Comprehensive accounting test plan (golden master)
- [x] Export CSV for financial reports
