<?php

declare(strict_types=1);

namespace Modules\ERP\Tests\Stubs\Import;

use Illuminate\Database\Eloquent\Model;

final class FakeImportRow extends Model
{
    public const string TABLE = 'erp_fake_import_rows';

    protected $table = self::TABLE;

    public $timestamps = false;

    protected $guarded = [];
}
