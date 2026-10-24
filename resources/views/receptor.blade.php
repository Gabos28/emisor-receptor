<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Receptor</title>
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-300 text-base-content antialiased">
<main class="mx-auto flex min-h-screen w-full max-w-4xl flex-col justify-center gap-8 px-4 py-10 sm:px-6">
    <header class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <span class="badge badge-success badge-outline mb-3 gap-2"><span aria-hidden="true">●</span> En pantalla</span>
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Receptor</h1>
            <p class="mt-2 text-sm text-base-content/60">Esta pantalla se actualiza cuando el emisor publica un mensaje.</p>
        </div>
        <a href="{{ route('emisor') }}" class="btn btn-ghost btn-sm">Ir al emisor <span aria-hidden="true">↗</span></a>
    </header>
    <section class="card min-h-72 border border-base-content/10 bg-base-200/70 shadow-2xl shadow-black/10">
        <div id="target-div" class="card-body flex min-h-72 items-center justify-center rounded-2xl border border-dashed border-base-content/20 p-8 text-center text-2xl font-semibold sm:text-4xl">
            Esperando mensaje...
        </div>
    </section>
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
const targetDiv = document.getElementById('target-div');
// Suscripción al canal público y escucha del evento
window.Echo.channel('style-channel')
.listen('.RemoteStyleUpdate', (e) => {
// El nombre con punto (.) escucha usando broadcastAs()
targetDiv.innerText = e.newText ?? '';
targetDiv.style.cssText = e.newStyle ?? '';
});
});
</script>
</body>
</html>