<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Autorizar este equipo</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-plancha text-tinta antialiased">
    <main class="mx-auto grid min-h-screen max-w-md content-center gap-6 px-4 py-10">
        <section class="panel px-6 py-6">
            <h1 class="font-display text-3xl font-semibold">Este equipo no está autorizado</h1>
            <p class="mt-2 text-acero">Pide al administrador un código de un solo uso y escríbelo aquí. Solo se hace una vez por equipo.</p>

            <form method="POST" action="{{ route('dispositivo.activar') }}" class="mt-6 grid gap-4">
                @csrf
                <x-aa.campo label="Código del equipo" for="codigo" :error="$errors->first('codigo')">
                    <input id="codigo" name="codigo" type="text" class="input font-display text-2xl font-semibold uppercase" placeholder="AB12-CD34"
                           autocomplete="off" autocapitalize="characters" spellcheck="false" required autofocus>
                </x-aa.campo>
                <button type="submit" class="btn btn-primario w-full">Autorizar equipo</button>
            </form>
        </section>
    </main>
</body>
</html>