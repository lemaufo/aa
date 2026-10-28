@php
    $usuario = auth()->user();

    if ($usuario->rol === \App\Enums\Rol::Consulta) {
        $enlaces = [
            ['ruta' => 'rastreo', 'patron' => 'rastreo', 'texto' => 'Rastreo', 'icono' => 'magnifying-glass'],
        ];
    } else {
        $enlaces = [
            ['ruta' => 'inicio', 'patron' => 'inicio', 'texto' => 'Inicio', 'icono' => 'home'],
            ['ruta' => 'pedidos.index', 'patron' => 'pedidos.*', 'texto' => 'Pedidos', 'icono' => 'clipboard-document-list'],
        ];

        if ($usuario->rol === \App\Enums\Rol::Admin) {
            $enlaces[] = ['ruta' => 'usuarios', 'patron' => 'usuarios', 'texto' => 'Usuarios y permisos', 'icono' => 'user-group'];
            $enlaces[] = ['ruta' => 'articulos', 'patron' => 'articulos', 'texto' => 'Artículos', 'icono' => 'cube'];
        }
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title ?? null])
</head>
<body>
    <div class="lg:grid lg:grid-cols-[17rem_minmax(0,1fr)]">

        {{-- Barra lateral (escritorio) --}}
        <aside class="hidden border-r border-linea bg-white px-5 py-6 lg:sticky lg:top-0 lg:flex lg:h-dvh lg:flex-col">
            <a href="{{ route('inicio') }}" wire:navigate class="block px-1" aria-label="Aceros el Águila, ir al inicio">
                <img src="{{ asset('img/logo.png') }}" alt="Aceros el Águila" class="w-44">
            </a>

            <nav class="mt-8 grid gap-1" aria-label="Principal">
                @foreach ($enlaces as $e)
                    <a href="{{ route($e['ruta']) }}" wire:navigate class="nav-link" @if (request()->routeIs($e['patron'])) aria-current="page" @endif>
                        <x-aa.icon :name="$e['icono']" /> {{ $e['texto'] }}
                    </a>
                @endforeach
            </nav>

            <div class="mt-auto border-t border-linea pt-4">
                <div class="flex items-center gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-naranja font-display text-lg font-semibold text-tinta" aria-hidden="true">{{ $usuario->initials() }}</span>
                    <div class="min-w-0">
                        <p class="truncate font-semibold leading-tight">{{ $usuario->name }}</p>
                        <p class="truncate text-[15px] text-acero">{{ $usuario->rol->label() }}{{ $usuario->sucursal ? ', '.$usuario->sucursal : '' }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="btn btn-secundario w-full">
                        <x-aa.icon name="arrow-right-on-rectangle" /> Salir
                    </button>
                </form>
            </div>
        </aside>

        <div class="min-w-0">
            {{-- Barra superior (celular y tablet) --}}
            <header class="sticky top-0 z-20 flex items-center justify-between border-b border-linea bg-white px-4 py-2.5 lg:hidden">
                <a href="{{ route('inicio') }}" wire:navigate aria-label="Aceros el Águila, ir al inicio">
                    <img src="{{ asset('img/logo.png') }}" alt="Aceros el Águila" class="h-12 w-auto">
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-secundario min-h-10 px-3" aria-label="Salir">
                        <x-aa.icon name="arrow-right-on-rectangle" />
                    </button>
                </form>
            </header>

            <main class="mx-auto w-full max-w-5xl px-4 pb-28 pt-6 lg:px-10 lg:pb-14 lg:pt-10">
                {{ $slot }}
            </main>
        </div>
    </div>

    {{-- Navegación inferior (celular y tablet) --}}
    <nav class="fixed inset-x-0 bottom-0 z-20 border-t border-linea bg-white pb-[env(safe-area-inset-bottom)] lg:hidden" aria-label="Principal">
        <ul class="mx-auto grid max-w-md" style="grid-template-columns: repeat({{ count($enlaces) }}, minmax(0, 1fr))">
            @foreach ($enlaces as $e)
                <li>
                    <a href="{{ route($e['ruta']) }}" wire:navigate
                       @class(['flex min-h-16 flex-col items-center justify-center gap-0.5 px-2 text-[14px] font-semibold', 'text-tinta' => request()->routeIs($e['patron']), 'text-acero' => ! request()->routeIs($e['patron'])])
                       @if (request()->routeIs($e['patron'])) aria-current="page" @endif>
                        <span @class(['rounded-full px-4 py-1', 'bg-naranja-tinte' => request()->routeIs($e['patron'])])>
                            <x-aa.icon :name="$e['icono']" />
                        </span>
                        {{ $e['texto'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <x-aa.toasts />
</body>
</html>