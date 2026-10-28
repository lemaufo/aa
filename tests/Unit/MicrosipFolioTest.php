<?php

use App\Services\Firebird\Microsip;

// Microsip guarda los folios en 9 caracteres: serie + número con ceros.

it('completa el folio como lo guarda Microsip', function (string $tecleado, string $esperado) {
    expect(Microsip::folio($tecleado))->toBe($esperado);
})->with([
    'ya completo' => ['CA0078212', 'CA0078212'],
    'sin ceros' => ['CA78212', 'CA0078212'],
    'minúsculas' => ['tba74886', 'TBA074886'],
    'con guion y espacios' => [' tsa-13341 ', 'TSA013341'],
    'serie de 3 letras' => ['ISF22680', 'ISF022680'],
    'número largo' => ['A123456789', 'A123456789'],
    'sin número' => ['ABC', 'ABC'],
]);
