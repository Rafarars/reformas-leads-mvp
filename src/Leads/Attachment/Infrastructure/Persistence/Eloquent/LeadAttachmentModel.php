<?php

namespace Reformas\Leads\Attachment\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class LeadAttachmentModel extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'lead_attachments';

    protected $keyType = 'string';

    protected $guarded = [];
}
