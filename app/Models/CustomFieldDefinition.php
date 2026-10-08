<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomFieldDefinition extends Model
{
    protected $fillable = [
        'name',
        'label',
        'type',
        'options',
        'user_id',
        'warehouse_id',
        'is_required',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
