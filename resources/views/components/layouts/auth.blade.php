<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => 'Acceso'])
</head>
<body class="bg-white">
    <main class="grid min-h-dvh lg:grid-cols-[minmax(28rem,34rem)_1fr]">

        <section class="flex flex-col px-6 py-8 sm:px-12 lg:px-14">
            <img src="{{ asset('img/logo.png') }}" alt="Aceros el Águila" class="w-44 sm:w-52">

            <div class="my-auto w-full max-w-sm py-10">
                {{ $slot }}
            </div>

            <p class="text-[15px] text-acero">Seguimiento de pedidos especiales</p>
        </section>

        <aside class="relative hidden overflow-hidden bg-[#3a3c3f] text-white lg:block" aria-hidden="true">
            <div class="cinta-vertical absolute inset-y-0 left-0 w-8"></div>
            <div class="absolute bottom-0 left-0 top-0 w-1.5 bg-naranja" style="height: 62%"></div>

            <div class="flex h-full flex-col justify-center px-20 py-16">
                <p class="max-w-xl font-display text-[64px] font-semibold leading-[0.95]">
                    Cada pedido, de la venta cobrada a la entrega.
                </p>

                <ol class="mt-14 grid max-w-md gap-6">
                    @foreach ([
                        ['Cobrado', 'La sucursal liga el traspaso con la venta.'],
                        ['Producido', 'Producción corta, etiqueta y sube la evidencia.'],
                        ['En CEDIS', 'Se pesa y se valida contra el peso teórico.'],
                        ['En ruta', 'Sale con repartidor o va a la sucursal.'],
                        ['Entregado', 'La sucursal cierra la venta en Microsip.'],
                    ] as [$etapa, $texto])
                        <li class="grid grid-cols-[7.5rem_1fr] items-baseline gap-4 border-t border-white/20 pt-3">
                            <span class="font-display text-2xl font-semibold text-naranja">{{ $etapa }}</span>
                            <span class="text-white/80">{{ $texto }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </aside>
    </main>
</body>
</html>