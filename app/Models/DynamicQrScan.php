<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynamicQrScan extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['scanned_at' => 'datetime'];
    }

    public function code(): BelongsTo
    {
        return $this->belongsTo(DynamicQrCode::class, 'dynamic_qr_code_id');
    }
}
