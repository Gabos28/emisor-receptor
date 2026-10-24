<?php

namespace Tests\Feature;

use App\Events\ChessMessage;
use App\Events\ChessUpdate;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ChessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Caché limpia para aislar las salas entre pruebas.
        cache()->flush();
    }

    /**
     * Simula un navegador distinto fijando su token de jugador.
     */
    private function asPlayer(string $name): static
    {
        $this->withSession(['chess.player' => 'player-'.$name]);

        return $this;
    }

    /**
     * Crea una sala (el jugador 'white' es blancas) y devuelve el código.
     */
    private function createRoom(): string
    {
        $response = $this->asPlayer('white')->post(route('chess.create'));
        $response->assertRedirect();

        $location = $response->headers->get('Location') ?? '';
        $this->assertMatchesRegularExpression('#/ajedrez/[A-Z0-9]{6}$#', $location);

        return substr($location, -6);
    }

    public function test_lobby_shows_create_and_join_forms(): void
    {
        $this->get(route('chess.lobby'))
            ->assertOk()
            ->assertSee('Crear partida', false)
            ->assertSee('Unirse a una partida', false);
    }

    public function test_creating_a_room_redirects_to_its_board_as_white(): void
    {
        $room = $this->createRoom();

        $this->asPlayer('white')
            ->get(route('chess.board', ['room' => $room]))
            ->assertOk()
            ->assertSee($room)
            ->assertSee('white', false);
    }

    public function test_joining_with_an_invalid_code_returns_an_error(): void
    {
        $this->post(route('chess.join'), ['code' => 'ZZZZZZ'])
            ->assertSessionHasErrors('code');
    }

    public function test_second_player_joins_as_black_and_latecomer_is_a_spectator(): void
    {
        $room = $this->createRoom();

        $this->asPlayer('black')
            ->post(route('chess.join'), ['code' => $room])
            ->assertRedirect(route('chess.board', ['room' => $room]));

        // El tercero entra como espectador: el tablero no le asigna color.
        $this->asPlayer('watcher')
            ->get(route('chess.board', ['room' => $room]))
            ->assertOk()
            ->assertSee('null', false);
    }

    public function test_board_redirects_to_lobby_when_the_room_does_not_exist(): void
    {
        $this->get(route('chess.board', ['room' => 'NOPE99']))
            ->assertRedirect(route('chess.lobby'));
    }

    public function test_white_player_can_move_and_the_event_is_broadcast(): void
    {
        Event::fake([ChessUpdate::class]);
        $room = $this->createRoom();

        $this->asPlayer('white')
            ->post(route('chess.move', ['room' => $room]), [
                'from' => 'e2',
                'to' => 'e4',
                'clientId' => 'test-client',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        Event::assertDispatched(ChessUpdate::class, fn (ChessUpdate $event): bool => $event->roomCode === $room
            && $event->type === 'move'
            && $event->from === 'e2'
            && $event->to === 'e4'
            && $event->color === 'white'
            && $event->clientId === 'test-client');
    }

    public function test_black_cannot_move_before_white(): void
    {
        $room = $this->createRoom();

        $this->asPlayer('black')->post(route('chess.join'), ['code' => $room]);

        $this->asPlayer('black')
            ->post(route('chess.move', ['room' => $room]), ['from' => 'e7', 'to' => 'e5'])
            ->assertStatus(409);
    }

    public function test_spectator_cannot_move(): void
    {
        Event::fake([ChessUpdate::class]);
        $room = $this->createRoom();

        $this->asPlayer('black')->post(route('chess.join'), ['code' => $room]);

        $this->asPlayer('watcher')
            ->post(route('chess.move', ['room' => $room]), ['from' => 'e2', 'to' => 'e4'])
            ->assertStatus(403);

        Event::assertNotDispatched(ChessUpdate::class);
    }

    public function test_moving_in_a_missing_room_returns_not_found(): void
    {
        $this->asPlayer('white')
            ->post(route('chess.move', ['room' => 'NOPE99']), ['from' => 'e2', 'to' => 'e4'])
            ->assertStatus(404);
    }

    public function test_state_returns_recorded_moves_for_resync(): void
    {
        Event::fake([ChessUpdate::class]);
        $room = $this->createRoom();

        $this->asPlayer('white')
            ->post(route('chess.move', ['room' => $room]), ['from' => 'e2', 'to' => 'e4'])
            ->assertOk();

        $this->get(route('chess.state', ['room' => $room]))
            ->assertOk()
            ->assertJson(['moves' => [['from' => 'e2', 'to' => 'e4', 'promotion' => null]]]);
    }

    public function test_state_returns_messages_and_player_presence_for_automatic_sync(): void
    {
        Event::fake([ChessMessage::class]);
        $room = $this->createRoom();

        $this->asPlayer('black')->post(route('chess.join'), ['code' => $room]);
        $this->asPlayer('white')->post(route('chess.message', ['room' => $room]), [
            'text' => 'Nos vemos en el tablero',
        ])->assertOk();

        $this->get(route('chess.state', ['room' => $room]))
            ->assertOk()
            ->assertJsonPath('messages.0.text', 'Nos vemos en el tablero')
            ->assertJsonPath('whiteJoined', true)
            ->assertJsonPath('blackJoined', true);
    }

    public function test_only_players_can_reset_the_match(): void
    {
        Event::fake([ChessUpdate::class]);
        $room = $this->createRoom();

        $this->asPlayer('white')->post(route('chess.move', ['room' => $room]), ['from' => 'e2', 'to' => 'e4'])->assertOk();
        $this->asPlayer('black')->post(route('chess.join'), ['code' => $room]);

        $this->asPlayer('watcher')
            ->post(route('chess.reset', ['room' => $room]))
            ->assertStatus(403);

        $this->asPlayer('white')
            ->post(route('chess.reset', ['room' => $room]))
            ->assertOk();

        $this->get(route('chess.state', ['room' => $room]))
            ->assertOk()
            ->assertJson(['moves' => []]);

        Event::assertDispatched(ChessUpdate::class, fn (ChessUpdate $event): bool => $event->type === 'reset');
    }

    public function test_players_can_send_chat_messages(): void
    {
        Event::fake([ChessMessage::class]);
        $room = $this->createRoom();

        $this->asPlayer('white')
            ->post(route('chess.message', ['room' => $room]), ['text' => 'Buena partida', 'clientId' => 'c1'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        Event::assertDispatched(ChessMessage::class, fn (ChessMessage $event): bool => $event->roomCode === $room
            && $event->text === 'Buena partida'
            && $event->color === 'white'
            && $event->clientId === 'c1'
            && $event->at > 0);
    }

    public function test_spectators_cannot_send_chat_messages(): void
    {
        Event::fake([ChessMessage::class]);
        $room = $this->createRoom();

        $this->asPlayer('black')->post(route('chess.join'), ['code' => $room]);

        $this->asPlayer('watcher')
            ->post(route('chess.message', ['room' => $room]), ['text' => 'hola'])
            ->assertStatus(403);

        Event::assertNotDispatched(ChessMessage::class);
    }

    public function test_chat_rejects_blank_messages(): void
    {
        $room = $this->createRoom();

        $this->asPlayer('white')
            ->postJson(route('chess.message', ['room' => $room]), ['text' => '    '])
            ->assertStatus(422);
    }

    public function test_board_shows_previous_chat_messages(): void
    {
        Event::fake([ChessMessage::class]);
        $room = $this->createRoom();

        $this->asPlayer('white')
            ->post(route('chess.message', ['room' => $room]), ['text' => 'Buenas rival'])
            ->assertOk();

        $this->asPlayer('black')->post(route('chess.join'), ['code' => $room]);

        $this->asPlayer('black')
            ->get(route('chess.board', ['room' => $room]))
            ->assertOk()
            ->assertSee('Buenas rival', false);
    }
}
