# Changelog

All notable changes to this project will be documented in this file.

## [unreleased]

### 🚀 Features

- *(filament)* Own the module navigation group in the plugin
- *(erp)* Update module description and add color attribute
- *(factories)* Add CompanyFactory, the tenant root every other factory needs
- *(factories)* Add Party, Item and Warehouse factories
- *(factories)* Add the fiscal frame — year, period and account
- *(factories)* Add the document chain — orders, delivery note, invoice
- *(seeders)* Give the dev seeder a demo company with both commercial flows

### 🚜 Refactor

- *(erp)* Remove IconColumn for 'is_active' from various tables
- *(erp)* Remove unnecessary empty lines in model docblocks
- *(tests)* Build ERP fixtures from factories in two files

### 📚 Documentation

- Describe the factories and the demo dataset

### 🎨 Styling

- Format with the application's Pint configuration
- Apply the module's own mb_str_functions rule

### ⚙️ Miscellaneous Tasks

- The module carries functionality, not the toolchain

## [1.23.3] - 2026-09-09

### 🐛 Bug Fixes

- *(SalesOrder)* Remove redundant comment in property documentation
- *(erp)* [**breaking**] Make the lock guards tell a lease from a freeze

### 🚜 Refactor

- *(erp)* Declare domain permissions instead of seeding a private list
- *(erp)* [**breaking**] Drop posting permissions on the documents that never post
- *(erp)* State on the line itself that fulfilment is not an edit

### 📚 Documentation

- *(erp)* Freeze the model connections map

### 🧪 Testing

- *(erp)* Enable the CRUD API via CrudApiExposure instead of Config::set
- *(erp)* Build policy permission names with PermissionName

## [1.23.2] - 2026-08-27

### 🐛 Bug Fixes

- *(tests)* Expect yellow module badge on erp:import description

## [1.23.1] - 2026-08-26

### 🐛 Bug Fixes

- *(erp)* Restore affinity lifecycle and test/console polish

## [1.23.0] - 2026-08-25

### 🚀 Features

- *(erp)* Add ProductionOrder document type for MES numbering
- *(erp)* Emit SalesOrderConfirmed event on confirmation
- *(import)* Register erp.item and erp.party entities

### 🐛 Bug Fixes

- *(erp)* Stop hiding created_at and updated_at on Contact
- *(erp)* Point payment-allocation m2m relations at the erp_payment_allocations pivot

### 🚜 Refactor

- *(erp)* Streamline DevERPDatabaseSeeder queries and add tests for seeding
- *(erp)* Extend Core TabularPdfExporter in ReportPdfExporter

### 🧪 Testing

- *(import)* Accept optional OutputInterface on importer stubs
- *(erp)* Assert ItalianTaxCodesSeeder actually seeds after ERPDatabaseSeeder

### ⚙️ Miscellaneous Tasks

- *(erp)* Update command descriptions to use a consistent badge icon
- Remove package-lock.json to streamline dependency management

## [1.22.0] - 2026-08-03

### 🚀 Features

- *(erp)* Post partner cash movements
- *(erp)* Add module import entry point
- *(erp)* Ingest external cash movements
- *(erp)* Ingest external expense allocations

### 🐛 Bug Fixes

- *(erp)* Resolve stock movements on owner connection
- *(erp)* Harden external cash imports

### 🚜 Refactor

- *(erp)* Global settings via SeedReconciler, seed dependency for tax codes

### 📚 Documentation

- *(erp)* Document domain actions over HTTP
- *(erp)* Establish point zero baseline
- *(erp)* Document external cash import foundation
- *(changelog)* Add bug fix for resolving source-less stock movements in inventory

### 🧪 Testing

- *(erp)* Drop the permission-denial case from the Filament resource test

## [1.21.0] - 2026-07-31

### 🚀 Features

- *(erp)* Register accounting domain actions
- *(erp)* Register return lifecycle actions and declare the approve override
- *(erp)* Register document sequence and quotation revision actions
- *(erp)* Govern payment, task and bank statement models by policy

### 🐛 Bug Fixes

- *(erp)* Preserve owner context in pricing and audits
- *(erp)* Reject mixed participants before queries
- *(erp)* Preserve active connection in migration sql
- *(erp)* Quote migration sql identifiers
- Bind ERP database helpers to model connections

### 🚜 Refactor

- *(erp)* Build permission names through PermissionName

### 🧪 Testing

- *(erp)* Make diagnostic connections explicit

### ⚙️ Miscellaneous Tasks

- *(phpstan)* Remove unused stub file reference from configuration

## [1.20.7] - 2026-07-29

### 🚜 Refactor

- *(filament)* Wire Core HasForm into ERP form schemas

### ⚙️ Miscellaneous Tasks

- *(erp)* Mark module as laraplate_owned

## [1.20.6] - 2026-07-29

### 🐛 Bug Fixes

- *(console)* Give ERP artisan commands a distinct yellow badge

### 🎨 Styling

- *(erp)* Align center for sortable columns in CompaniesTable and TaxCodesTable; remove unnecessary comments in model files

## [1.20.5] - 2026-07-28

### 🐛 Bug Fixes

- *(erp)* Scope runtime entrypoints to configured connection

## [1.20.4] - 2026-07-28

### 🐛 Bug Fixes

- *(erp)* Preserve connection in model lifecycle queries

## [1.20.3] - 2026-07-28

### 🐛 Bug Fixes

- *(erp)* Propagate connection through runtime helpers

## [1.20.2] - 2026-07-28

### 🐛 Bug Fixes

- *(erp)* Scope direct runtime queries to aggregate connection

## [1.20.1] - 2026-07-28

### 🐛 Bug Fixes

- *(erp)* Preserve payment and return connection affinity

## [1.20.0] - 2026-07-22

### 🚀 Features

- *(erp)* Replace DB transactions with ConnectionScopedTransaction in accounting services

### 📚 Documentation

- *(erp)* Document numbering stress checks

## [1.19.0] - 2026-07-21

### 🚀 Features

- *(erp)* Add operational health check
- *(erp)* Audit document sequence consistency
- *(erp)* Import bank statements in batches
- *(erp)* Compute VAT settlements in batches
- *(erp)* Add item-specific price lists
- *(erp)* Emit transactional integration events
- *(erp)* Post cash movements to journal
- *(erp)* Add journal-only cash movement UI
- *(erp)* Add quotation revisions and project locks
- *(erp)* Enforce document lock chains
- *(erp)* Add partner pool settlements
- *(erp)* Add payment request provider workflow
- *(erp)* Expose canonical places on sites
- *(erp)* Export tasks as calendar events

### 📚 Documentation

- *(erp)* Document operational commands
- *(erp)* Document VAT settlement batches
- *(erp)* Document item-specific pricing
- *(erp)* Document integration outbox
- *(erp)* Define architecture extension vision
- *(erp)* Document journal-backed cash movements
- *(erp)* Document cash movement UI
- *(erp)* Document quotation revisions and project locks
- *(erp)* Document lock-chain enforcement
- *(erp)* Document partner pool settlements
- *(erp)* Document payment request workflow
- *(erp)* Document site place integration
- *(erp)* Document task calendar export
- *(erp)* Clarify immutable version strategies

### 🧪 Testing

- *(erp)* Stress document number allocation

## [1.18.0] - 2026-07-16

### 🚀 Features

- *(erp)* Safely reverse processed returns
- *(erp)* Complete Aruba e-invoice operations
- *(erp)* Add Italian banking exports
- *(erp)* Archive immutable report snapshots
- *(erp)* Add FX rates and revaluation
- *(erp)* Add money value object and analytics

### 📚 Documentation

- *(erp)* Document enterprise backlog delivery

## [1.17.0] - 2026-07-16

### 💼 Other

- Map ERP pivot models

### 🚜 Refactor

- *(erp)* Enhance ERPTables enum with module table utilities and clean up model docblocks

## [1.16.2] - 2026-07-14

### 🧪 Testing

- *(erp)* Introduce FilamentSchemaTestHarness for improved schema testing

## [1.16.1] - 2026-07-13

### 🚜 Refactor

- *(erp)* Optimize invoice posting and stock movement services for performance and clarity

## [1.16.0] - 2026-07-12

### 🚀 Features

- *(erp)* Reconcile bank differences with journals
- *(erp)* Import CAMT and MT940 bank statements
- *(erp)* Export financial reports from Filament
- *(erp)* Polish operational dashboards
- *(erp)* Add fatturapa readiness fields
- *(erp)* Map fatturapa anagraphic payload
- *(erp)* Add fatturapa xml validation
- *(erp)* Add configurable aruba einvoice adapter
- *(erp)* Add extended admin domain permissions
- *(erp)* Update navigation icons for various resources and pages

### 📚 Documentation

- *(erp)* Prepare phase 2c einvoice documentation
- *(erp)* Document current rag limitations
- *(erp)* Mark phase 2c permissions complete

## [1.15.3] - 2026-07-11

### 🚀 Features

- *(erp)* Add supplier payment runs and SEPA export

### 📚 Documentation

- *(erp)* Add supplier payment run backlog

## [1.15.2] - 2026-07-11

### 🚀 Features

- *(erp)* Auto-create return fiscal notes

## [1.15.1] - 2026-07-11

### 🚀 Features

- *(erp)* Add enterprise return fiscal pricing

## [1.15.0] - 2026-07-10

### 🚀 Features

- *(erp)* Add document sequence reset functionality and price rules management

### 🐛 Bug Fixes

- *(erp)* Improve document number allocation under sqlite contention

## [1.14.7] - 2026-07-09

### 🧪 Testing

- *(erp)* Add feature tests for ERPServiceProvider and e-invoice provider binding
- *(erp)* Expand Filament resources and domain action coverage

## [1.14.6] - 2026-07-09

### 🧪 Testing

- *(erp)* Enhance coverage for invoice and journal entry models, add bank statement CSV validation tests

## [1.14.5] - 2026-07-08

### 🚜 Refactor

- *(erp)* Remove TimeEntryObserver and adjust TimeEntry model

### ⚙️ Miscellaneous Tasks

- *(erp)* Normalize PHPDoc spacing in model docblocks

## [1.14.4] - 2026-06-30

### 🧪 Testing

- *(erp)* Raise coverage on models and services above 90%

## [1.14.3] - 2026-06-30

### 🚀 Features

- *(erp)* Add decimal-exact money math helper
- *(erp)* Add single-source decimal line tax to TaxLineCalculator
- *(erp)* Restrict generic-CRUD writes on immutable/derived models

### 🐛 Bug Fixes

- *(erp)* Seed e-invoice domain permissions for invoices
- *(erp)* Block invoice posting into closed fiscal periods

### 🚜 Refactor

- *(erp)* Decimal-exact money math across accounting services

### 🧪 Testing

- *(erp)* Pin ERPModelPolicy authorization semantics
- *(erp)* Cover GR/unmatched three-way match and bank CSV row validation

## [1.14.2] - 2026-06-30

### 🚀 Features

- *(erp)* Add income statement csv export

### 🐛 Bug Fixes

- *(erp)* Correct permission check logic in ERPModelPolicy and improve type checking in ERPDatabaseSeeder

## [1.14.1] - 2026-06-28

### 🚜 Refactor

- *(erp)* Enhance model property annotations and type handling

## [1.14.0] - 2026-06-28

### 🚀 Features

- *(erp)* Add trial balance csv export

### 🚜 Refactor

- *(erp)* Use core tabular csv exporter

## [1.13.2] - 2026-06-27

### 🐛 Bug Fixes

- *(erp)* Harden document number allocation

### 🧪 Testing

- *(erp)* Add accounting golden masters
- *(erp)* Add inventory accounting golden masters
- *(erp)* Lock confirmed vat settlements
- *(erp)* Inline FakeFiscalPeriod and expand FiscalPeriodCloser coverage

## [1.13.1] - 2026-06-23

### 🚀 Features

- *(erp)* Add operational reporting pages

### 🚜 Refactor

- *(erp)* Update Core model concern imports

### 📚 Documentation

- *(erp)* Align module status and returns rag
- *(erp)* Mark e-invoice stub complete
- *(erp)* Clarify roadmap follow-ups

## [1.13.0] - 2026-06-18

### 🚀 Features

- *(docs)* Added swagger documentation definition inside the module
- *(erp)* Add manual return financial follow-up actions

### ⚙️ Miscellaneous Tasks

- *(erp)* Place tracing type cast with domain casts

## [1.12.0] - 2026-06-11

### 🚀 Features

- *(erp)* Enhance delivery note and return order functionalities
- *(erp)* Update validation rules and model properties for quantity fields

## [1.11.0] - 2026-05-30

### 🚀 Features

- *(erp)* Enhance invoice and payment term models with party associations and validation updates

## [1.10.0] - 2026-05-28

### 🚀 Features

- *(erp)* Add bank reconciliation page and enhance financial reporting

## [1.9.0] - 2026-05-28

### 🚀 Features

- *(erp)* Enhance invoice posting workflow with delivery note integration
- *(tests)* Add integration tests for ERP services and models
- *(erp)* Introduce bank account and bank statement resources

## [1.8.1] - 2026-05-17

### 🚀 Features

- *(models)* Implement getPresettableClass method in OpportunityStage model

### ⚙️ Miscellaneous Tasks

- *(models)* Update access modifiers and add type hints for clarity

## [1.8.0] - 2026-05-15

### ⚙️ Miscellaneous Tasks

- Update project configuration and enhance enums

## [1.7.0] - 2026-05-09

### 🚀 Features

- *(erp)* Add migration for invoice_lines table
- *(models)* Enhance FiscalPeriod model with validation and versioning capabilities

### 🚜 Refactor

- *(models)* Rename party methods to clarify roles and update return type annotations

### 📚 Documentation

- *(rules)* Remove deprecated AI module rules and add ERP module context

## [1.6.0] - 2026-05-07

### 🚀 Features

- *(erp)* Add invoice-DDT pivot, compaction, 3-way match, and DB triggers
- *(erp)* Implement M5 financial cycle

### 🚜 Refactor

- *(erp)* [**breaking**] Replace customers with unified parties entity

### 📚 Documentation

- *(erp)* Update module documentation with complete M0-M5 feature coverage

## [1.5.0] - 2026-05-05

### 🚀 Features

- *(erp)* Enhance invoice management with posting, unposting, and amendment features

### 🚜 Refactor

- *(erp)* Consolidate opportunity stages and activities seeding into a single seeder

## [1.4.0] - 2026-05-04

### 🚀 Features

- *(erp)* Implement COGS journal posting for delivery notes and enhance purchase order handling

### ⚙️ Miscellaneous Tasks

- *(models)* Add IDE helper annotations for Item, PurchaseOrder, StockCostLayer, StockLevel, StockMovement, and Warehouse models

## [1.3.0] - 2026-05-01

### 🚀 Features

- *(erp)* Enhance delivery note and goods receipt forms with line items and validation

### ⚙️ Miscellaneous Tasks

- Update version to v1.2.0 and enhance ERP module documentation

## [1.2.0] - 2026-05-01

### 🚀 Features

- *(business)* Add Filament resources for accounting domain
- *(erp)* Add CRM leads and opportunities, sales orders, e-invoice, and Filament resources
- *(erp)* Complete CRM/SO baseline and add M3 foundation resources
- *(erp)* Implement inventory management for stock movements and receipts

### 🚜 Refactor

- [**breaking**] Rename Business module to ERP

## [1.1.0] - 2026-04-28

### 🚀 Features

- Add migrations for quotes and projects, and update Quote model
- Enhance Business module with new Site model and relationships
- Introduce new models and enums for business logic
- Enhance Contact model with user handling and validation rules
- Add BillingMode enum and enhance migrations with comments and constraints
- Enhance models with validation rules and relationships, and add new migrations
- Introduce Preset and Presettable models with relationships and methods
- *(business)* Close MVP pre-accounting layer (time-domain + dev seeders)
- *(business)* Lay multi-tenant ERP foundations (M0-ERP)
- *(accounting)* Chart of accounts and fiscal periods (B3a)
- *(accounting)* Document sequences and journal posting (B3b)
- *(accounting)* Complete M1 journal reversal and document sequence formatting
- *(business)* Tax codes, VAT calculator, invoice stubs, opportunity stages

### 🐛 Bug Fixes

- Remove trailing whitespace in Activity model

### 🚜 Refactor

- Rename CRM module to Business module and update related files
- Update relationships in Contact and Site models, and modify migrations
- Remove redundant method in Preset model

## [1.0.0] - 2026-04-01

<!-- generated by git-cliff -->
