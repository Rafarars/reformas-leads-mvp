<?php

namespace Reformas\Integrations\ApiClient\Domain;

interface ApiKeyGenerator
{
    public function generate(): PlainApiKey;
}
