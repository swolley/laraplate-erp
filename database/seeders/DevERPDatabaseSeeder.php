<?php

declare(strict_types=1);

namespace Modules\ERP\Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Core\Models\Translations\TaxonomyTranslation;
use Modules\Core\Overrides\Seeder;
use Modules\ERP\Casts\DeliveryNoteDirection;
use Modules\ERP\Casts\EntityType;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Casts\PurchaseOrderStatus;
use Modules\ERP\Casts\SalesOrderLineStatus;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Models\Activity;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\DeliveryNoteLine;
use Modules\ERP\Models\Entity;
use Modules\ERP\Models\GoodsReceipt;
use Modules\ERP\Models\GoodsReceiptLine;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\InvoiceLine;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\OpportunityStage;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\Pivot\Presettable;
use Modules\ERP\Models\PurchaseOrder;
use Modules\ERP\Models\PurchaseOrderLine;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SalesOrderLine;
use Modules\ERP\Models\Warehouse;
use Modules\ERP\Support\ConnectionScopedTransaction;

/**
 * Dev fixture: CRM taxonomies ({@see EntityType::OpportunityStages}) plus one demo company
 * carrying both commercial flows end to end.
 *
 * Every row is keyed on a stable identifier so a second run changes nothing, and so a test, an
 * evaluation or a screenshot can anchor on a known record the way SAO work anchors on `SAO-1`.
 */
final class DevERPDatabaseSeeder extends Seeder
{
    public const DEMO_COMPANY_SLUG = 'demo-erp';

    private const DEMO_CUSTOMER_TAX_ID = '01234567890';

    private const DEMO_SUPPLIER_TAX_ID = '09876543210';

    private const DEMO_WAREHOUSE_CODE = 'DEMO-WH';

    /**
     * @var list<array{sku: string, name: string, price: float}>
     */
    private const DEMO_ITEMS = [
        ['sku' => 'DEMO-ITEM-001', 'name' => 'Licenza software annuale', 'price' => 1200.00],
        ['sku' => 'DEMO-ITEM-002', 'name' => 'Giornata di consulenza', 'price' => 650.00],
        ['sku' => 'DEMO-ITEM-003', 'name' => 'Canone assistenza mensile', 'price' => 180.00],
    ];

    public function run(): void
    {
        $entity_model = new Entity;
        $presettable_model = new Presettable;
        $stage_model = new OpportunityStage;
        $activity_model = new Activity;
        $translation_model = new TaxonomyTranslation;
        ConnectionScopedTransaction::connection(
            $entity_model,
            $presettable_model,
            $stage_model,
            $activity_model,
            $translation_model,
        );

        Model::unguarded(function () use ($entity_model): void {
            $entity_model->getConnection()->transaction(function (): void {
                $this->seedOpportunityStages();
                $this->seedActivities();
                $this->seedDemoDataset();
            });
        });
    }

    private function seedOpportunityStages(): void
    {
        $this->logOperation(OpportunityStage::class);

        /**
         * @var array<int, array{slug: string, it: string, en: string}> $stages
         */
        $stages = [
            ['slug' => 'opp-new', 'it' => 'Nuovo', 'en' => 'New'],
            ['slug' => 'opp-qualified', 'it' => 'Qualificata', 'en' => 'Qualified'],
            ['slug' => 'opp-proposal', 'it' => 'Proposta', 'en' => 'Proposal'],
            ['slug' => 'opp-won', 'it' => 'Vinta', 'en' => 'Won'],
            ['slug' => 'opp-lost', 'it' => 'Persa', 'en' => 'Lost'],
        ];

        $entity_id = Entity::query()
            ->withoutGlobalScopes()
            ->where('name', 'opportunity_stage')
            ->where('type', EntityType::OpportunityStages->value)
            ->value('id');

        if (! $entity_id) {
            $this->command?->warn('Skipping opportunity stages: Entity "opportunity_stage" not found. Run ERPDatabaseSeeder first.');

            return;
        }

        $presettable_id = Presettable::query()
            ->withoutEagerLoads()
            ->where('entity_id', $entity_id)
            ->whereNull('deleted_at')
            ->latest('id')
            ->value('id');

        if (! $presettable_id) {
            $this->command?->warn('Skipping opportunity stages: no active presettable for entity.');

            return;
        }

        $existing_slugs = TaxonomyTranslation::query()
            ->whereIn('slug', array_column($stages, 'slug'))
            ->pluck('slug')
            ->all();

        foreach ($stages as $node) {
            if (in_array($node['slug'], $existing_slugs, true)) {
                $this->command?->line("    - opportunity stage {$node['slug']} already exists");

                continue;
            }

            $stage = OpportunityStage::query()->forceCreate([
                'parent_id' => null,
                'presettable_id' => $presettable_id,
                'entity_id' => $entity_id,
            ]);

            foreach (['it', 'en'] as $locale) {
                TaxonomyTranslation::query()->forceCreate([
                    'taxonomy_id' => $stage->id,
                    'locale' => $locale,
                    'name' => $node[$locale],
                    'slug' => Str::slug($node['slug']),
                    'components' => [],
                ]);
            }

            $this->command?->line("    - opportunity stage {$node['slug']} <fg=green>created</>");
        }
    }

    private function seedActivities(): void
    {
        $activity_model = new Activity;
        $translation_model = new TaxonomyTranslation;

        if (! $activity_model->getConnection()->getSchemaBuilder()->hasTable($activity_model->getTable())
            || ! $translation_model->getConnection()->getSchemaBuilder()->hasTable($translation_model->getTable())) {
            $this->command?->warn('Skipping activities: taxonomies tables are missing.');

            return;
        }

        $this->logOperation(Activity::class);

        /**
         * @var array<int, array{slug: string, it: string, en: string}> $activities
         */
        $activities = [
            ['slug' => 'software-development', 'it' => 'Sviluppo software',     'en' => 'Software development'],
            ['slug' => 'consulting',           'it' => 'Consulenza',            'en' => 'Consulting'],
            ['slug' => 'project-management',   'it' => 'Project management',    'en' => 'Project management'],
            ['slug' => 'support',              'it' => 'Supporto',              'en' => 'Support'],
            ['slug' => 'training',             'it' => 'Formazione',            'en' => 'Training'],
        ];

        $entity_id = Entity::query()
            ->withoutGlobalScopes()
            ->where('name', 'activity')
            ->where('type', EntityType::Activities->value)
            ->value('id');

        if (! $entity_id) {
            $this->command?->warn('Skipping activities: Entity "activity" not found. Run ERPDatabaseSeeder first.');

            return;
        }

        $presettable_id = Presettable::query()
            ->withoutEagerLoads()
            ->where('entity_id', $entity_id)
            ->whereNull('deleted_at')
            ->latest('id')
            ->value('id');

        if (! $presettable_id) {
            $this->command?->warn('Skipping activities: no active presettable for Entity "activity".');

            return;
        }

        $existing_slugs = TaxonomyTranslation::query()
            ->whereIn('slug', array_column($activities, 'slug'))
            ->pluck('slug')
            ->all();

        foreach ($activities as $node) {
            if (in_array($node['slug'], $existing_slugs, true)) {
                $this->command?->line("    - {$node['slug']} already exists");

                continue;
            }

            $activity = Activity::query()->forceCreate([
                'parent_id' => null,
                'presettable_id' => $presettable_id,
                'entity_id' => $entity_id,
            ]);

            foreach (['it', 'en'] as $locale) {
                TaxonomyTranslation::query()->forceCreate([
                    'taxonomy_id' => $activity->id,
                    'locale' => $locale,
                    'name' => $node[$locale],
                    'slug' => Str::slug($node['slug']),
                    'components' => [],
                ]);
            }

            $this->command?->line("    - {$node['slug']} <fg=green>created</>");
        }
    }

    /**
     * One company carrying both commercial flows: sales order to delivery note to invoice, and
     * purchase order to goods receipt to invoice.
     *
     * Documents keep `reference` null. The number belongs to `DocumentNumberAllocator` and is
     * allocated at posting time; a seeder that wrote one would put a fabricated number in front of
     * whoever opens the panel to learn how numbering works.
     */
    private function seedDemoDataset(): void
    {
        $this->logOperation(Company::class);

        $company = Company::query()->firstOrCreate(
            ['slug' => self::DEMO_COMPANY_SLUG],
            [
                'name' => 'Demo ERP',
                'legal_name' => 'Demo ERP S.r.l.',
                'tax_id' => '11223344556',
                'fiscal_country' => 'IT',
                'default_currency' => 'EUR',
            ],
        );

        $customer = Party::query()->firstOrCreate(
            ['company_id' => $company->id, 'tax_id' => self::DEMO_CUSTOMER_TAX_ID],
            [
                'name' => 'Cliente Demo S.p.A.',
                'fiscal_country' => 'IT',
                'city' => 'Milano',
                'country' => 'IT',
                'is_customer' => true,
                'is_supplier' => false,
            ],
        );

        $supplier = Party::query()->firstOrCreate(
            ['company_id' => $company->id, 'tax_id' => self::DEMO_SUPPLIER_TAX_ID],
            [
                'name' => 'Fornitore Demo S.r.l.',
                'fiscal_country' => 'IT',
                'city' => 'Torino',
                'country' => 'IT',
                'is_customer' => false,
                'is_supplier' => true,
            ],
        );

        $warehouse = Warehouse::query()->firstOrCreate(
            ['company_id' => $company->id, 'code' => self::DEMO_WAREHOUSE_CODE],
            ['name' => 'Magazzino Demo'],
        );

        $items = [];

        foreach (self::DEMO_ITEMS as $demo_item) {
            $items[] = Item::query()->firstOrCreate(
                ['company_id' => $company->id, 'sku' => $demo_item['sku']],
                [
                    'name' => $demo_item['name'],
                    'uom' => 'unit',
                    'costing_method' => 'fifo',
                ],
            );
        }

        $this->seedSalesFlow($company, $customer, $warehouse, $items);
        $this->seedPurchaseFlow($company, $supplier, $warehouse, $items);
    }

    /**
     * @param  list<Item>  $items
     */
    private function seedSalesFlow(Company $company, Party $customer, Warehouse $warehouse, array $items): void
    {
        $order = SalesOrder::query()->firstOrCreate(
            ['company_id' => $company->id, 'party_id' => $customer->id, 'notes' => 'Demo sales flow'],
            ['currency' => 'EUR', 'status' => SalesOrderStatus::Confirmed->value],
        );

        if ($order->lines()->count() === 0) {
            foreach (array_slice($items, 0, 2) as $item) {
                SalesOrderLine::query()->create([
                    'sales_order_id' => $order->id,
                    'item_id' => $item->id,
                    'name' => $item->name,
                    'qty_ordered' => 2,
                    'qty_delivered' => 0,
                    'qty_invoiced' => 0,
                    'qty_returned' => 0,
                    'unit_price' => $this->priceFor($item),
                    'status' => SalesOrderLineStatus::Open->value,
                ]);
            }
        }

        $delivery_note = DeliveryNote::query()->firstOrCreate(
            ['company_id' => $company->id, 'sales_order_id' => $order->id],
            [
                'direction' => DeliveryNoteDirection::Outbound->value,
                'delivered_at' => now(),
                'notes' => 'Demo outbound delivery',
            ],
        );

        if ($delivery_note->lines()->count() === 0) {
            foreach ($order->lines()->get() as $line) {
                DeliveryNoteLine::query()->create([
                    'company_id' => $company->id,
                    'delivery_note_id' => $delivery_note->id,
                    'item_id' => $line->item_id,
                    'warehouse_id' => $warehouse->id,
                    'quantity' => $line->qty_ordered,
                    'sales_order_line_id' => $line->id,
                ]);
            }
        }

        $this->seedInvoice($company, $customer, InvoiceDirection::Sale, 'Demo sales invoice', $order->lines()->get());
    }

    /**
     * @param  list<Item>  $items
     */
    private function seedPurchaseFlow(Company $company, Party $supplier, Warehouse $warehouse, array $items): void
    {
        $order = PurchaseOrder::query()->firstOrCreate(
            ['company_id' => $company->id, 'party_id' => $supplier->id],
            [
                'currency' => 'EUR',
                'status' => PurchaseOrderStatus::Confirmed->value,
                'ordered_at' => now(),
            ],
        );

        if ($order->lines()->count() === 0) {
            foreach (array_slice($items, 1, 2) as $item) {
                PurchaseOrderLine::query()->create([
                    'purchase_order_id' => $order->id,
                    'item_id' => $item->id,
                    'name' => $item->name,
                    'qty_ordered' => 5,
                    'qty_received' => 0,
                    'qty_returned' => 0,
                    'unit_price' => round($this->priceFor($item) * 0.6, 2),
                ]);
            }
        }

        $receipt = GoodsReceipt::query()->firstOrCreate(
            ['company_id' => $company->id, 'purchase_order_id' => $order->id],
            ['received_at' => now(), 'notes' => 'Demo goods receipt'],
        );

        if ($receipt->lines()->count() === 0) {
            foreach ($order->lines()->get() as $line) {
                GoodsReceiptLine::query()->create([
                    'company_id' => $company->id,
                    'goods_receipt_id' => $receipt->id,
                    'item_id' => $line->item_id,
                    'warehouse_id' => $warehouse->id,
                    'quantity' => $line->qty_ordered,
                    'qty_returned' => 0,
                    'unit_cost' => $line->unit_price,
                    'purchase_order_line_id' => $line->id,
                ]);
            }
        }

        $this->seedInvoice($company, $supplier, InvoiceDirection::Purchase, 'Demo purchase invoice', $order->lines()->get());
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>  $source_lines
     */
    private function seedInvoice(Company $company, Party $party, InvoiceDirection $direction, string $notes, $source_lines): void
    {
        $invoice = Invoice::query()->firstOrCreate(
            ['company_id' => $company->id, 'party_id' => $party->id, 'direction' => $direction->value],
            [
                'invoice_type' => InvoiceType::Invoice->value,
                'currency' => 'EUR',
                'notes' => $notes,
            ],
        );

        if ($invoice->lines()->count() > 0) {
            return;
        }

        $line_no = 1;

        foreach ($source_lines as $line) {
            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
                'line_no' => $line_no++,
                'description' => $line->name,
                'quantity' => $line->qty_ordered,
                'qty_returned' => 0,
                'unit_price' => $line->unit_price,
            ]);
        }
    }

    private function priceFor(Item $item): float
    {
        foreach (self::DEMO_ITEMS as $demo_item) {
            if ($demo_item['sku'] === $item->sku) {
                return $demo_item['price'];
            }
        }

        return 100.00;
    }
}
