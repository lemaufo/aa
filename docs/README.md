# Trazabilidad Aceros el Águila — documentación

| Documento | Para qué |
|---|---|
| [manual.md](manual.md) | Manual corto por rol (Cajero, Producción, CEDIS, Sucursal, Administrador, Consulta) |
| [iis.md](iis.md) | Instalación en IIS: PHP, extensiones, Firebird, MySQL, tareas programadas, servidor del cliente |
| [firebird/mapa-tablas.md](firebird/mapa-tablas.md) | Tablas de Microsip/AdminExpertos que usa la app, columnas, triggers y cómo marca la entrega "Control de Entregas" (se regenera con `php artisan aa:firebird --mapa`) |

## Qué hace la app

Sigue cada pedido especial desde el traspaso en AdminExpertos hasta la entrega al cliente con acuse firmado, y al
final marca la venta como entregada en Microsip. Pila: Laravel 12, Livewire 4 + Volt, Tailwind 4.

```
AdminExpertos (traspaso) ─┐                         ┌─> LIBRES_VTA_PV.ENTREGADO = 'S'
Microsip PV (ticket) ─────┼─ lectura (Firebird) ────┤
                          │                         └── única escritura en Microsip
                          v
            App Laravel ── MySQL aa: pedidos, bitácora, usuarios, equipos, rutas de evidencias
                          │
                          └─ storage/app/private/evidencias (fotos y acuses, fuera de public/)
```

### Dos bases, dos usos

- **MySQL `aa`** (`config/database.php`, conexión por defecto): todo lo propio de la app.
- **Firebird** (conexión `firebird`): Microsip y AdminExpertos en la misma base. Funciona con Firebird 3, 4 o 5.
  Se lee para ventas, traspasos y cancelaciones; se escribe solo `LIBRES_VTA_PV.ENTREGADO`.
  No se modifica el esquema de Microsip.
- **`AA_ORIGEN`** (`config/aa.php`): `firebird` (por defecto) o `demo` (tablas `demo_ventas` y `demo_traspasos` en
  MySQL, sin Microsip). Las pruebas automáticas siempre corren en `demo`.

### Dónde está cada cosa

| Pieza | Archivo |
|---|---|
| Reglas del flujo: acción, rol, desde/hacia | `app/Support/Flujo.php` |
| Aplicar una acción con bitácora y transacción; reintento de venta; cancelaciones | `app/Services/FlujoService.php` |
| Validación del ticket contra el traspaso | `app/Services/ValidadorTicket.php` |
| Alta de pedido desde un traspaso de Expertos o Microsip | `app/Services/AltaPedido.php` |
| Contratos con Microsip / Expertos | `app/Contracts/VentasMicrosip.php`, `TraspasosExpertos.php` |
| Implementación real (Firebird) | `app/Services/Firebird/FirebirdVentas.php`, `FirebirdTraspasos.php`, `Microsip.php` |
| Catálogo de artículos (solo lectura, pantalla *Artículos* del administrador) | `app/Services/Firebird/FirebirdArticulos.php`, `resources/views/livewire/aa/articulos.blade.php` |
| Implementación demo | `app/Services/Demo/` |
| Pantallas (Volt) | `resources/views/livewire/aa/` — una por pantalla y un panel por acción en `acciones/` |
| Etiqueta imprimible con código de barras (JsBarcode empaquetado, funciona sin internet) | `resources/views/aa/etiqueta.blade.php` |
| Configuración de negocio (tolerancia, retención, sucursales, dispositivos) | `config/aa.php` |
| IIS | `public/web.config` |

### Comandos

| Comando | Qué hace |
|---|---|
| `php artisan aa:firebird` | Prueba la conexión con Microsip: versión, charset, tablas. `--mapa` regenera el mapa de tablas; `--buscar=TEXTO` busca tablas |
| `php artisan aa:importar-traspasos` | Da de alta como pedido los traspasos de salida capturados en Microsip (programado cada minuto; también al abrir Inicio) |
| `php artisan aa:importar-traspaso FOLIO --partida=1` | Da de alta a mano un traspaso (de Expertos o Microsip), a nombre del administrador |
| `php artisan aa:sincronizar-cancelaciones` | Cancela los pedidos EN EXPERTOS cuyo traspaso se canceló en Expertos (programado cada 5 min) |
| `php artisan aa:purgar-evidencias` | Borra fotos y acuses de más de 2 años; el pedido se conserva (programado a las 3:00) |
| `php artisan aa:crear-admin USUARIO` | Crea el primer administrador o le cambia la contraseña (en producción no corren los seeders) |
| `php artisan aa:dispositivo-codigo` | Código de un solo uso para autorizar un equipo |

## Desarrollo

```powershell
composer install; npm ci; npm run build
php artisan migrate --seed        # en local: usuarios de prueba (+ pedidos demo si AA_ORIGEN=demo)
php artisan serve                 # o el sitio de IIS (docs/iis.md)
php artisan test                  # pruebas automáticas (Pest), siempre con AA_ORIGEN=demo
```

Usuarios de prueba (solo local; `UsuarioSeeder` no corre en producción): `admin`, `cajero`, `produccion`, `cedis`,
`sucursal`, `consulta`. Contraseñas en `config/aa.php`.

### Pruebas automáticas

`tests/Feature` y `tests/Unit`, con lo que el plan marca como mínimo:

| Archivo | Cubre |
|---|---|
| `FlujoTest.php` | Transiciones y permisos del flujo, pedido de punta a punta, reintento de venta, cancelación solo EN EXPERTOS |
| `ValidadorTicketTest.php` | Ticket válido y cada motivo de rechazo |
| `ProduccionYEntregaTest.php` | Tolerancia de peso ±5 % en el límite, fotos de producción y acuse obligatorio |
| `VistasPorRolTest.php` | Cada pantalla con el rol que la usa y con uno que no debe verla; alta y edición de usuarios |
| `Unit/MicrosipFolioTest.php` | Normalización de folios de Microsip (`tba74886` → `TBA074886`) |

La integración con Firebird no tiene prueba automática a propósito: se prueba a mano sobre la copia
(`php artisan aa:firebird`, registrar un traspaso real y ligarlo con su ticket).

### Flujo de trabajo en GitHub

- `main` producción, `develop` integración; `feature/<tema>` y `fix/<tema>` nacen de `develop`.
- Commits pequeños con prefijo `feat:`, `fix:`, `test:`, `docs:`.
- PR a `develop` con qué cambia, cómo probarlo y captura si toca una vista. **Lo aprueba el otro desarrollador.**
- `.env` nunca se sube; solo `.env.example` sin valores reales.

## Estado contra el plan (al 27 oct 2026)

| Sem. | Tarea | Estado |
|---|---|---|
| S1 | Conexión Laravel–Firebird y mapa de tablas | ✅ Firebird 5 con `pdo_firebird`; `aa:firebird --mapa` |
| S1 | Cómo lo hace "Control de Entregas" | ✅ `LIBRES_VTA_PV.ENTREGADO` (falta confirmarlo en IBExpert) |
| S1 | IIS y PHP en los dos equipos | ⏳ Guía y `web.config` listos; falta montarlo y que Luis siga la guía |
| S2 | Lectura real de ventas y traspasos, validador de ticket | ✅ |
| S2 | Cancelaciones de AdminExpertos (solo EN EXPERTOS) | ✅ |
| S2 | Ligar, cambiar entrega con contraseña, fecha prometida y prioridad | ✅ (venía del prototipo; probado con datos reales) |
| S2 | Usuarios, roles y sucursal | ✅ Alta y edición desde *Usuarios* |
| S3 | Evidencias privadas y purga a 2 años | ✅ |
| S3 | Compresión de fotos | ❌ Pendiente (GD ya activo en PHP) |
| S3 | Etiqueta con tamaño real | ⏳ Hoy 10×15 cm; falta el tamaño y diseño del cliente |
| S3 | Código de barras, producción en 3 pasos, modo balanza, ±5 % | ✅ |
| S4 | Marcar la venta como entregada en la copia, con reintento | ✅ |
| S4 | Usuario Firebird de permisos mínimos | ❌ Pendiente: hoy conecta como SYSDBA. `GRANT` exactos en `iis.md` |
| S4 | Fotos desde celular, escaneo, zona del patio, recepción | ✅ Falta probar con celulares y lector reales |
| S5 | Bitácora de cada escritura | ✅ Evento "Venta actualizada en Microsip" o "Error al actualizar Microsip" |
| S5 | Pruebas de error (Firebird apagado, venta ya entregada) | ✅ Ya entregada no reescribe; sin conexión queda en error y se reintenta |
| S5 | Acuse obligatorio, entrega en CEDIS y sucursal | ✅ |
| S5 | Aviso por WhatsApp | ✅ Enlace `wa.me` con el mensaje escrito, al quedar listo en CEDIS y en sucursal |
| S5–S6 | Equipos autorizados, panel de administrador, consulta por folio, Inicio por prioridad | ✅ |
| S6 | Respaldos de MySQL y evidencias | ❌ Pendiente |
| S6 | Seguridad: secretos fuera del código | ⚠️ `config/database.php` trae SYSDBA/masterkey como default local; quitar antes del PR |
| S7 | Hardware real, instalación limpia, servidor del cliente → Firebird | ❌ Requiere el equipo físico y el servidor |
| S8 | Capacitación por rol y manual corto | ✅ Manual en `manual.md`; la capacitación es presencial |

## Supuestos por confirmar con el cliente

- La cantidad del traspaso se guarda como `metros_traspaso` y el `PESO_UNITARIO` del artículo como kg por metro.
  En los traspasos revisados la unidad es `PZA`: confirmar que el peso teórico tiene sentido para estos artículos.
- Estatus de `EXP_TRASPASOS`: solo `C` (cancelado) está confirmado; P, E, F y A se dedujeron de la bitácora.
- Las sucursales de la app deben llamarse igual que en Expertos (`PASO DEL TORO`, `ISLA`…) para que Cajero y
  Sucursal vean sus pedidos; `config/aa.php` todavía trae los nombres del prototipo.
