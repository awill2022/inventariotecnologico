# Ruta de trabajo — Sistema de Inventario Tecnológico (ISTSGA)

## Contexto

El proyecto ya tiene el núcleo MVC, el login y la página del rol `consulta` (`/docente`). Los roles `administrador`, `inventario` y `tecnico` solo ven `/panel` "en construcción". Esta ruta ordena la construcción del resto del sistema a partir del esquema `db_mysql.sql` (16 tablas).

Decisiones del usuario (2026-11-11):

- **Alcance**: sistema completo, construido por Daniel solo en la rama `Daniel`.
- **Ritmo**: sprints de 1 semana.
- **Esquema**: se permiten cambios pequeños mediante scripts de migración en `database/`.
- **Extras incluidos**: archivos adjuntos, página del QR, dashboard y visor de auditoría.
- **Fuera de alcance**: reportes exportables (PDF/Excel), préstamos, recuperación de clave por correo.

## Análisis

### Orden de dependencias (según llaves foráneas)

`sedes` / `areas` / `categorias` → `ubicaciones` → `personas` → `usuarios` → `equipos` (+ `especificaciones`, `componentes`) → `asignaciones` / `movimientos` → `mantenimientos` (+ `repuestos`, `mantenimiento_repuestos`) → `archivos` → `auditoria` (transversal).

### Permisos por rol (propuesta por defecto)

| Módulo | administrador | inventario | tecnico | consulta |
|---|---|---|---|---|
| Catálogos (sedes, áreas, ubicaciones, categorías) | CRUD | ver | ver | — |
| Personas | CRUD | CRUD | ver | — |
| Usuarios y auditoría | CRUD / ver | — | — | — |
| Equipos, especificaciones, baja | CRUD | CRUD | ver | solo los suyos |
| Asignaciones y movimientos | CRUD | CRUD | ver | — |
| Mantenimientos, repuestos, componentes | CRUD | ver | CRUD | reportar falla |
| Archivos | subir / ver | subir / ver | subir / ver | — |

### Reglas que impone el esquema

- **Sin borrado físico**: catálogos y personas se desactivan (`activo = 0`); los equipos pasan a `estado_inventario = 'de_baja'`.
- **Una asignación abierta por equipo**: `uq_asignacion_abierta` lo garantiza; la aplicación debe validar antes y capturar el error de duplicado.
- **Ubicación del equipo**: `equipos.ubicacion_actual_id` solo cambia a través de un registro en `movimientos`, dentro de una transacción.
- **Estados del equipo**: `estado_operativo` lo mueven el reporte de falla y el mantenimiento; `estado_inventario` lo mueven asignación, devolución y baja. El valor `prestado` no se usa.
- **Auditoría**: toda escritura registra en `auditoria` con `Auditoria::registrar()` (`crear`, `editar`, `cerrar`, `anular`).

### Migraciones pequeñas previstas (`database/migraciones/`)

| Script | Cambio | Para qué |
|---|---|---|
| `001_equipos_baja.sql` | `equipos.fecha_baja DATE NULL`, `equipos.motivo_baja VARCHAR(255) NULL` | Registrar la baja |
| `002_usuarios_cambio_clave.sql` | `usuarios.debe_cambiar_clave BOOLEAN DEFAULT FALSE` | Forzar cambio tras un restablecimiento |
| `003_repuestos_stock.sql` | `repuestos.stock DECIMAL(10,2) DEFAULT 0`, `repuestos.stock_minimo` | Controlar existencias |
| `004_categorias_prefijo.sql` | `categorias.prefijo VARCHAR(6) NULL UNIQUE` | Proponer el código del equipo: `EQ-{PREFIJO}-{número}` (Sprint 3) |

### Piezas existentes que se reutilizan

- `app/core/Controller.php`: `view()`, `redirect()`, `json()`, `requireCsrf()`.
- `app/core/Model.php`: `fetchOne()`, `fetchAll()`, `execute()`. `transaccion()` está hoy en `app/models/Mantenimiento.php` y se sube a `Model`.
- `app/models/Auditoria.php`: `registrar()`.
- `app/core/Session.php`: `flash()` y `csrfToken()` para mensajes y formularios.
- `app/helpers/Formato.php`, `EtiquetaEquipo.php`, `EventoHistorial.php` y `app/models/Historial.php` para la ficha del equipo.
- `public/assets/js/vendor/qrcode.min.js` para etiquetas QR.
- `DESIGN.md`, `base.css`, `layout.css`, `partials/header.php`, `partials/iconos.php` para el aspecto visual.
- Patrón de validación de `DocenteController::reportar()`: errores por campo + `old` en flash + redirección.

## Sprints

Cada sprint empieza con análisis y preguntas abiertas, y termina sin commit (lo hace el usuario).

### Sprint 0 — Hecho
Núcleo MVC, login, página `/docente`, datos demo.

### Sprint 1 — Base del panel y catálogos
1. Ampliar `app/core/Router.php` para rutas con parámetros (`/equipos/{id}`).
2. Subir `transaccion()` a `app/core/Model.php`.
3. Ordenar `database/`: `schema.sql` (sin los INSERT erróneos), `migraciones/`, `seed_demo.sql`.
4. Layout del panel: navegación por rol que reemplaza a `panel/en_construccion.php`.
5. Piezas comunes: helper `Validador`, parciales de tabla con buscador y paginación, formulario, mensajes flash, confirmación de desactivar.
6. CRUD de `categorias`.
7. CRUD de `sedes`.
8. CRUD de `areas` con área padre (evitar ciclos).
9. CRUD de `ubicaciones` (sede + área + tipo).

### Sprint 2 — Personas y usuarios
1. CRUD de `personas` (identificación única, área, activar/desactivar).
2. Impedir desactivar una persona con asignaciones abiertas.
3. CRUD de `usuarios`: vincular persona, rol, activar/desactivar.
4. Restablecer clave por el administrador + migración `002`.
5. "Mi perfil": cambiar mi clave; cambio obligatorio si `debe_cambiar_clave = 1`.
6. Protecciones: no desactivarse a sí mismo ni dejar el sistema sin administrador activo.

### Sprint 3 — Equipos
1. Listado con filtros (categoría, estado, ubicación, área), buscador y paginación.
2. Alta: `codigo` único, `qr_token` con `bin2hex(random_bytes(16))`, `creado_por`.
3. Edición y ficha de detalle por secciones.
4. `especificaciones`: pares atributo–valor (único por equipo).
5. `componentes`: instalar y retirar.
6. Baja de equipo + migración `001` (exige que no tenga asignación abierta).
7. Etiqueta QR imprimible.

### Sprint 4 — Asignaciones y movimientos
1. Entrega: elegir equipo disponible y persona activa, `estado_entrega`, ubicación de entrega.
2. Devolución: `fecha_devolucion`, `estado_devolucion`, destino (bodega → `en_bodega`).
3. Movimientos: origen = ubicación actual, destino, motivo, `autorizado_por`; actualiza el equipo.
4. Entrega y devolución generan su movimiento en la misma transacción.
5. Historial de asignaciones y movimientos en la ficha del equipo y en la de la persona.
6. Verificar que `/docente` refleja entregas y devoluciones.

### Sprint 5 — Mantenimiento y repuestos
1. Bandeja del técnico: reportes de falla (`diagnostico` / `programado` sin técnico), en proceso, programados.
2. Tomar e iniciar: `tecnico_id`, `fecha_inicio`, `en_proceso`; equipo → `en_mantenimiento`.
3. Programar preventivos (`fecha_programada`).
4. Finalizar: diagnóstico, trabajo, resultado, costo, `proximo_mantenimiento`, estado final del equipo.
5. Cancelar con motivo (auditoría `anular`).
6. CRUD de `repuestos` + migración `003`.
7. Repuestos usados (`mantenimiento_repuestos`) con descuento de stock y costo total.
8. Componentes instalados o retirados enlazados al mantenimiento.

### Sprint 6 — Archivos y página del QR
1. Subida de archivos: validar tipo con `finfo`, tamaño máximo, nombre aleatorio, carpeta fuera de `public/`.
2. Descarga por controlador con control de rol.
3. Fotos de la falla en el reporte del docente (hasta 3, cámara o galería, reducidas en el navegador); el técnico las ve en el mantenimiento.
4. Adjuntos en asignación (acta firmada) y movimiento (respaldo).
5. Ruta `/q/{qr_token}` pública: ficha técnica + responsable y último mantenimiento; «Reportar una falla» pide iniciar sesión y solo lo envía el docente a cargo; los roles del panel ven el enlace a la ficha.
6. Lector QR con la cámara en la página del docente (foto del código como alternativa).
- Quedó fuera (decisión del usuario): galería y documentos en la ficha del equipo; adjuntos subidos por el técnico en el mantenimiento.

### Sprint 7 — Dashboard, auditoría y cierre
1. Dashboard arriba del inicio de administrador e inventario: indicadores, equipos por funcionamiento e inventario, por categoría y por sede, agenda de mantenimientos (atrasados y próximos 30 días) y, solo para el administrador, actividad reciente. El técnico conserva su inicio de soporte.
2. Visor de `auditoria` (solo administrador) con filtros por acción, usuario, módulo y fechas; cambios legibles «campo: antes → después»; los inicios de sesión se ocultan salvo que se pidan. Enlace *Historial de cambios* en la ficha del equipo.
3. Ampliar `datos_demostracion.sql`: segunda sede, categoría Redes, 5 equipos más, agenda con fechas relativas a hoy y auditoría de ejemplo.
4. Recorrido por rol y vista móvil a 375 px del dashboard y del visor.
5. Revisión de seguridad: CSRF en los 49 POST, acceso por rol de las rutas nuevas, `.htaccess` en la raíz (el SQL y `.git` se podían descargar), cabeceras de seguridad, página de error genérica y `debug = false`.
6. Actualizar `DESIGN.md` y el README.
- Quedó fuera (decisión del usuario): garantías por vencer en el dashboard.

## Verificación

Al cerrar cada sprint:

- Aplicar las migraciones y el seed en MySQL local (puerto 3307).
- Probar cada ruta con `curl` (códigos 200/302/403/404 según rol y CSRF).
- Recorrer el flujo en Chrome con cada rol, en escritorio y a 375 px.
- Revisar en phpMyAdmin que cada escritura dejó su fila en `auditoria` y que los estados del equipo quedaron coherentes.
- Sprint 4: intentar una segunda asignación abierta del mismo equipo y confirmar que se rechaza con mensaje claro.
- Sprint 6: intentar subir un archivo no permitido y descargar uno sin sesión.
