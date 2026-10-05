<?php

return [

    /*
    | Modo demostración: muestra en la pantalla de acceso los usuarios de prueba.
    | Apágalo (AA_DEMO=false) antes de enseñar el sistema fuera del equipo.
    */
    'demo' => (bool) env('AA_DEMO', true),

    /*
    | Tolerancia, en %, entre el peso real (báscula de CEDIS) y el peso teórico.
    | Valor provisional: lo define Aceros el Águila.
    */
    'tolerancia_peso' => (float) env('AA_TOLERANCIA_PESO', 2.0),

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