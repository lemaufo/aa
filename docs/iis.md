# Instalación en IIS (equipos de desarrollo y servidor del cliente)

Guía para dejar la app sirviéndose por IIS con PHP por FastCGI, MySQL para `aa` y la copia de Microsip en Firebird.

La misma configuración se usa en los dos equipos de desarrollo y en el servidor del cliente. Si un paso no se puede
reproducir tal cual, se corrige aquí en un PR, no solo en el equipo.

> Nunca se desarrolla ni se prueba contra la base de producción de Microsip. Solo contra una copia restaurada.

<br>

## 1. Requisitos

| Pieza | Versión | Notas |
|---|---|---|
| Windows | 10/11 Pro o Server 2019+ | Probado en Windows 11 |
| IIS | El de Windows | Con CGI (FastCGI) |
| URL Rewrite | 2.1 | https://www.iis.net/downloads/microsoft/url-rewrite |
| PHP | 8.2 o superior, **x64, Non Thread Safe** | https://windows.php.net/download |
| Visual C++ Redistributable | El que pida esa versión de PHP (VS16/VS17) x64 | |
| Firebird | 3.0, 4.0 o 5.0 | La app funciona igual con cualquiera (ver sección 4) |
| MySQL | 8.x | Base `aa` |
| Composer y Node | Composer 2, Node 20+ | Solo para compilar; en el servidor basta subir `public/build` |

---

<br>

## 2. Activar IIS y FastCGI

En PowerShell como administrador:

```powershell
Enable-WindowsOptionalFeature -Online -All -FeatureName `
  IIS-WebServerRole, IIS-WebServer, IIS-CommonHttpFeatures, IIS-DefaultDocument, IIS-StaticContent, `
  IIS-HttpErrors, IIS-RequestFiltering, IIS-CGI, IIS-ManagementConsole
```

Después instalar **URL Rewrite** (sin él, IIS responde 500.19 al leer el `web.config`).

---

<br>

## 3. PHP

1. Descomprimir PHP NTS x64 en `C:\php` y agregar `C:\php` al `PATH` del sistema.
   (XAMPP sirve para correr `artisan`, pero su PHP es Thread Safe; para IIS se usa el NTS.)
2. Copiar `php.ini-production` a `php.ini` y ajustar:

```ini
extension_dir = "C:\php\ext"
extension=pdo_firebird
extension=pdo_mysql
extension=mbstring
extension=openssl
extension=fileinfo
extension=gd
extension=exif
extension=curl
extension=zip

zend_extension=opcache
opcache.enable=1

date.timezone = America/Mexico_City

; Fotos de celular (van de la mano con maxAllowedContentLength del web.config: 30 MB)
upload_max_filesize = 12M
post_max_size = 30M
max_file_uploads = 20
memory_limit = 256M

; FastCGI en IIS
cgi.fix_pathinfo = 1
fastcgi.impersonate = 1
```

3. Comprobar: `php -m` debe listar `PDO_Firebird`, `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd` y `exif`.

---

<br>

## 4. Firebird (cualquier versión 3, 4 o 5)

La app no depende de la versión del servidor: PHP habla con Firebird a través de `fbclient.dll`, y un cliente de
3.0 en adelante conecta con servidores 3, 4 y 5. Lo único que importa:

- **`fbclient.dll` de 64 bits** (la misma arquitectura que PHP). Tomarlo de la carpeta de instalación de Firebird
  (`C:\Program Files\Firebird\Firebird_5_0\fbclient.dll`) y copiarlo a `C:\php` o tenerlo en el `PATH`.
  Si solo hay un Firebird de 32 bits instalado, PHP no carga `pdo_firebird`.

- **El archivo `.FDB` lo abre solo el servidor de su versión o una más nueva** (estructura ODS):

  | Servidor | ODS que crea | Abre archivos de |
  |---|---|---|
  | Firebird 3.0 | 12 | 3.0 |
  | Firebird 4.0 | 13.0 | 4.0 |
  | Firebird 5.0 | 13.1 | 4.0 y 5.0 |

  Si aparece `unsupported on-disk structure`, la copia se hizo con otra versión: se restaura desde el respaldo
  `.fbk` con el `gbak` del servidor que la va a abrir. El `.fbk` sí sirve entre versiones.

- Puerto **3050** abierto en el servidor de Firebird.

<br>

### Restaurar la copia de Microsip

Con el `gbak` del Firebird instalado en el equipo y un respaldo `.fbk` de Microsip:

```powershell
$env:ISC_USER = "SYSDBA"; $env:ISC_PASSWORD = "<contraseña>"
& "C:\Program Files\Firebird\Firebird_5_0\gbak.exe" -c -v "C:\Microsip datos\Respaldos\microsip.fbk" "C:\Microsip datos\ACEROS EL AGUILA.FDB"
```

<br>

### Alias (opcional, recomendado)

Para no depender de la ruta con espacios, en `databases.conf` del servidor Firebird:

```
aa_microsip = C:\Microsip datos\ACEROS EL AGUILA.FDB
```

y en el `.env`: `DB_FIREBIRD_DATABASE=aa_microsip`. Reiniciar el servicio de Firebird tras editarlo.

<br>

### Usuario dedicado con permisos mínimos

La app no usa SYSDBA fuera del equipo de desarrollo. Con SYSDBA, sobre la **copia**:

```sql
CREATE USER AA_APP PASSWORD '<contraseña>';
GRANT SELECT ON DOCTOS_PV TO USER AA_APP;
GRANT SELECT ON DOCTOS_PV_DET TO USER AA_APP;
GRANT SELECT ON ARTICULOS TO USER AA_APP;
GRANT SELECT ON CLAVES_ARTICULOS TO USER AA_APP;
GRANT SELECT ON ROLES_CLAVES_ARTICULOS TO USER AA_APP;
GRANT SELECT ON LINEAS_ARTICULOS TO USER AA_APP;
GRANT SELECT ON EXP_TRASPASOS TO USER AA_APP;
GRANT SELECT ON EXP_TRASPASOS_DET TO USER AA_APP;
GRANT SELECT ON EXP_SYNC_SUCURSALES TO USER AA_APP;
-- Traspasos capturados directo en Microsip Inventarios.
GRANT SELECT ON DOCTOS_IN TO USER AA_APP;
GRANT SELECT ON DOCTOS_IN_DET TO USER AA_APP;
GRANT SELECT ON ALMACENES TO USER AA_APP;
GRANT SELECT ON LIBRES_VTA_PV TO USER AA_APP;
-- Lo único que escribe la app, igual que "Control de Entregas": la marca de venta entregada.
GRANT UPDATE (ENTREGADO) ON LIBRES_VTA_PV TO USER AA_APP;
```

Y en el catálogo de usuarios de Microsip (`System\CONFIG.FDB`, conexión `microsip_config`), para el acceso:

```sql
GRANT SELECT ON USUARIOS TO USER AA_APP;
GRANT SELECT ON EMPRESAS TO USER AA_APP;
GRANT SELECT ON EMPRESAS_USUARIOS TO USER AA_APP;
```

Las contraseñas no se leen de ninguna tabla: la app abre una conexión de un solo uso a Firebird con el usuario y la
contraseña que se teclean en el login, igual que Microsip. No hace falta ningún permiso extra para eso.

Probar en la copia que los triggers de esa tabla no fallen con este usuario antes de llevarlo al servidor.

---

<br>

## 5. Registrar PHP en IIS

En PowerShell como administrador:

```powershell
$appcmd = "$env:windir\system32\inetsrv\appcmd.exe"
& $appcmd set config /section:system.webServer/fastCgi /+"[fullPath='C:\php\php-cgi.exe']"
& $appcmd set config /section:system.webServer/fastCgi /[fullPath='C:\php\php-cgi.exe'].instanceMaxRequests:10000
& $appcmd set config /section:system.webServer/fastCgi /+"[fullPath='C:\php\php-cgi.exe'].environmentVariables.[name='PHP_FCGI_MAX_REQUESTS',value='10000']"
& $appcmd set config /section:system.webServer/handlers /+"[name='PHP_via_FastCGI',path='*.php',verb='*',modules='FastCgiModule',scriptProcessor='C:\php\php-cgi.exe',resourceType='Either']"
```

---

<br>

## 6. Sitio y grupo de aplicaciones

1. Clonar el repositorio, por ejemplo en `C:\Laravel\aa`.
2. Crear el grupo de aplicaciones **sin código administrado** y el sitio apuntando a `public\`:

```powershell
Import-Module WebAdministration
New-WebAppPool -Name "aa"
Set-ItemProperty IIS:\AppPools\aa managedRuntimeVersion ""
New-Website -Name "aa" -Port 8080 -PhysicalPath "C:\Laravel\aa\public" -ApplicationPool "aa"
```

3. Permisos: la identidad del grupo solo escribe en `storage` y `bootstrap\cache`; el resto, lectura.

```powershell
icacls "C:\Laravel\aa" /grant "IIS AppPool\aa:(OI)(CI)RX" /T
icacls "C:\Laravel\aa\storage" /grant "IIS AppPool\aa:(OI)(CI)M" /T
icacls "C:\Laravel\aa\bootstrap\cache" /grant "IIS AppPool\aa:(OI)(CI)M" /T
```

Las evidencias (fotos y acuses) van en `storage\app\private`, fuera de `public\`; nunca se sirven directo por IIS.
Al guardarlas se reducen a 1600 px y se recomprimen en JPEG (unos 300 KB por foto): `gd` es obligatoria y `exif`
endereza las fotos que el celular guarda acostadas. Se borran a los 2 años con `aa:purgar-evidencias` (tarea programada).

El `web.config` de Laravel ya está en el repositorio (`public/web.config`): reglas de URL Rewrite, límite de subida
de 30 MB y errores de Laravel sin reemplazo de IIS.

---

<br>

## 7. Configurar la app

```powershell
cd C:\Laravel\aa
composer install --no-dev --optimize-autoloader   # en desarrollo: composer install
npm ci; npm run build
copy .env.example .env
php artisan key:generate
```

En el `.env` de cada equipo (nunca se sube a Git):

```ini
APP_URL=http://localhost:8080
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aa
DB_USERNAME=<usuario>
DB_PASSWORD=<contraseña>

AA_ORIGEN=demo            # demo = datos de prueba; sin esta línea la app lee Microsip (firebird)
AA_DISPOSITIVOS=false     # dispositivos autorizados: false en desarrollo, true en el servidor (ver abajo)

DB_FIREBIRD_HOST=127.0.0.1
DB_FIREBIRD_PORT=3050
DB_FIREBIRD_DATABASE="C:/Microsip datos/ACEROS EL AGUILA.FDB"
DB_FIREBIRD_CONFIG_DATABASE="C:/Microsip datos/System/CONFIG.FDB"
DB_FIREBIRD_USERNAME=<usuario>
DB_FIREBIRD_PASSWORD=<contraseña>
DB_FIREBIRD_CHARSET=UTF8
```

Las conexiones de MySQL y de Firebird (rutas, usuario y contraseña) **solo** están en el `.env`: `config/database.php`
ya no trae valores de respaldo para Firebird, así que sin estas líneas la app no se conecta a Microsip.

### Dispositivos autorizados (`AA_DISPOSITIVOS`)

Con `AA_DISPOSITIVOS=true` solo entran a la app los dispositivos dados de alta y **activos** (PC de caja, tablets,
celulares de CEDIS). Uno nuevo, aunque el usuario tenga contraseña, ve la pantalla **Este dispositivo no está
autorizado** y no pasa de ahí hasta capturar un código. El rol Consulta (rastreo por folio) queda exento.

- **Servidor: siempre `true`.** Con `false` cualquier equipo de la red (o de fuera, si el sitio se publica) entra solo
  con usuario y contraseña: una contraseña filtrada basta para operar pedidos, autorizar entregas y escribir en
  Microsip. Suspender o dar de baja un dispositivo tampoco tiene efecto mientras esté en `false`.
- **Desarrollo local: `false`**, para no pedir código en cada equipo de los desarrolladores.

Cada dispositivo tiene **nombre**, **dirección MAC** y **estatus**:

| Estatus | Opera | Se puede pasar a |
|---|---|---|
| Activo | Sí | Suspendido o Baja |
| Suspendido | No (bloqueo temporal) | Activo o Baja |
| Baja | No, nunca (equipo retirado o robado) | — Es definitiva: si vuelve a usarse se da de alta otra vez |

**Eliminar** (con cualquier estatus) lo quita de la lista de autorizados; también es definitivo. El registro queda
oculto para la bitácora y para reconocer su llave: si ese dispositivo intenta operar, se bloquea y se anota.

**Cómo se valida.** El navegador no puede leer la MAC del equipo, así que el acceso se valida en dos capas:

1. **Código y cookie (siempre):** el dispositivo se vincula una vez con un código de un solo uso; queda una llave en
   una cookie del navegador y solo pasa si su dispositivo está Activo.
2. **MAC en la red local (cuando se puede ver):** el servidor busca la IP del dispositivo en su tabla ARP. Si la ve y
   no coincide con la registrada (p. ej. la cookie se copió a otro equipo), lo bloquea. Por VPN, desde otra red o con
   IIS en otra subred no la ve y vale solo la cookie. `AA_DISPOSITIVOS_MAC=false` apaga esta capa.
   En celulares hay que **desactivar la "MAC privada / aleatoria"** para la red Wi-Fi de la empresa (Ajustes → Wi-Fi →
   la red), o el celular muestra una MAC distinta a la registrada.

La MAC se valida (12 dígitos hexadecimales; se aceptan `AA:BB:..`, `AA-BB-..`, `AABB.CCDD.EEFF`) y no se puede repetir
entre dispositivos activos o suspendidos (la de uno en Baja o eliminado se puede volver a registrar). Los intentos de
uso con un dispositivo suspendido, dado de baja, eliminado o con MAC distinta quedan en la **bitácora de
dispositivos** (una vez cada 10 minutos por dispositivo), igual que las altas, ediciones, cambios de estatus,
eliminaciones y los intentos de entrar al apartado sin ser administrador.

El apartado **Dispositivos autorizados** (menú del administrador) solo lo abre SYSDBA o un usuario con rol
Administrador; el permiso se revisa en el servidor en cada acción, no solo en el menú.

Alta de dispositivos, con `true`:

1. El **primero** es la máquina de **SYSDBA**, con el **código inicial** (sin consola):
   - En el `.env` del servidor: `AA_DISPOSITIVOS_CODIGO_INICIAL=` con un código inventado igual a los demás:
     **XXXX-XXXX**, 8 letras y números (p. ej. `K7Q2-M9XA`), y `php artisan config:clear` (sin consola: borrar
     `bootstrap/cache/config.php` si existe).
   - En esa máquina, entrar como **SYSDBA** y escribir el código en *Este dispositivo no está autorizado*. Se crea
     **PC Administración (SYSDBA)** ya vinculada. La MAC se llena sola si el servidor la ve; si no, se captura con
     **Editar**.
   - **Solo SYSDBA** puede usarlo (otro usuario se rechaza y queda en la bitácora) y **sirve una sola vez**. Si SYSDBA
     cambia de máquina y no hay otro administrador autorizado, se pone un código **distinto** en el `.env`.
   - Respaldo por consola: `php artisan aa:dispositivo-codigo "PC Administración" --mac=AA:BB:CC:DD:EE:FF`.
2. Los códigos de los demás valen 24 horas y una sola vez. El dispositivo queda vinculado con una cookie de 2 años;
   si se borran los datos del navegador, **Nuevo código** en el apartado y se captura otra vez.
3. Los demás: **Dispositivos autorizados → Dar de alta** (nombre y MAC) y capturar el código en cada dispositivo.
4. Para bloquearlo: **Suspender** (temporal), **Dar de baja** o **Eliminar** (definitivos). Se bloquea en su
   siguiente clic.

Al cambiar a `true` en el servidor: `php artisan config:clear` (o `config:cache`).

### Prueba de la entrega contra la copia (Firebird apagado y reintento)

La única escritura de la app en Microsip es marcar la venta entregada (`LIBRES_VTA_PV.ENTREGADO = 'S'`). Las
pruebas automáticas simulan Firebird (`tests/Feature/EscrituraMicrosipTest.php`); contra la **copia** se prueba así:

1. Con `AA_ORIGEN=firebird` y el `.env` apuntando a la copia, llevar un pedido hasta *En CEDIS* con un ticket real
   de la copia que no esté entregado. En IBExpert, `LIBRES_VTA_PV.ENTREGADO` de ese ticket debe estar en `N` o vacío.
2. **Apagar Firebird** (PowerShell como administrador): `Stop-Service FirebirdServerDefaultInstance`
   (el nombre exacto sale de `Get-Service *firebird*`).
3. Entregar el pedido en CEDIS con el acuse. Esperado: el pedido queda **Entregado**, aparece el aviso de que la
   venta no se actualizó, y la bitácora muestra *Error al actualizar Microsip* con el motivo (sin conexión).
4. **Encender Firebird**: `Start-Service FirebirdServerDefaultInstance`.
5. En el pedido, **Reintentar**. Esperado: *Reintento de actualización de venta* y *Venta actualizada en Microsip*
   en la bitácora, y en IBExpert `ENTREGADO = 'S'`.
6. Reintentar otra vez no debe ser posible (el botón desaparece); si se fuerza, la venta ya entregada no se vuelve
   a escribir.

La parte de escritura también tiene una prueba de integración que se corre a mano, con un ticket de la copia que no
esté entregado (lo marca y lo regresa como estaba):

```powershell
$env:AA_PRUEBA_FIREBIRD_FOLIO="TBA074886"; php artisan test --group=firebird-real; Remove-Item Env:AA_PRUEBA_FIREBIRD_FOLIO
```

Luego:

```powershell
php artisan migrate --seed      # el seeder demo solo en desarrollo
php artisan aa:firebird         # prueba la conexión con Microsip
php artisan config:cache; php artisan route:cache; php artisan view:cache
```

`php artisan aa:firebird` muestra la versión de Firebird, el charset de la base y si existen las tablas que usa la
app. Con `--mapa` escribe `docs/firebird/mapa-tablas.md` (columnas y triggers); con `--buscar=TEXTO` lista tablas
por nombre; con `--tabla=NOMBRE` (repetible) elige qué tablas documentar. Solo lee, nunca escribe en Microsip.

Con Microsip conectado, los pedidos nacen solos: `aa:importar-traspasos` (cada minuto y al abrir *Inicio* el cajero
o el administrador) da de alta como EN EXPERTOS cada traspaso capturado en Microsip Inventarios
(`SISTEMA_ORIGEN` = IN) con el concepto `AA_CONCEPTOS_TRASPASO` (por defecto 223048 = TRASPASO DE SALIDA), desde
`AA_TRASPASOS_DESDE`. Como no traen destino, quedan en la sucursal `AA_SUCURSAL_TRASPASOS_MICROSIP` (por defecto
`PASO DEL TORO`), que debe llamarse igual que la del cajero. Los resurtidos que genera Expertos no entran.

Un traspaso de Expertos se puede dar de alta a mano por consola:
`php artisan aa:importar-traspaso TSA013341 --partida=1` (opciones `--cliente=` y `--sucursal=`).

Las sucursales que se asignan a Cajero y Sucursal en *Usuarios* son los almacenes de Microsip (`ALMACENES`), sin los
de `AA_ALMACENES_NO_SUCURSAL` (por defecto CEDIS, materia prima, producción y los de tránsito). Requiere el
`GRANT SELECT ON ALMACENES` de arriba.

Con `AA_ORIGEN=firebird`, `db:seed` solo crea los usuarios; los pedidos demo se siembran únicamente en modo `demo`.

Después de cambiar el `.env` con la caché activa: `php artisan config:clear` (o volver a `config:cache`).

---

<br>

## 8. Tareas programadas

`schedule:run` cada minuto con el Programador de tareas de Windows (cancelaciones cada 5 min y purga de
evidencias a las 3:00):

```powershell
$accion = New-ScheduledTaskAction -Execute "C:\php\php.exe" -Argument "artisan schedule:run" -WorkingDirectory "C:\Laravel\aa"
$disparo = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1)
Register-ScheduledTask -TaskName "aa schedule:run" -Action $accion -Trigger $disparo -User "SYSTEM" -RunLevel Highest
```

---

<br>

## 9. Acceso desde celulares de la red local

```powershell
New-NetFirewallRule -DisplayName "aa IIS 8080" -Direction Inbound -Protocol TCP -LocalPort 8080 -Action Allow
```

Abrir `http://<IP del equipo>:8080` desde el celular en la misma red. La cámara del navegador puede exigir HTTPS;
en ese caso agregar un enlace `https` al sitio con un certificado de prueba.

---

<br>

## 10. Servidor del cliente

Mismos pasos 2 a 8. Además, antes del despliegue (S7):

- Confirmar que el servidor alcanza el Firebird de Microsip: `Test-NetConnection <servidor Microsip> -Port 3050`
  (directo o por VPN) y `php artisan aa:firebird` con el usuario `AA_APP`.

- `APP_ENV=production`, `APP_DEBUG=false`, `AA_DEMO=false`, `AA_DISPOSITIVOS=true` (obligatorio; antes de
  activarlo, autorizar la máquina de SYSDBA con el código inicial, sección 7). Revisar con la red del
  cliente si el IIS está en la misma subred que los dispositivos (validación por MAC) o si llegan por VPN.

- Los seeders de prueba no corren en producción (se saltan solos). Se entra con los usuarios de Microsip: el
  primero es **SYSDBA** con su contraseña de Microsip (entra siempre como administrador) y en **Usuarios → Asignar
  usuario de Microsip** se les da rol y sucursal a los demás. Revisar `DB_FIREBIRD_CONFIG_DATABASE` (ruta de
  `System\CONFIG.FDB` en el servidor) y `AA_EMPRESA_MICROSIP` (nombre corto de la empresa en Microsip).
  `aa:crear-admin` solo sirve en modo demo, con contraseñas de la app.

- Las sucursales de los usuarios deben llamarse igual que el destino del traspaso en Expertos (`PASO DEL TORO`,
  `ISLA`…), o Cajero y Sucursal no verán sus pedidos.

---

<br>

## Problemas comunes

| Síntoma | Causa y solución |
|---|---|
| 500.19 al abrir el sitio | Falta URL Rewrite |
| 500 sin detalle | Revisar `storage\logs\laravel.log`; si está vacío, permisos de `storage` |
| `could not find driver` | `pdo_firebird` no está en el `php.ini` que usa IIS (`C:\php\php.ini`, no el de XAMPP) |
| `pdo_firebird` no carga | `fbclient.dll` falta o es de 32 bits |
| `unsupported on-disk structure` | El `.FDB` es de otra versión de Firebird: restaurar desde `.fbk` (sección 4) |
| `Your user name and password are not defined` | Usuario o contraseña de Firebird; el usuario se crea en el servidor, no en la base |
| `I/O error ... CreateFile (open)` | La ruta del `.env` no es la que ve el servidor Firebird, o se usa un alias inexistente |
| `413` al subir fotos | Subir `maxAllowedContentLength` (web.config) y `post_max_size` juntos |
| Acentos o Ñ raros | Revisar el charset que reporta `aa:firebird` y `DB_FIREBIRD_CHARSET` |
| Todos caen en *Este dispositivo no está autorizado* | `AA_DISPOSITIVOS=true` sin dispositivos dados de alta: SYSDBA entra con el código inicial (`AA_DISPOSITIVOS_CODIGO_INICIAL`) o `php artisan aa:dispositivo-codigo "<nombre>" --mac=<MAC>` (sección 7) |
| Un dispositivo autorizado vuelve a pedir código | Se borró la cookie del navegador: **Nuevo código**. Si dice suspendido, dado de baja o eliminado, revisar su estatus |
| "La llave de este navegador es de otro dispositivo" | La MAC vista en la red no es la registrada: corregir la MAC (en celulares, quitar la MAC privada) o `AA_DISPOSITIVOS_MAC=false` |
