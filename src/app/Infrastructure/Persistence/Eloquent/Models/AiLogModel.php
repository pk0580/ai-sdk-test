<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class AiLogModel extends Model
{
    protected $table = 'ai_logs';

    protected $fillable = [
        'session_id',
        'agent_name',
        'thought',
        'action',
        'input',
        'output',
        'latency',
    ];

    protected $casts = [
        'input' => 'array',
    ];
}
