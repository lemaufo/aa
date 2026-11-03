<?php

use App\Contracts\UsuariosMicrosip;
use App\Enums\Rol;
use App\Exceptions\MicrosipNoResponde;
use App\Models\User;
use Livewire\Volt\Volt;

// Acceso con los usuarios de Microsip. Microsip se simula: catálogo, contraseñas y servidor apagado.

class MicrosipFalso implements UsuariosMicrosip
{
    public bool $apagado = false;

    /** @var array<string, array{id: ?int, usuario: string, rol: ?string, activo: bool, password: string}> */
    public array $usuarios = [
        'SYSDBA' => ['id' => null, 'usuario' => 'SYSDBA', 'rol' => null, 'activo' => true, 'password' => 'Master2026!'],
        'CAJEROAA' => ['id' => 328, 'usuario' => 'CAJEROAA', 'rol' => 'CAJERO', 'activo' => true, 'password' => 'Caja2026'],
        'JOHNNY' => ['id' => 32, 'usuario' => 'JOHNNY', 'rol' => null, 'activo' => true, 'password' => 'Johnny2026'],
    ];

    public function listar(): array
    {
        $this->revisar();

        return array_values(array_map(fn ($u) => array_diff_key($u, ['password' => 0]), $this->usuarios));
    }

    public function buscar(string $usuario): ?array
    {
        $this->revisar();
        $u = $this->usuarios[strtoupper($usuario)] ?? null;

        return $u ? array_diff_key($u, ['password' => 0]) : null;
    }

    public function validar(string $usuario, string $password): bool
    {
        $this->revisar();

        return ($this->usuarios[strtoupper($usuario)]['password'] ?? null) === $password;
    }

    private function revisar(): void
    {
        if ($this->apagado) {
            throw new MicrosipNoResponde('Firebird apagado (simulado).');
        }
    }
}

beforeEach(function () {
    config(['aa.login_microsip' => true]);
    $this->microsip = new MicrosipFalso;
    app()->instance(UsuariosMicrosip::class, $this->microsip);
});

function entrar(string $usuario, string $password)
{
    return Volt::test('auth.login')->set('username', $usuario)->set('password', $password)->call('login');
}

function cajeroMicrosip(): User
{
    return User::create(['name' => 'Cajero Isla', 'username' => 'cajeroaa', 'rol' => Rol::Cajero, 'sucursal' => 'ISLA',
        'microsip_usuario_id' => 328, 'microsip_activo' => true, 'password' => Str::random(40)]);
}

it('entra con el usuario y la contraseña de Microsip si tiene rol en la app', function () {
    $u = cajeroMicrosip();

    entrar('CajeroAA', 'Caja2026')->assertHasNoErrors()->assertRedirect(route('inicio', absolute: false));

    $u->refresh();
    expect(auth()->id())->toBe($u->id)
        ->and($u->microsip_rol)->toBe('CAJERO')
        ->and($u->microsip_sync_at)->not->toBeNull()
        ->and(Hash::check('Caja2026', $u->password))->toBeTrue();
});

it('no entra con una contraseña que Microsip no acepta', function () {
    cajeroMicrosip();

    entrar('cajeroaa', 'otra')->assertHasErrors('username');
    expect(auth()->check())->toBeFalse();
});

it('pide al administrador asignar el usuario de Microsip que no está en la app', function () {
    entrar('johnny', 'Johnny2026')->assertHasErrors(['username' => 'Tu usuario de Microsip todavía no tiene acceso a esta app. Pide al administrador que te asigne un rol en Usuarios.']);
    expect(auth()->check())->toBeFalse()->and(User::where('username', 'johnny')->exists())->toBeFalse();
});

it('no deja entrar a quien está de baja en Microsip y lo marca en la app', function () {
    $u = cajeroMicrosip();
    $this->microsip->usuarios['CAJEROAA']['activo'] = false;

    entrar('cajeroaa', 'Caja2026')->assertHasErrors(['username' => 'Tu usuario está dado de baja en Microsip.']);
    expect(auth()->check())->toBeFalse()->and($u->fresh()->microsip_activo)->toBeFalse();
});

it('SYSDBA entra como administrador aunque la app esté recién instalada', function () {
    entrar('sysdba', 'Master2026!')->assertHasNoErrors();

    expect(auth()->user()->username)->toBe('sysdba')->and(auth()->user()->rol)->toBe(Rol::Admin);
});

it('sin Firebird entra solo quien ya entró antes, con la última contraseña que funcionó', function () {
    $u = cajeroMicrosip();
    entrar('cajeroaa', 'Caja2026')->assertHasNoErrors();
    auth()->logout();

    $this->microsip->apagado = true;

    entrar('cajeroaa', 'otra')->assertHasErrors('username');
    entrar('cajeroaa', 'Caja2026')->assertHasNoErrors();
    expect(auth()->id())->toBe($u->id);

    // Un usuario local que nunca entró por Microsip no entra, aunque su contraseña de la app sea correcta.
    auth()->logout();
    usuario(Rol::Produccion)->update(['username' => 'prod_local', 'password' => 'secreto123']);
    entrar('prod_local', 'secreto123')->assertHasErrors('username');
});

it('el administrador asigna un usuario de Microsip con el rol sugerido y sin contraseña', function () {
    $admin = usuario(Rol::Admin);

    Volt::actingAs($admin)->test('aa.usuarios')
        ->assertSee('Asignar usuario de Microsip')
        ->call('nuevo')
        ->assertSee('CAJEROAA')
        ->set('microsip', 'CAJEROAA')
        ->assertSet('rol', 'cajero')
        ->assertSet('username', 'cajeroaa')
        ->call('guardar')
        ->assertHasErrors('sucursal') // cajero sin sucursal
        ->set('sucursal', 'ISLA')
        ->call('guardar')
        ->assertHasNoErrors();

    $u = User::where('username', 'cajeroaa')->firstOrFail();
    expect($u->rol)->toBe(Rol::Cajero)->and($u->sucursal)->toBe('ISLA')
        ->and($u->microsip_usuario_id)->toBe(328)->and($u->microsip_rol)->toBe('CAJERO');

    // Ya asignado, deja de ofrecerse.
    Volt::actingAs($admin)->test('aa.usuarios')->call('nuevo')->assertDontSee('CAJEROAA ·');
});

it('SYSDBA siempre es administrador', function () {
    $sysdba = User::create(['name' => 'SYSDBA', 'username' => 'sysdba', 'rol' => Rol::Admin, 'password' => Str::random(40)]);

    Volt::actingAs(usuario(Rol::Admin))->test('aa.usuarios')
        ->call('editar', $sysdba->id)->set('rol', 'consulta')->call('guardar')
        ->assertHasErrors(['rol' => 'SYSDBA siempre es administrador.']);
    expect($sysdba->fresh()->rol)->toBe(Rol::Admin);
});

it('Actualizar desde Microsip marca de baja a quien ya no está en Microsip', function () {
    $u = cajeroMicrosip();
    unset($this->microsip->usuarios['CAJEROAA']);

    Volt::actingAs(usuario(Rol::Admin))->test('aa.usuarios')->call('sincronizar');

    expect($u->fresh()->microsip_activo)->toBeFalse();
    entrar('cajeroaa', 'Caja2026')->assertHasErrors('username');
});
