<?php

namespace Reformas\Integrations\ApiClient\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class ApiClientModel extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'api_clients';

    protected $keyType = 'string';

    protected $guarded = [];
}
