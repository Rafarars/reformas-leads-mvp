<?php

namespace Reformas\Leads\Lead\Domain;

enum LeadHistoryType: string
{
    case Received = 'received';
    case RepeatedContact = 'repeated_contact';
}
