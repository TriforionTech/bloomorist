<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportLineTemplate extends Model
{
    protected $table = 'bl_report_line_templates_t';

    protected $fillable = [
        'report_type',
        'line_key',
        'label',
        'account_codes',
        'sign',
        'sort_order',
        'is_subtotal',
        'subtotal_formula',
        'is_active',
    ];

    protected $casts = [
        'account_codes' => 'array',
        'sign' => 'integer',
        'sort_order' => 'integer',
        'is_subtotal' => 'boolean',
        'is_active' => 'boolean',
    ];
}
