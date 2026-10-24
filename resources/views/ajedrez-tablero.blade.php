<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ajedrez · {{ $room }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .cb-board {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            width: 100%;
            aspect-ratio: 1 / 1;
            border-radius: 16px;
            overflow: hidden;
            container-type: inline-size;
            touch-action: none;
            box-shadow: 0 24px 60px -18px rgba(0, 0, 0, .75);
        }

        .cb-square {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            user-select: none;
        }

        .cb-light { background: #ebecd0; }
        .cb-dark { background: #739552; }

        .cb-last { box-shadow: inset 0 0 0 9999px rgba(246, 230, 74, .35); }
        .cb-sel { box-shadow: inset 0 0 0 4px rgba(255, 255, 255, .9); }
        .cb-hover { box-shadow: inset 0 0 0 4px rgba(56, 189, 248, .95); }
        .cb-origin .cb-piece { opacity: .25; }

        .cb-ghost {
            position: fixed;
            z-index: 60;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            transform: translate(-50%, -50%);
            filter: drop-shadow(0 10px 14px rgba(0, 0, 0, .55));
        }

        .cb-check {
            background-image: radial-gradient(circle at center, rgba(239, 68, 68, .95) 0%, rgba(239, 68, 68, .5) 45%, transparent 72%);
        }

        .cb-hint {
            position: absolute;
            pointer-events: none;
        }

        .cb-hint-dot {
            width: 30%;
            height: 30%;
            border-radius: 9999px;
            background: rgba(15, 23, 42, .28);
        }

        .cb-hint-cap {
            inset: 7%;
            border-radius: 9999px;
            box-shadow: inset 0 0 0 6px rgba(15, 23, 42, .3);
        }

        .cb-piece {
            font-size: clamp(22px, 9.5cqw, 60px);
            line-height: 1;
            position: relative;
            z-index: 1;
            transition: transform .12s ease;
            cursor: grab;
        }

        .cb-square:hover .cb-piece { transform: scale(1.06); }
        .cb-square:active .cb-piece { cursor: grabbing; }

        .cb-w {
            color: #f8fafc;
            text-shadow: 0 1px 0 #1e293b, 1px 0 0 #1e293b, -1px 0 0 #1e293b, 0 -1px 0 #1e293b, 0 3px 6px rgba(0, 0, 0, .4);
        }

        .cb-b {
            color: #16202f;
            text-shadow: 0 1px 1px rgba(255, 255, 255, .25), 0 3px 7px rgba(0, 0, 0, .45);
        }

        .cb-coord {
            position: absolute;
            font-weight: 700;
            font-size: clamp(8px, 1.7cqw, 13px);
            line-height: 1;
            pointer-events: none;
        }

        .cb-coord-rank { top: 4px; left: 4px; }
        .cb-coord-file { bottom: 4px; right: 4px; }

        .cb-coord-dark { color: #739552; }
        .cb-coord-light { color: #ebecd0; }

        .cb-history { scrollbar-width: thin; scrollbar-color: rgba(255, 255, 255, .25) transparent; }
    </style>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
<div id="chess-app" class="mx-auto max-w-6xl px-4 py-6 lg:py-10">
    <header class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('chess.lobby') }}"
               class="btn btn-ghost btn-sm text-slate-300">
                ← Salir
            </a>
            <div>
                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Sala</p>
                <p class="badge badge-outline badge-lg font-mono font-bold tracking-[0.2em]">{{ $room }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button id="copy-room" type="button"
                    class="btn btn-outline btn-primary btn-sm">
                Copiar código
            </button>
            <a href="{{ route('chess.lobby') }}"
               class="btn btn-ghost btn-sm text-slate-300">
                Nueva partida
            </a>
        </div>
    </header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
        <section class="mx-auto w-full max-w-[640px]">
            <div class="card border border-base-300/60 bg-base-200/40 p-2 shadow-2xl sm:p-3">
                <div id="board" class="cb-board" aria-label="Tablero de ajedrez"></div>
            </div>

            <p id="hint" class="mt-4 text-center text-sm text-slate-500"></p>
            <p id="sync-status" class="mt-2 text-center text-xs text-base-content/50" role="status" aria-live="polite">
                Sincronización automática activa
            </p>
        </section>

        <aside class="flex flex-col gap-4">
            <div class="card border border-base-300/60 bg-base-200/40 shadow-xl">
                <div class="card-body gap-2 p-4">
                    <p class="text-xs uppercase tracking-[0.2em] text-base-content/50">Estado</p>
                    <div id="status" class="mt-1 flex items-center gap-2 text-lg font-semibold text-base-content">
                        Cargando…
                    </div>
                    <div id="players" class="mt-2 space-y-1 text-sm text-base-content/70"></div>
                </div>
            </div>

            <div class="card border border-base-300/60 bg-base-200/40 shadow-xl">
                <div class="card-body gap-3 p-4">
                    <p class="text-xs uppercase tracking-[0.2em] text-base-content/50">Chat</p>
                    <ul id="chat-messages"
                        class="cb-history flex max-h-72 flex-col gap-2 overflow-y-auto pr-1 text-left">
                        <li id="chat-empty" class="text-xs text-base-content/50">Todavía no hay mensajes.</li>
                    </ul>
                    <form id="chat-form" class="flex gap-2">
                        <input id="chat-input" maxlength="500" autocomplete="off" placeholder="Escribí un mensaje…"
                               class="input input-sm min-w-0 flex-1 bg-base-100/70">
                        <button type="submit" class="btn btn-primary btn-sm">
                            Enviar
                        </button>
                    </form>
                    <p id="chat-note" class="hidden text-xs text-base-content/50">Sólo los jugadores pueden escribir.</p>
                </div>
            </div>

            <div class="card border border-base-300/60 bg-base-200/40 shadow-xl">
                <div class="card-body gap-2 p-4">
                    <p class="text-xs uppercase tracking-[0.2em] text-base-content/50">Capturas</p>
                    <div id="captured" class="space-y-1 text-sm text-base-content/80">—</div>
                </div>
            </div>

            <div class="card border border-base-300/60 bg-base-200/40 shadow-xl">
                <div class="card-body gap-2 p-4">
                    <p class="text-xs uppercase tracking-[0.2em] text-base-content/50">Movimientos</p>
                    <ol id="history"
                        class="cb-history max-h-64 space-y-1 overflow-y-auto pr-1 font-mono text-sm text-base-content/80">
                        <li class="text-base-content/50">Todavía no hay jugadas.</li>
                    </ol>
                </div>
            </div>

            <button id="reset" type="button"
                    class="btn btn-outline btn-primary w-full">
                Nueva partida
            </button>
        </aside>
    </div>
</div>

<script type="application/json" id="chess-config">
    {!! json_encode([
        'room' => $room,
        'color' => $color,
        'moves' => $moves,
        'messages' => $messages,
        'whiteJoined' => $whiteJoined,
        'blackJoined' => $blackJoined,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
</script>
</body>
</html>
