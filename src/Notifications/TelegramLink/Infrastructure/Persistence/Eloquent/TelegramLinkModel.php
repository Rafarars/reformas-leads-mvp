<?php

namespace Reformas\Notifications\TelegramLink\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class TelegramLinkModel extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'telegram_links';

    protected $keyType = 'string';

    protected $guarded = [];
}
