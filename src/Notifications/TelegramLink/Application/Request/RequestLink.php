<?php

namespace Reformas\Notifications\TelegramLink\Application\Request;

use DateTimeInterface;
use Reformas\Notifications\TelegramLink\Domain\LinkTokenGenerator;
use Reformas\Notifications\TelegramLink\Domain\TelegramLink;
use Reformas\Notifications\TelegramLink\Domain\TelegramLinkId;
use Reformas\Notifications\TelegramLink\Domain\TelegramLinkRepository;
use Reformas\Shared\Domain\Clock;
use Reformas\Shared\Domain\UuidGenerator;

final readonly class RequestLink
{
    private const TTL_MINUTES = 15;

    public function __construct(
        private TelegramLinkRepository $repository,
        private LinkTokenGenerator $generator,
        private UuidGenerator $uuidGenerator,
        private Clock $clock,
    ) {}

    public function __invoke(RequestLinkRequest $request): RequestLinkResponse
    {
        $now = $this->clock->now();
        $token = $this->generator->generate();
        $expiresAt = $now->modify('+'.self::TTL_MINUTES.' minutes');

        $link = $this->repository->searchByUserId($request->userId);

        if ($link !== null) {
            // Si el usuario ya tenía un vínculo (activo o pendiente), renueva el token
            // preservando el chat_id anterior hasta que el nuevo enlace se confirme.
            $link->renewToken($token->hash(), $expiresAt, $now);
            $this->repository->save($link);
        } else {
            $link = TelegramLink::request(
                new TelegramLinkId($this->uuidGenerator->generate()),
                $request->userId,
                $token->hash(),
                $expiresAt,
                $now,
            );
            $this->repository->save($link);
        }

        return new RequestLinkResponse(
            $token->value,
            $expiresAt->format(DateTimeInterface::ATOM),
        );
    }
}
