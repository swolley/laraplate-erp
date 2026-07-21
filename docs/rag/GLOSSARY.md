# ERP module glossary

Canonical English names for ERP entities in this module. Use these terms in code, APIs, and cross-module documentation.

## Multi-tenancy


| Term                 | Meaning                                                                                        |
| -------------------- | ---------------------------------------------------------------------------------------------- |
| **Company**          | Tenant root: fiscal country, default currency (`amount_local` basis), settings.                |
| **BelongsToCompany** | Trait + global scope: transactional rows are scoped to the active company when context is set. |


## Taxonomies

The Core table `taxonomies` stores hierarchical catalog data. **Business** discriminates trees with `Modules\ERP\Casts\EntityType` on the related **Entity** record:


| EntityType value     | Model (subclass of Taxonomy) | Typical use                                           |
| -------------------- | ---------------------------- | ----------------------------------------------------- |
| `activities`         | `Activity`                   | Work types for tasks, time entries, price list items. |
| `opportunity_stages` | `OpportunityStage`           | CRM pipeline stages (M3.1+).                          |


Leaf nodes hold operational catalog data; they are **not** replaced by enums per vertical. Seeds: `DevERPTaxonomySeeder`, `DevERPOpportunityStagesTaxonomySeeder`.

**Removed:** `EntityType::MOVEMENTS` — cash movement tagging is expressed via **Chart of Accounts** (`JournalEntryLine.account_id`) and optional analytic FKs (e.g. `project_id`, `site_id`).

## Fiscal and tax


| Term                  | Meaning                                                                                                                                                                                           |
| --------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **TaxCode**           | One immutable row per `(company_id, code)`: kind (`vat` / `withholding`), `rate`, `country`, `effective_from`, `is_active`, optional `replaced_by_tax_code_id`.                                   |
| **Supersession**      | Changing a rate or legal definition creates a **new** row with a **new** `code`; the old row is deactivated and links to the new row. No `UPDATE` to `rate`/`code` on existing rows.              |
| **TaxLineCalculator** | Resolves the active `TaxCode` at a posting date and computes VAT-from-net or withholding-from-gross amounts (Brick Math).                                                                         |
| **Fiscal snapshot**   | String columns on `journal_entry_lines` and `invoice_lines`: `tax_code`, `tax_rate`, `tax_label` frozen at posting time. Historical lines never change when master `TaxCode` rows are superseded. |
| **TaxKind**           | `vat` — value-added tax on taxable base; `withholding` — e.g. retention on gross (jurisdiction-specific rules extend via `meta` or future strategies).                                            |


Italian baseline codes are seeded by `ItalianTaxCodesSeeder` on the default company.

## Documents and accounting


| Term                                               | Meaning                                                                                                                                  |
| -------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| **JournalEntry**                                   | Balanced double-entry header; **JournalEntryLine** rows sum to zero on `amount_local`.                                                   |
| **JournalPostingService**                          | Only supported path to post (and **reverse**) journals. Posted rows are immutable from the ORM layer.                                    |
| **DocumentSequence** / **DocumentNumberAllocator** | Per-company, per-type, per-fiscal-year counters with pessimistic lock; optional `format_pattern` / `suffix`.                             |
| **DocumentType**                                   | Stream key for numbering (quotation, invoices, etc.). `defaultGapAllowed()` encodes whether gaps on transaction rollback are acceptable. |


## Parties (unified customer/supplier)


| Term                 | Meaning                                                                                                                       |
| -------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| **Party**            | Unified entity replacing `Customer`. Flags `is_customer` / `is_supplier` (both booleans, both can be true). Table: `parties`. |
| **scopeCustomers()** | Scope on `Party` model filtering `is_customer = true`. Used by sales-side Filament dropdowns.                                 |
| **scopeSuppliers()** | Scope on `Party` model filtering `is_supplier = true`. Used by purchase-side Filament dropdowns.                              |


## CRM


| Term                            | Meaning                                                                                                         |
| ------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| **Lead**                        | Early-stage prospect with `LeadStatus` lifecycle (new, contacted, qualified, converted, lost).                  |
| **Opportunity**                 | Qualified deal linked to a party with `OpportunityStatus` (open, won, lost) and taxonomy-based pipeline stages. |
| **OpportunityLifecycleService** | Manages opportunity status transitions.                                                                         |
| **QuotationObserver**           | Auto-marks opportunity as `won` when linked quotation status = accepted.                                        |


## Sales orders


| Term                            | Meaning                                                                                                                                    |
| ------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| **SalesOrder / SalesOrderLine** | Customer order with lock-chain progression. Lines track `qty_ordered`, `qty_delivered`, `qty_invoiced`.                                    |
| **SalesOrderEvasionService**    | Tracks delivery and invoice quantities against SO lines; manages status transitions (draft → confirmed → partially_evased → fully_evased). |
| **SalesOrderAmendmentService**  | Creates a new draft SO from a confirmed/partially-evased SO, cloning only residual qty lines.                                              |


## Inventory


| Term                     | Meaning                                                                                |
| ------------------------ | -------------------------------------------------------------------------------------- |
| **Item**                 | Product or service with SKU and `costing_method` (fifo, weighted_average).             |
| **Warehouse**            | Physical storage location per company.                                                 |
| **StockLevel**           | Current quantity per item+warehouse, derived from movements.                           |
| **StockMovement**        | Inbound or outbound stock change with document reference and cost data.                |
| **StockMovementService** | Handles inbound/outbound postings with FIFO or weighted-average cost layer management. |
| **stock_cost_layers**    | Per-item cost layers for FIFO costing; weighted average recalculated on each inbound.  |


## Delivery notes


| Term                                | Meaning                                                                                             |
| ----------------------------------- | --------------------------------------------------------------------------------------------------- |
| **DeliveryNote / DeliveryNoteLine** | Outbound shipping document (DDT). Lines link to `SalesOrderLine`.                                   |
| **DeliveryNoteInventoryService**    | Posts outbound stock movements on DDT confirmation; creates compensating inbound on rollback.       |
| **DeliveryNoteCogsJournalService**  | Creates COGS journal entry aggregating unit costs from outbound movements; full reversal on unpost. |


## Invoices (full / extended)


| Term                         | Meaning                                                                                                                                                                              |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Invoice**                  | Header with `direction` (sale/purchase), `invoice_type` (invoice, credit_note, debit_note), `currency`, `posted_at`, `reference` (assigned by `DocumentNumberAllocator` at posting). |
| **InvoiceLine**              | Commercial/fiscal line with quantity, `unit_price`, optional tax code, and **snapshot** columns frozen at posting. Purchase lines may link PO/GR and store match status. Return fiscal corrections use invoice lines as price source. |
| **InvoicePostingService**    | Orchestrates posting: journal entry, document numbering, tax snapshot, payment schedule generation, VAT registration, SO evasion tracking, DDT validation (sale), three-way match (purchase). Full reversal on unpost. Triggered by `InvoiceObserver` when `posted_at` changes. |
| **InvoiceObserver**          | On `posted_at` change: calls `InvoicePostingService::post()` or `::unpost()`. Keeps UI actions and domain logic in sync.                                                          |
| **InvoicePostingActions**    | Filament **Post** / **Unpost** actions (edit page + list). Purchase Post modal includes **Force three-way match** checkbox.                                                       |
| **InvoiceDeliveryNoteValidationService** | Optional sale-invoice rules at posting: linked DDT must be posted, pivot qty ≤ line qty, cumulative invoiced qty ≤ delivered qty, SO line consistency.              |
| **InvoiceCompactionService** | Compacts expanded invoice lines (from multiple DDTs) into aggregated lines per item, or expands back.                                                                                |
| **InvoiceType**              | Enum: `invoice`, `credit_note`, `debit_note`. Determines document numbering stream and journal sign.                                                                                 |
| **forceThreeWayMatchOnPosting** | Transient flag on `Invoice` (not persisted). When true, purchase posting calls `ThreeWayMatchService` with `force=true`. Set from Filament or code before posting.          |


## Invoice ↔ DDT linking


| Term                                | Meaning                                                                                                     |
| ----------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| **invoice_line_delivery_note_line** | Pivot table (`erp_invoice_line_delivery_note_line`) linking invoice lines to delivery note lines with covered quantity and timestamps. Mapped by `InvoiceLineHasDeliveryNoteLine`; optional at posting and validated by `InvoiceDeliveryNoteValidationService`. |

## E-invoice / FatturaPA


| Term                         | Meaning                                                                                                                                                         |
| ---------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **EInvoiceProvider**         | Neutral provider contract used by the ERP module for prepare, submit, XML validation, and remote status operations.                                             |
| **EInvoiceSubmission**       | Submission audit row linked to an invoice. Tracks provider code, external id, status, timestamps, and payload metadata.                                          |
| **StubEInvoiceProvider**     | Current default local provider. It is deterministic and test-friendly; it does not contact SDI or generate valid FatturaPA XML.                                  |
| **FatturaPaEInvoiceProvider** | Local FatturaPA provider selected with `erp.einvoice.driver = fatturapa`; it builds and XSD-validates XML but does not send to SDI.                              |
| **FatturaPA**                | Italian electronic invoice XML format. Phase 2C has schema fields, mapper, ordinary FPR12 XML builder, and XSD validation for this format.                       |
| **SDI**                      | Sistema di Interscambio, the Italian interchange system for electronic invoices. ERP stores the data required for provider submission; direct SDI behavior is provider-specific. |
| **Codice Destinatario**      | Recipient routing code used by SDI. It belongs to the party/invoice fiscal data collected by Phase 2C.                                                           |
| **PEC**                      | Certified email address used as an alternate SDI delivery channel. It is part of the FatturaPA/SDI anagraphic data.                                              |
| **Regime Fiscale**           | Italian taxpayer regime code required in FatturaPA sender data.                                                                                                  |
| **FatturaPaAnagraphicMapper** | Phase 2C service mapping `Company`, `Party`, `Invoice`, and invoice lines into FatturaPA-shaped neutral payload data.                                           |
| **FatturaPaXmlBuilder**      | Phase 2C service building ordinary FPR12 FatturaPA XML from the mapped payload and validating it with vendored official XSD resources.                           |
| **ArubaEInvoiceProvider**    | Phase 2C configurable HTTP adapter for Aruba-style submission/status APIs. It sends validated FatturaPA XML to configured endpoints and keeps credentials in Laravel config/env. |


## Purchasing (M3.6)


| Term                                  | Meaning                                                                                                |
| ------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| **PurchaseOrder / PurchaseOrderLine** | Supplier order with document numbering and qty tracking.                                               |
| **GoodsReceipt / GoodsReceiptLine**   | Inbound receiving document; posts stock via `StockMovementService`.                                    |
| **ThreeWayMatchService**              | Validates PO/GR/Invoice line qty/price at purchase posting. Persists `match_status` / `match_discrepancy` on `InvoiceLine`. |
| **ErpCompanySettings**                | Reads per-company keys from `Company.settings` JSON (dot notation). Used for three-way tolerances and invoice generation mode. |
| **MatchStatus**                       | Enum: `matched`, `tolerance`, `forced`, `unmatched`. Written at purchase invoice posting. |
| **erp.three_way_match.***             | Company settings keys: `price_tolerance_percent`, `qty_tolerance_percent` in `companies.settings`. Default 0. |


## Payment schedule & receivables


| Term                                | Meaning                                                                                                     |
| ----------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| **PaymentTerm**                     | Template defining installment rules via `rate_lines` JSON: `[{days, percent, payment_method}]`.             |
| **PaymentScheduleLine**             | Auto-generated at invoice posting: one line per installment with `due_date`, `amount`, `status`.            |
| **Payment**                         | Actual cash receipt or disbursement. Direction: `inbound` (AR) or `outbound` (AP).                          |
| **PaymentAllocation**               | Links a `Payment` to one or more `PaymentScheduleLine` rows; tracks allocated amounts.                      |
| **PaymentScheduleGeneratorService** | Generates schedule lines from `PaymentTerm` at invoice posting; removes them on unpost (if no allocations). |
| **PaymentAllocationService**        | Allocates/deallocates payments to schedule lines; updates line status (open → partial → paid).              |
| **AgingReportService**              | AR/AP aging grouped by party in 30/60/90/120+ day buckets.                                                  |
| **PaymentScheduleStatus**           | Enum: `open`, `partial`, `paid`, `cancelled`.                                                               |
| **PaymentDirection**                | Enum: `inbound`, `outbound`.                                                                                |


## Credit & debit notes


| Term                    | Meaning                                                                                                     |
| ----------------------- | ----------------------------------------------------------------------------------------------------------- |
| **CreditNoteService**   | Creates a credit note from a posted invoice; copies lines, validates total ≤ remaining creditable amount.   |
| **credited_invoice_id** | FK on `invoices` linking a credit/debit note to the original invoice.                                       |
| **Inverted journal**    | Credit notes produce journal entries with flipped debits/credits (negative amounts in `buildJournalLines`). |
| **Fiscal correction price source** | Credit/debit notes default to source invoice-line prices, not order prices. Return-line `unit_price` is an explicit manual override only. |

## Returns management


| Term                              | Meaning                                                                                                   |
| --------------------------------- | --------------------------------------------------------------------------------------------------------- |
| **ReturnOrder**                   | Customer-return workflow header: approval, cancellation, completion, source invoice link, generated DDT link, and manual credit-note follow-up link. |
| **ReturnOrderLine**               | Customer-return line with item, warehouse, quantity, source sales `invoice_line_id`, DDT line link, optional inventory cost, and optional credit-note price override. |
| **SupplierReturn**                | Supplier-return workflow header: approval, cancellation, completion, source purchase order link, generated DDT link, and manual debit-note follow-up link. |
| **SupplierReturnLine**            | Supplier-return line with item, warehouse, quantity, logistics source links (`purchase_order_line_id`, `goods_receipt_line_id`), fiscal source purchase `invoice_line_id`, generated DDT line link, and optional debit-note price override. |
| **CustomerReturnReceiptService**  | Generates/links inbound DDTs for customer returns and updates returned quantities on source invoice / sales order lines. |
| **SupplierReturnShipmentService** | Generates/links outbound DDTs for supplier returns and updates returned quantities on source purchase order / goods receipt lines. |
| **ReturnStatus**                  | Enum: `draft`, `approved`, `processed`, `cancelled`.                                                       |


## VAT registers & settlement (Italian compliance)


| Term                     | Meaning                                                                                                      |
| ------------------------ | ------------------------------------------------------------------------------------------------------------ |
| **VatRegisterEntry**     | One row per tax code per invoice in the VAT register; `protocol_number` is sequential per company/type/year. |
| **VatRegisterService**   | Auto-registers invoices at posting time (called by `InvoicePostingService`); removes entries on unpost.      |
| **VatSettlement**        | Periodic computation: sales VAT − purchase VAT − previous credit = amount due (or carry-forward credit).     |
| **VatSettlementService** | Computes settlement (`compute()`) and confirms with carry-forward logic (`confirm()`).                       |
| **VatRegisterType**      | Enum: `sales`, `purchases`.                                                                                  |
| **VatSettlementStatus**  | Enum: `draft`, `confirmed`.                                                                                  |


## Financial statements


| Term                       | Meaning                                                                                |
| -------------------------- | -------------------------------------------------------------------------------------- |
| **TrialBalanceService**    | Debit/credit balance per account at a given date, derived from posted journal entries. |
| **BalanceSheetService**    | Assets = Liabilities + Equity + Net Income. Uses `TrialBalanceService` data.           |
| **IncomeStatementService** | Revenue − Expenses for a date range.                                                   |


## Lock mechanisms


| Term            | Meaning                                                                                                                 |
| --------------- | ----------------------------------------------------------------------------------------------------------------------- |
| **HasLocks**    | Core trait for application-level record locking on business events.                                                     |
| **DB triggers** | MySQL/MariaDB and PostgreSQL safety nets for locked document headers and sales-order lock chains. Sales-order lines protect commercial fields but allow operational counters/status. |


## Cash / Tricount adapters


| Term             | Meaning                                                                                                                           |
| ---------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| **MovementType** | Synthetic direction for legacy cash adapters: `income` / `expense`. Mapped to journal lines, not to a separate `EntityType` tree. |
| **MovementPostingService** | Idempotently maps a cash `Movement` to one balanced journal. Income uses bank debit/revenue credit; expense uses expense debit/bank credit. |
| **CashBalanceService** | Derives company cash balance by summing posted journal lines on `bank_cash` accounts; it never updates a parallel balance table. |
| **PartnerPool** | Company/currency-scoped group of Core users sharing expenses. It stores membership, not a mutable balance. |
| **MovementAllocation** | One participant's exact owed and paid amounts for an expense; both totals must equal the movement amount. |
| **Pool balance** | Derived participant position (`paid - owed`, adjusted by confirmed transfers), separate from journal-derived company cash. |
| **Settle-up** | Debtor-to-creditor reimbursement suggested and transactionally recorded as a confirmed `PoolTransaction`. |
| **Cash Movements UI** | Filament create/list/detail workflow that posts through `MovementPostingService` atomically and deliberately exposes no edit route for posted movements. |
| **Quotation revision** | New draft commercial snapshot linked to its immediate predecessor by unique `revises_quotation_id`; lines are copied and branching is prohibited. |
| **Project bind lock** | Automatic project lock when a linked sales order becomes operational; ORM and Filament prevent business updates/deletion. |

## Known limitation terms


| Term | Meaning |
| ---- | ------- |
| **Stub e-invoice** | Current implemented e-invoice mode. It records submissions and statuses locally; it is not legal FatturaPA delivery. |
| **Phase 2C e-invoice scope** | Implemented production-readiness slice: schema/readiness fields, mapper, FPR12 XML builder, XSD validation, Aruba upload/polling/callback adapter, polling command, and extended permissions. Remaining work is contracted Aruba go-live verification and full legal retention governance. |
| **ReportSnapshot** | Immutable archive row for generated ERP financial reports. Stores parameters, payload, CSV content, base64 PDF content, content hash, and generated timestamp. |
| **ExchangeRate** | Historical FX rate row used by `DatabaseCurrencyConverter`; supports direct and inverse currency conversion as of a given date. |
| **Money** | Immutable amount/currency value object using ERP `Decimal` math for same-currency add/subtract/multiply/allocation. |
| **AnalyticDimension** | Company-owned analytic axis, such as cost center or profit center. Values attach to journal entry lines through a pivot with allocation percentage. |
| **FxRevaluationService** | Posts balanced unrealized FX revaluation journals for open foreign-currency payment schedules. |
| **ERP health check** | Read-only `erp:health-check` command that reports installation, accounting setup, permissions, sequence, and e-invoice configuration health in table or JSON format. |
| **Document sequence audit** | Read-only `erp:sequences:audit` command that compares invoice/order references with configured sequence formatters, counters, duplicates, and gap policy. It reports but never repairs inconsistencies. |
| **Bank statement batch import** | `erp:bank-statements:import` command for file/directory CSV, CAMT.053, and MT940 ingestion. SHA-256 per-account idempotency prevents duplicates; dry-run does not persist, and archiving requires an explicit path. |
| **VAT settlement batch** | `erp:vat-settlements:compute` command that previews or persists draft liquidazioni IVA for open fiscal periods. It skips confirmed settlements and never confirms or pays them. |
| **Extended admin domain permissions** | Seeded Phase 2C abilities for high-risk admin operations: `default.erp_tax_codes.supersede`, `default.erp_companies.switch_context`, and `default.erp_document_sequences.reserve`. |
| **Payment file only** | ERP generates bank files but does not submit them to a bank API. Supplier payment runs support SEPA `pain.001` and CBI bonifici; receivable services generate Ri.Ba and SDD CORE files. |
| **CBI bonifici** | Italian fixed-record bank transfer export generated from approved supplier `PaymentRun` records and audited through export checksum metadata. |
| **Ri.Ba / SDD CORE** | Customer receivable bank-file generators. Ri.Ba exports text records; SDD exports SEPA `pain.008` XML and requires mandate data on `PartyBankAccount`. |
| **Minimal MT940** | Only the implemented transaction subset is parsed. Do not treat MT940 support as full-format coverage. |
| **Processed-return reverse** | Safe service/UI action that reverses a processed return before linked fiscal notes exist: unposts the generated DDT, restores source returned quantities, clears `processed_at`, and returns the document to `approved`. |
| **No DDT pricing** | Delivery note lines intentionally have no prices or costs. Fiscal correction pricing comes from invoice lines. |
| **Live report** | Report data is queried live from accounting/operational tables. CSV export exists; financial report snapshots also archive immutable payload/CSV/simple-PDF content. |
| **FX rates and revaluation** | Database FX rates, direct/inverse conversion, and unrealized revaluation journals for open schedules are implemented; external feed imports and realized FX automation remain future work. |
| **Money value object** | `Money` provides decimal-safe amount/currency arithmetic and allocation. Some legacy services still use lower-level decimal helpers. |
| **Analytic dimensions** | Journal lines support analytic dimension values through a first-class pivot with allocation percentage; analytic reporting cubes remain future work. |
| **Direct item price** | A `PriceListItem` targeting one concrete ERP item. It takes precedence over an active taxonomy price; party rules are applied after that base price is selected. A price-list row must target exactly one item or one taxonomy. |
| **ERP integration outbox** | Transactional Core events emitted by posted invoices, matched payments, and completed returns. External delivery requires replacing Core's default no-I/O `OutboxPublisher`. |
| **ERP extension point** | Explicit service-container contract for chart of accounts, FX conversion, e-invoicing, or Core outbox publication. ERP does not currently provide tagged plugin discovery. |
| **App-lock portability** | Application guards enforce lock chains on every supported database. MySQL/MariaDB and PostgreSQL additionally enforce them with triggers; SQLite/Oracle use the application fallback. |
| **Default permission connection** | Models without explicit `$connection` correctly use the default connection for permission naming and lookup. This is not a bug. |
| **Out-of-scope verticals** | MES, Gantt planning, calendar/ICS, mobile API, and ETL legacy are outside the current ERP slice. External pool-payment execution remains separate. |


## Related reading

- `EntityType` enum PHPDoc in `app/Casts/EntityType.php`
- `TaxCode` / `TaxLineCalculator` PHPDoc in `app/Models/TaxCode.php` and `app/Services/Taxation/`
- `InvoicePostingService` in `app/Services/Accounting/InvoicePostingService.php` (central orchestrator)
- `CreditNoteService` in `app/Services/Accounting/CreditNoteService.php`
- `VatRegisterService` / `VatSettlementService` in `app/Services/Accounting/`
- `PaymentScheduleGeneratorService` / `PaymentAllocationService` / `AgingReportService` in `app/Services/Payments/`
- `TrialBalanceService` / `BalanceSheetService` / `IncomeStatementService` in `app/Services/Reporting/`
