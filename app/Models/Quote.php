<?php

namespace Modules\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Business\Database\Factories\QuoteFactory;

/**
 * @mixin IdeHelperQuote
 */
class Quote extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    // protected static function newFactory(): QuoteFactory
    // {
    //     // return QuoteFactory::new();
    // }
}
