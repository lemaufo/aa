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

3. Comprobar: `php -m` debe listar `PDO_Firebird`, `pdo_mysql`, `mbstring`, `openssl`, `fileinfo` y `gd`.

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

DB_FIREBIRD_HOST=127.0.0.1
DB_FIREBIRD_PORT=3050
DB_FIREBIRD_DATABASE="C:/Microsip datos/ACEROS EL AGUILA.FDB"
DB_FIREBIRD_USERNAME=<usuario>
DB_FIREBIRD_PASSWORD=<contraseña>
DB_FIREBIRD_CHARSET=UTF8
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

- `APP_ENV=production`, `APP_DEBUG=false`, `AA_DEMO=false`, `AA_DISPOSITIVOS=true`.

- Los seeders de prueba no corren en producción (se saltan solos). El primer administrador se crea con
  `php artisan aa:crear-admin <usuario> --nombre="Nombre"` (pide la contraseña sin mostrarla); el resto de los
  usuarios se dan de alta en **Usuarios** dentro de la app.

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
