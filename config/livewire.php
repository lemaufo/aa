<?php

return [

    /*
    | Layout por defecto de las pantallas completas de Livewire 4.
    | Nuestros layouts viven en resources/views/components/layouts.
    */
    'component_layout' => 'components.layouts.app',

    /*
    | Subida temporal de fotos (antes de que el panel las guarde como evidencia).
    | Va al disco privado, en storage/app/private/livewire-tmp, y se limpia sola a las 24 h.
    |
    | Límites, de afuera hacia adentro; cada uno debe ser mayor o igual que el siguiente:
    |   IIS  maxAllowedContentLength (public/web.config)  30 MB por envío
    |   PHP  post_max_size / upload_max_filesize           30 MB / 12 MB (docs/iis.md)
    |   Livewire (aquí)                                    12 MB por foto
    |   App  Evidencia::MAX_KB                             10 MB por foto, con mensaje claro
    |
    | Se copian todas las llaves porque Livewire no mezcla este arreglo con el suyo.
    */
    'temporary_file_upload' => [
        'disk' => 'local',
        'rules' => ['required', 'file', 'max:12288'],
        'directory' => 'livewire-tmp',
        'middleware' => null,
        'preview_mimes' => ['png', 'gif', 'bmp', 'webp', 'jpg', 'jpeg'],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],

];
