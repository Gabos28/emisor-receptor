<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Mensaje de chat de una sala, difundido por el mismo canal público chess.{codigo}.
 *
 * Mismo patrón que RemoteStyleUpdate y ChessUpdate: broadcastAs.
 */
class ChessMessage implements ShouldBroadcastNow
{
    /**
     * @param  string  $roomCode  Código de la sala.
     * @param  string  $text  Texto del mensaje.
     * @param  string  $color  'white'|'black' de quien lo envía.
     * @param  string|null  $clientId  Id de la pestaña emisora, para no duplicar su propio mensaje.
     * @param  int  $at  Marca de tiempo en milisegundos.
     */
    public function __construct(
        public string $roomCode,
        public string $text,
        public string $color,
        public ?string $clientId = null,
        public int $at = 0,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('chess.'.$this->roomCode)];
    }

    public function broadcastAs(): string
    {
        return 'ChessMessage';
    }
}
