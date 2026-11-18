<?php

use App\Services\Firebird\FirebirdVentas;
use App\Services\Firebird\Microsip;

/*
| Prueba de integración contra la COPIA restaurada de Microsip (nunca producción). No corre en la suite
| normal: se salta salvo que se pida a propósito, con un ticket de prueba que todavía no esté entregado:
|
|   AA_PRUEBA_FIREBIRD_FOLIO=TBA074886 php artisan test --group=firebird-real
|
| Usa la conexión 'firebird' del .env. Marca la venta como entregada, comprueba que ENTREGADO quedó en 'S',
| que un segundo intento no escribe nada, y deja el ticket como estaba. La parte de "Firebird apagado y
| reintento" se hace a mano: docs/iis.md, sección 7, "Prueba de la entrega contra la copia".
*/

it('marca la entrega en la copia de Microsip y no la repite', function () {
    $folio = Microsip::folio((string) env('AA_PRUEBA_FIREBIRD_FOLIO'));
    $leer = fn () => Microsip::fila(Microsip::db()->selectOne(
        "select first 1 l.docto_pv_id, l.entregado from doctos_pv p join libres_vta_pv l on l.docto_pv_id = p.docto_pv_id
          where p.tipo_docto in ('V', 'F') and p.folio = ? order by case p.aplicado when 'S' then 0 else 1 end, p.docto_pv_id desc",
        [$folio]
    ));

    $antes = $leer() ?? $this->fail("El ticket {$folio} no existe en la copia o no tiene datos de entrega (LIBRES_VTA_PV).");
    expect($antes['entregado'])->not->toBe('S', "Elige un ticket que todavía no esté entregado en la copia.");

    try {
        $ventas = new FirebirdVentas;
        $ventas->marcarEntregada($folio, 'Prueba de integración', null);
        expect($leer()['entregado'])->toBe('S');

        // Ya entregada: no falla ni vuelve a escribir.
        $ventas->marcarEntregada($folio, 'Prueba de integración', null);
        expect($leer()['entregado'])->toBe('S');
    } finally {
        // El ticket de prueba vuelve a como estaba, para poder repetir la prueba.
        Microsip::db()->update('update libres_vta_pv set entregado = ? where docto_pv_id = ?', [$antes['entregado'], $antes['docto_pv_id']]);
    }
})->group('firebird-real')
    ->skip(fn () => blank(env('AA_PRUEBA_FIREBIRD_FOLIO')), 'Prueba contra la copia de Microsip: se corre a mano con AA_PRUEBA_FIREBIRD_FOLIO (ver el encabezado).');
