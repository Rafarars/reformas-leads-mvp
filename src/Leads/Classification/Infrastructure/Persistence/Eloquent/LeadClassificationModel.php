<?php

namespace Reformas\Leads\Classification\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class LeadClassificationModel extends Model
{
    protected $table = 'lead_classifications';

    protected $primaryKey = 'lead_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];
}
