<?php

namespace Reformas\Integrations\ApiClient\Infrastructure;

use Reformas\Integrations\ApiClient\Domain\ApiKeyGenerator;
use Reformas\Integrations\ApiClient\Domain\PlainApiKey;

final class RandomApiKeyGenerator implements ApiKeyGenerator
{
    public function generate(): PlainApiKey
    {
        return new PlainApiKey('rl_'.bin2hex(random_bytes(32)));
    }
}
