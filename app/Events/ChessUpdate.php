<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Transmite el avance de una sala de ajedrez a su canal público chess.{codigo}.
 *
 * Sigue la misma forma que RemoteStyleUpdate (broadcastAs),
 * sólo que en lugar de texto/estilo envía movimientos o una reiniciada.
 */
class ChessUpdate implements ShouldBroadcastNow
{
    /**
     * @param  string  $roomCode  Código de la sala.
     * @param  string  $type  'move' cuando avanza un movimiento, 'reset' al reiniciar.
     * @param  string|null  $from  Casilla de origen (sólo en movimientos).
     * @param  string|null  $to  Casilla de destino (sólo en movimientos).
     * @param  string|null  $promotion  Promoción elegida: q|r|b|n.
     * @param  string|null  $color  'white'|'black' de quien emitió.
     * @param  string|null  $clientId  Id de la pestaña emisora, para que no aplique dos veces su propio movimiento.
     */
    public function __construct(
        public string $roomCode,
        public string $type,
        public ?string $from = null,
        public ?string $to = null,
        public ?string $promotion = null,
        public ?string $color = null,
        public ?string $clientId = null,
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
        return 'ChessUpdate';
    }
}
