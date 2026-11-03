<?php

namespace App\Exceptions;

use RuntimeException;

/** El servidor Firebird de Microsip no contestó (apagado, red, ruta). No dice nada de la contraseña. */
class MicrosipNoResponde extends RuntimeException {}
