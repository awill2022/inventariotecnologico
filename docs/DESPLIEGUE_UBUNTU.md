# Manual de despliegue en Ubuntu Server

Guía paso a paso para publicar **Inventario Tecnológico · ISTSGA** en un servidor con **Ubuntu Server**, Apache, PHP y MariaDB (el motor compatible con MySQL que usa XAMPP).

Cada bloque de comandos se copia y se ejecuta tal cual en la terminal del servidor, salvo los valores entre `<...>`, que se reemplazan por los tuyos.

---

## Contenido

1. [Versiones compatibles](#1-versiones-compatibles)
2. [Requisitos previos](#2-requisitos-previos)
3. [Preparar el sistema](#3-preparar-el-sistema)
4. [Instalar Apache](#4-instalar-apache)
5. [Instalar PHP](#5-instalar-php)
6. [Instalar MariaDB](#6-instalar-mariadb)
7. [Subir el proyecto](#7-subir-el-proyecto)
8. [Crear la base de datos](#8-crear-la-base-de-datos)
9. [Configurar la aplicación](#9-configurar-la-aplicación)
10. [Permisos de carpetas](#10-permisos-de-carpetas)
11. [Sitio de Apache](#11-sitio-de-apache)
12. [HTTPS](#12-https)
13. [Firewall y seguridad](#13-firewall-y-seguridad)
14. [Comprobar la instalación](#14-comprobar-la-instalación)
15. [Respaldos](#15-respaldos)
16. [Actualizar a una nueva versión](#16-actualizar-a-una-nueva-versión)
17. [Solución de problemas](#17-solución-de-problemas)
18. [Anexo: usar MySQL 8 en lugar de MariaDB](#18-anexo-usar-mysql-8-en-lugar-de-mariadb)

---

## 1. Versiones compatibles

El sistema es PHP puro con un MVC propio: **no usa Composer, Laravel ni npm**, así que no hay dependencias que instalar aparte. Las librerías de JavaScript (`qrcode-generator` y `jsQR 1.4.0`) ya vienen dentro de `public/assets/js/vendor`.

| Componente | Desarrollo (XAMPP) | Ubuntu 24.04 LTS | Ubuntu 26.04 LTS | Versión mínima |
|---|---|---|---|---|
| PHP | 8.2.12 | 8.3 | 8.5 | **8.1** (el código usa el tipo `never`) |
| MariaDB | 10.4.32 | 10.11 | 11.8 | **10.4** |
| Apache | 2.4 | 2.4.58 | 2.4.66 | 2.4 con `mod_rewrite` |

**Recomendación: Ubuntu Server 24.04 LTS.** Sus versiones son las más cercanas a las de XAMPP con las que se desarrolló y tiene soporte hasta 2029. Ubuntu 26.04 LTS también funciona: el código no usa nada obsoleto en PHP 8.4 ni 8.5.

Tres reglas para no tener problemas de versiones:

- **Instala PHP con los paquetes genéricos** (`php`, `php-mysql`, ...), sin número de versión. Así apt instala la versión que trae tu Ubuntu y todas las extensiones quedan de la misma versión. No agregues el PPA `ondrej/php`: no hace falta y en 26.04 ni siquiera existe.
- **Usa MariaDB, no MySQL de Oracle.** Las migraciones de `database/migraciones/` usan `ADD COLUMN IF NOT EXISTS`, que solo existe en MariaDB. En MySQL 8 fallan con el error 1064. Si de todos modos necesitas MySQL, sigue el [anexo](#18-anexo-usar-mysql-8-en-lugar-de-mariadb).
- **Un respaldo se restaura en la misma versión de MariaDB o en una más nueva**, nunca en una más vieja.

Extensiones de PHP que usa el sistema: `pdo_mysql`, `mbstring`, `fileinfo`, `ctype` y `json`. **No necesita GD**, porque las fotos se reducen en el navegador.

Para ver qué versiones ofrece tu servidor antes de instalar:

```bash
lsb_release -a
apt policy php apache2 mariadb-server
```

---

## 2. Requisitos previos

- Un servidor con Ubuntu Server 24.04 o 26.04 recién instalado. Basta con 1 CPU, 1 GB de RAM y 10 GB de disco, más el espacio para las fotos y actas que se suban.
- Un usuario con permisos `sudo` y acceso por SSH.
- Opcional, pero recomendado: un **dominio** que apunte a la IP del servidor (por ejemplo `inventario.sangabrielriobamba.edu.ec`). Sin dominio el sistema funciona por IP, pero sin un certificado HTTPS válido (ver [HTTPS](#12-https)).

Conéctate desde Windows (PowerShell) o Linux:

```bash
ssh <usuario>@<ip-del-servidor>
```

---

## 3. Preparar el sistema

```bash
# Actualizar los paquetes
sudo apt update && sudo apt upgrade -y

# Herramientas básicas
sudo apt install -y git unzip curl ca-certificates

# Zona horaria de Ecuador: la usan PHP y la base de datos (fechas de registro, agenda de mantenimientos)
sudo timedatectl set-timezone America/Guayaquil
timedatectl
```

> La zona horaria importa: la base de datos guarda `creado_en` con su propio reloj y PHP con el suyo. Si no coinciden, los registros aparecen con 5 horas de diferencia.

---

## 4. Instalar Apache

```bash
sudo apt install -y apache2

# Módulos que necesita el sistema: rutas amigables (.htaccess) y cabeceras
sudo a2enmod rewrite headers

sudo systemctl enable --now apache2
systemctl status apache2 --no-pager
```

Si abres `http://<ip-del-servidor>` en el navegador debe aparecer la página de bienvenida de Apache.

---

## 5. Instalar PHP

```bash
sudo apt install -y php php-cli libapache2-mod-php php-mysql php-mbstring

# Comprobar la versión y las extensiones
php -v
php -m | grep -Ei 'pdo_mysql|mbstring|fileinfo|ctype|json'
```

Las cinco extensiones deben aparecer en la lista.

### Ajustes de PHP

Un reporte de falla admite 3 fotos de hasta 5 MB y un acta en PDF puede pesar hasta 10 MB. Ubuntu trae límites de 2 MB y 8 MB, así que hay que subirlos. Para no editar el `php.ini` original se crea un archivo propio, y este comando lo pone en la carpeta de la versión de PHP instalada:

```bash
PHPV=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
echo "Versión de PHP: $PHPV"

sudo tee /etc/php/$PHPV/apache2/conf.d/99-inventario.ini > /dev/null <<'EOF'
; Ajustes para Inventario Tecnológico
date.timezone       = America/Guayaquil
upload_max_filesize = 10M
post_max_size       = 25M
max_file_uploads    = 20
memory_limit        = 256M
max_execution_time  = 60

; Producción: los errores van al log de Apache, nunca a la pantalla
display_errors      = Off
log_errors          = On
expose_php          = Off

; Cookie de sesión
session.cookie_httponly = 1
session.use_strict_mode = 1
EOF

sudo systemctl restart apache2
```

---

## 6. Instalar MariaDB

```bash
sudo apt install -y mariadb-server mariadb-client
sudo systemctl enable --now mariadb
mariadb --version
```

Asegura la instalación:

```bash
sudo mariadb-secure-installation
```

Respuestas recomendadas:

| Pregunta | Respuesta |
|---|---|
| Enter current password for root | *(Enter, está vacía)* |
| Switch to unix_socket authentication | `n` (ya viene así en Ubuntu) |
| Change the root password? | `n` (root entra con `sudo mariadb`, sin clave) |
| Remove anonymous users? | `Y` |
| Disallow root login remotely? | `Y` |
| Remove test database? | `Y` |
| Reload privilege tables now? | `Y` |

MariaDB en Ubuntu solo escucha en `127.0.0.1`, así que la base de datos no queda expuesta a la red. Para comprobarlo:

```bash
sudo ss -ltnp | grep 3306
# Debe mostrar 127.0.0.1:3306, nunca 0.0.0.0:3306
```

> En XAMPP el proyecto usa el puerto **3307**; en el servidor MariaDB usa el puerto estándar **3306**. Esto se ajusta en el paso 9.

---

## 7. Subir el proyecto

El proyecto se instala en `/var/www/inventariotecnologico`.

### Opción A: clonar desde GitHub (recomendada)

```bash
cd /var/www
sudo git clone -b main https://github.com/awill2022/inventariotecnologico.git
cd /var/www/inventariotecnologico
git log --oneline -1
```

> Clona la rama que tenga la versión final (`main` después de unir los cambios). Si el repositorio es privado, GitHub pide usuario y un *token de acceso personal* en lugar de la contraseña.

### Opción B: copiar desde la PC con `scp`

En la PC con Windows (PowerShell), desde la carpeta del proyecto:

```powershell
cd C:\xampp\htdocs\inventariotecnologico
git archive --format=zip -o inventario.zip HEAD
scp inventario.zip <usuario>@<ip-del-servidor>:/tmp/
```

`git archive` empaqueta solo los archivos del repositorio, sin `.git` ni las fotos de prueba. Luego, en el servidor:

```bash
sudo mkdir -p /var/www/inventariotecnologico
sudo unzip /tmp/inventario.zip -d /var/www/inventariotecnologico
rm /tmp/inventario.zip
```

---

## 8. Crear la base de datos

### 8.1 Usuario propio para la aplicación

La aplicación **no debe conectarse como root**. Crea un usuario con permisos solo sobre su base y cambia `<clave-segura>` por una clave larga. Puedes generar una con `openssl rand -base64 24`.

```bash
sudo mariadb <<'EOF'
CREATE DATABASE IF NOT EXISTS inventario_tecnologico CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'inventario'@'localhost' IDENTIFIED BY '<clave-segura>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES, LOCK TABLES
   ON inventario_tecnologico.* TO 'inventario'@'localhost';
FLUSH PRIVILEGES;
EOF
```

### 8.2 Esquema y migraciones (en este orden)

```bash
cd /var/www/inventariotecnologico

# Esquema: ejecútalo UNA sola vez (sus CREATE TABLE fallan si las tablas ya existen)
sudo mariadb --default-character-set=utf8mb4 < database/db_mysql.sql

# Migraciones, en orden. Estas sí se pueden repetir sin problema
for f in database/migraciones/0*.sql; do
  echo "Aplicando $f"
  sudo mariadb --default-character-set=utf8mb4 < "$f" || break
done

# Comprobar: deben aparecer 16 tablas
sudo mariadb -e "SHOW TABLES" inventario_tecnologico
```

### 8.3 Primer administrador

El esquema no trae administrador. La clave se escribe aquí en texto plano y el sistema la convierte en *hash* en el primer inicio de sesión. Con `debe_cambiar_clave = TRUE` el sistema obliga a cambiarla al entrar. Usa una cédula válida y tus datos:

```bash
sudo mariadb inventario_tecnologico <<'EOF'
INSERT INTO personas (identificacion, nombres, apellidos, cargo)
VALUES ('<cedula>', '<Nombres>', '<Apellidos>', 'Administrador del sistema');

INSERT INTO usuarios (persona_id, usuario, clave_hash, rol, debe_cambiar_clave)
VALUES (LAST_INSERT_ID(), 'admin', '<ClaveTemporal>', 'administrador', TRUE);
EOF
```

### 8.4 Cuenta de prueba del esquema

`db_mysql.sql` crea al final la cuenta de prueba **`daniel`** (rol consulta) con la clave **`1234`**. En un servidor real **desactívala**, o cámbiale la clave desde el panel de usuarios:

```bash
sudo mariadb -e "UPDATE usuarios SET activo = 0 WHERE usuario = 'daniel'" inventario_tecnologico
```

### 8.5 Datos de demostración (solo en un servidor de pruebas)

Cargan dos sedes, 14 equipos, mantenimientos y las cuentas `paul` y `diego` con la clave `1234`. **No los cargues en producción.**

```bash
sudo mariadb --default-character-set=utf8mb4 < database/datos_demostracion.sql
```

---

## 9. Configurar la aplicación

Toda la configuración está en `app/config/config.php`:

```bash
sudo nano /var/www/inventariotecnologico/app/config/config.php
```

Cambia estos valores y deja el resto igual:

```php
'app' => [
    // ...
    'debug'    => false,   // OBLIGATORIO en el servidor: no mostrar errores a los usuarios
    // ...
],

'db' => [
    'host'    => 'localhost',          // por socket local
    'port'    => 3306,                 // en XAMPP era 3307
    'name'    => 'inventario_tecnologico',
    'user'    => 'inventario',         // el usuario creado en el paso 8.1
    'pass'    => '<clave-segura>',
    'charset' => 'utf8mb4',
],
```

Guarda con `Ctrl+O`, `Enter` y sal con `Ctrl+X`. Comprueba que no quedó ningún error de sintaxis:

```bash
sudo php -l /var/www/inventariotecnologico/app/config/config.php
```

Como `config.php` está en el repositorio, hay que evitar que un `git pull` futuro sobrescriba la clave o choque con tus cambios:

```bash
cd /var/www/inventariotecnologico
sudo git update-index --skip-worktree app/config/config.php
```

> La ruta base de las URL se calcula sola a partir de `public/index.php`: no hay nada que configurar para el dominio.

---

## 10. Permisos de carpetas

El código queda en modo solo lectura para Apache (`www-data`). Solo `storage/archivos/`, donde se guardan las fotos y actas, necesita permiso de escritura.

```bash
cd /var/www/inventariotecnologico

# Código: dueño root, grupo www-data, solo lectura para Apache
sudo chown -R root:www-data .
sudo find . -type d -exec chmod 750 {} \;
sudo find . -type f -exec chmod 640 {} \;

# Carpeta de subidas: Apache escribe aquí
sudo mkdir -p storage/archivos
sudo chown -R www-data:www-data storage/archivos
sudo chmod -R 770 storage/archivos
```

Con esto `config.php`, que contiene la clave de la base de datos, no lo pueden leer otros usuarios del servidor.

---

## 11. Sitio de Apache

El `DocumentRoot` apunta a **`public/`**. Así `app/`, `database/` (con los `.sql`), `storage/` y `.git` quedan fuera de la web.

```bash
sudo tee /etc/apache2/sites-available/inventario.conf > /dev/null <<'EOF'
<VirtualHost *:80>
    ServerName <tu-dominio-o-ip>
    ServerAdmin webmaster@localhost

    DocumentRoot /var/www/inventariotecnologico/public

    <Directory /var/www/inventariotecnologico/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Nada fuera de public/ se sirve, aunque alguien cambie el DocumentRoot por error
    <Directory /var/www/inventariotecnologico/app>
        Require all denied
    </Directory>
    <Directory /var/www/inventariotecnologico/storage>
        Require all denied
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/inventario_error.log
    CustomLog ${APACHE_LOG_DIR}/inventario_access.log combined
</VirtualHost>
EOF

# Activar el sitio y desactivar la página por defecto de Apache
sudo a2ensite inventario.conf
sudo a2dissite 000-default.conf

# Ocultar la versión de Apache en las respuestas y en las páginas de error
sudo sed -i 's/^ServerTokens .*/ServerTokens Prod/; s/^ServerSignature .*/ServerSignature Off/' /etc/apache2/conf-available/security.conf

# Revisar la sintaxis y recargar
sudo apache2ctl configtest
sudo systemctl reload apache2
```

`apache2ctl configtest` debe responder `Syntax OK`. `AllowOverride All` es necesario porque `public/.htaccess` envía todas las rutas (`/login`, `/equipos/5`, ...) a `index.php`.

---

## 12. HTTPS

HTTPS protege las claves al iniciar sesión, y además **el lector QR con cámara solo funciona en `https`** (por `http` solo se puede subir una foto del código). La cookie de sesión se marca como segura sola cuando el sitio entra por `https`.

### Con dominio: certificado gratuito de Let's Encrypt

El dominio debe apuntar a la IP del servidor y los puertos 80 y 443 deben estar abiertos.

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d <tu-dominio>
# Elige "redirect" para que todo http pase a https

# El certificado se renueva solo; para probar la renovación:
sudo certbot renew --dry-run
```

### Sin dominio (solo la IP, en la red del instituto): certificado autofirmado

El navegador mostrará una advertencia que hay que aceptar una vez en cada equipo. Sirve para pruebas internas; para uso real conviene un dominio.

```bash
sudo apt install -y ssl-cert
sudo a2enmod ssl

sudo tee /etc/apache2/sites-available/inventario-ssl.conf > /dev/null <<'EOF'
<VirtualHost *:443>
    ServerName <ip-del-servidor>
    DocumentRoot /var/www/inventariotecnologico/public

    SSLEngine on
    SSLCertificateFile    /etc/ssl/certs/ssl-cert-snakeoil.pem
    SSLCertificateKeyFile /etc/ssl/private/ssl-cert-snakeoil.key

    <Directory /var/www/inventariotecnologico/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/inventario_error.log
    CustomLog ${APACHE_LOG_DIR}/inventario_access.log combined
</VirtualHost>
EOF

sudo a2ensite inventario-ssl.conf
sudo apache2ctl configtest && sudo systemctl reload apache2
```

> **Etiquetas QR:** el QR guarda la dirección con la que se abrió el sistema al imprimir la etiqueta. Imprime las etiquetas entrando con el **dominio o la IP definitivos** (`https://<tu-dominio>`), nunca desde `localhost`, o los celulares no podrán abrirlas.

---

## 13. Firewall y seguridad

```bash
# Firewall: solo SSH y web
sudo ufw allow OpenSSH
sudo ufw allow 'Apache Full'      # puertos 80 y 443
sudo ufw enable
sudo ufw status

# Actualizaciones de seguridad automáticas
sudo apt install -y unattended-upgrades
sudo dpkg-reconfigure -plow unattended-upgrades

# (Opcional) Bloquear IP que intentan adivinar la clave de SSH
sudo apt install -y fail2ban
sudo systemctl enable --now fail2ban
```

Lista de control antes de abrir el sistema a los usuarios:

- [ ] `debug` en `false` en `app/config/config.php`.
- [ ] La aplicación usa el usuario `inventario`, no `root`.
- [ ] La cuenta `daniel` está desactivada y no se cargaron los datos de demostración.
- [ ] El administrador cambió su clave temporal.
- [ ] El sitio entra por `https`.
- [ ] `https://<servidor>/app/config/config.php` y `https://<servidor>/database/db_mysql.sql` responden 404 (comprobación del paso 14).

---

## 14. Comprobar la instalación

```bash
# Debe responder 200 y mostrar las cabeceras de seguridad (nosniff, SAMEORIGIN)
curl -I http://localhost/login

# Una ruta que no existe debe dar 404 (la responde la aplicación, no Apache)
curl -o /dev/null -s -w "%{http_code}\n" http://localhost/no-existe

# Los archivos internos no se sirven: deben dar 404
curl -o /dev/null -s -w "%{http_code}\n" http://localhost/database/db_mysql.sql
curl -o /dev/null -s -w "%{http_code}\n" http://localhost/app/config/config.php

# Conexión con el usuario de la aplicación
mariadb -u inventario -p -e "SELECT COUNT(*) AS usuarios FROM usuarios" inventario_tecnologico
```

Luego, en el navegador:

1. Abre `https://<tu-dominio>/` e inicia sesión con `admin` y la clave temporal. El sistema pide cambiarla.
2. Crea una categoría con prefijo, registra un equipo e imprime su etiqueta.
3. Escanea la etiqueta con un celular: debe abrir la ficha pública del equipo.
4. Sube una foto en un reporte de falla para confirmar que `storage/archivos` permite escribir.

Para ver errores en vivo mientras pruebas:

```bash
sudo tail -f /var/log/apache2/inventario_error.log
```

---

## 15. Respaldos

Hay que respaldar dos cosas: la **base de datos** y la carpeta **`storage/archivos`** (fotos y actas). El script guarda las dos cada noche y borra las copias de más de 14 días.

```bash
sudo mkdir -p /var/backups/inventario

sudo tee /usr/local/bin/respaldo-inventario.sh > /dev/null <<'EOF'
#!/bin/bash
set -euo pipefail
DESTINO=/var/backups/inventario
FECHA=$(date +%Y-%m-%d_%H%M)

# Base de datos (root entra por socket, sin clave)
mariadb-dump --single-transaction --default-character-set=utf8mb4 \
  --databases inventario_tecnologico | gzip > "$DESTINO/bd_$FECHA.sql.gz"

# Archivos subidos
tar -czf "$DESTINO/archivos_$FECHA.tar.gz" -C /var/www/inventariotecnologico/storage archivos

# Conservar 14 días
find "$DESTINO" -type f -mtime +14 -delete
EOF

sudo chmod 700 /usr/local/bin/respaldo-inventario.sh
sudo /usr/local/bin/respaldo-inventario.sh
ls -lh /var/backups/inventario

# Programarlo todos los días a las 02:00
echo "0 2 * * * root /usr/local/bin/respaldo-inventario.sh" | sudo tee /etc/cron.d/respaldo-inventario
```

> Copia los respaldos también **fuera del servidor**, por ejemplo con `scp` a otra máquina. Desde Windows: `scp <usuario>@<ip>:/var/backups/inventario/bd_*.sql.gz .`

### Restaurar

```bash
# Base de datos: reemplaza la actual por la del respaldo
gunzip < /var/backups/inventario/bd_<fecha>.sql.gz | sudo mariadb --default-character-set=utf8mb4

# Archivos subidos
sudo tar -xzf /var/backups/inventario/archivos_<fecha>.tar.gz -C /var/www/inventariotecnologico/storage
sudo chown -R www-data:www-data /var/www/inventariotecnologico/storage/archivos
```

### Pasar los datos de XAMPP al servidor

Si quieres llevar al servidor los datos que ya cargaste en tu PC, en lugar de empezar de cero:

```powershell
# En Windows (PowerShell): exportar desde el MariaDB de XAMPP (puerto 3307)
C:\xampp\mysql\bin\mysqldump.exe -uroot -P3307 -h127.0.0.1 --default-character-set=utf8mb4 --single-transaction --databases inventario_tecnologico -r inventario.sql
scp inventario.sql <usuario>@<ip-del-servidor>:/tmp/
```

```bash
# En el servidor, en lugar de los pasos 8.2 a 8.5 (el usuario del paso 8.1 sí hace falta)
sudo mariadb --default-character-set=utf8mb4 < /tmp/inventario.sql
rm /tmp/inventario.sql
```

XAMPP usa MariaDB 10.4 y el servidor una versión más nueva, así que la importación es compatible. Copia también la carpeta `C:\xampp\htdocs\inventariotecnologico\storage\archivos` a `/var/www/inventariotecnologico/storage/archivos` y repite los permisos del paso 10. Sin esa carpeta las fotos y actas aparecen rotas.

---

## 16. Actualizar a una nueva versión

```bash
cd /var/www/inventariotecnologico

# 1. Respaldo antes de tocar nada
sudo /usr/local/bin/respaldo-inventario.sh

# 2. Traer los cambios
sudo git pull origin main

# 3. Aplicar las migraciones nuevas (las ya aplicadas se pueden repetir sin problema).
#    Se leen con sudo cat porque tras el paso 10 los archivos ya no los lee tu usuario
for f in database/migraciones/0*.sql; do
  sudo cat "$f" | sudo mariadb --default-character-set=utf8mb4 || break
done

# 4. Volver a aplicar los permisos (los archivos nuevos llegan con los de git)
sudo chown -R root:www-data . && sudo chown -R www-data:www-data storage/archivos
sudo find . -path ./storage -prune -o -type d -exec chmod 750 {} \;
sudo find . -path ./storage -prune -o -type f -exec chmod 640 {} \;

# 5. Recargar Apache
sudo systemctl reload apache2
```

Si `git pull` dice que `config.php` tiene cambios en el repositorio, alguien cambió ese archivo en GitHub. Revísalo antes de forzar nada:

```bash
sudo git update-index --no-skip-worktree app/config/config.php
sudo git stash && sudo git pull origin main && sudo git stash pop
# resuelve las diferencias si las hay y vuelve a protegerlo:
sudo git update-index --skip-worktree app/config/config.php
```

**Actualizar Ubuntu de versión** (por ejemplo de 24.04 a 26.04) cambia PHP de 8.3 a 8.5 y MariaDB de 10.11 a 11.8. Haz un respaldo antes. Después de actualizar, vuelve a crear `99-inventario.ini` del [paso 5](#ajustes-de-php) en la carpeta de la nueva versión de PHP, porque el archivo anterior se queda en la carpeta vieja.

---

## 17. Solución de problemas

| Síntoma | Causa probable | Solución |
|---|---|---|
| Solo funciona la página principal; `/login` da **404 de Apache** | `mod_rewrite` apagado o `AllowOverride None` | `sudo a2enmod rewrite`, revisa `AllowOverride All` en el sitio (paso 11) y `sudo systemctl reload apache2` |
| Se ve el **código PHP** en el navegador o se descarga `index.php` | Falta `libapache2-mod-php` | `sudo apt install libapache2-mod-php && sudo systemctl restart apache2` |
| Página "Algo salió mal" | Error interno (con `debug` en `false` no se muestra el detalle) | `sudo tail -50 /var/log/apache2/inventario_error.log` |
| `SQLSTATE[HY000] [2002]` en el log | MariaDB apagado o host/puerto incorrecto | `sudo systemctl status mariadb`; revisa `host` y `port` (3306) en `config.php` |
| `SQLSTATE[HY000] [1045] Access denied` | Usuario o clave de la base mal escritos | Comprueba con `mariadb -u inventario -p`; si hace falta: `sudo mariadb -e "ALTER USER 'inventario'@'localhost' IDENTIFIED BY '<clave>'"` |
| `could not find driver` | Falta la extensión `pdo_mysql` | `sudo apt install php-mysql && sudo systemctl restart apache2` |
| `Call to undefined function mb_strlen()` | Falta `mbstring` | `sudo apt install php-mbstring && sudo systemctl restart apache2` |
| Error **1064** cerca de `IF NOT EXISTS` en una migración | Se instaló MySQL de Oracle, no MariaDB | Usa MariaDB o sigue el [anexo](#18-anexo-usar-mysql-8-en-lugar-de-mariadb) |
| Error `Table 'sedes' already exists` | `db_mysql.sql` se ejecutó dos veces | No hace falta repetirlo; continúa con las migraciones |
| Tildes y eñes aparecen como `Ã¡` | Datos importados sin utf8mb4 | Vuelve a importar con `--default-character-set=utf8mb4` |
| No se pueden subir fotos o actas | Permisos de `storage/archivos` o límites de PHP | Repite el paso 10; revisa con `php -i \| grep -E 'upload_max\|post_max'` (en Apache, mira `/etc/php/*/apache2/conf.d/99-inventario.ini`) |
| Las horas aparecen con 5 horas de diferencia | Zona horaria del servidor en UTC | `sudo timedatectl set-timezone America/Guayaquil && sudo systemctl restart mariadb apache2` |
| El lector QR no abre la cámara | El sitio entra por `http` | Activa HTTPS (paso 12) |
| El QR impreso abre `localhost` en el celular | La etiqueta se imprimió desde `localhost` | Vuelve a imprimirla entrando con el dominio o la IP del servidor |
| Se cierra la sesión al iniciar o el formulario dice que caducó | La cookie de sesión no se guarda | Revisa que `/var/lib/php/sessions` exista con permisos `drwx-wx-wt` (`ls -ld /var/lib/php/sessions`); no mezcles `http` y `https` |

Comandos útiles:

```bash
sudo systemctl status apache2 mariadb --no-pager     # estado de los servicios
sudo systemctl restart apache2                       # reiniciar Apache
sudo apache2ctl -M | grep -E 'rewrite|headers|php'   # módulos cargados
sudo tail -f /var/log/apache2/inventario_error.log   # errores de PHP y Apache
sudo tail -f /var/log/apache2/inventario_access.log  # visitas
```

---

## 18. Anexo: usar MySQL 8 en lugar de MariaDB

Solo si MariaDB no es una opción, por ejemplo porque el servidor ya tiene MySQL. El esquema, los datos de demostración y la aplicación funcionan igual en MySQL 8; lo único que cambia son las **migraciones**, porque MySQL no entiende `IF NOT EXISTS` en `ALTER TABLE`.

```bash
sudo apt install -y mysql-server
sudo systemctl enable --now mysql
sudo mysql_secure_installation
```

Pasos 8.1 a 8.5: usa `sudo mysql` en lugar de `sudo mariadb`. Las migraciones se aplican **una sola vez** y quitando el `IF NOT EXISTS` al vuelo:

```bash
cd /var/www/inventariotecnologico
sudo mysql --default-character-set=utf8mb4 < database/db_mysql.sql

for f in database/migraciones/0*.sql; do
  echo "Aplicando $f"
  sudo sed 's/ IF NOT EXISTS//g' "$f" | sudo mysql --default-character-set=utf8mb4 || break
done
```

> En MySQL las migraciones **no se pueden repetir**: si las ejecutas otra vez fallan con `Duplicate column name`. En una actualización, aplica solo las migraciones nuevas, una por una.

En los respaldos (paso 15) cambia `mariadb-dump` por `mysqldump` y `mariadb` por `mysql`. En `config.php` los valores son los mismos (`localhost`, puerto `3306`).

---

Instituto Superior Tecnológico San Gabriel · Riobamba, Ecuador
