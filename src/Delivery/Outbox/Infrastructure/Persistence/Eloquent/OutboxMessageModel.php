<?php

namespace Reformas\Delivery\Outbox\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class OutboxMessageModel extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'outbox_messages';

    protected $keyType = 'string';

    protected $guarded = [];
}
