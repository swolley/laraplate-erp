# ERP module — commercial, logistics, and accounting domain

## Purpose

`ERP` delivers operational business workflows in Laraplate: sales, purchasing, inventory, accounting, tax, and fiscal governance.

It models the transactional backbone where business documents, stock movements, and accounting entries must remain consistent.

## Core functional areas

### Master data and CRM-lite

- Companies, customers, contacts, sites.
- Leads/opportunities/stages/activities/tasks.
- Projects linked to commercial lifecycle.

### Order-to-cash

- Quotations and sales orders.
- Delivery notes and outbound inventory posting.
- Invoice generation and submission tracking (where enabled).

### Procure-to-pay

- Purchase orders and inbound goods receipts.
- Inventory updates from receiving pipeline.
- Supplier-side document chaining into accounting.

### Inventory and valuation

- Warehouses, stock levels, stock movements, cost layers.
- Inventory services coordinate stock consistency around document posting events.

### Accounting and fiscal controls

- Chart of accounts and posting services (`JournalEntry`, lines, posting orchestrators).
- Fiscal years and periods, close operations, calendar setup utilities.
- Tax code services (rates, supersession/evolution handling).
- Document numbering/sequence services for traceability and compliance.

## Typical operational flows

### Sales flow

1. Customer and product/list-price preparation.
2. Quotation and conversion to sales order.
3. Delivery note posting updates inventory.
4. Invoicing and accounting posting alignment.

### Purchasing flow

1. Purchase order issuance.
2. Goods receipt posting updates stock.
3. Supplier invoice reconciliation and accounting posting.

### Period-end flow

1. Validate ledger and inventory consistency.
2. Review tax code applicability.
3. Close fiscal period after reconciliation checks.

## Internal architecture notes

- Business transitions should pass through service layer, not ad-hoc controller updates.
- Observers and posting services are the safest extension points for new document states.
- Currency conversion is pluggable; default converter is intentionally minimal.

## Risks and controls

- Inventory/accounting divergence if posting logic is bypassed.
- Fiscal period closure without reconciliation can lock incorrect balances.
- Tax behavior must remain parameterized and version-aware for regulatory changes.

## Dependencies

- Strong dependency on `Core` lifecycle infrastructure (permissions, locks, settings, approvals, versioning).
- Optional `AI` support for assistant/search scenarios.

## FAQ prompts for RAG

- Which service posts inventory when a delivery note is confirmed?
- How do purchase receipts affect stock and accounting?
- Where is fiscal period closing logic implemented?
- How are tax code supersessions handled?
- What is the safe extension pattern for new ERP document states?
- How do document sequences interact with legal/audit requirements?
