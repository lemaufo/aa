@php
    $maximo = (int) config('aa.max_medidas', 11);
    $renglones = collect($pedido->cortes_solicitados ?? [])->take($maximo)->values();
    $n = $renglones->count();
    // Hasta 7 renglones, letra cómoda; de 8 a 11, letra compacta para que todo quepa sin ocultar nada.
    $denso = $n > 7;
    $totalMetros = $renglones->sum(fn ($c) => $c['piezas'] * $c['medida']);
    $elaboro = $pedido->elaboro() ?? auth()->user()->name;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Etiqueta {{ $pedido->folio }}</title>
    <style>
        @page { size: 100mm 150mm; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; background: #e5e7eb; font-family: "Source Sans 3", Arial, sans-serif; color: #111; }
        .barra { display: flex; gap: 8px; justify-content: center; padding: 12px; }
        .barra button { font: 600 15px Arial, sans-serif; padding: 8px 16px; border: 0; border-radius: 6px; background: #f97316; color: #fff; cursor: pointer; }
        .etiqueta { width: 100mm; height: 150mm; margin: 0 auto 24px; padding: 4mm; background: #fff; display: flex; flex-direction: column; gap: 2mm; overflow: hidden; border: 1px solid #111; }
        .marca { display: flex; justify-content: space-between; align-items: center; gap: 3mm; border-bottom: 2px solid #111; padding-bottom: 1mm; font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
        .marca img { height: 14mm; width: auto; max-width: 56mm; display: block; }
        .denso .marca img { height: 11mm; }
        .folio { font-family: "Barlow Condensed", Arial Narrow, Arial, sans-serif; font-weight: 700; font-size: 40px; line-height: 1; letter-spacing: .02em; }
        .codigo { display: flex; align-items: center; justify-content: space-between; gap: 3mm; }
        .codigo svg { height: 16mm; max-width: 52mm; }
        .cliente { font-size: 15px; font-weight: 700; line-height: 1.15; overflow-wrap: anywhere; }
        .dato { font-size: 11px; line-height: 1.2; }
        .dato b { font-weight: 700; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border: 1px solid #111; padding: 1px 4px; text-align: right; line-height: 1.15; }
        th { background: #eee; font-size: 10px; text-transform: uppercase; }
        td:first-child, th:first-child { text-align: center; }
        tfoot td { font-weight: 700; background: #f3f3f3; }
        .denso table { font-size: 11px; }
        .denso th { font-size: 9px; }
        .denso td, .denso th { padding-top: 0; padding-bottom: 0; }
        .denso .cliente { font-size: 13px; }
        .denso .folio { font-size: 34px; }
        .notas { font-size: 10px; line-height: 1.2; overflow-wrap: anywhere; }
        .pie { margin-top: auto; display: flex; justify-content: space-between; gap: 3mm; border-top: 1px solid #111; padding-top: 1mm; font-size: 10px; }
        @media print {
            html, body { background: #fff; }
            .barra { display: none; }
            .etiqueta { margin: 0; border: 0; }
        }
    </style>
</head>
<body>
    <div class="barra">
        <button type="button" onclick="window.print()">Imprimir</button>
    </div>

    <main class="etiqueta {{ $denso ? 'denso' : '' }}" id="etiqueta">
        <div class="marca">
            {{-- El logo se toma de public/ (config aa.logo). Si el archivo no existe, se muestra el nombre en texto. --}}
            <img src="{{ asset(config('aa.logo', 'img/logo-aa.png')) }}" alt="Aceros el Águila"
                 onerror="this.replaceWith(document.createTextNode('Aceros el Águila'))">
            <span>Orden de producción</span>
        </div>

        <div class="codigo">
            <span class="folio">{{ $pedido->folio }}</span>
            <svg id="codigo-barras" data-folio="{{ $pedido->folio }}" aria-label="Código de barras {{ $pedido->folio }}"></svg>
        </div>

        <div class="cliente">{{ $pedido->cliente }}</div>
        <div class="dato"><b>{{ $pedido->material }}</b></div>

        <table>
            <thead>
                <tr><th>Pzas</th><th>Largo (m)</th><th>Subtotal (m)</th></tr>
            </thead>
            <tbody>
                @foreach ($renglones as $c)
                    <tr>
                        <td>{{ $c['piezas'] }}</td>
                        <td>{{ number_format($c['medida'], 2) }}</td>
                        <td>{{ number_format($c['piezas'] * $c['medida'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr><td>{{ $pedido->totalPiezas() }}</td><td>Total</td><td>{{ number_format($totalMetros, 2) }}</td></tr>
            </tfoot>
        </table>

        <div class="dato">
            <b>Peso teórico:</b> {{ number_format($pedido->pesoTeorico(), 2) }} kg
            @if ($pedido->fecha_prometida)
                &nbsp;·&nbsp; <b>Entrega:</b> {{ $pedido->fecha_prometida->format('d/m/Y') }}
            @endif
        </div>
        <div class="dato">
            <b>Destino:</b> {{ $pedido->forma_entrega?->label() ?? 'Sin definir' }} · {{ $pedido->sucursal }}
        </div>
        @if ($pedido->instrucciones)
            <div class="notas"><b>Notas:</b> {{ $pedido->instrucciones }}</div>
        @endif

        <div class="pie">
            <span>Elaboró: {{ $elaboro }}</span>
            <span>Autorizó: {{ $pedido->autorizo() ?? '—' }}</span>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script>
        (function () {
            var svg = document.getElementById('codigo-barras');
            if (window.JsBarcode && svg) {
                JsBarcode(svg, svg.dataset.folio, { format: 'CODE128', displayValue: false, margin: 0, height: 60, width: 2 });
            }
            if (location.search.indexOf('auto=1') !== -1) { setTimeout(function () { window.print(); }, 400); }
        })();
    </script>
</body>
</html>