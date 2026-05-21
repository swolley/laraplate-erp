<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Company;

use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Models\Setting;
use Modules\Core\Services\PerModelSettingResolver;
use Modules\ERP\Models\Company;

/**
 * Reads ERP settings with resolution order:
 * company JSON ({@see Company::$settings}) → global {@see Setting} row → code defaults.
 *
 * Keys use dot notation, e.g. `erp.three_way_match.price_tolerance_percent`.
 */
final class ErpCompanySettings
{
    public const string PRICE_TOLERANCE_PERCENT = 'erp.three_way_match.price_tolerance_percent';

    public const string QTY_TOLERANCE_PERCENT = 'erp.three_way_match.qty_tolerance_percent';

    public const string INVOICE_GENERATION_MODE = 'erp.invoice_generation_mode';

    public const string INVOICE_GENERATION_MODE_EXPANDED = 'expanded';

    public const string INVOICE_GENERATION_MODE_COMPACT = 'compact';

    public const string GLOBAL_SETTINGS_GROUP = 'erp';

    public function __construct(
        private readonly PerModelSettingResolver $settings,
    ) {}

    /**
     * Default ERP settings seeded for new companies and backfilled when missing.
     *
     * @return array<string, mixed>
     */
    public static function defaultSettings(): array
    {
        return [
            'erp' => [
                'three_way_match' => [
                    'price_tolerance_percent' => 0,
                    'qty_tolerance_percent' => 0,
                ],
                'invoice_generation_mode' => self::INVOICE_GENERATION_MODE_EXPANDED,
            ],
        ];
    }

    /**
     * Global {@see Setting} rows (group {@see GLOBAL_SETTINGS_GROUP}) aligned with {@see defaultSettings()}.
     *
     * @return array<int, array{name: string, value: mixed, type: SettingTypeEnum, group_name: string, description: string}>
     */
    public static function globalSettingDefinitions(): array
    {
        return [
            [
                'name' => self::PRICE_TOLERANCE_PERCENT,
                'value' => 0,
                'type' => SettingTypeEnum::Float,
                'group_name' => self::GLOBAL_SETTINGS_GROUP,
                'description' => 'Three-way match price tolerance (percent)',
            ],
            [
                'name' => self::QTY_TOLERANCE_PERCENT,
                'value' => 0,
                'type' => SettingTypeEnum::Float,
                'group_name' => self::GLOBAL_SETTINGS_GROUP,
                'description' => 'Three-way match quantity tolerance (percent)',
            ],
            [
                'name' => self::INVOICE_GENERATION_MODE,
                'value' => self::INVOICE_GENERATION_MODE_EXPANDED,
                'type' => SettingTypeEnum::String,
                'group_name' => self::GLOBAL_SETTINGS_GROUP,
                'description' => 'Invoice line generation mode (expanded or compact)',
            ],
        ];
    }

    /**
     * Merge {@see defaultSettings()} under existing company settings (existing values win).
     *
     * @return array<string, mixed>
     */
    public function mergeWithDefaults(Company $company): array
    {
        $current = is_array($company->settings) ? $company->settings : [];

        return array_replace_recursive(self::defaultSettings(), $current);
    }

    public function get(Company $company, string $key, mixed $default = null): mixed
    {
        $settings = $company->settings;

        if (is_array($settings)) {
            $company_value = data_get($settings, $key);

            if ($company_value !== null) {
                return $company_value;
            }
        }

        $global_value = $this->settings->value($key, null);

        if ($global_value !== null) {
            return $global_value;
        }

        $code_default = data_get(self::defaultSettings(), $key, $default);

        return $code_default ?? $default;
    }

    public function float(Company $company, string $key, float $default = 0.0): float
    {
        return (float) $this->get($company, $key, $default);
    }

    public function priceTolerancePercent(Company $company): float
    {
        return $this->float($company, self::PRICE_TOLERANCE_PERCENT, 0.0);
    }

    public function qtyTolerancePercent(Company $company): float
    {
        return $this->float($company, self::QTY_TOLERANCE_PERCENT, 0.0);
    }

    public function invoiceGenerationMode(Company $company): string
    {
        $mode = $this->get($company, self::INVOICE_GENERATION_MODE, self::INVOICE_GENERATION_MODE_EXPANDED);

        return in_array($mode, [self::INVOICE_GENERATION_MODE_EXPANDED, self::INVOICE_GENERATION_MODE_COMPACT], true)
            ? (string) $mode
            : self::INVOICE_GENERATION_MODE_EXPANDED;
    }
}
