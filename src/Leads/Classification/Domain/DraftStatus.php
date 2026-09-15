<?php

namespace Reformas\Leads\Classification\Domain;

enum DraftStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
}
