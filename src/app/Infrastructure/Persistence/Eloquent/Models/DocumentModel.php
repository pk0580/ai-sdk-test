<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

class DocumentModel extends Model
{
    use HasNeighbors;
    protected $table = 'documents';
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
