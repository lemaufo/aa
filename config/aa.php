<?php

return [

    /*
    | Modo demostración: muestra en la pantalla de acceso los usuarios de prueba.
    | Apágalo (AA_DEMO=false) antes de enseñar el sistema fuera del equipo.
    */
    'demo' => (bool) env('AA_DEMO', true),

    /*
    | De dónde salen los tickets de venta y los traspasos:
    |   demo     = tablas demo_ventas y demo_traspasos en MySQL (prototipo)
    |   firebird = Microsip / AdminExpertos (conexión firebird de config/database.php)
    */
    'origen' => env('AA_ORIGEN', 'firebird'),

    /*
    | Conceptos de Microsip Inventarios (CONCEPTOS_IN.CONCEPTO_IN_ID) que cuentan como traspaso cuando el folio
    | no está en AdminExpertos. 223048 = TRASPASO DE SALIDA. Varios separados por coma; vacío = solo Expertos.
    */
    'conceptos_traspaso' => array_filter(array_map('trim', explode(',', (string) env('AA_CONCEPTOS_TRASPASO', '223048')))),

    /*
    | Los traspasos capturados directo en Microsip con esos conceptos entran solos como pedido EN EXPERTOS
    | (comando aa:importar-traspasos, cada minuto y al abrir Inicio). Solo los de esta fecha en adelante.
    | No traen sucursal destino: se asignan a esta, que debe llamarse igual que la del cajero.
    */
    'traspasos_desde' => env('AA_TRASPASOS_DESDE', '2026-10-28'),
    'sucursal_traspasos_microsip' => env('AA_SUCURSAL_TRASPASOS_MICROSIP', 'PASO DEL TORO'),

    /* Solo demo: simula que Microsip no responde al marcar la venta como entregada. */
    'demo_falla_microsip' => (bool) env('AA_DEMO_FALLA_MICROSIP', false),

    /*
    | Tolerancia, en %, entre el peso medido (báscula de producción) y el peso teórico.
    | Acordado en la reunión: 5 % hacia arriba o hacia abajo.
    */
    'tolerancia_peso' => (float) env('AA_TOLERANCIA_PESO', 5.0),

    /* Máximo de renglones (medidas distintas) que caben en la etiqueta sin ocultar información. */
    'max_medidas' => 11,

    /* Política: el pedido especial se vende en un ticket independiente, sin productos de línea. */
    'ticket_exclusivo' => (bool) env('AA_TICKET_EXCLUSIVO', true),

    /* Fotos y acuses se borran solos pasado este tiempo (comando aa:purgar-evidencias). */
    'retencion_fotos_anios' => (int) env('AA_RETENCION_FOTOS_ANIOS', 2),

    /*
    | Dispositivos autorizados. Apagado por defecto para no estorbar en el desarrollo;
    | en producción: AA_DISPOSITIVOS=true. El rol Consulta queda exento.
    */
    'dispositivos' => [
        'activo' => (bool) env('AA_DISPOSITIVOS', false),
        'cookie' => 'aa_dispositivo',
        'codigo_vigencia_horas' => 24,
    ],

    'logo' => env('AA_LOGO', 'img/logo.png'),

    /* Solo demo (o si Microsip no responde): con Microsip, las sucursales son sus almacenes. */
    'sucursales' => ['Sucursal Centro', 'Sucursal Norte'],

    /*
    | Almacenes de Microsip que no son sucursal y no se ofrecen al asignar usuarios (ALMACENES.ALMACEN_ID):
    | 19 CEDIS, 105674 materia prima, 105675 producción, 221759 tránsito, 238791 mercancía en tránsito.
    */
    'almacenes_no_sucursal' => array_filter(array_map('trim', explode(',', (string) env('AA_ALMACENES_NO_SUCURSAL', '19,105674,105675,221759,238791')))),

    /*
    | Usuarios de prueba (se crean con el seeder). El acceso es por nombre de usuario, no por correo.
    */
    'usuarios' => [
        ['name' => 'Administrador', 'username' => 'admin', 'password' => 'Admin#AA26', 'rol' => 'admin', 'sucursal' => null],
        ['name' => 'Cajero Centro', 'username' => 'cajero', 'password' => 'Caja#AA26', 'rol' => 'cajero', 'sucursal' => 'Sucursal Centro'],
        ['name' => 'Producción Planta', 'username' => 'produccion', 'password' => 'Prod#AA26', 'rol' => 'produccion', 'sucursal' => null],
        ['name' => 'Operador CEDIS', 'username' => 'cedis', 'password' => 'Cedis#AA26', 'rol' => 'cedis', 'sucursal' => null],
        ['name' => 'Encargado Sucursal Centro', 'username' => 'sucursal', 'password' => 'Suc#AA26', 'rol' => 'sucursal', 'sucursal' => 'Sucursal Centro'],
        ['name' => 'Consulta Dirección', 'username' => 'consulta', 'password' => 'Consulta#AA26', 'rol' => 'consulta', 'sucursal' => null],
    ],
];