<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Emisor</title>
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-300 text-base-content antialiased">
<main class="mx-auto flex min-h-screen w-full max-w-3xl flex-col justify-center gap-6 px-4 py-10 sm:px-6">
<header class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <span class="badge badge-primary badge-outline mb-3">Control remoto</span>
        <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Emisor</h1>
        <p class="mt-2 text-sm text-base-content/60">Actualizá el contenido que aparece en la pantalla receptora.</p>
    </div>
    <a href="{{ route('receptor') }}" class="btn btn-ghost btn-sm">Abrir receptor <span aria-hidden="true">↗</span></a>
</header>
@if (session('ok'))
<div class="alert alert-success" role="status">{{ session('ok') }}</div>
@endif
@if ($errors->any())
<div class="alert alert-error" role="alert">{{ $errors->first() }}</div>
@endif
<form class="card border border-base-content/10 bg-base-200/70 shadow-xl shadow-black/10" method="POST" action="{{ route('emitir') }}">
@csrf
<div class="card-body gap-5">
<label class="block w-full">
    <span class="mb-2 block text-sm font-semibold">Texto a mostrar</span>
    <input type="text" name="newText" placeholder="Ej: Ahora atendiendo: A-037"
           required class="input w-full">
</label>
<label class="block w-full">
    <span class="mb-2 block text-sm font-semibold">CSS inline <span class="font-normal text-base-content/50">(opcional)</span></span>
    <textarea name="newStyle" rows="6" placeholder="Ej:
background:#111;color:#fff;padding:24px;border-radius:16px;font-size:28px;text-align:
center;"
              class="textarea min-h-36 w-full font-mono text-sm"></textarea>
</label>
<button type="submit" class="btn btn-primary sm:btn-wide">Emitir actualización</button>
</div>
</form>
<p class="text-center text-sm text-base-content/55">
    Abrí <code class="badge badge-ghost font-mono">/receptor</code> en otra pestaña para ver los cambios en tiempo real.
</p>
</main>
</body>
</html>