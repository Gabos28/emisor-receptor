<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ajedrez en tiempo real</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-300 text-base-content antialiased">
<div class="relative isolate flex min-h-screen flex-col items-center justify-center overflow-hidden px-4 py-12">
    <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-primary/15 via-base-300 to-base-300"></div>
    <main class="flex w-full max-w-4xl flex-col items-center gap-8">
        <header class="max-w-xl text-center">
            <span class="badge badge-primary badge-outline mb-4 gap-2 px-3 py-3">
                <span aria-hidden="true">●</span>
                Partidas en vivo
            </span>
            <div class="mb-3 text-6xl text-primary" aria-hidden="true">♞</div>
            <h1 class="text-3xl font-bold tracking-tight sm:text-5xl">Ajedrez en tiempo real</h1>
            <p class="mx-auto mt-3 max-w-lg text-sm leading-6 text-base-content/65 sm:text-base">
                Creá una partida y compartile el código al otro jugador, o unite con un código
                existente. Las piezas se mueven al instante por WebSockets.
            </p>
        </header>

        @if ($errors->any())
            <div class="alert alert-error w-full max-w-2xl" role="alert">
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <div class="grid w-full gap-5 sm:grid-cols-2">
            <form method="POST" action="{{ route('chess.create') }}"
                  class="card border border-base-content/10 bg-base-200/70 shadow-xl shadow-black/10 backdrop-blur">
                @csrf
                <div class="card-body">
                    <span class="inline-flex items-center gap-2 text-2xl" aria-hidden="true">
                        <span class="text-base-content">♟</span>
                        <span class="text-base-content/45">♟</span>
                    </span>
                    <h2 class="card-title mt-2">Crear partida</h2>
                    <p class="flex-1 text-sm leading-6 text-base-content/65">
                        Jugás con las <span class="font-medium text-base-content">blancas</span> y empezás vos.
                    </p>
                    <button type="submit" class="btn btn-primary mt-4 w-full">
                        Crear sala
                    </button>
                </div>
            </form>

            <form method="POST" action="{{ route('chess.join') }}"
                  class="card border border-base-content/10 bg-base-200/70 shadow-xl shadow-black/10 backdrop-blur">
                @csrf
                <div class="card-body">
                    <h2 class="card-title">Unirse a una partida</h2>
                    <label for="code" class="mt-1 text-sm text-base-content/65">Código de sala</label>
                    <input id="code" name="code" value="{{ old('code') }}" maxlength="12" autocomplete="off"
                           placeholder="ABC123"
                           class="input mt-1 w-full font-mono text-lg uppercase tracking-[0.2em]">
                    <button type="submit" class="btn btn-outline btn-primary mt-3 w-full">
                        Entrar
                    </button>
                </div>
            </form>
        </div>

        <p class="text-center text-xs text-base-content/50">
            Demo original de
            <a href="{{ route('emisor') }}" class="link link-hover link-primary">emisor/receptor</a>.
        </p>
    </main>
</div>
</body>
</html>
