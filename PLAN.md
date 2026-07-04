# Plan de modernización — Microservicio de Facturación Electrónica SRI (Ecuador)

> Documento vivo. Consolida el análisis del proyecto legado y la hoja de ruta del
> refactor hacia un microservicio moderno, elegante y mantenible.
>
> **Estado:** Fases 0 y 1 completadas ✅ · **Actualizado:** 2026-07-03

---

## 1. Visión del producto

Construir un **microservicio de facturación electrónica** que:

- **Sea consumido por terceros** vía API HTTP (factura, nota de crédito, nota de
  débito, comprobante de retención, guía de remisión).
- Ofrezca un **dashboard de autoservicio** donde los usuarios:
  - generen y gestionen sus **tokens de acceso**,
  - consulten su **cuota de consumo**,
  - estén asociados a un **plan** (cada plan define su cuota).
- Encapsule toda la complejidad del SRI: clave de acceso, firma XAdES, ciclo
  recepción → autorización, y generación del RIDE (PDF).

---

## 2. Punto de partida (proyecto legado, hoy en `legacy/`)

| Componente | Estado actual |
|---|---|
| Framework | Laravel 5.8 (EOL) |
| PHP | 7.1.3 (EOL) |
| Firma | `java -jar sri.jar` vía `exec()` (XAdES-BES) |
| Transporte SRI | SOAP (`SoapClient`) — modalidades online y offline |
| RIDE | `wkhtmltopdf` (Snappy) desde plantillas Blade |
| Persistencia | MySQL — solo tablas `users` y `comprobantes` |
| Panel | AdminLTE |

### Problemas heredados que este plan corrige

**Arquitectura / código**
- Toda la lógica vive dentro de `store()` de los controllers (cientos de líneas).
- Acceso posicional ilegible al payload: `$factura[array_keys($factura)[0]][array_keys(...)[1]]['fechaEmision']`.
- Duplicación masiva: `ApiSRI` ≈ `ApiOfflineSRI`; `ApiController` ≈ `ApiOfflineController`.
- Supresión de errores con `@` por todas partes; `sleep()` fijos en vez de reintentos.
- Sin tests reales (solo el scaffolding de Laravel).
- Bugs latentes en `recibirWs` (typos `$comprobantes`/`$comprobante`, `mensajesDB`/`mensajesDb`).

**Seguridad (crítico)**
- Certificados `.p12` privados servidos desde `public/` (`sv.p12`, `p12/active.p12`).
- Path traversal / lectura arbitraria de archivos en las rutas `ride/"{url}"` y `xml/"{url}"`.
- `.env` versionado con `APP_KEY` y `APP_DEBUG=true`.
- Posible inyección de comandos en `exec()` (valores del request sin escapar).
- `verify_peer=false` y validación de entrada inexistente.

**Concurrencia**
- Escribe siempre en archivos fijos globales (`public/p12/active.p12`,
  `public/img/logoride.png`): dos peticiones simultáneas se pisan el certificado.

---

## 3. Decisiones tomadas

| Decisión | Elección | Motivo |
|---|---|---|
| **Estrategia de upgrade** | **Reconstruir** sobre esqueleto Laravel 12 limpio, portando la lógica | El dominio es pequeño y el código legado hay que reescribirlo igual; 6 saltos de versión in-place no compensan |
| **Modelo de API** | **Dual: síncrona + asíncrona** | Síncrona para pruebas/bajo volumen; asíncrona (encolada) para producción, robusta ante el SOAP lento del SRI |
| **Ubicación del legado** | Movido a `legacy/` | Referencia consultable mientras se porta la lógica |

---

## 4. Versiones objetivo y tooling

| Componente | Actual | Destino |
|---|---|---|
| PHP | 7.1.3 | **8.4** |
| Laravel | 5.8 | **12.x** (requiere PHP ≥ 8.2) |
| Tests | PHPUnit 7 (sin uso) | **Pest 3** |
| Análisis estático | — | **Larastan (PHPStan) nivel max** |
| Estilo | StyleCI | **Laravel Pint** |
| Modernización de código | — | **Rector** (portar lógica 7.1 → 8.4) |
| Tokens de API (fase 6) | — | **Laravel Sanctum** |

---

## 5. Arquitectura y patrones

El núcleo del refactor es transformar el payload crudo en un modelo de dominio
tipado y expresar el flujo como un **pipeline de acciones**.

### 5.1 Patrones (de mayor a menor impacto)

1. **DTOs tipados con `spatie/laravel-data`** — pilar. Convierte el JSON en
   objetos (`FacturaData`, `InfoTributariaData`, `DetalleData`, `ImpuestoData`…).
   Elimina el acceso posicional y centraliza validación/casting.
2. **Value Objects + Enums (PHP 8.1+)**
   - Enums: `Ambiente`, `TipoEmision`, `TipoComprobante` (codDoc), `EstadoAutorizacion`.
   - Value Objects: `ClaveAcceso` (49 dígitos + verificador módulo 11), `Ruc`, `Secuencial`.
3. **Action Pattern** — clases invocables de responsabilidad única:
   `GenerarClaveAcceso`, `ConstruirXml`, `FirmarXml`, `EnviarRecepcion`,
   `SolicitarAutorizacion`, `GenerarRide`.
4. **Pipeline Pattern** (`Illuminate\Pipeline\Pipeline`) — el flujo es lineal;
   cada Action es una etapa. **Es el núcleo compartido** entre el endpoint
   síncrono (lo ejecuta inline) y el asíncrono (lo ejecuta desde un Job).
5. **Strategy + Factory** por tipo de comprobante — una interfaz
   `ComprobanteBuilder` con una implementación por documento, resuelta según `codDoc`.
6. **Gateway con interfaz** — `SriGateway` (real `SoapSriGateway` / test
   `FakeSriGateway`) y `XmlSigner` (`JarXmlSigner` / fake). Imprescindible para
   testear sin golpear al SRI.
7. **Jobs / Colas** — `ProcesarComprobanteJob` para la modalidad asíncrona.

### 5.2 Superficie de API (dual)

```
POST /api/v1/comprobantes                 → síncrono   (200 + RIDE)
POST /api/v1/comprobantes?async=1         → asíncrono  (202 + id)
GET  /api/v1/comprobantes/{id}            → estado + resultado (polling)
POST /api/v1/comprobantes/{id}/webhook    → (fase 5) notificación push
```

### 5.3 Estructura de directorios propuesta

```
app/
├── Sri/                          (módulo de dominio)
│   ├── Enums/          Ambiente, TipoEmision, TipoComprobante, EstadoAutorizacion
│   ├── ValueObjects/   ClaveAcceso, Ruc, Secuencial
│   ├── Data/           FacturaData, InfoTributariaData, DetalleData…  (laravel-data)
│   ├── Actions/        GenerarClaveAcceso, ConstruirXml, FirmarXml, EnviarRecepcion…
│   ├── Pipeline/       EmitirComprobantePipeline
│   ├── Contracts/      SriGateway, XmlSigner, RideGenerator
│   ├── Gateways/       SoapSriGateway, FakeSriGateway
│   ├── Documents/      ComprobanteBuilder + FacturaBuilder, NotaCreditoBuilder…
│   └── Exceptions/
├── Models/             Comprobante, User, Plan…
├── Http/Controllers/Api/  EmitirComprobanteController (delgado)
└── Jobs/               ProcesarComprobanteJob
```

---

## 6. Testing como pilar

Refactor guiado por tests, con red de seguridad **antes** de tocar la lógica.

- **Golden master (Fase 0):** capturar, desde los `exampleBody*.json` del legado,
  la **clave de acceso** y el **XML** que el código actual produce, como snapshots
  de referencia. Cada paso del refactor se valida contra estos snapshots.
- **Unit tests:** value objects (el módulo 11 tiene casos borde), enums, DTOs y
  cada Action en aislamiento.
- **Feature tests:** el endpoint completo con `FakeSriGateway` + fake signer.
- **Integración (opcional, tag `@integration`, fuera de CI):** ejecuta `sri.jar`
  real para cubrir la firma.
- **Calidad verificable en CI:** Larastan nivel max + Pint.

---

## 7. Fases de ejecución

| Fase | Contenido | Resultado |
|---|---|---|
| **0. Red de seguridad** ✅ | Fixtures golden-master (XML + clave de acceso) desde los `exampleBody*.json`; documentar la estructura real de cada tipo | `fixtures/golden/` + `tools/golden/generate.php` — ver `fixtures/golden/README.md` |
| **1. Esqueleto** ✅ | Laravel 12 + PHP 8.4; Pest, Larastan, Pint, Rector; migraciones portadas | Laravel 12.62 en la raíz; `composer quality` en verde |
| **2. Dominio** | Enums, Value Objects, DTOs con laravel-data | Fin del acceso posicional |
| **3. Lógica** | Actions + Pipeline + Strategy + Gateway/Signer con fakes | Controller delgado, todo testeado |
| **4. Endurecimiento** | Sacar `.p12` de `public/`, cerrar path traversal, aislar certificados por request, validación de entrada | Apto para exponer |
| **5. Microservicio** | Jobs/colas, API síncrona **y** asíncrona, versionado, OpenAPI | Consumible por terceros |
| **6. Dashboard** *(futuro)* | Sanctum (tokens), planes, cuotas, rate limiting | Panel de autoservicio |

---

## 8. Visión de la fase 6 (dashboard, tokens, planes y cuotas)

- **Tokens de acceso:** Laravel Sanctum (tokens personales por usuario/aplicación).
- **Planes:** tabla `plans` (nombre, cuota mensual, límite de tasa); relación
  `users.plan_id`.
- **Cuotas:** contador de consumo por usuario + periodo; se apoya en el rate
  limiting nativo de Laravel para el límite por minuto y en un contador
  persistente para la cuota mensual.
- **Dashboard:** panel para emitir/gestionar tokens, ver consumo vs. cuota e
  historial de comprobantes.

---

## 9. Próximo paso

**Fase 2 — Dominio**: crear el módulo `app/Sri/` con Enums (`Ambiente`,
`TipoEmision`, `TipoComprobante`, `EstadoAutorizacion`), Value Objects
(`ClaveAcceso`, `Ruc`, `Secuencial`) y DTOs con laravel-data. Cada pieza se
valida contra los fixtures de `fixtures/golden/` (la clave y el XML generados
por el nuevo código deben ser idénticos a los del legado).

### Registro de la Fase 1 (2026-07-03)

- Laravel **12.62** / PHP **8.4.22** instalado en la raíz (el legado sigue
  intacto en `legacy/`); Herd vuelve a servir `https://fe.test` (200).
- Tooling: Pest 4.7 (+plugin Laravel), Larastan 3.10 (nivel max, 0 errores),
  Pint (preset laravel, excluye `legacy/` y `tools/`), Rector 2.5 (sets PHP 8.4),
  spatie/laravel-data 4.23.
- Scripts: `composer lint` · `composer analyse` · `composer quality`
  (pint + phpstan + tests).
- Migración `comprobantes` portada y modernizada (PK `id`, snake_case,
  `importe_total` decimal, columnas nuevas `clave_acceso` única y `estado`
  para el flujo asíncrono). SQLite para dev/tests.
- Suite inicial: 11 tests / 53 aserciones en verde — incluye la suite de
  regresión sobre `fixtures/golden/` (integridad, módulo 11 re-verificado,
  XML bien formado).
- Repo git inicializado (`main`); `.gitignore` protege los secretos del
  legado (`legacy/.env`, certificados `.p12`). **Primer commit pendiente.**

### Registro de la Fase 0 (2026-07-03)

- Fixtures generados para `factura`, `notaCredito` y `comprobanteRetencion`
  (input sanitizado, clave de acceso, XML pre-firma, meta de trazabilidad).
- El algoritmo módulo 11 del legado **coincide con la ficha técnica del SRI**,
  casos borde incluidos (verificación cruzada + `claveAcceso-vectors.json`).
- Contrato del payload documentado en `fixtures/golden/README.md`, con
  hallazgos clave: código numérico `22568496` hardcodeado, `codDoc` erróneo en
  los ejemplos (el nuevo dominio debe derivarlo del tipo), importes como string
  y fechas `dd/mm/aaaa`, y la clave `#omit-xml-declaration` que el legado ignora.
