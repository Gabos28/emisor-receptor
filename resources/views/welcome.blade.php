<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Emisor y Receptor') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-300 text-base-content antialiased">
<main class="relative isolate flex min-h-screen flex-col items-center justify-center overflow-hidden px-4 py-12">
    <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-primary/15 via-base-300 to-base-300"></div>
    <div class="w-full max-w-5xl">
        <header class="mb-12 text-center">
            <span class="badge badge-primary badge-outline mb-5">Herramientas en tiempo real</span>
            <h1 class="text-4xl font-bold tracking-tight sm:text-6xl">{{ config('app.name', 'Emisor y Receptor') }}</h1>
            <p class="mx-auto mt-4 max-w-xl text-base leading-7 text-base-content/60">
                Elegí una experiencia para empezar: jugá una partida o transmite contenido entre pantallas.
            </p>
        </header>

        <section class="grid gap-5 md:grid-cols-3" aria-label="Aplicaciones">
            <a href="{{ route('chess.lobby') }}"
               class="card border border-base-content/10 bg-base-200/70 shadow-xl shadow-black/10 transition duration-200 hover:-translate-y-1 hover:border-primary/40 hover:shadow-primary/10">
                <div class="card-body">
                    <span class="text-4xl text-primary" aria-hidden="true">♞</span>
                    <h2 class="card-title mt-3">Ajedrez en vivo</h2>
                    <p class="flex-1 text-sm leading-6 text-base-content/60">
                        Creá una sala, invitá a otra persona y seguí la partida en tiempo real.
                    </p>
                    <span class="btn btn-primary mt-4">Abrir ajedrez <span aria-hidden="true">→</span></span>
                </div>
            </a>

            <a href="{{ route('emisor') }}"
               class="card border border-base-content/10 bg-base-200/70 shadow-xl shadow-black/10 transition duration-200 hover:-translate-y-1 hover:border-primary/40 hover:shadow-primary/10">
                <div class="card-body">
                    <span class="text-4xl text-primary" aria-hidden="true">↗</span>
                    <h2 class="card-title mt-3">Emisor</h2>
                    <p class="flex-1 text-sm leading-6 text-base-content/60">
                        Publicá un mensaje y personalizá cómo se muestra en la pantalla receptora.
                    </p>
                    <span class="btn btn-outline btn-primary mt-4">Abrir emisor <span aria-hidden="true">→</span></span>
                </div>
            </a>

            <a href="{{ route('receptor') }}"
               class="card border border-base-content/10 bg-base-200/70 shadow-xl shadow-black/10 transition duration-200 hover:-translate-y-1 hover:border-primary/40 hover:shadow-primary/10">
                <div class="card-body">
                    <span class="text-4xl text-primary" aria-hidden="true">▣</span>
                    <h2 class="card-title mt-3">Receptor</h2>
                    <p class="flex-1 text-sm leading-6 text-base-content/60">
                        Mostrá los mensajes que envía el emisor desde otra pestaña o dispositivo.
                    </p>
                    <span class="btn btn-outline btn-primary mt-4">Abrir receptor <span aria-hidden="true">→</span></span>
                </div>
            </a>
        </section>
    </div>
</main>
</body>
</html>
