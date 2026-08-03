<?php

declare(strict_types=1);

namespace Modules\ERP\Tests\Stubs\Import;

use Illuminate\Database\ConnectionInterface;
use Modules\Core\Import\Contracts\ConnectionAwareBulkImporterInterface;
use Modules\ERP\Import\Contracts\BulkImporterInterface;

final class SuccessfulErpImporter implements BulkImporterInterface, ConnectionAwareBulkImporterInterface
{
    /** @var array<string, mixed> */
    public static array $lastArguments = [];

    public function __construct(
        public readonly string $records = '0',
        public readonly bool $dryRun = false,
        public readonly ?int $limit = null,
        public readonly ?string $connectionName = null,
    ) {}

    public function import(): int
    {
        self::$lastArguments = [
            'records' => $this->records,
            'dryRun' => $this->dryRun,
            'limit' => $this->limit,
            'connectionName' => $this->connectionName,
        ];
        $total = max(0, (int) $this->records);

        if ($this->limit !== null && $this->limit > 0) {
            $total = min($total, $this->limit);
        }

        for ($index = 0; $index < $total; $index++) {
            $this->importConnection()->table(FakeImportRow::TABLE)->insert(['name' => "row-{$index}"]);
        }

        return $total;
    }

    public function importConnection(): ConnectionInterface
    {
        $model = new FakeImportRow;

        if ($this->connectionName !== null) {
            $model->setConnection($this->connectionName);
        }

        return $model->getConnection();
    }
}
