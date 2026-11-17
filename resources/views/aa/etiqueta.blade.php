@php
    $maximo = (int) config('aa.max_medidas', 11);
    $renglones = collect($pedido->cortes_solicitados ?? [])->take($maximo)->values();
    $totalMetros = $renglones->sum(fn ($c) => $c['piezas'] * $c['medida']);
    $elaboro = $pedido->elaboro() ?? auth()->user()->name;

    // Tamaño por defecto (config aa.etiqueta: "4x6in", "100x150mm"...), en mm. Si no se entiende, 4×6 in.
    $tam = preg_match('/^\s*(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)\s*(mm|in)?\s*$/i', (string) config('aa.etiqueta', '4x6in'), $m)
        ? array_map(fn ($v) => round((float) $v * (strtolower($m[3] ?? 'in') === 'mm' ? 1 : 25.4), 1), [$m[1], $m[2]])
        : [101.6, 152.4];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Etiqueta {{ $pedido->folio }}</title>
    {{-- El tamaño de la hoja lo pone el script según el tamaño elegido. --}}
    <style id="pagina">@page { size: {{ $tam[0] }}mm {{ $tam[1] }}mm; margin: 0; }</style>
    <style>
        /*
         * Etiqueta térmica de cualquier tamaño. Todas las medidas del contenido van multiplicadas por --k
         * (escala) y el script busca la --k más grande con la que todo cabe dentro de la etiqueta, sin cortar
         * ni deformar nada. Vertical: un solo bloque. Horizontal (más ancha que alta): la tabla va a la derecha.
         * Si ni así cabe (etiquetas muy chicas), sale solo lo esencial: folio, código de barras y resumen.
         */
        * { box-sizing: border-box; }
        html, body { margin: 0; background: #e5e7eb; font-family: "Source Sans 3", Arial, sans-serif; color: #111; }

        .barra { display: flex; flex-wrap: wrap; gap: 8px 12px; align-items: end; justify-content: center; padding: 12px; font: 14px Arial, sans-serif; }
        .barra label { display: grid; gap: 2px; font-weight: 600; }
        .barra select, .barra input { font: 15px Arial, sans-serif; padding: 6px 8px; border: 1px solid #9ca3af; border-radius: 6px; background: #fff; }
        .barra input { width: 6.5em; }
        .barra button { font: 600 15px Arial, sans-serif; padding: 8px 16px; border: 0; border-radius: 6px; background: #f97316; color: #fff; cursor: pointer; }
        .barra .nota { flex-basis: 100%; text-align: center; color: #4b5563; font-size: 13px; }

        .etiqueta {
            --k: 1;
            --ancho: {{ $tam[0] }}mm;
            --alto: {{ $tam[1] }}mm;
            /* Margen interior: proporcional a la etiqueta, nunca menos de 2 mm (zona que la térmica no imprime bien). */
            --margen: max(2mm, calc(min(var(--ancho), var(--alto)) * .035));
            width: var(--ancho); height: var(--alto); margin: 0 auto 24px; padding: var(--margen);
            background: #fff; overflow: hidden; border: 1px solid #111;
            display: flex; flex-direction: column; gap: calc(var(--k) * 2mm);
        }
        .bloque { display: flex; flex-direction: column; gap: calc(var(--k) * 2mm); min-width: 0; }

        .marca { display: flex; justify-content: space-between; align-items: center; gap: calc(var(--k) * 3mm); border-bottom: calc(var(--k) * 2px) solid #111;
                 padding-bottom: calc(var(--k) * 1mm); font-size: calc(var(--k) * 10px); font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
        .marca img { height: calc(var(--k) * 14mm); width: auto; max-width: 60%; display: block; object-fit: contain; }
        .folio { font-family: "Barlow Condensed", "Arial Narrow", Arial, sans-serif; font-weight: 700; font-size: calc(var(--k) * 40px); line-height: 1; letter-spacing: .02em; white-space: nowrap; }
        .codigo { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: calc(var(--k) * 1mm) calc(var(--k) * 3mm); }
        /* El código de barras nunca baja de 8 mm de alto para que la pistola lo lea. */
        .codigo svg { height: max(8mm, calc(var(--k) * 16mm)); width: auto; max-width: 100%; flex: 0 1 auto; }
        .cliente { font-size: calc(var(--k) * 15px); font-weight: 700; line-height: 1.15; overflow-wrap: anywhere; }
        .dato { font-size: calc(var(--k) * 11px); line-height: 1.2; overflow-wrap: anywhere; }
        .dato b { font-weight: 700; }
        table { width: 100%; border-collapse: collapse; font-size: calc(var(--k) * 13px); }
        th, td { border: 1px solid #111; padding: calc(var(--k) * 1px) calc(var(--k) * 4px); text-align: right; line-height: 1.15; white-space: nowrap; }
        th { background: #eee; font-size: calc(var(--k) * 10px); text-transform: uppercase; }
        td:first-child, th:first-child { text-align: center; }
        tfoot td { font-weight: 700; background: #f3f3f3; }
        .pie { margin-top: auto; display: flex; flex-wrap: wrap; justify-content: space-between; gap: 0 calc(var(--k) * 3mm); border-top: 1px solid #111;
               padding-top: calc(var(--k) * 1mm); font-size: calc(var(--k) * 10px); }
        .resumen { display: none; font-size: calc(var(--k) * 12px); font-weight: 700; }

        /* Horizontal: datos a la izquierda, tabla a la derecha y el pie abajo, a todo lo ancho. */
        .etiqueta.horizontal { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); grid-template-rows: auto 1fr auto;
                               grid-template-areas: "a b" "c b" "pie pie"; column-gap: calc(var(--k) * 3mm); }
        .horizontal .bloque-a { grid-area: a; }
        .horizontal .bloque-b { grid-area: b; }
        .horizontal .bloque-c { grid-area: c; }
        .horizontal .pie { grid-area: pie; }

        /* Mínima: etiquetas muy chicas (p. ej. 2×1 in). Solo folio, código y una línea de resumen. */
        .minima .marca, .minima .bloque-b, .minima .bloque-c, .minima .pie, .minima .material { display: none; }
        .minima .resumen { display: block; }
        .minima.horizontal { grid-template-columns: minmax(0, 1fr); grid-template-areas: "a"; }

        @media print {
            html, body { background: #fff; }
            .barra { display: none; }
            .etiqueta { margin: 0; border: 0; }
        }
    </style>
</head>
<body>
    <div class="barra">
        <label>Tamaño de la etiqueta
            <select id="tam-lista" aria-label="Tamaño de la etiqueta"></select>
        </label>
        <label>Ancho (mm) <input id="tam-ancho" type="number" min="20" max="300" step="0.1" inputmode="decimal"></label>
        <label>Alto (mm) <input id="tam-alto" type="number" min="15" max="400" step="0.1" inputmode="decimal"></label>
        <button type="button" onclick="window.print()">Imprimir</button>
        <p class="nota">El diseño se acomoda solo al tamaño. Elige el mismo tamaño en las opciones de la impresora; este equipo lo recuerda.</p>
    </div>

    <main class="etiqueta" id="etiqueta" data-ancho="{{ $tam[0] }}" data-alto="{{ $tam[1] }}">
        <div class="bloque bloque-a">
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
            <div class="dato material"><b>{{ $pedido->material }}</b></div>
            <div class="resumen">{{ $pedido->totalPiezas() }} pzas · {{ number_format($totalMetros, 2) }} m · {{ number_format($pedido->pesoTeorico(), 2) }} kg</div>
        </div>

        <div class="bloque bloque-b">
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
        </div>

        <div class="bloque bloque-c">
            <div class="dato">
                <b>Peso teórico:</b> {{ number_format($pedido->pesoTeorico(), 2) }} kg
                @if ($pedido->fecha_prometida)
                    &nbsp;·&nbsp; <b>Entrega:</b> {{ $pedido->fecha_prometida->format('d/m/Y') }}
                @endif
            </div>
            <div class="dato">
                <b>Destino:</b> {{ $pedido->forma_entrega?->label() ?? 'Sin definir' }} · {{ $pedido->sucursal }}
            </div>
        </div>

        <div class="pie">
            <span>Elaboró: {{ $elaboro }}</span>
            <span>Autorizó: {{ $pedido->autorizo() ?? '—' }}</span>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script>
        (function () {
            var etiqueta = document.getElementById('etiqueta');
            var pagina = document.getElementById('pagina');
            var lista = document.getElementById('tam-lista');
            var campoAncho = document.getElementById('tam-ancho');
            var campoAlto = document.getElementById('tam-alto');
            var LLAVE = 'aa.etiqueta.tam';

            // Tamaños comunes de etiqueta térmica (ancho × alto, en mm). "A la medida" usa los campos.
            var TAMANOS = [
                ['4 × 6 in', 101.6, 152.4], ['4 × 4 in', 101.6, 101.6], ['4 × 3 in', 101.6, 76.2], ['4 × 2 in', 101.6, 50.8],
                ['4 × 1 in', 101.6, 25.4], ['3 × 2 in', 76.2, 50.8], ['3 × 1 in', 76.2, 25.4], ['2 × 1 in', 50.8, 25.4],
                ['100 × 150 mm', 100, 150], ['100 × 100 mm', 100, 100], ['100 × 50 mm', 100, 50], ['75 × 50 mm', 75, 50],
                ['58 × 40 mm', 58, 40], ['50 × 30 mm', 50, 30], ['50 × 25 mm', 50, 25],
            ];

            var svg = document.getElementById('codigo-barras');
            if (window.JsBarcode && svg) {
                JsBarcode(svg, svg.dataset.folio, { format: 'CODE128', displayValue: false, margin: 0, height: 60, width: 2 });
            }

            function cabe() {
                return etiqueta.scrollHeight <= etiqueta.clientHeight + 0.5 && etiqueta.scrollWidth <= etiqueta.clientWidth + 0.5;
            }

            // La escala más grande con la que todo cabe (búsqueda binaria). Devuelve false si ni la mínima alcanza.
            function escalar(minima) {
                var bajo = minima, alto = 3;
                etiqueta.style.setProperty('--k', bajo);
                if (!cabe()) return false;
                for (var i = 0; i < 16; i++) {
                    var medio = (bajo + alto) / 2;
                    etiqueta.style.setProperty('--k', medio);
                    if (cabe()) { bajo = medio; } else { alto = medio; }
                }
                etiqueta.style.setProperty('--k', bajo);
                return true;
            }

            function aplicar(ancho, alto) {
                ancho = Math.min(300, Math.max(20, +ancho || 101.6));
                alto = Math.min(400, Math.max(15, +alto || 152.4));

                etiqueta.style.setProperty('--ancho', ancho + 'mm');
                etiqueta.style.setProperty('--alto', alto + 'mm');
                pagina.textContent = '@page { size: ' + ancho + 'mm ' + alto + 'mm; margin: 0; }';
                etiqueta.classList.toggle('horizontal', ancho > alto * 1.05);
                etiqueta.classList.remove('minima');

                // Debajo de 0.45 la letra ya no se lee bien: mejor quitar lo secundario que encogerlo más.
                if (!escalar(0.45)) {
                    etiqueta.classList.add('minima');
                    escalar(0.3);
                }

                campoAncho.value = ancho;
                campoAlto.value = alto;
                var i = TAMANOS.findIndex(function (t) { return Math.abs(t[1] - ancho) < 0.6 && Math.abs(t[2] - alto) < 0.6; });
                lista.value = i === -1 ? 'medida' : String(i);
            }

            function guardar(ancho, alto) {
                try { localStorage.setItem(LLAVE, JSON.stringify([+ancho, +alto])); } catch (e) {}
                aplicar(ancho, alto);
            }

            TAMANOS.forEach(function (t, i) { lista.add(new Option(t[0], String(i))); });
            lista.add(new Option('A la medida…', 'medida'));
            lista.addEventListener('change', function () {
                if (lista.value === 'medida') { campoAncho.focus(); return; }
                var t = TAMANOS[+lista.value];
                guardar(t[1], t[2]);
            });
            [campoAncho, campoAlto].forEach(function (c) {
                c.addEventListener('change', function () { guardar(campoAncho.value, campoAlto.value); });
            });

            // Tamaño: ?tam=100x50 (mm) > el último elegido en este equipo > el de la configuración.
            function inicial() {
                var q = /[?&]tam=(\d+(?:\.\d+)?)x(\d+(?:\.\d+)?)/.exec(location.search);
                if (q) return [q[1], q[2]];
                try { var g = JSON.parse(localStorage.getItem(LLAVE)); if (g && g.length === 2) return g; } catch (e) {}
                return [etiqueta.dataset.ancho, etiqueta.dataset.alto];
            }

            // Se ajusta cuando ya cargaron el logo y las fuentes; si se abrió para imprimir, imprime después.
            window.addEventListener('load', function () {
                var t = inicial();
                aplicar(t[0], t[1]);
                if (location.search.indexOf('auto=1') !== -1) { setTimeout(function () { window.print(); }, 300); }
            });
        })();
    </script>
</body>
</html>
