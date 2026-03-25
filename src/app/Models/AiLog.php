<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiLog extends Model
{
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
