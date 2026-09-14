<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Infraestructura HTTP sin dominio propio: vive junto al middleware que la usa, no en src/.
final class IdempotencyKey extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
