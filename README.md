# Inventario Tecnológico · ISTSGA

Sistema web para llevar el inventario de los equipos tecnológicos del **Instituto Superior Tecnológico San Gabriel** (Riobamba): qué equipos hay, dónde están, quién los tiene a cargo, en qué estado se encuentran y qué mantenimiento han recibido.

Proyecto de la asignatura **Programación PHP** (4.º semestre), construido en sprints semanales.

> **Estado:** los 8 sprints (0 a 7) están terminados: login, página del docente, catálogos, personas y usuarios, equipos, entregas y movimientos, mantenimientos y repuestos, página pública del QR y archivos adjuntos, dashboard y auditoría. Ver [Avance por sprint](#avance-por-sprint).

---

## Contenido

- [Funciones por rol](#funciones-por-rol)
- [Tecnologías](#tecnologías)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Instalación](#instalación)
- [Cuentas de prueba](#cuentas-de-prueba)
- [Reglas de negocio](#reglas-de-negocio)
- [Avance por sprint](#avance-por-sprint)
- [Seguridad](#seguridad)

---

## Funciones por rol

| Módulo | Administrador | Inventario | Técnico | Consulta (docente) |
|---|---|---|---|---|
| Catálogos (categorías, sedes, áreas, ubicaciones) | Crea y edita | Ve | — | — |
| Personas | Crea y edita | Crea y edita | Ve | — |
| Usuarios (cuentas, roles, claves) | Crea y edita | — | — | — |
| Equipos (alta, edición, baja) | Sí | Sí | Solo la ficha, desde sus trabajos | Solo los suyos |
| Revertir la baja de un equipo | Sí | — | — | — |
| Especificaciones del equipo | Sí | Sí | Sí | — |
| Componentes (instalar y retirar) | Sí | Ve | Sí | — |
| Etiquetas QR imprimibles | Sí | Sí | — | — |
| Entregas y devoluciones | Sí | Sí | — | Solo las suyas |
| Traslados entre ubicaciones | Sí | Sí | — | — |
| Actas de entrega imprimibles | Sí | Sí | — | — |
| Adjuntos de entregas y traslados (acta firmada, respaldo) | Sí | Sí | — | — |
| Mantenimientos (programar, iniciar, finalizar, cancelar) | Sí | Ve | Sí (los libres o los suyos) | — |
| Asignar un mantenimiento a otro técnico | Sí | — | — | — |
| Repuestos y su stock | Sí | Ve | Sí | — |
| Reportar una falla (con fotos, también desde el QR) | — | — | — | Sí, solo de sus equipos |
| Ver las fotos de un reporte de falla | Sí | Sí | Sí | Las que subió |
| Página pública del QR `/q/{token}` (también sin sesión) | Sí, con enlace a la ficha | Sí, con enlace a la ficha | Sí, con enlace a la ficha | Sí |
| Dashboard del inicio (indicadores, equipos por estado, agenda de mantenimientos) | Sí | Sí | Su propio inicio de soporte | — |
| Actividad reciente y visor de auditoría | Sí | — | — | — |
| Mi perfil (cambiar clave) | Sí | Sí | Sí | Sí |

Cada rol entra a su propia página de inicio: el docente a **`/docente`** (sus equipos, reporte de fallas e historial) y los demás al **panel** con barra lateral.

El **técnico** solo da soporte técnico: no ve catálogos ni inventario. Su inicio le muestra primero los **reportes de falla por atender** (del más antiguo al más reciente, con *Tomar e iniciar*) y debajo sus trabajos abiertos. Abre la ficha de un equipo en solo lectura desde sus trabajos, y ahí registra especificaciones y componentes.

### Lo que ya se puede hacer

- **Iniciar sesión** con bloqueo tras 5 intentos fallidos (15 minutos) y registro de cada acceso en la auditoría.
- **Docente:** ver las etiquetas de los equipos a su cargo con QR, buscarlos, filtrar los que requieren atención y **reportar una falla** (crea un mantenimiento de diagnóstico y cambia el estado del equipo).
- **Catálogos:** categorías (con prefijo para el código de equipo), sedes, áreas (con área padre, sin ciclos) y ubicaciones.
- **Personas y usuarios:** cédula ecuatoriana validada, una cuenta por persona, roles, clave temporal que se debe cambiar al entrar.
- **Equipos:**
  - Listado con buscador (código, patrimonial, serie, nombre, marca), filtros por categoría, estado, ubicación y área, y pestañas *Vigentes / En uso / En bodega / De baja / Todos*.
  - Alta con **código propuesto** según la categoría (`EQ-NB-0231`), token QR aleatorio y **movimiento de ingreso** a la ubicación inicial.
  - Ficha por secciones: datos generales, ubicación y responsable, adquisición y garantía, especificaciones, componentes, entregas y movimientos.
  - Editor de **especificaciones** (atributo–valor) con sugerencias.
  - **Componentes**: instalar, corregir y retirar (los retirados quedan en el historial).
  - **Baja** con fecha y motivo (solo si no está entregado ni en mantenimiento); el administrador puede revertirla.
  - **Etiquetas QR** de 90 × 50 mm para imprimir en A4, una o varias a la vez.
- **Asignaciones (entregas y devoluciones):**
  - Entregar un equipo libre (operativo o con fallas) a una persona activa, con la ubicación donde lo usará, la fecha y hora y el estado en que se entrega. Si cambia de lugar, el traslado se registra solo.
  - Registrar la devolución: cómo vuelve (*buen estado / con fallas / no funciona*) con una nota, y a dónde vuelve (por defecto, la bodega). Con fallas o sin funcionar cambia su estado operativo.
  - Listado con buscador y pestañas *Entregados / Devueltos / Todas*.
  - **Acta de entrega-recepción** en A4 para imprimir y firmar; si ya se devolvió, incluye la devolución.
- **Movimientos:** historial de todos los cambios de ubicación con filtros (equipo, ubicación, fechas) y registro de **traslados** con motivo y quién lo autoriza.
- **Ficha de la persona:** sus datos, los equipos que tiene a cargo y los que ya devolvió.
- **Mantenimientos:**
  - Bandeja con indicadores (reportes de falla sin atender, atrasados, en proceso, mis trabajos), buscador, filtros por tipo y técnico, y pestañas *Por atender / En proceso / Finalizados / Cancelados / Todos*.
  - Programar un preventivo, correctivo, diagnóstico o actualización, con fecha y técnico opcional; editarlo mientras no se inicie.
  - **Tomar e iniciar**: el técnico se lo asigna y el equipo queda *en mantenimiento*. El administrador puede asignar o cambiar el técnico.
  - **Finalizar** con diagnóstico, trabajo realizado, resultado, mano de obra, recomendaciones y el estado en que queda el equipo. Si se indica el *próximo mantenimiento*, se programa un preventivo para esa fecha.
  - **Cancelar** con motivo (si estaba en proceso, se elige el estado del equipo).
  - **Repuestos usados** con costo total (repuestos + mano de obra) y **componentes** instalados o retirados durante el trabajo.
  - La ficha del equipo muestra sus mantenimientos y permite programar uno.
- **Repuestos:** catálogo con stock, mínimo y costo de referencia; el stock se pinta en rojo al llegar al mínimo y formulario **Agregar stock** para los ingresos.
- **Página del QR (`/q/{token}`):** al escanear la etiqueta, cualquiera ve la ficha técnica del equipo sin iniciar sesión: código, patrimonial, estado, ubicación, responsable, último mantenimiento y especificaciones (no muestra serie, valor, proveedor ni historial). **Reportar una falla** desde ahí pide iniciar sesión y vuelve al formulario; solo lo envía el docente que tiene el equipo a su cargo.
- **Fotos de la falla:** el docente adjunta hasta 3 fotos al reportar (*Tomar foto* con la cámara del celular o *Elegir de la galería*). El navegador las reduce a 1600 px en JPEG antes de enviarlas. El técnico las ve en la ficha del reporte y su inicio indica cuántas trae cada uno.
- **Lector QR del docente:** el botón *QR* abre la cámara y lleva a la página del equipo. Si el navegador no permite la cámara en vivo (sitio abierto por `http` desde otra máquina), pide una foto del código y la lee.
- **Adjuntos:** acta de entrega o devolución firmada (PDF o foto) en cada asignación y un respaldo en cada movimiento, con descarga y eliminación.
- **Dashboard del inicio** (administrador e inventario), arriba de los accesos a los módulos:
  - Indicadores: equipos vigentes, reportes de falla sin atender, en mantenimiento, mantenimientos atrasados y repuestos por reponer; cada uno lleva a su listado.
  - **Estado de los equipos** en barras: por funcionamiento (operativo, con fallas, en mantenimiento, inoperativo) y por inventario (en uso, en bodega, de baja). Cada barra abre el listado filtrado.
  - **Dónde están:** equipos vigentes por categoría y por sede.
  - **Agenda de mantenimientos:** los programados atrasados y los de los próximos 30 días, con su técnico.
  - **Actividad reciente** (solo administrador): las últimas acciones registradas.
- **Auditoría** (solo administrador): todas las altas, cambios, cierres y bajas con quién, cuándo y qué cambió (*campo: antes → después*), filtros por acción, usuario, módulo y fechas, y la IP y el navegador en el detalle. Los inicios de sesión se ven al elegir esa acción. Desde la ficha de un equipo, *Historial de cambios* abre la auditoría de ese equipo.

---

## Tecnologías

| Capa | Herramientas |
|---|---|
| Frontend | HTML, CSS y JavaScript sin frameworks (archivos separados), diseño *mobile first* |
| Backend | PHP 8 con arquitectura **MVC** propia (front controller, router, controladores, modelos, vistas) |
| Base de datos | MySQL / MariaDB (XAMPP, phpMyAdmin) en el puerto **3307** |
| Librerías | [qrcode-generator](https://github.com/kazuhikoarase/qrcode-generator) y [jsQR 1.4.0](https://github.com/cozmo/jsQR) (Apache-2.0), locales en `public/assets/js/vendor` |
| Tipografía | Source Serif 4 y Source Sans 3 (Google Fonts) |

La identidad visual (colores, tipografía, componentes) está documentada en [`DESIGN.md`](DESIGN.md).

---

## Estructura del proyecto

```
inventariotecnologico/
├── app/
│   ├── config/config.php      # Nombre, zona horaria, base de datos, inicio por rol
│   ├── core/                  # App, Router, Controller, Model, View, Session, Auth, Database
│   ├── controllers/           # Un controlador por módulo (Equipo, Persona, Usuario, ...)
│   ├── models/                # Acceso a datos con PDO y consultas preparadas
│   ├── helpers/               # Validador, Formato, Menu, Clave, EtiquetaEquipo, ...
│   ├── views/                 # Vistas por módulo, layouts (auth, main, panel, impresion) y parciales
│   └── routes.php             # Tabla de rutas con los roles permitidos
├── database/
│   ├── db_mysql.sql           # Esquema completo (16 tablas)
│   ├── migraciones/           # Cambios pequeños al esquema, numerados
│   └── datos_demostracion.sql # Datos de prueba (se puede ejecutar varias veces)
├── public/                    # Única carpeta pública: index.php + assets (css, js)
├── storage/archivos/          # Fotos y actas subidas (privada: .htaccess niega el acceso; no va al repositorio)
├── DESIGN.md                  # Sistema de diseño
└── planificacion_del_sistema.md  # Ruta de trabajo por sprints
```

Toda petición entra por `public/index.php`. Las rutas admiten parámetros (`/equipos/{id}`) y cada una declara qué roles pueden usarla.

---

## Instalación

### Requisitos

- [XAMPP](https://www.apachefriends.org/) con PHP 8.1 o superior y MySQL/MariaDB.
- MySQL escuchando en el puerto **3307** (o cambia el puerto en `app/config/config.php`).

### Pasos

1. **Clonar** el repositorio dentro de `htdocs`:
   ```bash
   cd C:/xampp/htdocs
   git clone <url-del-repositorio> inventariotecnologico
   ```

2. **Crear la base de datos** con el esquema y luego aplicar las migraciones **en orden**:
   ```bash
   mysql -uroot -P3307 -h127.0.0.1 < database/db_mysql.sql
   mysql -uroot -P3307 -h127.0.0.1 < database/migraciones/001_equipos_baja.sql
   mysql -uroot -P3307 -h127.0.0.1 < database/migraciones/002_usuarios_cambio_clave.sql
   mysql -uroot -P3307 -h127.0.0.1 < database/migraciones/003_repuestos_stock.sql
   mysql -uroot -P3307 -h127.0.0.1 < database/migraciones/004_categorias_prefijo.sql
   ```
   También se pueden importar desde phpMyAdmin en el mismo orden. Las migraciones se pueden ejecutar más de una vez sin problema.

3. **Crear el primer administrador.** El esquema no trae uno. La clave que se escribe aquí en texto plano se convierte en *hash* en el primer inicio de sesión; cámbiala enseguida en *Mi perfil*:
   ```sql
   USE inventario_tecnologico;
   INSERT INTO personas (identificacion, nombres, apellidos, cargo) VALUES ('0601234560', 'Nombre', 'Apellido', 'Administrador del sistema');
   INSERT INTO usuarios (persona_id, usuario, clave_hash, rol) VALUES (LAST_INSERT_ID(), 'admin', 'ClaveTemporal1', 'administrador');
   ```

4. **(Opcional) Cargar los datos de demostración**: dos sedes, áreas, ubicaciones, categorías con prefijo, personal técnico, 14 equipos con especificaciones, componentes, asignaciones, mantenimientos (con una agenda de fechas relativas al día en que se ejecuta) y repuestos con stock, más acciones de ejemplo en la auditoría. Al volver a ejecutarlo, el historial de los equipos demo (también los reportes y fotos hechos en las pruebas) se crea de nuevo:
   ```bash
   mysql -uroot -P3307 -h127.0.0.1 --default-character-set=utf8mb4 < database/datos_demostracion.sql
   ```

5. **Abrir** `http://localhost/inventariotecnologico/public/` con Apache y MySQL encendidos.

6. **Archivos subidos:** la carpeta `storage/archivos/` debe permitir escritura a Apache. Las fotos se suben hasta 5 MB y los PDF hasta 10 MB (`archivos` en `app/config/config.php`); PHP debe aceptar envíos de ese tamaño (`upload_max_filesize` y `post_max_size`, en XAMPP vienen en 40 MB). No hace falta la extensión GD: las fotos se reducen en el navegador.

> **QR desde el celular:** el QR guarda la dirección con la que se abrió el sistema al imprimir la etiqueta. Si imprimes etiquetas desde `localhost`, un celular no podrá abrirlas: imprímelas entrando con la IP o el dominio del servidor. La cámara en vivo del lector QR solo funciona con `https` o en `localhost`; por `http` se usa la foto del código.

> Si cambias la ubicación del proyecto, la ruta base se calcula sola a partir de `public/index.php`; no hay que configurarla.

---

## Cuentas de prueba

Con los datos de demostración quedan estas cuentas (clave inicial `1234`, se convierte en *hash* al entrar por primera vez):

| Usuario | Rol | Qué ve |
|---|---|---|
| `paul` | Inventario | Panel: personas, equipos, etiquetas |
| `diego` | Técnico | Panel: inicio con los reportes de falla, bandeja de mantenimientos, repuestos y personas |
| `daniel` | Consulta | Página del docente con sus equipos |

El administrador es el que se crea en el paso 3 de la instalación.

> Son cuentas solo para pruebas locales. No uses estas claves en un servidor real.

---

## Reglas de negocio

- **Nada se borra:** catálogos y personas se desactivan; los equipos se dan **de baja** con fecha y motivo; los componentes retirados quedan en el historial.
- **La ubicación de un equipo solo cambia con un movimiento** (el alta registra el movimiento de ingreso; el formulario de edición no la toca).
- **Estados del equipo:** el *estado operativo* (operativo, con fallas, en mantenimiento, inoperativo) lo cambian los reportes de falla y el mantenimiento; el *estado de inventario* (en uso, en bodega, de baja) lo cambian las entregas, devoluciones y la baja.
- **Una sola entrega abierta por equipo** (lo garantiza un índice único del esquema; además, la fila del equipo se bloquea durante la entrega).
- **Qué se puede entregar:** equipos vigentes, sin otra entrega abierta y *operativos* o *con fallas* (la falla queda anotada en el estado de entrega). Los inoperativos y los que están en mantenimiento no se entregan.
- **Estado de inventario según el destino:** al llegar a una bodega queda *en bodega*; a otro lugar (aula, laboratorio, oficina) queda *en uso*. Un equipo entregado sigue *en uso* aunque se traslade.
- **Fechas en orden:** una entrega, devolución o traslado no puede ser futuro ni anterior al último evento del equipo.
- **No hay préstamos:** la Unidad de Inventario entrega equipos a una persona y registra su devolución.
- **Código del equipo:** se propone `EQ-{PREFIJO DE LA CATEGORÍA}-{número}`, se puede corregir y no se repite.
- **Mantenimientos:** un reporte de falla del docente llega como *diagnóstico* sin técnico. Solo hay un trabajo en proceso por equipo; al finalizarlo se cierran también los reportes pendientes de ese equipo. Un técnico solo opera los trabajos libres o asignados a él.
- **Stock de repuestos:** se descuenta al agregar el repuesto a un trabajo en proceso y vuelve al stock si se quita antes de finalizar; nunca queda en negativo. Un trabajo con repuestos no se cancela: primero se quitan.
- **Archivos:** fotos JPG, PNG o WebP y documentos PDF; el tipo se comprueba por el contenido, no por el nombre. Se guardan con nombre aleatorio y solo se descargan por `/archivos/{id}` con el permiso del módulo al que pertenecen (o por quien los subió). Eliminar un adjunto sí lo borra, y queda en la auditoría.
- **Auditoría:** cada alta, cambio, baja, reversión, retiro, subida y acceso queda en la tabla `auditoria` con el usuario, la fecha y el detalle de lo que cambió.

---

## Avance por sprint

| Sprint | Contenido | Estado |
|---|---|---|
| 0 | Núcleo MVC, login con límite de intentos, página del docente, reporte de fallas | ✅ Hecho |
| 1 | Panel con barra lateral, router con parámetros, catálogos (categorías, sedes, áreas, ubicaciones) | ✅ Hecho |
| 2 | Personas, usuarios, roles, clave temporal y *Mi perfil* | ✅ Hecho |
| 3 | Equipos: listado y filtros, alta con código propuesto, ficha, especificaciones, componentes, baja y etiquetas QR | ✅ Hecho |
| 4 | Entregas y devoluciones con acta imprimible, traslados, historial de movimientos y ficha de la persona | ✅ Hecho |
| 5 | Mantenimientos (bandeja del técnico, programar, iniciar, finalizar, cancelar), repuestos con stock y componentes enlazados | ✅ Hecho |
| 6 | Página pública del QR `/q/{token}`, fotos en el reporte de falla, adjuntos de entregas y traslados, lector QR del docente | ✅ Hecho |
| 7 | Dashboard del inicio, visor de auditoría, datos demo ampliados y revisión de seguridad | ✅ Hecho |

El detalle de cada sprint está en [`planificacion_del_sistema.md`](planificacion_del_sistema.md).

### Migraciones

| Script | Cambio | Sprint |
|---|---|---|
| `001_equipos_baja.sql` | `equipos.fecha_baja`, `equipos.motivo_baja` | 3 |
| `002_usuarios_cambio_clave.sql` | `usuarios.debe_cambiar_clave` | 2 |
| `003_repuestos_stock.sql` | `repuestos.stock`, `stock_minimo`, `costo_referencial` | 5 |
| `004_categorias_prefijo.sql` | `categorias.prefijo` (único) | 3 |

---

## Seguridad

- Claves con `password_hash` / `password_verify`; las claves antiguas en texto plano se migran solas al iniciar sesión.
- Token **CSRF** en todos los formularios que escriben.
- Control de acceso **por rol en cada ruta** (`app/routes.php`) y comprobaciones extra en el servidor (por ejemplo, un componente solo se edita desde su propio equipo).
- Consultas preparadas con PDO (sin emulación) y salida escapada con `View::e()`.
- Validación en el navegador **y** en el servidor, con mensajes por campo.
- Subidas validadas con `finfo` (tipo real), `getimagesize` y cabecera PDF; guardadas fuera de `public/` y servidas con `X-Content-Type-Options: nosniff`.
- Tras iniciar sesión se vuelve a la página pedida, guardada en la sesión del servidor (nunca un parámetro de la URL).
- Las rutas del visor de auditoría y de la actividad reciente son solo del administrador.
- El proyecto puede vivir dentro de `htdocs`: el `.htaccess` de la raíz responde 404 a `app/`, `config/`, `database/`, `storage/`, los archivos ocultos (`.git`) y los `.sql`/`.md`, y la raíz lleva a `public/`.
- Cabeceras en todas las respuestas: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN` y `Referrer-Policy: same-origin`.
- `debug` en `app/config/config.php` viene en `false`: un error inesperado muestra una página genérica y el detalle queda en el log de PHP (en XAMPP, `C:\xampp\php\logs\php_error_log`). Para desarrollar en local se puede poner en `true`.

---

Instituto Superior Tecnológico San Gabriel · Riobamba, Ecuador
