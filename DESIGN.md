---
name: Inventario Tecnológico · ISTSGA
description: Inventario institucional de equipos tecnológicos del Instituto Superior Tecnológico San Gabriel, con la identidad de sangabrielriobamba.edu.ec.
colors:
  navy: "#1e3453"
  navy-900: "#121e30"
  navy-700: "#2b4a72"
  gold: "#d2a648"
  gold-700: "#9c7424"
  gold-50: "#fbf6ea"
  blue: "#004c82"
  blue-700: "#003a63"
  ground: "#ebedef"
  panel: "#ffffff"
  ink: "#1d3744"
  ink-2: "#4b5d69"
  ink-on-navy: "#d9e1ec"
  line: "#d6dbe0"
  line-2: "#e6e9ec"
  ok: "#1d6b42"
  ok-50: "#e6f2eb"
  warn: "#8a4f00"
  warn-50: "#fbf0dc"
  maint: "#004c82"
  maint-50: "#e3eef7"
  bad: "#b3261e"
  loan: "#5a3d8a"
  loan-50: "#efeaf6"
typography:
  display:
    fontFamily: "Source Serif 4, Georgia, Times New Roman, serif"
    fontSize: "clamp(2rem, 1.4rem + 2.4vw, 3rem)"
    fontWeight: 700
    lineHeight: 1.2
    letterSpacing: "-0.015em"
  headline:
    fontFamily: "Source Serif 4, Georgia, Times New Roman, serif"
    fontSize: "clamp(1.5rem, 1.25rem + 0.8vw, 1.875rem)"
    fontWeight: 700
    lineHeight: 1.2
    letterSpacing: "-0.01em"
  title:
    fontFamily: "Source Serif 4, Georgia, Times New Roman, serif"
    fontSize: "1.375rem"
    fontWeight: 700
    lineHeight: 1.2
  title-sm:
    fontFamily: "Source Serif 4, Georgia, Times New Roman, serif"
    fontSize: "1.1875rem"
    fontWeight: 700
    lineHeight: 1.2
  body:
    fontFamily: "Source Sans 3, Segoe UI, system-ui, -apple-system, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.55
  body-sm:
    fontFamily: "Source Sans 3, Segoe UI, system-ui, -apple-system, sans-serif"
    fontSize: "0.9375rem"
    fontWeight: 400
    lineHeight: 1.55
  code:
    fontFamily: "Source Sans 3, Segoe UI, system-ui, -apple-system, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 700
    lineHeight: 1.1
    letterSpacing: "0.02em"
    fontFeature: "tnum"
  label:
    fontFamily: "Source Sans 3, Segoe UI, system-ui, -apple-system, sans-serif"
    fontSize: "0.8125rem"
    fontWeight: 700
    lineHeight: 1.2
    letterSpacing: "0.1em"
rounded:
  radius-sm: "4px"
  radius: "8px"
  pill: "999px"
spacing:
  s-1: "4px"
  s-2: "8px"
  s-3: "12px"
  s-4: "16px"
  s-5: "24px"
  s-6: "32px"
  s-7: "48px"
  s-8: "64px"
components:
  button-primary:
    backgroundColor: "{colors.blue}"
    textColor: "{colors.panel}"
    rounded: "{rounded.radius-sm}"
    padding: "0 24px"
    height: "44px"
  button-primary-hover:
    backgroundColor: "{colors.blue-700}"
  button-gold:
    backgroundColor: "{colors.gold}"
    textColor: "{colors.navy-900}"
    rounded: "{rounded.radius-sm}"
    padding: "0 24px"
    height: "44px"
  button-outline:
    backgroundColor: "{colors.panel}"
    textColor: "{colors.blue}"
    rounded: "{rounded.radius-sm}"
    padding: "0 24px"
    height: "44px"
  button-outline-hover:
    backgroundColor: "{colors.maint-50}"
  chip:
    backgroundColor: "{colors.panel}"
    textColor: "{colors.ink}"
    rounded: "{rounded.pill}"
    padding: "0 16px"
    height: "40px"
  chip-active:
    backgroundColor: "{colors.navy}"
    textColor: "{colors.panel}"
  tag-ref:
    backgroundColor: "{colors.panel}"
    textColor: "{colors.navy}"
    rounded: "{rounded.radius-sm}"
    padding: "1px 8px"
  tag-ref-hover:
    backgroundColor: "{colors.gold-50}"
  tag-band:
    backgroundColor: "{colors.navy}"
    textColor: "{colors.gold}"
    typography: "{typography.label}"
    padding: "6px 16px"
  stamp:
    backgroundColor: "{colors.panel}"
    typography: "{typography.label}"
    padding: "3px 10px"
  input-lookup:
    backgroundColor: "{colors.panel}"
    textColor: "{colors.ink}"
    rounded: "{rounded.radius-sm}"
    padding: "0 16px 0 44px"
    height: "48px"
  panel:
    backgroundColor: "{colors.panel}"
    rounded: "{rounded.radius}"
    padding: "32px"
---

# Design System: Inventario Tecnológico · ISTSGA

## Overview

**Creative North Star: "La Placa Patrimonial"**

El sistema es la herramienta propia del Instituto Superior Tecnológico San Gabriel, no un SaaS genérico. Su identidad está fijada por la marca institucional (sitio sangabrielriobamba.edu.ec y Sistema de Control Académico): bandas navy que enmarcan la página, oro como hilo de regla y foco, azul académico para la acción, y un suelo gris frío sobre el que descansan paneles blancos. Titulares en serif con autoridad académica; interfaz en sans humanista, sobria y legible.

El objeto que da carácter al mundo es la etiqueta patrimonial física que cada bien lleva pegada: banda navy con "ISTSGA · Propiedad del instituto", código en cifras tabulares, número patrimonial, QR y sellos de estado impresos sobre la placa. La interfaz dibuja cada equipo como esa placa, y el resto del sistema (agenda, búsqueda, historial) remite a ella mediante el chip de código. La densidad es media: información operativa, ordenada por tiempo y estado, sin teatralidad.

La profundidad es baja y funcional: sombras suaves para separar paneles del suelo, y una elevación mayor solo como respuesta al vínculo agenda-etiqueta. El movimiento es breve y con propósito.

**Key Characteristics:**
- Marco navy arriba (encabezado y banda de saludo) y navy profundo abajo (pie), ambos cerrados por una regla oro de 4px.
- Oro como regla, subrayado de título, indicador de página actual y anillo de foco; nunca como relleno de grandes superficies.
- Azul académico para acciones primarias, enlaces y foco de campos.
- La etiqueta patrimonial como unidad visual de cada equipo, con estado siempre visible.
- Cifras tabulares en todo código, fecha, hora y número patrimonial.
- Vocabulario de estados del esquema (`estado_operativo`, `estado_inventario`) con un color fijo por estado.

## Colors

Paleta institucional de navy y oro sobre gris frío, con un azul académico para la acción y una familia de estados cerrada.

### Primary
- **Navy San Gabriel** (`navy`): encabezado fijo, banda de saludo, banda superior de la etiqueta, chip de filtro activo y color de los titulares sobre fondo claro.
- **Navy Profundo** (`navy-900`): pie de página y texto sobre oro (botón oro, avatar, selección). También el código grande de la etiqueta.
- **Navy Medio** (`navy-700`): superficie del bloque de búsqueda dentro de la banda navy; iconos de metadatos.

### Secondary
- **Oro Institucional** (`gold`): reglas de cierre de bandas (4px), barra bajo títulos de sección (56 × 3px), indicador de página actual en la navegación, anillo de foco, contorno de la etiqueta vinculada, texto de la banda de la etiqueta y botón oro (acción QR sobre navy).
- **Oro Pálido** (`gold-50`): fondo de la columna "Hoy" en la agenda y hover del chip de código.
- **Oro Oscuro** (`gold-700`): tono de reserva para oro sobre fondo claro cuando haga falta contraste de texto.

### Tertiary
- **Azul Académico** (`blue`, hover `blue-700`): botón primario, botón contorno, enlaces, botones-enlace de la etiqueta, foco de `select`/`textarea`, `accent-color` de radios y caret.

### Neutral
- **Suelo Gris Frío** (`ground`): fondo de página y de los círculos de la línea de tiempo.
- **Panel Blanco** (`panel`): agenda, paneles de reporte e historial, chips, sellos.
- **Tinta** (`ink`) y **Tinta Secundaria** (`ink-2`): texto principal y texto de apoyo (fechas, modelos, ayudas).
- **Tinta sobre Navy** (`ink-on-navy`): texto secundario dentro del encabezado, la banda y el pie.
- **Línea** (`line`) y **Línea Suave** (`line-2`): bordes de chips y divisores entre columnas o secciones de panel.

### Estados
- **Operativo** (`ok`, fondo `ok-50`), **Con fallas** (`warn`, `warn-50`), **En mantenimiento** (`maint`, `maint-50`, mismo valor que el azul académico), **Inoperativo / De baja** (`bad`), **Prestado** (`loan`, `loan-50`).

### Named Rules
**The Gold Thread Rule.** El oro marca límites, foco y vínculo: reglas, subrayados, contornos y una sola acción sobre navy. No se usa como fondo de paneles ni de bloques de texto.

**The Blue Acts Rule.** Toda acción primaria o enlace sobre fondo claro es azul académico; el navy encuadra, el azul actúa.

**The One State, One Color Rule.** Cada valor de `estado_operativo` / `estado_inventario` tiene su color fijo; ningún otro elemento reutiliza los colores de estado como decoración.

## Typography

**Display Font:** Source Serif 4 (con Georgia, Times New Roman, serif)
**Body Font:** Source Sans 3 (con Segoe UI, system-ui, sans-serif)

**Character:** Una serif de texto con autoridad académica para titulares, emparejada con una sans humanista clara para toda la interfaz operativa. Pesos cargados: serif 600/700, sans 400/600/700.

### Hierarchy
- **Display** (serif 700, `clamp(2rem, 1.4rem + 2.4vw, 3rem)`, 1.2): saludo de la banda navy; una vez por página.
- **Headline** (serif 700, `clamp(1.5rem, 1.25rem + 0.8vw, 1.875rem)`, 1.2): títulos de sección, siempre con la barra oro debajo.
- **Title** (serif 700, 1.375rem): encabezados de columna de la agenda. **Title-sm** (1.1875rem): nombre del equipo en la etiqueta. El rótulo del buscador usa serif 600 a 1.25rem.
- **Body** (sans 400, 1rem, 1.55): texto general; subtítulo del saludo limitado a 52ch. **Body-sm** (0.9375rem) para metadatos, detalle y fichas.
- **Code** (sans 700, 1.5rem, 1.1, +0.02em, tabular): código del equipo en la etiqueta.
- **Label** (sans 700, 0.8125rem, +0.1em, mayúsculas): sellos de estado; la banda de la etiqueta usa 0.75rem con +0.12em. Ambos son impresiones propias de la placa física.

### Named Rules
**The Tabular Figures Rule.** Códigos, números patrimoniales, fechas y horas llevan `font-variant-numeric: tabular-nums`.

**The Serif Speaks, Sans Works Rule.** La serif se reserva para titulares (h1-h3, rótulo del buscador, marca, frase del pie); controles, datos y etiquetas son siempre sans.

## Layout

Contenedor centrado de `--wrap` (1200px) con relleno lateral de 24px (16px en móvil). Escala de espaciado de 4px a 64px (`s-1`…`s-8`), ritmo principal en 12/16/24px. La página se apila en bandas de ancho completo: encabezado navy fijo (76px, 68px en móvil), banda de saludo con rejilla 1fr / 1.2fr (texto a la izquierda, buscador a la derecha, alineados abajo), agenda en tres columnas temporales dentro de un panel, rejilla de etiquetas `auto-fill` con mínimo 300px y separación 24px, bloque dividido 1.15fr / 1fr para reporte e historial, y pie de tres columnas.

Puntos de quiebre: 1080px (la banda de saludo pasa a una columna; se oculta el nombre del usuario), 900px (navegación en menú desplegable con indicador oro vertical; agenda y bloque dividido a una columna; pie a dos columnas), 560px (etiquetas a una columna, fichas sin columna de rótulos, pie a una columna; banda de saludo compacta: saludo a 1.75rem, buscador sin caja y sin texto de ejemplo visible, para que "Mis equipos" asome en la primera pantalla; la misma banda compacta aplica en celular horizontal de hasta 520px de alto). Objetivos táctiles de 44px mínimo (40px en chips y botones-enlace). `scroll-padding-top: 96px` compensa el encabezado fijo.

## Elevation & Depth

Sistema híbrido de baja elevación: los paneles blancos y las etiquetas se separan del suelo gris con una sombra suave de dos capas teñida de navy; las bandas navy no tienen sombra propia salvo el encabezado fijo. La elevación mayor solo aparece como estado (etiqueta vinculada desde la agenda). En impresión desaparecen encabezado, banda, filtros, acciones y pie.

### Shadow Vocabulary
- **Reposo** (`--shadow`: `0 1px 2px rgba(18, 30, 48, 0.06), 0 6px 18px -8px rgba(18, 30, 48, 0.18)`): agenda, paneles y etiquetas en reposo.
- **Elevación** (`--shadow-lift`: `0 2px 4px rgba(18, 30, 48, 0.08), 0 16px 32px -12px rgba(18, 30, 48, 0.32)`): etiqueta vinculada (`is-linked`) y destello.
- **Encabezado** (`0 1px 0 rgba(255,255,255,0.06), 0 6px 16px -10px rgba(0,0,0,0.6)`): solo el encabezado fijo.

### Named Rules
**The Lift Means Linked Rule.** Una etiqueta solo se eleva (−4px, `--shadow-lift`, contorno oro) cuando algo la señala; en reposo todo está a la misma altura.

## Shapes

Esquinas contenidas: 8px (`radius`) para paneles, agenda, etiquetas y filas de agenda; 4px (`radius-sm`) para botones, campos, chips de código, avisos y foco; 3px en sellos y 2px en el marco del QR, porque imitan impresión. Píldora (`999px`) solo para chips de filtro; círculo para avatar e iconos de agenda y línea de tiempo. Bordes de 1px en `line`; el sello usa borde de 2px en `currentColor`; la línea de corte punteada (`1px dashed`) separa las acciones de la etiqueta y enmarca el estado vacío.

## Components

### Buttons
Sobrios y firmes: altura mínima 44px (48px en el buscador), sans 600 a 1rem, esquinas de 4px, transición de 160ms y hundimiento de 1px al pulsar.
- **Primary:** azul académico con texto blanco; hover `blue-700`.
- **Gold:** oro con texto `navy-900`; solo sobre bandas navy (acción "Escanear QR").
- **Ghost claro:** transparente con borde blanco al 45% sobre navy; hover fondo blanco al 10%.
- **Outline:** panel blanco, texto y borde azul; hover `maint-50`.
- **Text:** transparente, `ink-2`, subrayado al hover.
- **Botón-enlace** (acciones de etiqueta): azul, 0.9375rem, 40px, hover con fondo azul al 8%; la variante atenuada vira a `warn` al hover ("Reportar falla").

### Chips
- **Filtro:** píldora blanca de 40px con borde `line`; hover borde navy; activo (`aria-pressed="true"`) navy con texto blanco.
- **Chip de código** (`tag-ref`): caja de 4px con borde `line`, texto navy 0.8125rem 700 tabular; hover borde oro y fondo `gold-50`. Es un enlace a la etiqueta del equipo.

### Cards / Containers
- **Panel:** blanco, 8px, `--shadow`, relleno 32px (24/16px en móvil).
- **Agenda:** panel con tres columnas separadas por `line-2`; la columna "Hoy" va sobre `gold-50` con su divisor en oro. Cada ítem tiene icono circular teñido con el color de su estado, hora en navy tabular y chip de código.
- **Estado vacío:** panel con borde punteado e icono navy medio.

### Inputs / Fields
- **Buscador:** campo blanco de 48px con icono de lupa a 14px, borde transparente de 2px que pasa a oro al foco (sobre navy).
- **Select / Textarea:** borde `#a9b4be` 1px, 4px; foco borde azul más halo `0 0 0 3px rgba(0,76,130,0.2)`. Error: borde `bad` y halo rojo; mensaje en `bad` 600.
- **Radios:** nativos con `accent-color` azul, filas de 44px.

### Navigation
Enlaces sans 600 en `ink-on-navy` sobre navy; hover blanco con fondo blanco al 8%; página actual en blanco con barra oro de 3px abajo (vertical a la izquierda en el menú móvil). Chip de usuario con avatar oro y separador blanco al 18%. Bajo 900px, botón "Menú" con borde y panel desplegable navy; abierto, el icono pasa a cerrar (×) y un velo navy al 50% cubre la página: tocarlo o pulsar Escape cierra el menú (Escape devuelve el foco al botón). Bajo 560px el botón queda solo con icono, pero conserva el nombre "Menú" para lectores de pantalla.

### Etiqueta patrimonial (componente firma)
La placa impresa de cada bien. Superficie `#f6f7f8` con borde `#c3cbd2` y radio de 8px, `--shadow`. Arriba la banda navy con "ISTSGA" en oro y "Propiedad del instituto" en `ink-on-navy`, mayúsculas espaciadas. Debajo, código grande tabular (`code`) y "Patrimonial …" en `ink-2`, con el QR (generado desde `qr_token`) de 68px en marco blanco de 2px a la derecha. Bajo 560px el QR miniatura se reemplaza por el botón "Ver código" (76px, icono QR sobre el texto, fondo blanco, texto azul) que abre una ventana (`<dialog>`) con la placa ampliada: banda navy, código, patrimonial, QR de hasta 240px, nombre y botón "Cerrar"; se cierra también con Escape o tocando el fondo, y el foco vuelve al botón. Luego nombre en serif, modelo y serie, metadatos con iconos de trazo (ubicación, próximo mantenimiento; alerta en `warn` 600), sellos de estado, línea de corte punteada con "Ver ficha" (despliega una lista de definición con cifras tabulares) y "Reportar falla".

- **Sello de estado:** plano y derecho, borde de 2px en `currentColor`, radio 3px, fondo blanco, mayúsculas 0.8125rem 700 +0.1em. Texto del vocabulario del esquema: Operativo, Con fallas, En mantenimiento, Inoperativo, Prestado, En bodega, De baja.
- **Vínculo agenda-etiqueta:** pasar el mouse por la agenda no afecta a las etiquetas. Solo al pulsar un chip de código, la página se desplaza a la etiqueta centrada; recién ahí se eleva 4px con `--shadow-lift` y contorno oro de 3px, con un halo oro de 10px que se desvanece en 1400ms, y vuelve al reposo a los 2.6s. Con movimiento reducido, sin desplazamiento ni elevación.
- **Etiqueta retirada** (`.tag--retired`): equipos con `estado_inventario = de_baja` o asignación cerrada (`fecha_devolucion` no nula). Misma anatomía, pero banda gris `#5b6873`, fondo `#eef0f2`, borde discontinuo, sin sombra y QR al 45%. La banda dice "Dado de baja" o "Asignación cerrada"; los sellos usan `bad` (De baja) o `ink-2` (Devuelto, Inoperativo). Viven en el bloque "Ya no están a tu cargo", debajo de "Mis equipos".

### Panel de gestión (administrador, inventario, técnico)
Layout propio (`layouts/panel.php`, `panel.css`), pensado primero para celular. La página del docente conserva el encabezado superior.
- **Barra lateral:** navy, 264px, cerrada a la derecha por la regla oro de 4px. Marca arriba, módulos agrupados con rótulo en mayúsculas espaciadas (0.75rem), enlaces sans 600 de 44px con icono; la página actual lleva fondo blanco al 10% y barra oro vertical de 3px. Abajo, avatar oro, nombre, rol y cerrar sesión. Los módulos se declaran en `app/helpers/Menu.php`.
- **Celular y tablet (hasta 900px):** barra superior navy de 60px con regla oro, botón de menú de 44px y marca. La barra lateral entra desde la izquierda sobre un velo navy al 50%; se cierra con el botón, el velo o Escape.
- **Cabecera de página:** título serif con barra oro, nota en `ink-2` y, si corresponde, un solo botón primario (a todo el ancho en celular).
- **Listados:** buscador + chips de filtro (enlaces; activo en navy). Cada registro es una tarjeta con rótulo y valor; a partir de 600px de ancho de contenido van en dos columnas y desde 880px pasan a tabla real (consulta de contenedor, no de pantalla). Acciones como botones-enlace tras línea punteada.
- **Sello de registro:** mismo sello plano de la placa; `Activo` en `ok`, `Inactivo` en `ink-2`.
- **Formularios:** en página aparte, panel blanco de hasta 640px, un campo por fila, obligatorios con `*` rojo y opcionales rotulados; error bajo cada campo. Guardar es primario; Cancelar es botón de texto.
- **Confirmación:** ventana `<dialog>` para desactivar, con botón rojo (`bad`) y Cancelar con el foco inicial.

## Do's and Don'ts

### Do:
- **Do** cerrar cada banda navy con una regla oro de 4px y poner la barra oro de 56 × 3px bajo cada título de sección.
- **Do** dibujar cada equipo como etiqueta patrimonial, con su estado siempre impreso en un sello visible.
- **Do** escribir los estados con el vocabulario exacto del esquema y su color fijo (`ok`, `warn`, `maint`, `bad`, `loan`).
- **Do** usar cifras tabulares en códigos, patrimoniales, fechas y horas.
- **Do** enlazar toda mención de un equipo con el chip de código hacia su etiqueta.
- **Do** usar el anillo de foco oro de 3px con 2px de separación en todo elemento interactivo.
- **Do** mantener objetivos táctiles de 44px (40px mínimo en chips y botones-enlace).

### Don't:
- **Don't** ocultar el estado de un equipo tras un hover, un tooltip o la ficha desplegable.
- **Don't** usar el oro como relleno de paneles o bloques de texto; el botón oro vive solo sobre navy.
- **Don't** inclinar, rotar ni texturizar los sellos de estado; son planos y derechos.
- **Don't** elevar una etiqueta en reposo; la elevación significa vínculo.
- **Don't** reemplazar Source Serif 4 / Source Sans 3 ni la paleta navy-oro-azul; la identidad está fijada por la marca del instituto.
- **Don't** usar los colores de estado para decoración ajena al estado.
