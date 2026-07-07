# Plan de modernización — Microservicio de Facturación Electrónica SRI (Ecuador)

> Documento vivo. Consolida el análisis del proyecto legado y la hoja de ruta del
> refactor hacia un microservicio moderno, elegante y mantenible.
>
> **Estado:** Fases 0–5 completadas ✅ (incluido RIDE con dompdf) · **Actualizado:** 2026-07-07

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
| **2. Dominio** ✅ | Enums, Value Objects, DTOs con laravel-data | `app/Sri/` — clave golden reproducida desde el DTO |
| **3. Lógica** ✅ | Actions + Pipeline + Gateway/Signer con fakes | Endpoint síncrono funcionando; XML golden byte a byte |
| **4. Endurecimiento** ✅ | Errores de dominio → 422, rate limiting, límites de payload, JSON forzado en API, jar versionado fuera de public | Apto para exponer |
| **5. Microservicio** ✅ | Persistencia con estados, Job cifrado, API síncrona **y** asíncrona, consulta por uuid, RIDE (dompdf), OpenAPI | Consumible por terceros |
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

**Fase 6 — Dashboard**: Sanctum (tokens por usuario), planes con cuotas,
rate limiting por plan, y el panel de autoservicio. También pendiente:
firmador XAdES nativo en PHP (eliminar dependencia de Java) — opcional.

### Registro de la Fase 5b — RIDE (2026-07-07)

- **Motor elegido: dompdf** (`barryvdh/laravel-dompdf`) — puro PHP, sin
  binarios; el usuario lo aprobó frente a Browsershot y wkhtmltopdf.
- **`ComprobanteXmlParser`** (XML → DTO): el RIDE se genera desde el XML
  firmado almacenado (la fuente de verdad legal). Verificado por
  **roundtrip byte a byte** contra los golden (parse → render == original);
  la firma en namespace `ds:` queda naturalmente fuera.
- Plantillas Blade (`resources/views/ride/`): base común + factura, nota de
  crédito y retención, conforme al Anexo 2 (código de barras opcional:
  omitido por ahora; logo del emisor llegará con la fase 6).
- `GET /api/v1/comprobantes/{uuid}/ride` → PDF; 409 si no autorizado, 404
  sin XML; se cachea en `rides/` tras la primera generación.
- Suite: 97 tests / 283 aserciones; PHPStan max limpio.

### Registro de la Fase 5 (2026-07-06)

- **Fuente de verdad**: se incorporó `ficha-tecnica.pdf` (v2.2x, 143 pp.) al
  repo. Validó lo implementado (tablas 1-6, WSDLs, módulo 11, estados
  PPR/AUT/NAT) y aportó estas reglas de diseño:
  - §5.10: tras un rechazo se debe reutilizar **la misma clave de acceso y
    secuencial** → la clave se persiste también en emisiones fallidas.
  - §5.9 / Anexo 2: la clave de acceso ES el número de autorización.
  - Anexo 2: código de barras opcional en el RIDE; fecha de autorización no
    obligatoria en el RIDE del emisor.
- **Persistencia**: modelo `Comprobante` (uuid público, estados via
  `EstadoComprobante` +caso `Fallido`, mensajes json, XML firmado en
  storage privado) + factory con estado `autorizado()`. `user_id` nullable
  hasta la fase 6.
- **`RegistroDeEmision`**: servicio compartido crear/completar/fallar; mapea
  etapa del fallo → estado (recepción→devuelto, autorización→no_autorizado,
  autorización pendiente→recibido, firma→fallido).
- **Async**: `ProcesarComprobanteJob` (**ShouldBeEncrypted**: transporta el
  certificado; tries=3 con backoff, failed() → fallido) ejecuta el mismo
  pipeline. `POST /api/v1/comprobantes?async=1` → 202 + uuid;
  `GET /api/v1/comprobantes/{uuid}` para polling (`ComprobanteResource`,
  XML solo cuando autorizado).
- **OpenAPI**: `docs/openapi.yaml` (3.1) con ambas modalidades.
- Suite: 86 tests / 262 aserciones; PHPStan max limpio.
- **RIDE diferido a 5b**: decisión de motor PDF pendiente (dompdf
  recomendado: puro PHP, sin binarios; el wkhtmltopdf del legado está
  abandonado upstream). Portar plantillas de los 5 tipos.

### Registro de la Fase 4 (2026-07-06)

- **Errores de dominio → 422**: la construcción del DTO en el Form Request
  captura `DatoInvalido` y las excepciones de laravel-data/Carbon (campo
  faltante, enum desconocido, fecha malformada, RUC inválido…) y las
  convierte en errores de validación con mensaje útil; render global de
  `DatoInvalido` en `bootstrap/app.php` como red de seguridad.
- **API siempre JSON** (`shouldRenderJsonWhen` para `api/*`).
- **Rate limiting**: `throttleApi()` + limiter `api` explícito (60/min por
  usuario o IP); en la fase 6 el límite dependerá del plan.
- **Límites de payload**: `info.p12` ≤ 120 000 chars base64, clave ≤ 255.
- **`sri.jar` versionado** en `resources/firmador/` (estaba en
  `storage/app/`, que está git-ignored — no habría llegado a producción).
- 9 tests nuevos de payloads hostiles. Suite: 76 tests / 222 aserciones;
  PHPStan max limpio.
- **Pendiente señalado**: los certificados reales del legado siguen en disco
  (`legacy/public/sv.p12`, `legacy/public/p12/active.p12`, git-ignored);
  conviene borrarlos o moverlos fuera del repo. La clave del certificado
  viaja como argv al jar (visible en `ps` local): mitigable a futuro con un
  firmador XAdES nativo en PHP.

### Registro de la Fase 3 (2026-07-04)

- **Actions** (`app/Sri/Actions/`): `GenerarClaveAcceso` (código numérico
  aleatorio por defecto), `ConstruirXml`, `FirmarXml`, `EnviarRecepcion`,
  `SolicitarAutorizacion` (reintentos configurables vs. el sleep fijo del
  legado). `GenerarRide` quedó **diferido**: exige decidir wkhtmltopdf vs.
  dompdf y portar las plantillas Blade del legado.
- **Pipeline** `EmitirComprobante` (Illuminate\Pipeline): núcleo compartido
  que el endpoint síncrono ejecuta inline y el Job asíncrono reutilizará.
- **Contracts + dobles**: `SriGateway` (SOAP real con parseo tolerante a
  objeto-vs-lista + `FakeSriGateway`), `XmlSigner` (`JarXmlSigner` con
  Process/timeout/temporales aislados por emisión + `FakeXmlSigner`).
- **Seguridad ya corregida respecto al legado**: certificado por emisión en
  memoria (`CertificadoFirma`), jar fuera de `public/` (storage), argumentos
  de proceso escapados, sin archivos globales compartidos entre peticiones.
- **HTTP**: `POST /api/v1/comprobantes` (síncrono), Form Request que
  normaliza el contrato legado `{factura: …, info: …}`, controller delgado
  (~40 líneas vs. ~150 del legado), errores 422 con etapa y mensajes del SRI.
- **Java no está instalado en la máquina de desarrollo**: la firma real
  queda cubierta por el contract + fake; añadir un test de integración
  cuando haya JRE (pendiente).
- Suite: 67 tests + 1 todo / 195 aserciones; PHPStan max sin errores.
- Dependencias añadidas: `spatie/array-to-xml` ^3.4 (la misma lib del
  legado) y `laravel/sanctum` (vía `install:api`, para los tokens de la fase 6).

### Registro de la Fase 2 (2026-07-04)

- Módulo `app/Sri/` creado: 5 enums (`Ambiente`, `TipoEmision`,
  `TipoComprobante` con codDoc/rootElement/versionEsquema,
  `TipoIdentificacion`, `EstadoComprobante`), 4 value objects (`ClaveAcceso`,
  `Ruc`, `Secuencial`, `CodigoNumerico`), excepción de dominio `DatoInvalido`.
- `ClaveAcceso::generar()` **reproduce la clave golden del legado** y todos los
  vectores del módulo 11; `fromString()` valida el dígito verificador.
- DTOs con laravel-data para factura, nota de crédito y retención:
  normalización 1-vs-N (`Payload::lista`), fechas `dd/mm/aaaa` → CarbonImmutable,
  cast genérico `ValueObjectCast`, importes como string (pass-through al XML).
- Decisiones aplicadas: el `codDoc` del payload se ignora (lo define la clase
  del comprobante); `CodigoNumerico::aleatorio()` disponible para reemplazar el
  hardcodeado del legado; la nota de crédito golden tiene 2 detalles con
  impuesto-objeto (normalización 1-vs-N cubierta por tests).
- Suite: 43 tests / 128 aserciones; PHPStan nivel max sin errores.

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
