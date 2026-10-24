<?php

namespace App\Http\Controllers;

use App\Events\ChessMessage;
use App\Events\ChessUpdate;
use App\Services\ChessRoomStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ChessController extends Controller
{
    /**
     * Llave de sesión que identifica a este navegador como jugador.
     */
    private const PLAYER_KEY = 'chess.player';

    public function __construct(private readonly ChessRoomStore $rooms) {}

    /**
     * Pantalla de entrada: crear una sala o unirse con código.
     */
    public function lobby(): View
    {
        return view('ajedrez-lobby');
    }

    /**
     * Crea una sala y deja a este jugador como blancas.
     */
    public function create(Request $request): RedirectResponse
    {
        $code = $this->rooms->create($this->playerToken($request));

        return redirect()->route('chess.board', ['room' => $code]);
    }

    /**
     * Entra a una sala con código: toma un color libre o queda como espectador.
     */
    public function join(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:12'],
        ]);

        $code = strtoupper(trim($data['code']));

        if ($this->rooms->get($code) === null) {
            return back()->withErrors(['code' => 'No encontramos esa sala. Revisá el código o creá una nueva.'])->withInput();
        }

        $this->rooms->join($code, $this->playerToken($request));

        return redirect()->route('chess.board', ['room' => $code]);
    }

    /**
     * Tablero de la sala, con la lista de movimientos ya registrados.
     */
    public function board(Request $request, string $room): View|RedirectResponse
    {
        $code = strtoupper(trim($room));
        $state = $this->rooms->get($code);

        if ($state === null) {
            return redirect()->route('chess.lobby')->withErrors(['code' => 'Esa sala no existe o expiró.'])->withInput();
        }

        $token = $this->playerToken($request);

        return view('ajedrez-tablero', [
            'room' => $code,
            'color' => $this->rooms->colorOf($code, $token),
            'moves' => $state['moves'],
            'messages' => $state['messages'] ?? [],
            'whiteJoined' => $state['white'] !== null,
            'blackJoined' => $state['black'] !== null,
        ]);
    }

    /**
     * Registra un movimiento y lo retransmite a la sala.
     */
    public function move(Request $request, string $room): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'string', 'size:2'],
            'to' => ['required', 'string', 'size:2'],
            'promotion' => ['nullable', 'string', 'in:q,r,b,n'],
            'clientId' => ['nullable', 'string', 'max:64'],
        ]);

        $code = strtoupper(trim($room));
        $state = $this->rooms->get($code);

        if ($state === null) {
            return response()->json(['error' => 'La sala no existe o expiró.'], 404);
        }

        $color = $this->rooms->colorOf($code, $this->playerToken($request));

        if ($color === null) {
            return response()->json(['error' => 'Sos espectador: no podés mover piezas.'], 403);
        }

        if ($color !== $this->rooms->turn($code)) {
            return response()->json(['error' => 'No es tu turno.'], 409);
        }

        $this->rooms->addMove($code, [
            'from' => $data['from'],
            'to' => $data['to'],
            'promotion' => $data['promotion'] ?? null,
        ]);

        broadcast(new ChessUpdate(
            roomCode: $code,
            type: 'move',
            from: $data['from'],
            to: $data['to'],
            promotion: $data['promotion'] ?? null,
            color: $color,
            clientId: $data['clientId'] ?? null,
        ));

        return response()->json(['ok' => true]);
    }

    /**
     * Reinicia la partida y lo retransmite a la sala.
     */
    public function reset(Request $request, string $room): JsonResponse
    {
        $code = strtoupper(trim($room));

        if ($this->rooms->get($code) === null) {
            return response()->json(['error' => 'La sala no existe o expiró.'], 404);
        }

        if ($this->rooms->colorOf($code, $this->playerToken($request)) === null) {
            return response()->json(['error' => 'Sólo los jugadores pueden reiniciar la partida.'], 403);
        }

        $this->rooms->reset($code);

        broadcast(new ChessUpdate(roomCode: $code, type: 'reset'));

        return response()->json(['ok' => true]);
    }

    /**
     * Recibe un mensaje de chat de un jugador y lo retransmite a la sala.
     */
    public function message(Request $request, string $room): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:500'],
            'clientId' => ['nullable', 'string', 'max:64'],
        ]);

        $code = strtoupper(trim($room));

        if ($this->rooms->get($code) === null) {
            return response()->json(['error' => 'La sala no existe o expiró.'], 404);
        }

        $color = $this->rooms->colorOf($code, $this->playerToken($request));

        if ($color === null) {
            return response()->json(['error' => 'Sólo los jugadores pueden enviar mensajes.'], 403);
        }

        $text = trim($data['text']);

        if ($text === '') {
            return response()->json(['error' => 'El mensaje no puede estar vacío.'], 422);
        }

        $at = (int) round(microtime(true) * 1000);

        $this->rooms->addMessage($code, [
            'color' => $color,
            'text' => $text,
            'at' => $at,
        ]);

        broadcast(new ChessMessage(
            roomCode: $code,
            text: $text,
            color: $color,
            clientId: $data['clientId'] ?? null,
            at: $at,
        ));

        return response()->json(['ok' => true, 'at' => $at]);
    }

    /**
     * Estado actual de la sala; sirve para resincronizar tras un fallo de red.
     */
    public function state(string $room): JsonResponse
    {
        $code = strtoupper(trim($room));
        $state = $this->rooms->get($code);

        if ($state === null) {
            return response()->json(['error' => 'La sala no existe o expiró.'], 404);
        }

        return response()->json([
            'moves' => $state['moves'],
            'messages' => $state['messages'] ?? [],
            'whiteJoined' => $state['white'] !== null,
            'blackJoined' => $state['black'] !== null,
        ]);
    }

    /**
     * Token estable por navegador; crea uno si el jugador llegó por URL directa.
     */
    private function playerToken(Request $request): string
    {
        $token = $request->session()->get(self::PLAYER_KEY);

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $token = Str::random(16);
        $request->session()->put(self::PLAYER_KEY, $token);

        return $token;
    }
}
