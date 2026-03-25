<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Pgvector\Laravel\Vector;
use Pgvector\Laravel\HasNeighbors;

class Document extends Model
{
    use HasNeighbors;

    protected $fillable = [
        'content',
        'metadata',
        'embedding',
    ];

    protected $casts = [
        'metadata' => 'array',
        'embedding' => Vector::class,
    ];
}
