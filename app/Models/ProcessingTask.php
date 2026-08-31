<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProcessingTask extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'input_metadata' => 'array',
            'options' => 'array',
            'output_metadata' => 'array',
            'expires_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function ownedByCurrentSession(): bool
    {
        $token = session()->get('image_tools_owner_token');

        return is_string($token) && hash_equals($this->session_hash, hash('sha256', $token));
    }
}
