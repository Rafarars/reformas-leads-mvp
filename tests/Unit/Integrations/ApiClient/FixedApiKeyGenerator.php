<?php

namespace Tests\Unit\Integrations\ApiClient;

use Reformas\Integrations\ApiClient\Domain\ApiKeyGenerator;
use Reformas\Integrations\ApiClient\Domain\PlainApiKey;

final class FixedApiKeyGenerator implements ApiKeyGenerator
{
    public const KEY = 'rl_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function generate(): PlainApiKey
    {
        return new PlainApiKey(self::KEY);
    }
}
