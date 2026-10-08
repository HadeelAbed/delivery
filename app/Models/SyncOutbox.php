<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncOutbox extends Model
{
    use HasFactory;

    /** Matches the table created by 0001_01_01_000015_create_sync_outbox_table (singular). */
    protected $table = 'sync_outbox';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'type',
        'payload',
        'client_timestamp',
        'status',
        'result',
    ];

    protected $casts = [
        'payload' => 'array',
        'client_timestamp' => 'datetime',
        'result' => 'array',
    ];
}
