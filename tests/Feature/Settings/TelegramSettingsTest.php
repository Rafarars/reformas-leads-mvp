<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Reformas\Notifications\TelegramLink\Domain\TelegramLink;
use Reformas\Notifications\TelegramLink\Domain\TelegramLinkRepository;
use Tests\TestCase;

class TelegramSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_on_all_telegram_routes(): void
    {
        $this->get(route('telegram.edit'))->assertRedirect(route('login'));
        $this->post(route('telegram.link.store'))->assertRedirect(route('login'));
        $this->delete(route('telegram.destroy'))->assertRedirect(route('login'));
    }

    public function test_user_can_view_telegram_settings_when_disconnected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('telegram.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/telegram')
                ->where('telegram.connected', false)
                ->where('telegram.pending', false)
                ->where('telegram.fresh_link', null)
                ->where('telegram.bot_username', 'leads_reformas_mvp_bot')
            );
    }

    public function test_user_can_request_link_and_token_is_flashed_only_once(): void
    {
        $user = User::factory()->create();

        // 1. POST solicita el token y redirige de vuelta con el flash
        $postResponse = $this->actingAs($user)
            ->from(route('telegram.edit'))
            ->post(route('telegram.link.store'));

        $postResponse->assertRedirect(route('telegram.edit'));

        // El registro existe en base con el token hasheado (no en claro)
        $this->assertDatabaseHas('telegram_links', [
            'user_id' => $user->id,
            'chat_id' => null,
        ]);
        $row = DB::table('telegram_links')->where('user_id', $user->id)->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->token_hash);
        $this->assertSame(64, strlen($row->token_hash));

        // 2. Primera respuesta GET tras la redirección: recibe el enlace efímero
        $getResponse = $this->actingAs($user)->get(route('telegram.edit'));
        $getResponse->assertOk();
        $token = null;

        $getResponse->assertInertia(function (Assert $page) use (&$token) {
            $page->component('settings/telegram')
                ->where('telegram.connected', false)
                ->where('telegram.pending', true)
                ->has('telegram.fresh_link.token')
                ->has('telegram.fresh_link.url')
                ->has('telegram.fresh_link.expires_at');
            $props = $page->toArray()['props'];
            $token = $props['telegram']['fresh_link']['token'];
        });

        $this->assertNotNull($token);
        $this->assertSame(64, strlen($token));

        // 3. Recarga de la página (segundo GET): el token en claro ya NO aparece en la respuesta,
        // pero la pantalla sabe que hay un enlace pendiente según la entidad en base de datos.
        $reloadResponse = $this->actingAs($user)->get(route('telegram.edit'));
        $reloadResponse->assertOk();

        $reloadResponse->assertInertia(fn (Assert $page) => $page
            ->component('settings/telegram')
            ->where('telegram.connected', false)
            ->where('telegram.pending', true)
            ->where('telegram.fresh_link', null)
        );
    }

    public function test_user_can_disconnect_telegram(): void
    {
        $user = User::factory()->create();

        // Vinculamos previamente la cuenta
        $repository = app(TelegramLinkRepository::class);
        $now = new DateTimeImmutable('now');
        $link = TelegramLink::fromPrimitives([
            'id' => '11111111-1111-4111-8111-111111111111',
            'user_id' => (int) $user->id,
            'chat_id' => '987654321',
            'token_hash' => hash('sha256', str_repeat('a', 64)),
            'token_expires_at' => $now->modify('+15 minutes')->format(DateTimeImmutable::ATOM),
            'token_used_at' => $now->format(DateTimeImmutable::ATOM),
            'linked_at' => $now->format(DateTimeImmutable::ATOM),
            'created_at' => $now->format(DateTimeImmutable::ATOM),
            'updated_at' => $now->format(DateTimeImmutable::ATOM),
        ]);
        $repository->save($link);

        $this->actingAs($user)
            ->get(route('telegram.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/telegram')
                ->where('telegram.connected', true)
                ->where('telegram.chat_id', '987654321')
            );

        // Desconectar elimina el vínculo
        $deleteResponse = $this->actingAs($user)
            ->from(route('telegram.edit'))
            ->delete(route('telegram.destroy'));

        $deleteResponse->assertRedirect(route('telegram.edit'));
        $this->assertDatabaseMissing('telegram_links', ['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('telegram.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/telegram')
                ->where('telegram.connected', false)
                ->where('telegram.pending', false)
                ->where('telegram.chat_id', null)
            );
    }
}
