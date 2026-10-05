<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => 'Etiqueta '.$pedido->folio])
    <style>
        @page { size: 100mm 150mm; margin: 0; }
        body { background: #d9dcdf; }
        .hoja {
            width: 100mm; height: 150mm; margin: 0 auto; background: #fff;
            padding: 6mm; display: flex; flex-direction: column; gap: 3.5mm;
            box-sizing: border-box; border: 1px solid #b4b9bd;
        }
        #qr svg { width: 34mm; height: 34mm; display: block; }
        @media print {
            body { background: #fff; }
            .hoja { border: 0; margin: 0; }
            .no-imprimir { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="no-imprimir mx-auto flex max-w-[100mm] flex-wrap items-center justify-between gap-3 px-1 py-4">
        <a href="{{ route('pedidos.show', $pedido) }}" class="btn btn-secundario min-h-10">Volver al pedido</a>
        <button type="button" onclick="window.print()" class="btn btn-primario min-h-10">
            <x-aa.icon name="printer" /> Imprimir etiqueta
        </button>
    </div>

    <article class="hoja" aria-label="Etiqueta del pedido {{ $pedido->folio }}">
        <img src="{{ asset('img/logo.png') }}" alt="Aceros el Águila" style="width: 46mm">

        <div style="border-top: 0.6mm solid #25272a; padding-top: 2.5mm;">
            <p style="font-size: 3.2mm; color: #606060;">Folio del traspaso</p>
            <p class="folio" style="font-size: 17mm; line-height: 1;">{{ $pedido->folio }}</p>
        </div>

        <div>
            <p style="font-size: 5.2mm; font-weight: 700; line-height: 1.15;">{{ $pedido->cliente }}</p>
            <p style="font-size: 3.8mm; color: #606060;">{{ $pedido->material }}</p>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2mm 4mm; font-size: 3.8mm;">
            <div><p style="font-size: 3.1mm; color: #606060;">Metros</p><p class="folio" style="font-size: 7mm;">{{ number_format($pedido->metros_traspaso, 2) }} m</p></div>
            <div><p style="font-size: 3.1mm; color: #606060;">Peso teórico</p><p class="folio" style="font-size: 7mm;">{{ number_format($pedido->peso_teorico ?? $pedido->pesoTeorico(), 2) }} kg</p></div>
            <div style="grid-column: 1 / -1;"><p style="font-size: 3.1mm; color: #606060;">Entrega</p><p style="font-weight: 600;">{{ $pedido->forma_entrega?->label() ?? 'Por definir' }}, {{ $pedido->sucursal }}</p></div>
        </div>

        <div style="margin-top: auto; display: flex; align-items: flex-end; justify-content: space-between; gap: 4mm;">
            <p style="font-size: 3.2mm; color: #606060; max-width: 52mm;">
                Escanea el código para recibir, enviar o entregar este pedido.<br>
                Impreso {{ now()->translatedFormat('j M Y, H:i') }}
            </p>
            <div id="qr" aria-label="Código QR con el folio {{ $pedido->folio }}"></div>
        </div>
    </article>

    <script>
        window.addEventListener('load', function () {
            if (window.QRCode) {
                QRCode.toString(@js($pedido->folio), { type: 'svg', margin: 0, errorCorrectionLevel: 'M' })
                    .then(function (svg) { document.getElementById('qr').innerHTML = svg; });
            }
        });
    </script>
</body>
</html>