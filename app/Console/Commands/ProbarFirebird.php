<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Prueba la conexión con Microsip (Firebird 3, 4 o 5) y, con --mapa, documenta las tablas.
 * Solo lee: consultas sobre las tablas de sistema RDB$ / MON$, nunca escribe en Microsip.
 */
class ProbarFirebird extends Command
{
    protected $signature = 'aa:firebird
        {--mapa : Escribe docs/firebird/mapa-tablas.md con columnas y triggers de las tablas}
        {--tabla=* : Tablas a documentar (por defecto las candidatas para ventas y traspasos)}
        {--buscar= : Lista las tablas cuyo nombre contenga este texto}';

    protected $description = 'Prueba la conexión con Microsip/Firebird y genera el mapa de tablas';

    /** Las tablas que usa la app (FirebirdVentas, FirebirdTraspasos). Son las del permiso de AA_APP. */
    private const CANDIDATAS = ['DOCTOS_PV', 'DOCTOS_PV_DET', 'ARTICULOS', 'LIBRES_VTA_PV', 'EXP_TRASPASOS', 'EXP_TRASPASOS_DET', 'EXP_TRASPASOS_BITACORA', 'EXP_SYNC_SUCURSALES'];

    public function handle(): int
    {
        if (! extension_loaded('pdo_firebird')) {
            $this->error('Falta la extensión pdo_firebird. Habilita extension=pdo_firebird en '.(php_ini_loaded_file() ?: 'php.ini').' y verifica que fbclient.dll (x64) esté en el PATH.');

            return self::FAILURE;
        }

        $config = config('database.connections.firebird');
        foreach (['database', 'username', 'password'] as $clave) {
            if (blank($config[$clave] ?? null)) {
                $this->error('Falta DB_FIREBIRD_'.strtoupper($clave).' en el .env.');

                return self::FAILURE;
            }
        }

        try {
            $fb = DB::connection('firebird');
            $info = $this->fila($fb->selectOne(
                "select rdb\$get_context('SYSTEM', 'ENGINE_VERSION') as VERSION, current_user as USUARIO,
                        m.mon\$ods_major || '.' || m.mon\$ods_minor as ODS, m.mon\$page_size as PAGINA,
                        m.mon\$database_name as ARCHIVO, d.rdb\$character_set_name as CHARSET
                   from mon\$database m cross join rdb\$database d"
            ));
            $tablas = array_map(fn ($t) => $this->fila($t)['NOMBRE'], $fb->select(
                'select rdb$relation_name as NOMBRE from rdb$relations
                  where coalesce(rdb$system_flag, 0) = 0 and rdb$view_blr is null order by 1'
            ));
        } catch (Throwable $e) {
            $this->error('No conecta con Firebird: '.$e->getMessage());
            $this->line('Revisa: servicio de Firebird encendido y puerto '.$config['port'].' abierto; la ruta es la que ve el servidor;');
            $this->line('usuario y contraseña; "unsupported on-disk structure" = el .FDB es de otra versión, restaúralo con gbak.');

            return self::FAILURE;
        }

        $this->info('Conexión correcta con Microsip.');
        $this->table(['Dato', 'Valor'], [
            ['Firebird', $info['VERSION']],
            ['ODS', $info['ODS']],
            ['Archivo', $info['ARCHIVO']],
            ['Charset de la base', $info['CHARSET'] ?: 'NONE'],
            ['Charset de la conexión', $config['charset']],
            ['Usuario', $info['USUARIO']],
            ['Tamaño de página', $info['PAGINA']],
            ['Tablas de usuario', count($tablas)],
        ]);

        if (! in_array($info['CHARSET'], ['', 'NONE', 'UTF8'], true) && $config['charset'] === 'UTF8') {
            $this->comment("La base es {$info['CHARSET']}: Firebird convierte a UTF8 al leer, revisa acentos y Ñ en el mapa.");
        }

        if ($buscar = $this->option('buscar')) {
            $this->line('Tablas con "'.strtoupper($buscar).'":');
            $this->line(implode(PHP_EOL, preg_grep('/'.preg_quote(strtoupper($buscar), '/').'/', $tablas)) ?: '(ninguna)');
        }

        $pedidas = array_map('strtoupper', $this->option('tabla') ?: self::CANDIDATAS);
        $this->table(['Tabla', '¿Existe?'], array_map(fn ($t) => [$t, in_array($t, $tablas, true) ? 'sí' : 'no'], $pedidas));

        if ($this->option('mapa')) {
            $ruta = base_path('docs/firebird/mapa-tablas.md');
            File::ensureDirectoryExists(dirname($ruta));
            File::put($ruta, $this->mapa($fb, array_values(array_intersect($pedidas, $tablas)), $info));
            $this->info('Mapa escrito en docs/firebird/mapa-tablas.md');
        }

        return self::SUCCESS;
    }

    private function mapa($fb, array $tablas, array $info): string
    {
        $md = "# Mapa de tablas de Microsip\n\n"
            .'Generado con `php artisan aa:firebird --mapa` el '.now()->format('Y-m-d H:i')
            ." sobre la copia local (Firebird {$info['VERSION']}, ODS {$info['ODS']}, charset ".($info['CHARSET'] ?: 'NONE').").\n"
            ."Confirmar con IBExpert antes de escribir cualquier servicio.\n\n"
            ."## Cómo usa la app estas tablas\n\n"
            ."- **Ticket** (`FirebirdVentas::consultar`): `DOCTOS_PV` con `TIPO_DOCTO` V (ticket) o F (factura) y `FOLIO`;"
            ." `ESTATUS` C = cancelado, `APLICADO` <> 'S' = pendiente. Artículos en `DOCTOS_PV_DET` (sin `ROL` = 'C', componentes de juego) + `ARTICULOS.NOMBRE`.\n"
            ."- **Entrega** (`FirebirdVentas::marcarEntregada`): igual que \"Control de Entregas\", `LIBRES_VTA_PV.ENTREGADO` = 'S'"
            ." en la fila del `DOCTO_PV_ID`. La tabla no tiene triggers ni dependencias sobre esa columna. Es la única escritura de la app;"
            ." no inserta filas ni toca `POR_RECOGER` u `OBSERVACIONES`.\n"
            ."- **Traspaso** (`FirebirdTraspasos`): `EXP_TRASPASOS` por `FOLIO` (el más reciente), `ESTATUS` C = cancelado;"
            ." partidas en `EXP_TRASPASOS_DET`, destino en `EXP_SYNC_SUCURSALES`. Los cambios de estatus quedan en `EXP_TRASPASOS_BITACORA`.\n";

        foreach ($tablas as $tabla) {
            $md .= "\n## {$tabla}\n\n| Columna | Tipo | Nulo | Default |\n|---|---|---|---|\n";

            foreach ($fb->select(
                'select rf.rdb$field_name as COLUMNA, f.rdb$field_type as TIPO, f.rdb$field_sub_type as SUBTIPO,
                        f.rdb$field_length as LARGO, f.rdb$character_length as CARACTERES, f.rdb$field_precision as PRECISION_,
                        f.rdb$field_scale as ESCALA, rf.rdb$null_flag as NO_NULO, rf.rdb$default_source as DEFAULT_
                   from rdb$relation_fields rf join rdb$fields f on f.rdb$field_name = rf.rdb$field_source
                  where rf.rdb$relation_name = ? order by rf.rdb$field_position', [$tabla]
            ) as $c) {
                $c = $this->fila($c);
                $md .= "| {$c['COLUMNA']} | {$this->tipo($c)} | ".($c['NO_NULO'] ? 'no' : 'sí').' | '
                    .str_replace('|', '\|', preg_replace('/^default\s+/i', '', $c['DEFAULT_'] ?? ''))." |\n";
            }

            // Los triggers dicen qué más pasa al escribir; clave para copiar a "Control de Entregas".
            $triggers = array_map(fn ($t) => $this->fila($t), $fb->select(
                'select rdb$trigger_name as NOMBRE, rdb$trigger_type as TIPO, coalesce(rdb$trigger_inactive, 0) as INACTIVO
                   from rdb$triggers where rdb$relation_name = ? and coalesce(rdb$system_flag, 0) = 0
                  order by rdb$trigger_sequence, 1', [$tabla]
            ));
            if ($triggers) {
                $md .= "\nTriggers: ".implode(', ', array_map(
                    fn ($t) => $t['NOMBRE'].($t['INACTIVO'] ? ' (inactivo)' : ''), $triggers
                ))."\n";
            }
        }

        return $md;
    }

    /** Traduce los códigos de RDB$FIELDS al tipo que se ve en IBExpert. */
    private function tipo(array $c): string
    {
        $escala = -(int) $c['ESCALA'];
        if (in_array((int) $c['TIPO'], [7, 8, 16, 26], true) && in_array((int) $c['SUBTIPO'], [1, 2], true)) {
            return ($c['SUBTIPO'] == 1 ? 'NUMERIC' : 'DECIMAL')."({$c['PRECISION_']},{$escala})";
        }

        return match ((int) $c['TIPO']) {
            7 => 'SMALLINT',
            8 => 'INTEGER',
            10 => 'FLOAT',
            12 => 'DATE',
            13 => 'TIME',
            14 => 'CHAR('.($c['CARACTERES'] ?? $c['LARGO']).')',
            16 => 'BIGINT',
            23 => 'BOOLEAN',
            24, 25 => 'DECFLOAT',
            26 => 'INT128',
            27 => 'DOUBLE PRECISION',
            28 => 'TIME WITH TIME ZONE',
            29 => 'TIMESTAMP WITH TIME ZONE',
            35 => 'TIMESTAMP',
            37 => 'VARCHAR('.($c['CARACTERES'] ?? $c['LARGO']).')',
            261 => 'BLOB'.($c['SUBTIPO'] == 1 ? ' TEXT' : ''),
            default => 'TIPO '.$c['TIPO'],
        };
    }

    /** Firebird rellena con espacios los CHAR de las tablas de sistema. */
    private function fila(object $fila): array
    {
        return array_map(fn ($v) => is_string($v) ? trim($v) : $v, array_change_key_case((array) $fila, CASE_UPPER));
    }
}
