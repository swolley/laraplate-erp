<?php

namespace Modules\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Business\Database\Factories\TaskFactory;

/**
 * @mixin IdeHelperTask
 */
class Task extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    // protected static function newFactory(): TaskFactory
    // {
    //     // return TaskFactory::new();
    // }
}
