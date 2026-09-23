<?php

namespace Reformas\Notifications\TelegramLink\Application\List;

use DateTimeInterface;
use Reformas\Notifications\TelegramLink\Domain\TelegramLinkRepository;

/**
 * @phpstan-import-type RecipientPrimitives from ListRecipientsResponse
 */
final readonly class ListRecipients
{
    public function __construct(private TelegramLinkRepository $repository) {}

    public function __invoke(): ListRecipientsResponse
    {
        $links = $this->repository->allLinked();

        /** @var list<RecipientPrimitives> $recipients */
        $recipients = [];

        foreach ($links as $link) {
            $chatId = $link->chatId();

            if ($chatId === null) {
                continue;
            }

            $recipients[] = [
                'user_id' => $link->userId(),
                'chat_id' => $chatId->value,
                'linked_at' => $link->linkedAt()?->format(DateTimeInterface::ATOM),
            ];
        }

        return new ListRecipientsResponse($recipients);
    }
}
