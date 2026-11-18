<?php

use App\Enums\EstatusDispositivo;
use App\Enums\Rol;
use App\Models\Dispositivo;
use App\Models\DispositivoEvento;
use App\Models\User;
use App\Services\MacRed;
use Illuminate\Support\Facades\Process;
use Livewire\Volt\Volt;

// Dispositivos autorizados (AA_DISPOSITIVOS=true en el servidor): solo los dispositivos activos entran a la app.

beforeEach(function () {
    config(['aa.dispositivos.activo' => true]);
    macEnLaRed(null); // por defecto el servidor no ve la MAC (como por VPN); nunca se ejecuta arp en las pruebas
});

/** Lo que el servidor "ve" en su tabla ARP para cualquier IP. */
function macEnLaRed(?string $mac): void
{
    app()->instance(MacRed::class, new class($mac) extends MacRed {
        public function __construct(private ?string $mac) {}

        public function de(?string $ip): ?string
        {
            return $this->mac;
        }
    });
}

/** Da de alta un dispositivo y devuelve [dispositivo, código]. */
function altaDispositivo(string $nombre = 'Caja Centro', ?string $mac = 'AA:BB:CC:00:00:01'): array
{
    $d = Dispositivo::create(['nombre' => $nombre, 'mac' => $mac, 'estatus' => EstatusDispositivo::Activo]);

    return [$d, $d->nuevoCodigo()];
}

/** Canjea el código en /dispositivo y devuelve la llave que queda en la cookie del dispositivo. */
function activarEquipo($test, string $codigo): ?string
{
    return $test->actingAs(usuario(Rol::Cajero))
        ->post(route('dispositivo.activar'), ['codigo' => $codigo])
        ->getCookie(config('aa.dispositivos.cookie'))?->getValue();
}

function conLlave($test, ?string $llave, ?User $usuario = null)
{
    return $test->actingAs($usuario ?? usuario(Rol::Cajero))->withCookie(config('aa.dispositivos.cookie'), (string) $llave);
}

function accionesDe(Dispositivo $d): array
{
    return $d->eventos()->orderBy('id')->pluck('accion')->all();
}

it('un dispositivo sin autorizar no entra a la app y se le pide el código; Consulta queda exento', function () {
    $this->actingAs(usuario(Rol::Cajero))->get(route('inicio'))->assertRedirect(route('dispositivo.mostrar'));
    $this->actingAs(usuario(Rol::Admin))->get(route('pedidos.index'))->assertRedirect(route('dispositivo.mostrar'));

    // La pantalla para capturar el código siempre abre; si no, ningún dispositivo podría activarse.
    $this->actingAs(usuario(Rol::Cajero))->get(route('dispositivo.mostrar'))->assertOk();

    $this->actingAs(usuario(Rol::Consulta))->get(route('rastreo'))->assertOk();
});

it('con la protección apagada (desarrollo local) no se pide código', function () {
    config(['aa.dispositivos.activo' => false]);

    $this->actingAs(usuario(Rol::Cajero))->get(route('inicio'))->assertOk();
});

it('activa el dispositivo con un código válido y desde entonces entra', function () {
    [$d, $codigo] = altaDispositivo();

    $llave = activarEquipo($this, strtolower($codigo)); // se acepta en minúsculas, como lo teclean

    expect($llave)->toHaveLength(64)
        ->and($d->fresh()->conexion())->toBe('Vinculado')
        ->and(accionesDe($d))->toContain('Dispositivo vinculado');

    conLlave($this, $llave)->get(route('inicio'))->assertOk();
});

it('rechaza un código ya usado o vencido', function () {
    [, $usado] = altaDispositivo();
    expect(activarEquipo($this, $usado))->not->toBeNull();

    $this->actingAs(usuario(Rol::Cajero))->post(route('dispositivo.activar'), ['codigo' => $usado])
        ->assertSessionHasErrors(['codigo' => 'El código no es válido o ya venció. Pide uno nuevo al administrador.'])
        ->assertCookieMissing(config('aa.dispositivos.cookie'));

    [$vencido, $codigo] = altaDispositivo('Báscula CEDIS', 'AA:BB:CC:00:00:02');
    $this->travel((int) config('aa.dispositivos.codigo_vigencia_horas') + 1)->hours();

    expect(activarEquipo($this, $codigo))->toBeNull()
        ->and($vencido->fresh()->conexion())->toBe('Código vencido');
});

it('un dispositivo suspendido no opera, el intento queda en la bitácora y al reactivarlo vuelve a entrar', function () {
    [$d, $codigo] = altaDispositivo();
    $llave = activarEquipo($this, $codigo);

    Volt::actingAs(usuario(Rol::Admin))->test('aa.dispositivos')->call('cambiarEstatus', $d->id, 'suspendido');
    expect($d->fresh()->estatus)->toBe(EstatusDispositivo::Suspendido);

    conLlave($this, $llave)->get(route('inicio'))
        ->assertRedirect(route('dispositivo.mostrar'))
        ->assertSessionHas('bloqueo', EstatusDispositivo::Suspendido->avisoBloqueo());
    conLlave($this, $llave)->get(route('pedidos.index'))->assertRedirect(route('dispositivo.mostrar'));

    // Se anota una vez, no en cada clic.
    expect(collect(accionesDe($d))->filter(fn ($a) => $a === 'Intento de uso con dispositivo suspendido'))->toHaveCount(1);

    Volt::actingAs(usuario(Rol::Admin))->test('aa.dispositivos')->call('cambiarEstatus', $d->id, 'activo');
    conLlave($this, $llave)->get(route('inicio'))->assertOk();

    expect(accionesDe($d))->toContain('Cambio de estatus: Activo → Suspendido', 'Cambio de estatus: Suspendido → Activo');
});

it('la baja es definitiva: el dispositivo no opera, el intento se anota y no se puede reactivar', function () {
    [$d, $codigo] = altaDispositivo();
    $llave = activarEquipo($this, $codigo);

    Volt::actingAs(usuario(Rol::Admin))->test('aa.dispositivos')->call('cambiarEstatus', $d->id, 'baja');
    $d->refresh();
    expect($d->estatus)->toBe(EstatusDispositivo::Baja)
        ->and($d->revocado_at)->not->toBeNull();

    // La llave se sigue reconociendo: se bloquea con el motivo y queda anotado.
    conLlave($this, $llave)->get(route('inicio'))
        ->assertRedirect(route('dispositivo.mostrar'))
        ->assertSessionHas('bloqueo', EstatusDispositivo::Baja->avisoBloqueo());
    expect(accionesDe($d))->toContain('Intento de uso con dispositivo baja');

    Volt::actingAs(usuario(Rol::Admin))->test('aa.dispositivos')->call('cambiarEstatus', $d->id, 'activo')->assertForbidden();
    Volt::actingAs(usuario(Rol::Admin))->test('aa.dispositivos')->call('nuevoCodigo', $d->id)->assertForbidden();
    expect($d->fresh()->estatus)->toBe(EstatusDispositivo::Baja);
});

it('un dispositivo dado de baja que todavía trae su llave se bloquea y se anota', function () {
    // Baja hecha sin pasar por la pantalla (p. ej. un dispositivo de antes de este cambio que conservó la llave).
    [$d, $codigo] = altaDispositivo();
    $llave = activarEquipo($this, $codigo);
    $d->update(['estatus' => EstatusDispositivo::Baja]);

    conLlave($this, $llave)->get(route('inicio'))
        ->assertRedirect(route('dispositivo.mostrar'))
        ->assertSessionHas('bloqueo', EstatusDispositivo::Baja->avisoBloqueo());

    expect(accionesDe($d))->toContain('Intento de uso con dispositivo baja');
});

it('eliminar lo quita de la lista; si intenta operar se bloquea y se anota; su MAC queda libre', function () {
    [$d, $codigo] = altaDispositivo('Caja Centro', 'AA:BB:CC:00:00:01');
    $llave = activarEquipo($this, $codigo);
    $d->update(['estatus' => EstatusDispositivo::Suspendido]); // se elimina con cualquier estatus

    $pantalla = Volt::actingAs(usuario(Rol::Admin))->test('aa.dispositivos')->call('eliminar', $d->id)->assertOk();

    expect(Dispositivo::find($d->id))->toBeNull()
        ->and(Dispositivo::withTrashed()->find($d->id)->trashed())->toBeTrue()
        ->and($pantalla->get('dispositivos')->pluck('id')->all())->not->toContain($d->id)
        ->and(DispositivoEvento::where('dispositivo_id', $d->id)->latest('id')->first()->only('accion', 'detalle'))->toBe([
            'accion' => 'Dispositivo eliminado',
            'detalle' => 'MAC AA:BB:CC:00:00:01, estaba suspendido',
        ]);

    // Ya no opera: se bloquea, se anota una sola vez aunque insista.
    conLlave($this, $llave)->get(route('inicio'))
        ->assertRedirect(route('dispositivo.mostrar'))
        ->assertSessionHas('bloqueo', 'Este dispositivo fue eliminado de la lista de autorizados y ya no puede usar el sistema.');
    conLlave($this, $llave)->get(route('inicio'))->assertRedirect(route('dispositivo.mostrar'));
    expect(DispositivoEvento::where('dispositivo_id', $d->id)->where('accion', 'Intento de uso con dispositivo eliminado')->count())->toBe(1);

    // No se puede editar, cambiar de estatus ni regenerar código, ni eliminar otra vez.
    foreach ([['editar', $d->id], ['cambiarEstatus', $d->id, 'activo'], ['nuevoCodigo', $d->id], ['eliminar', $d->id]] as $llamada) {
        Volt::actingAs(usuario(Rol::Admin))->test('aa.dispositivos')->call(...$llamada)->assertNotFound();
    }

    // La MAC se puede volver a registrar en un dispositivo nuevo.
    Volt::actingAs(usuario(Rol::Admin))->test('aa.dispositivos')
        ->set(['nombre' => 'Caja Centro (nueva)', 'mac' => 'AA:BB:CC:00:00:01'])->call('guardar')->assertHasNoErrors();
    $this->artisan('aa:dispositivo-codigo', ['nombre' => 'Otra', '--mac' => 'AA:BB:CC:00:00:01'])->assertFailed();
});

it('eliminar también valida el rol en el servidor', function () {
    [$d] = altaDispositivo();
    $pantalla = Volt::actingAs(usuario(Rol::Admin))->test('aa.dispositivos');

    $this->actingAs(usuario(Rol::Cedis));
    $pantalla->call('eliminar', $d->id)->assertForbidden();

    expect(Dispositivo::find($d->id))->not->toBeNull()
        ->and(DispositivoEvento::where('accion', 'Acceso denegado a Dispositivos autorizados')->count())->toBe(1);
});

it('si el servidor ve en la red una MAC distinta a la registrada, bloquea; si no la ve, vale la cookie', function () {
    [$d, $codigo] = altaDispositivo(mac: 'AA:BB:CC:00:00:01');
    $llave = activarEquipo($this, $codigo);

    macEnLaRed('AA:BB:CC:00:00:01');
    conLlave($this, $llave)->get(route('inicio'))->assertOk();

    macEnLaRed(null); // por VPN o desde otra red
    conLlave($this, $llave)->get(route('inicio'))->assertOk();

    macEnLaRed('11:22:33:44:55:66'); // la cookie se copió a otro equipo
    conLlave($this, $llave)->get(route('inicio'))->assertRedirect(route('dispositivo.mostrar'));
    expect(accionesDe($d))->toContain('MAC no coincide')
        ->and($d->eventos()->where('accion', 'MAC no coincide')->value('detalle'))->toContain('11:22:33:44:55:66');

    config(['aa.dispositivos.validar_mac' => false]);
    conLlave($this, $llave)->get(route('inicio'))->assertOk();
});

it('solo SYSDBA y el administrador entran al apartado; los demás roles se bloquean y se anota', function () {
    $sysdba = User::create(['name' => 'SYSDBA', 'username' => 'sysdba', 'email' => 'sysdba@prueba.test', 'rol' => Rol::Admin, 'password' => 'secreto123']);

    config(['aa.dispositivos.activo' => false]); // para revisar solo el permiso del apartado
    $this->actingAs($sysdba)->get(route('dispositivos'))->assertOk()->assertSee('Dispositivos autorizados');
    $this->actingAs(usuario(Rol::Admin))->get(route('dispositivos'))->assertOk();

    foreach ([Rol::Cajero, Rol::Produccion, Rol::Cedis, Rol::Sucursal] as $rol) {
        $this->actingAs(usuario($rol))->get(route('dispositivos'))->assertForbidden();
    }

    expect(DispositivoEvento::where('accion', 'Acceso denegado a Dispositivos autorizados')->count())->toBe(4);
});

it('las acciones también validan el rol en el servidor, no solo la pantalla', function () {
    [$d] = altaDispositivo();

    // La pantalla la abrió un administrador, pero la acción llega con la sesión de un cajero.
    $pantalla = Volt::actingAs(usuario(Rol::Admin))->test('aa.dispositivos');
    $this->actingAs(usuario(Rol::Cajero));
    $pantalla->call('cambiarEstatus', $d->id, 'baja')->assertForbidden();

    expect($d->fresh()->estatus)->toBe(EstatusDispositivo::Activo)
        ->and(DispositivoEvento::where('accion', 'Acceso denegado a Dispositivos autorizados')->exists())->toBeTrue();
});

it('da de alta con la MAC normalizada y rechaza una MAC inválida o repetida', function () {
    $pantalla = Volt::actingAs(usuario(Rol::Admin))->test('aa.dispositivos');

    $pantalla->set(['nombre' => 'Caja Centro', 'mac' => 'aa-bb-cc-dd-ee-ff'])->call('guardar')->assertHasNoErrors();
    $d = Dispositivo::where('nombre', 'Caja Centro')->first();
    expect($d->mac)->toBe('AA:BB:CC:DD:EE:FF')
        ->and($d->estatus)->toBe(EstatusDispositivo::Activo)
        ->and($pantalla->get('codigoNuevo.codigo'))->toMatch('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/')
        ->and(accionesDe($d))->toBe(['Alta de dispositivo']);

    foreach (['AA:BB:CC:DD:EE', 'GG:BB:CC:DD:EE:FF', '00:00:00:00:00:00', ''] as $mala) {
        $pantalla->set(['nombre' => 'Otro', 'mac' => $mala])->call('guardar')->assertHasErrors('mac');
    }

    // Repetida, escrita de otra forma.
    $pantalla->set(['nombre' => 'Otro', 'mac' => 'AABB.CCDD.EEFF'])->call('guardar')
        ->assertHasErrors(['mac' => 'unique']);

    // Una MAC en baja sí se puede volver a dar de alta.
    $pantalla->call('cambiarEstatus', $d->id, 'baja');
    $pantalla->set(['nombre' => 'Caja Centro (nueva)', 'mac' => 'AA:BB:CC:DD:EE:FF'])->call('guardar')->assertHasNoErrors();

    expect(Dispositivo::where('mac', 'AA:BB:CC:DD:EE:FF')->count())->toBe(2);
});

it('la edición guarda los cambios y los anota en la bitácora', function () {
    [$d] = altaDispositivo('Caja 1', 'AA:BB:CC:00:00:01');

    Volt::actingAs(usuario(Rol::Admin))->test('aa.dispositivos')
        ->call('editar', $d->id)
        ->set(['nombre' => 'Caja Centro', 'mac' => 'aa:bb:cc:00:00:09'])
        ->call('guardar')
        ->assertHasNoErrors();

    expect($d->fresh()->only('nombre', 'mac'))->toBe(['nombre' => 'Caja Centro', 'mac' => 'AA:BB:CC:00:00:09'])
        ->and($d->eventos()->latest('id')->first()->only('accion', 'detalle'))->toBe([
            'accion' => 'Dispositivo editado',
            'detalle' => 'Nombre: Caja 1 → Caja Centro; MAC: AA:BB:CC:00:00:01 → AA:BB:CC:00:00:09',
        ]);
});

it('busca por nombre y MAC y filtra por estatus', function () {
    altaDispositivo('Caja Centro', 'AA:BB:CC:00:00:01');
    [$bascula] = altaDispositivo('Báscula CEDIS', 'DE:AD:BE:EF:00:02');
    $bascula->update(['estatus' => EstatusDispositivo::Suspendido]);

    $pantalla = Volt::actingAs(usuario(Rol::Admin))->test('aa.dispositivos');

    expect($pantalla->set('q', 'caja')->get('dispositivos')->pluck('nombre')->all())->toBe(['Caja Centro'])
        ->and($pantalla->set('q', 'de-ad-be')->get('dispositivos')->pluck('nombre')->all())->toBe(['Báscula CEDIS'])
        ->and($pantalla->set(['q' => '', 'filtroEstatus' => 'suspendido'])->get('dispositivos')->pluck('nombre')->all())->toBe(['Báscula CEDIS']);
});

it('lee la MAC de la tabla ARP del servidor solo para IPs de la red local', function () {
    app()->forgetInstance(MacRed::class);
    Process::fake([
        '*' => Process::result("Interfaz: 192.168.1.10 --- 0x7\n  Dirección de Internet  Dirección física      Tipo\n  192.168.1.25          a4-5e-60-c1-22-3f     dinámico\n"),
    ]);

    $red = app(MacRed::class);

    expect($red->de('192.168.1.25'))->toBe('A4:5E:60:C1:22:3F')
        ->and($red->de('192.168.1.26'))->toBeNull()   // no está en la tabla
        ->and($red->de('8.8.8.8'))->toBeNull()        // pública: ni se consulta
        ->and($red->de('127.0.0.1'))->toBeNull();

    Process::assertRanTimes(fn ($p) => in_array('192.168.1.25', (array) $p->command, true), 1);
});
