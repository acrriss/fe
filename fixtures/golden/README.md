# Fixtures golden-master — Fase 0

Snapshots del comportamiento del proyecto legado (`legacy/`), capturados **antes**
del refactor. Todo test del nuevo código que genere clave de acceso o XML debe
producir exactamente estos resultados para estos inputs.

**Generador:** `tools/golden/generate.php` — réplica exacta de la lógica del
legado (`claveDeAcceso()` copiado verbatim; `createXML()` con los mismos
parámetros de `spatie/array-to-xml` v2). Regenerar con:

```bash
cd tools/golden && composer install && php generate.php
```

## Contenido

```
<tipo>/
├── input.json       payload de entrada (sanitizado: p12/logo/clavep12 → placeholders)
├── claveAcceso.txt  clave de acceso de 49 dígitos que el legado genera
├── comprobante.xml  XML que el legado genera (pre-firma)
└── meta.json        trazabilidad: cadena base, versión del attr, nombre de archivo…
claveAcceso-vectors.json   vectores del módulo 11 cubriendo casos borde
```

## Contrato del payload (ingeniería inversa del legado)

Estructura raíz — **el orden de las claves importa** (el legado accede por posición):

| Posición | Clave | Contenido |
|---|---|---|
| `[0]` | `factura` \| `notaCredito` \| `comprobanteRetencion` \| `notaDebito` \| `guiaRemision` | el comprobante |
| `[1]` | `info` | `p12` (base64), `logo` (base64), `clavep12` (password) |
| — | `#omit-xml-declaration` | **ignorada por el legado** (nunca se lee; probablemente residuo del cliente que consumía la API) |

Dentro del comprobante (también posicional):

| Posición | factura | notaCredito | comprobanteRetencion |
|---|---|---|---|
| `[0]` | `infoTributaria` | `infoTributaria` | `infoTributaria` |
| `[1]` | `infoFactura` | `infoNotaCredito` | `infoCompRetencion` |
| `[2]` | `detalles.detalle[]` | `detalles.detalle[]` | `impuestos.impuesto` |

`infoTributaria` (común): `ambiente`, `tipoEmision`, `razonSocial`,
`nombreComercial`, `ruc`, `claveAcceso` (llega vacía, el servidor la genera),
`codDoc`, `estab`, `ptoEmi`, `secuencial`, `dirMatriz`.

## Clave de acceso (49 dígitos)

Cadena de 48 dígitos + verificador módulo 11:

```
ddmmaaaa + codDoc + ruc(13) + ambiente + estab(3) + ptoEmi(3)
        + secuencial(9) + "22568496" + tipoEmision
```

- La fecha viene `dd/mm/aaaa` y se le quitan los `/`.
- `"22568496"` es el **código numérico hardcodeado** del legado (campo "código
  numérico" de la ficha del SRI; debería ser aleatorio/configurable — decisión
  pendiente para el rediseño).
- Módulo 11: pesos 2..7 de derecha a izquierda; `11 - (total % 11)`; 11→0, 10→1.

✅ **Verificación cruzada:** el algoritmo del legado coincide con una
implementación independiente de la ficha técnica del SRI en todos los casos,
incluidos los bordes 10→1 y 11→0 (ver `claveAcceso-vectors.json`).

## XML generado

- Elemento raíz = tipo de comprobante, con atributos `id="comprobante"` y
  `version="1.0.0"` (retención) o `"1.1.0"` (resto).
- Declaración `<?xml version="1.0" encoding="UTF-8"?>`, `formatOutput` (indentado 2 espacios).
- Nombre de archivo en el legado: `<claveAcceso>.xml`.

## Hallazgos / advertencias

1. **Los tres ejemplos comparten el mismo `infoTributaria`** (mismo RUC,
   secuencial y `codDoc=01`): por eso las tres claves de acceso son idénticas.
   Es un artefacto de cómo se construyeron los ejemplos, no un bug del algoritmo.
2. **`codDoc` incorrecto en los ejemplos** de notaCredito (debería ser `04`) y
   retención (debería ser `07`). Según la tabla 3 de la ficha del SRI:
   01=factura, 03=liquidación, 04=nota crédito, 05=nota débito, 06=guía
   remisión, 07=retención. El nuevo dominio debe **derivar** `codDoc` del tipo,
   no aceptarlo del payload.
3. Los importes viajan como **strings** (`"10.00"`) y las fechas como
   `dd/mm/aaaa` — el DTO del nuevo dominio debe castear ambos.
4. La retención usa versión de esquema `1.0.0` y su detalle es
   `impuestos.impuesto` (objeto, no lista) — cuidado con el caso 1 elemento vs N
   al construir XML (`ArrayToXml` los trata distinto).
5. El legado nunca implementó `notaDebito` ni `guiaRemision` en los ejemplos,
   aunque `createRide()` tiene plantillas para ambos.
