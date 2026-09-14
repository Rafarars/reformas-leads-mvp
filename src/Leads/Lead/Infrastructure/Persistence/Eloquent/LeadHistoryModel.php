<?php

namespace Reformas\Leads\Lead\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class LeadHistoryModel extends Model
{
    public $timestamps = false;

    protected $table = 'lead_history';

    protected $guarded = [];
}
