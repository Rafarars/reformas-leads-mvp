<?php

namespace Reformas\Integrations\ApiClient\Domain;

final readonly class PlainApiKey
{
    // El prefijo permite que un escáner de secretos la reconozca si llega a un commit.
    private const PATTERN = '/^rl_[a-f0-9]{64}$/';

    public function __construct(public string $value)
    {
        // Para quien llama, una clave con formato inválido es simplemente una clave inválida.
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidApiKeyError;
        }
    }

    // SHA-256 y no bcrypt: la clave tiene 256 bits aleatorios, no se puede adivinar por fuerza
    // bruta, y un hash determinista permite buscar al cliente por índice.
    public function hash(): ApiKeyHash
    {
        return new ApiKeyHash(hash('sha256', $this->value));
    }
}
