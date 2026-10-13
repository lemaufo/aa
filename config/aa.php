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
    |   firebird = Microsip / AdminExpertos (se conecta en la fase de integración)
    */
    'origen' => env('AA_ORIGEN', 'demo'),

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

    'sucursales' => ['Sucursal Centro', 'Sucursal Norte'],

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