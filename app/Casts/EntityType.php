<?php

declare(strict_types=1);

namespace Modules\Business\Casts;

use Modules\Core\Contracts\IDynamicEntityTypable;

/**
 * Selects which Business-owned taxonomy tree rows in `taxonomies` belong to.
 * Described in this module's `docs/GLOSSARY.md` under Taxonomies.
 */
enum EntityType: string implements IDynamicEntityTypable
{
    /**
     * Work-log and list/catalog activities ({@see Activity} / `taxonomies`).
     */
    case ACTIVITIES = 'activities';

    /**
     * CRM pipeline stages ({@see OpportunityStage} / `taxonomies`) for opportunities.
     */
    case OPPORTUNITY_STAGES = 'opportunity_stages';

    /**
     * Get all values as array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if value is valid.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Get validation rules for Laravel.
     */
    public static function validationRule(): string
    {
        return 'in:' . implode(',', self::values());
    }

    public function toScalar(): string
    {
        return $this->value;
    }
}
