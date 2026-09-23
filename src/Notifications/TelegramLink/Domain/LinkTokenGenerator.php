<?php

namespace Reformas\Notifications\TelegramLink\Domain;

interface LinkTokenGenerator
{
    public function generate(): LinkToken;
}
