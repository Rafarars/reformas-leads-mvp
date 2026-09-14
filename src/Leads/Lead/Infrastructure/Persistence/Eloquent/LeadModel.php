<?php

namespace Reformas\Leads\Lead\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class LeadModel extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'leads';

    protected $keyType = 'string';

    protected $guarded = [];
}
