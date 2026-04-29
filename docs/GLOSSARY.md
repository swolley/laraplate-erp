# ERP module glossary

Canonical English names for ERP entities in this module. Use these terms in code, APIs, and cross-module documentation.

## Multi-tenancy

| Term | Meaning |
|------|---------|
| **Company** | Tenant root: fiscal country, default currency (`amount_local` basis), settings. |
| **BelongsToCompany** | Trait + global scope: transactional rows are scoped to the active company when context is set. |

## Taxonomies

The Core table `taxonomies` stores hierarchical catalog data. **Business** discriminates trees with `Modules\ERP\Casts\EntityType` on the related **Entity** record:

| EntityType value | Model (subclass of Taxonomy) | Typical use |
|------------------|------------------------------|-------------|
| `activities` | `Activity` | Work types for tasks, time entries, price list items. |
| `opportunity_stages` | `OpportunityStage` | CRM pipeline stages (M3.1+). |

Leaf nodes hold operational catalog data; they are **not** replaced by enums per vertical. Seeds: `DevERPTaxonomySeeder`, `DevERPOpportunityStagesTaxonomySeeder`.

**Removed:** `EntityType::MOVEMENTS` — cash movement tagging is expressed via **Chart of Accounts** (`JournalEntryLine.account_id`) and optional analytic FKs (e.g. `project_id`, `site_id`).

## Fiscal and tax (M2)

| Term | Meaning |
|------|---------|
| **TaxCode** | One immutable row per `(company_id, code)`: kind (`vat` / `withholding`), `rate`, `country`, `effective_from`, `is_active`, optional `replaced_by_tax_code_id`. |
| **Supersession** | Changing a rate or legal definition creates a **new** row with a **new** `code`; the old row is deactivated and links to the new row. No `UPDATE` to `rate`/`code` on existing rows. |
| **TaxLineCalculator** | Resolves the active `TaxCode` at a posting date and computes VAT-from-net or withholding-from-gross amounts (Brick Math). |
| **Fiscal snapshot** | String columns on `journal_entry_lines` and `invoice_lines`: `tax_code`, `tax_rate`, `tax_label` frozen at posting time. Historical lines never change when master `TaxCode` rows are superseded. |
| **TaxKind** | `vat` — value-added tax on taxable base; `withholding` — e.g. retention on gross (jurisdiction-specific rules extend via `meta` or future strategies). |

Italian baseline codes are seeded by `ItalianTaxCodesSeeder` on the default company.

## Documents and accounting (M1)

| Term | Meaning |
|------|---------|
| **JournalEntry** | Balanced double-entry header; **JournalEntryLine** rows sum to zero on `amount_local`. |
| **JournalPostingService** | Only supported path to post (and **reverse**) journals. Posted rows are immutable from the ORM layer. |
| **DocumentSequence** / **DocumentNumberAllocator** | Per-company, per-type, per-fiscal-year counters with pessimistic lock; optional `format_pattern` / `suffix`. |
| **DocumentType** | Stream key for numbering (quotation, invoices, etc.). `defaultGapAllowed()` encodes whether gaps on transaction rollback are acceptable. |

## Invoices (M2 stub / M3.5 full)

| Term | Meaning |
|------|---------|
| **Invoice** | Minimal header in M2 (`direction` sale/purchase, `currency`, `posted_at`). Full workflow arrives in M3.5. |
| **InvoiceLine** | Commercial line with `quantity`, `unit_price`, optional `tax_code_id`, and **snapshot** columns after posting. |

## Cash / Tricount adapters (M2+)

| Term | Meaning |
|------|---------|
| **MovementType** | Synthetic direction for legacy cash adapters: `income` / `expense`. Mapped to journal lines, not to a separate `EntityType` tree. |

## Related reading

- `EntityType` enum PHPDoc in `app/Casts/EntityType.php`
- `TaxCode` / `TaxLineCalculator` PHPDoc in `app/Models/TaxCode.php` and `app/Services/Taxation/`
