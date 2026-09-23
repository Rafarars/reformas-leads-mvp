<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Reformas\Integrations\ApiClient\Application\Create\ApiClientCreator;
use Reformas\Integrations\ApiClient\Application\Create\ApiClientCreatorRequest;
use Reformas\Notifications\TelegramLink\Application\Request\RequestLink;
use Reformas\Notifications\TelegramLink\Application\Request\RequestLinkRequest;
use Reformas\Notifications\TelegramLink\Domain\TelegramLink;
use Reformas\Notifications\TelegramLink\Domain\TelegramLinkRepository;
use Tests\TestCase;

class TelegramLinkApiTest extends TestCase
{
    use RefreshDatabase;

    private string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->apiKey = app(ApiClientCreator::class)(
            new ApiClientCreatorRequest('n8n Webhook', 'n8n-webhook')
        )->apiKey;
    }

    private function postTelegramLink(array $payload, ?string $apiKey = null): TestResponse
    {
        $headers = [];
        $key = $apiKey ?? $this->apiKey;

        if ($key !== '') {
            $headers['Authorization'] = 'Bearer '.$key;
        }

        return $this->postJson('/api/v1/telegram/link', $payload, $headers);
    }

    public function test_rejects_request_without_api_key(): void
    {
        $this->postJson('/api/v1/telegram/link', [
            'token' => str_repeat('a', 64),
            'chat_id' => '12345678',
        ])
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer')
            ->assertExactJson(['type' => 'invalid_api_key', 'message' => 'La API key no es válida.']);
    }

    public function test_rejects_request_with_invalid_api_key(): void
    {
        $this->postTelegramLink([
            'token' => str_repeat('a', 64),
            'chat_id' => '12345678',
        ], 'rl_'.str_repeat('f', 64))
            ->assertUnauthorized()
            ->assertJsonPath('type', 'invalid_api_key');
    }

    public function test_validates_required_fields(): void
    {
        $this->postTelegramLink([])
            ->assertStatus(422)
            ->assertJsonPath('type', 'validation_failed')
            ->assertJsonValidationErrors(['token', 'chat_id']);
    }

    public function test_rejects_invalid_token_format(): void
    {
        $this->postTelegramLink([
            'token' => 'invalid-token-short',
            'chat_id' => '12345678',
        ])
            ->assertStatus(422)
            ->assertJsonPath('type', 'invalid_link_token');
    }

    public function test_rejects_invalid_chat_id_format(): void
    {
        $this->postTelegramLink([
            'token' => str_repeat('a', 64),
            'chat_id' => 'not-a-number',
        ])
            ->assertStatus(422)
            ->assertJsonPath('type', 'invalid_telegram_chat_id');
    }

    public function test_returns_404_when_token_not_found(): void
    {
        $this->postTelegramLink([
            'token' => str_repeat('b', 64),
            'chat_id' => '12345678',
        ])
            ->assertStatus(404)
            ->assertJsonPath('type', 'link_token_not_found');
    }

    public function test_returns_409_when_token_is_expired(): void
    {
        $user = User::factory()->create();
        $token = str_repeat('c', 64);
        $now = new DateTimeImmutable('2026-01-01 10:00:00');

        $repository = app(TelegramLinkRepository::class);
        $link = TelegramLink::fromPrimitives([
            'id' => '22222222-2222-4222-8222-222222222222',
            'user_id' => (int) $user->id,
            'chat_id' => null,
            'token_hash' => hash('sha256', $token),
            'token_expires_at' => '2026-01-01T09:00:00+00:00', // ya caducado
            'token_used_at' => null,
            'linked_at' => null,
            'created_at' => '2026-01-01T08:45:00+00:00',
            'updated_at' => '2026-01-01T08:45:00+00:00',
        ]);
        $repository->save($link);

        $this->postTelegramLink([
            'token' => $token,
            'chat_id' => '12345678',
        ])
            ->assertStatus(409)
            ->assertJsonPath('type', 'link_token_expired');
    }

    public function test_returns_409_when_token_is_already_used(): void
    {
        $user = User::factory()->create();
        $token = str_repeat('d', 64);

        $repository = app(TelegramLinkRepository::class);
        $link = TelegramLink::fromPrimitives([
            'id' => '33333333-3333-4333-8333-333333333333',
            'user_id' => (int) $user->id,
            'chat_id' => '11111111',
            'token_hash' => hash('sha256', $token),
            'token_expires_at' => (new DateTimeImmutable('+1 hour'))->format(DateTimeImmutable::ATOM),
            'token_used_at' => (new DateTimeImmutable('-5 minutes'))->format(DateTimeImmutable::ATOM),
            'linked_at' => (new DateTimeImmutable('-5 minutes'))->format(DateTimeImmutable::ATOM),
            'created_at' => (new DateTimeImmutable('-10 minutes'))->format(DateTimeImmutable::ATOM),
            'updated_at' => (new DateTimeImmutable('-5 minutes'))->format(DateTimeImmutable::ATOM),
        ]);
        $repository->save($link);

        $this->postTelegramLink([
            'token' => $token,
            'chat_id' => '11111111',
        ])
            ->assertStatus(409)
            ->assertJsonPath('type', 'link_token_already_used');
    }

    public function test_returns_409_when_chat_id_is_already_linked_to_another_user(): void
    {
        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();
        $chatId = '88889999';

        $repository = app(TelegramLinkRepository::class);

        // Usuario 1 ya tiene este chatId
        $linkOne = TelegramLink::fromPrimitives([
            'id' => '44444444-4444-4444-8444-444444444444',
            'user_id' => (int) $userOne->id,
            'chat_id' => $chatId,
            'token_hash' => hash('sha256', str_repeat('1', 64)),
            'token_expires_at' => (new DateTimeImmutable('+1 hour'))->format(DateTimeImmutable::ATOM),
            'token_used_at' => (new DateTimeImmutable('-1 hour'))->format(DateTimeImmutable::ATOM),
            'linked_at' => (new DateTimeImmutable('-1 hour'))->format(DateTimeImmutable::ATOM),
            'created_at' => (new DateTimeImmutable('-1 hour'))->format(DateTimeImmutable::ATOM),
            'updated_at' => (new DateTimeImmutable('-1 hour'))->format(DateTimeImmutable::ATOM),
        ]);
        $repository->save($linkOne);

        // Usuario 2 solicita token
        $requestLink = app(RequestLink::class);
        $requested = $requestLink(new RequestLinkRequest((int) $userTwo->id));

        // Intenta completar con el chatId del Usuario 1
        $this->postTelegramLink([
            'token' => $requested->token,
            'chat_id' => $chatId,
        ])
            ->assertStatus(409)
            ->assertJsonPath('type', 'chat_already_linked');
    }

    public function test_completes_link_successfully(): void
    {
        $user = User::factory()->create();
        $requestLink = app(RequestLink::class);
        $requested = $requestLink(new RequestLinkRequest((int) $user->id));

        $chatId = '55512345';

        $response = $this->postTelegramLink([
            'token' => $requested->token,
            'chat_id' => $chatId,
        ]);

        $response->assertOk()
            ->assertJsonPath('user_id', $user->id)
            ->assertJsonPath('chat_id', $chatId)
            ->assertJsonStructure(['user_id', 'chat_id', 'linked_at']);

        $repository = app(TelegramLinkRepository::class);
        $link = $repository->searchByUserId((int) $user->id);

        $this->assertNotNull($link);
        $this->assertTrue($link->isLinked());
        $this->assertSame($chatId, $link->chatId()?->value);
    }

    public function test_relinking_same_chat_id_to_same_user_is_idempotent(): void
    {
        $user = User::factory()->create();
        $chatId = '55512345';

        // 1. Primera vinculación
        $requestLink = app(RequestLink::class);
        $req1 = $requestLink(new RequestLinkRequest((int) $user->id));
        $this->postTelegramLink([
            'token' => $req1->token,
            'chat_id' => $chatId,
        ])->assertOk();

        // 2. Renovación y revinculación con el mismo chatId
        $req2 = $requestLink(new RequestLinkRequest((int) $user->id));
        $this->postTelegramLink([
            'token' => $req2->token,
            'chat_id' => $chatId,
        ])->assertOk()
            ->assertJsonPath('user_id', $user->id)
            ->assertJsonPath('chat_id', $chatId);
    }
}
