<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DynamicQrCode extends Model
{
    protected $guarded = [];

    protected $hidden = ['management_token_hash'];

    public function scans(): HasMany
    {
        return $this->hasMany(DynamicQrScan::class);
    }
}
