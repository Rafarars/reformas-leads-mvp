<?php

namespace Reformas\Notifications\TelegramLink\Application\List;

/**
 * @phpstan-type RecipientPrimitives array{
 *     user_id: int,
 *     chat_id: string,
 *     linked_at: ?string
 * }
 */
final readonly class ListRecipientsResponse
{
    /**
     * @param  list<RecipientPrimitives>  $recipients
     */
    public function __construct(public array $recipients) {}

    /**
     * @return list<string>
     */
    public function chatIds(): array
    {
        return array_values(array_unique(array_map(
            static fn (array $recipient): string => $recipient['chat_id'],
            $this->recipients,
        )));
    }
}
