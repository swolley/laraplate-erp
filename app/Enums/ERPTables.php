<?php

declare(strict_types=1);

namespace Modules\ERP\Enums;

use Modules\Core\Enums\Concerns\HasModuleTablesUtils;

enum ERPTables: string
{
    use HasModuleTablesUtils;
    
    case Companies = 'erp_companies';
    case Entities = 'erp_entities';
    case Presets = 'erp_presets';
    case Presettables = 'erp_presettables';
    case Accounts = 'erp_accounts';
    case FiscalYears = 'erp_fiscal_years';
    case FiscalPeriods = 'erp_fiscal_periods';
    case DocumentSequences = 'erp_document_sequences';
    case TaxCodes = 'erp_tax_codes';
    case JournalEntries = 'erp_journal_entries';
    case JournalEntryLines = 'erp_journal_entry_lines';
    case Invoices = 'erp_invoices';
    case InvoiceLines = 'erp_invoice_lines';
    case SalesOrders = 'erp_sales_orders';
    case SalesOrderLines = 'erp_sales_order_lines';
    case DeliveryNotes = 'erp_delivery_notes';
    case DeliveryNoteLines = 'erp_delivery_note_lines';
    case Quotations = 'erp_quotations';
    case QuotationItems = 'erp_quotation_items';
    case Projects = 'erp_projects';
    case ProjectLines = 'erp_project_lines';
    case Tasks = 'erp_tasks';
    case TaskLines = 'erp_task_lines';
    case TimeEntries = 'erp_time_entries';
    case TimeEntryLines = 'erp_time_entry_lines';
    case PriceLists = 'erp_price_lists';
    case PriceListLines = 'erp_price_list_lines';
    case PriceListItems = 'erp_price_list_items';
    case PriceListItemLines = 'erp_price_list_item_lines';
    case Parties = 'erp_parties';
    case Contacts = 'erp_contacts';
    case Contactables = 'erp_contactables';
    case Sites = 'erp_sites';
    case Movements = 'erp_movements';
    case PartnerPools = 'erp_partner_pools';
    case PartnerPoolMembers = 'erp_partner_pool_members';
    case MovementAllocations = 'erp_movement_allocations';
    case PoolTransactions = 'erp_pool_transactions';
    case Balances = 'erp_balances';
    case EInvoiceSubmissions = 'erp_e_invoice_submissions';
    case Leads = 'erp_leads';
    case Opportunities = 'erp_opportunities';
    case Items = 'erp_items';
    case Warehouses = 'erp_warehouses';
    case StockLevels = 'erp_stock_levels';
    case PurchaseOrders = 'erp_purchase_orders';
    case GoodsReceipts = 'erp_goods_receipts';
    case StockMovements = 'erp_stock_movements';
    case StockCostLayers = 'erp_stock_cost_layers';
    case PurchaseOrderLines = 'erp_purchase_order_lines';
    case GoodsReceiptLines = 'erp_goods_receipt_lines';
    case InvoiceLineDeliveryNoteLine = 'erp_invoice_line_delivery_note_line';
    case PaymentTerms = 'erp_payment_terms';
    case Payments = 'erp_payments';
    case PaymentScheduleLines = 'erp_payment_schedule_lines';
    case PaymentAllocations = 'erp_payment_allocations';
    case PartyBankAccounts = 'erp_party_bank_accounts';
    case PaymentRuns = 'erp_payment_runs';
    case PaymentRunLines = 'erp_payment_run_lines';
    case VatRegisterEntries = 'erp_vat_register_entries';
    case VatSettlements = 'erp_vat_settlements';
    case BankAccounts = 'erp_bank_accounts';
    case BankStatements = 'erp_bank_statements';
    case BankStatementLines = 'erp_bank_statement_lines';
    case ReturnOrders = 'erp_return_orders';
    case ReturnOrderLines = 'erp_return_order_lines';
    case SupplierReturns = 'erp_supplier_returns';
    case SupplierReturnLines = 'erp_supplier_return_lines';
    case PartyPriceRules = 'erp_party_price_rules';
    case ReportSnapshots = 'erp_report_snapshots';
    case ExchangeRates = 'erp_exchange_rates';
    case AnalyticDimensions = 'erp_analytic_dimensions';
    case AnalyticDimensionValues = 'erp_analytic_dimension_values';
    case JournalEntryLineAnalyticDimensionValue = 'erp_journal_entry_line_analytic_dimension_value';
}
