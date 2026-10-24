<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Estado efímero de las salas de ajedrez, guardado solo en caché (file/array).
 *
 * Cada sala guarda únicamente la lista de movimientos y qué sesión ocupa cada
 * color. Los clientes reconstruyen la posición con chess.js a partir de la
 * lista, por lo que el servidor no necesita librerías de ajedrez ni modelos.
 */
class ChessRoomStore
{
    /**
     * Clave de caché por sala: chess:room:{CODIGO}.
     */
    private const KEY_PREFIX = 'chess:room:';

    /**
     * 12 horas: suficiente para una partida larga sin acumular salas viejas.
     */
    private const TTL_SECONDS = 43200;

    /**
     * Alfabeto sin caracteres ambiguos (O/0 e I/1).
     */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * @return array{moves: list<array{from: string, to: string, promotion: ?string}>, white: ?string, black: ?string}|null
     */
    public function get(string $code): ?array
    {
        $room = Cache::get(self::KEY_PREFIX.$this->normalize($code));

        return is_array($room) ? $room : null;
    }

    /**
     * Crea una sala y asigna el primer color a la sesión indicada.
     *
     * @return string Código de la sala creada
     */
    public function create(?string $playerId): string
    {
        do {
            $code = $this->randomCode();
        } while ($this->get($code) !== null);

        $this->put($code, [
            'moves' => [],
            'messages' => [],
            'white' => $playerId,
            'black' => null,
        ]);

        return $code;
    }

    /**
     * Asigna color a la sesión si queda lugar; null si queda como espectador.
     */
    public function join(string $code, string $playerId): ?string
    {
        $room = $this->get($code);

        if ($room === null) {
            return null;
        }

        $color = $this->colorOf($code, $playerId);

        if ($color !== null) {
            return $color;
        }

        foreach (['white', 'black'] as $side) {
            if ($room[$side] === null) {
                $room[$side] = $playerId;
                $this->put($code, $room);

                return $side;
            }
        }

        return null;
    }

    /**
     * Color asignado a una sesión dentro de la sala, si lo tiene.
     */
    public function colorOf(string $code, string $playerId): ?string
    {
        $room = $this->get($code);

        if ($room === null) {
            return null;
        }

        if ($room['white'] === $playerId) {
            return 'white';
        }

        if ($room['black'] === $playerId) {
            return 'black';
        }

        return null;
    }

    /**
     * Turno actual derivado de cuántos movimientos hay: 0 = blancas, 1 = negras.
     */
    public function turn(string $code): ?string
    {
        $room = $this->get($code);

        if ($room === null) {
            return null;
        }

        return count($room['moves']) % 2 === 0 ? 'white' : 'black';
    }

    /**
     * @param  array{from: string, to: string, promotion: ?string}  $move
     */
    public function addMove(string $code, array $move): bool
    {
        $room = $this->get($code);

        if ($room === null) {
            return false;
        }

        $room['moves'][] = $move;
        $this->put($code, $room);

        return true;
    }

    /**
     * Guarda un mensaje de chat y conserva sólo los últimos 100.
     *
     * @param  array{color: string, text: string, at: int}  $message
     */
    public function addMessage(string $code, array $message): bool
    {
        $room = $this->get($code);

        if ($room === null) {
            return false;
        }

        $messages = $room['messages'] ?? [];
        $messages[] = $message;
        $room['messages'] = array_slice($messages, -100);
        $this->put($code, $room);

        return true;
    }

    public function reset(string $code): bool
    {
        $room = $this->get($code);

        if ($room === null) {
            return false;
        }

        $room['moves'] = [];
        $this->put($code, $room);

        return true;
    }

    private function put(string $code, array $room): void
    {
        Cache::put(self::KEY_PREFIX.$this->normalize($code), $room, self::TTL_SECONDS);
    }

    private function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }

    private function randomCode(): string
    {
        $max = strlen(self::CODE_ALPHABET) - 1;
        $code = '';

        for ($i = 0; $i < 6; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, $max)];
        }

        return $code;
    }
}
