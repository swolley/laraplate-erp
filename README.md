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
```

Per-company ERP settings are stored in `companies.settings` (JSON). Use `ErpCompanySettings` to read dotted keys (e.g. `erp.three_way_match.price_tolerance_percent`). The `config/erp.php` file only exposes module metadata.

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

-   `EInvoiceProvider` contract (prepare, submit, remoteStatus)
-   `EInvoiceSubmission` model + `EInvoiceSubmissionStatus` enum
-   Stub provider binding and deterministic local submission workflow
-   Filament actions for posted sale invoice submit / status refresh
-   No production SDI / PEPPOL / FatturaPA provider in the core module

### M6.1 — Bank Reconciliation

-   `BankAccount`, `BankStatement`, and `BankStatementLine` models
-   CSV bank statement import
-   Manual reconciliation service with ranked payment suggestions
-   Minimal Filament reconciliation workflow
-   Difference journal entries for fees, rounding, and write-offs remain a follow-up

### M6.2 — Returns Management

-   `ReturnOrder` for customer returns and `SupplierReturn` for supplier returns
-   Customer returns generate/link inbound DDTs for physical stock receipts
-   Supplier returns generate/link outbound DDTs for physical stock shipments
-   DDT lines remain quantity/source-link only; no prices or costs on bolle
-   Return completion tracks returned quantities on source invoice / sales order / purchase order / goods receipt lines
-   Manual follow-up actions create and link credit/debit note drafts; automatic NC/ND creation remains optional backlog
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
-   Implemented Phase 2B items: Party price rules UI, PriceList resource, quotation unlock, document sequence reset, and return line fiscal override contract.
-   Remaining Phase 2B items: automatic NC/ND on return complete, bank difference journals, match-with-difference UI, CAMT/MT940 import, financial report export UI, and BI/operational dashboard polish.

### Filament Admin UI

-   **Resources:** Party, Contact, Quotation, Project, Lead, Opportunity, SalesOrder, DeliveryNote, Invoice, PurchaseOrder, GoodsReceipt, ReturnOrder, SupplierReturn, PaymentTerm, Payment, BankAccount, BankStatement, VatRegister (read-only), VatSettlement (read-only)
-   **Core accounting:** Company, Account, JournalEntry (with view page), FiscalYear, FiscalPeriod, DocumentSequence, TaxCode
-   **Report pages:** Trial Balance, Balance Sheet, Income Statement, Sales Pipeline, Stock Valuation

### Current ERP Status

| Area | Status | Notes |
| --- | --- | --- |
| M3.6 Purchasing | Implemented / cleanup only | Purchase invoice posting and 3-way match are present; keep regression coverage focused. |
| M4 Permissions & reporting | Implemented v1 | Domain permissions, invoice action auth, accounting/operational reports, and read-only report pages are present; explicit DDT/fiscal-period/journal page actions remain follow-up. |
| M6.1 Bank reconciliation | Implemented v1 | CSV import, manual match, suggestions, and minimal UI are present; difference journal entries remain backlog. |
| M6.2 Returns management | Implemented v1 + fiscal override contract | Customer/supplier returns, DDT integration, returned-quantity tracking, manual NC/ND follow-up actions, and invoice-line-based fiscal pricing are present. |
| M6.3 E-invoice stub | Implemented v1 | Provider binding, deterministic stub submission workflow, and minimal invoice actions are present; full FatturaPA remains optional backlog. |
| M7.1 Advanced pricelists | Implemented v1 + UI | Validity windows, party rules, percent/fixed/override discounts, resolver, document-line integrations, PriceList resource, and Party price-rule UI are present. |
| Spec 2 Phase 2A | Implemented | Domain actions, state-aware policies, and Filament service-backed actions are present. |
| Spec 2 Phase 2B | In progress | 2B-01/02/03/07/08/09 are done; 2B-04/05/06/10/11/12 remain. |

### Roadmap

-   2B-06: automatic NC/ND creation on return `complete()` using the invoice-line fiscal contracts above
-   2B-04/05/10: bank reconciliation difference journals, UI matching with differences, CAMT/MT940 import
-   2B-11/12: financial export UI and BI/operational dashboard polish
-   Phase 2C: full FatturaPA XML/XSD/provider work remains optional backlog
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
- [x] M6.3 — E-invoice stub workflow; full FatturaPA optional backlog
- [x] M7.1 — Advanced pricelists with party-specific pricing
- [x] M4 — Policies, permissions, and reporting pages
- [x] Spec 2 Phase 2A — State-aware policies and Filament domain actions
- [x] Spec 2 Phase 2B partial — Party pricing UI, PriceList UI, quotation unlock, document sequence reset, return fiscal override contract
- [ ] API resources and form requests
- [ ] Comprehensive accounting test plan (golden master)
- [ ] Export CSV/PDF for financial reports
