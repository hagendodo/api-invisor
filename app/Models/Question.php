<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'summary_id',
        'question',
        'answer',
        'feedback',
        'score',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function summary(): BelongsTo
    {
        return $this->belongsTo(Summary::class, 'summary_id');
    }
}
