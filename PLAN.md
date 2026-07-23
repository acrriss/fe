# Plan de modernización — Microservicio de Facturación Electrónica SRI (Ecuador)

> Documento vivo. Consolida el análisis del proyecto legado y la hoja de ruta del
> refactor hacia un microservicio moderno, elegante y mantenible.
>
> **Estado:** fases 0–6 y 7a–7d (capa partner COMPLETA: on-behalf, webhooks,
> idempotencia, onboarding y panel) ✅ · **Actualizado:** 2026-07-13

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
| **6a. Multi-tenant + API autenticada** ✅ | Contribuyente/Plan/User, Sanctum, certificado cifrado, cuotas, rate limit por plan | API lista para terceros con tenancy |
| **6b. Dashboard UI** ✅ | Panel Inertia + Vue 3: registro/login, resumen con consumo vs. cuota, comprobantes con descargas, tokens, configuración (certificado/logo) | Autoservicio completo |

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

## 9. Trabajo futuro (backlog)

- ~~Firmador XAdES-BES nativo en PHP~~ ✅ 2026-07-10: `XadesXmlSigner`
  (firma en ~9 ms, en memoria, sin Java ni clave por argv). Validado de
  punta a punta contra el SRI real (AUTORIZADO en ambiente de pruebas).
  Estrategia: `VerificadorXades` como oráculo (la firma del jar, aceptada
  por el SRI, verifica al 100% con nuestro C14N) + driver conmutable
  `sri.firmador.driver` (default `nativo`, `jar` de fallback). Capa de
  entrada compartida: `app/Sri/Certificados/LectorPkcs12` (validación de
  certificados, fallback `openssl -legacy`, clave vía env nunca argv).
  Limpieza pendiente: eliminar Java/`sri.jar` cuando el nativo acumule
  rodaje en producción.
- ~~Test de integración de la firma con JRE~~ ✅ cubierto por
  `JarXmlSignerTest` (jar real) y `XadesXmlSignerTest`/`VerificadorXadesTest`
  (nativo); ambos se saltan si falta el binario.
- ~~Reintento de comprobantes devueltos reutilizando clave/secuencial (§5.10)~~
  ✅ 2026-07-08: `POST /api/v1/comprobantes/{id}/reintentar` (sync y async);
  reutiliza registro y clave, valida que los componentes de la clave no
  cambien, no consume cuota adicional.
- ~~Código de barras Code 128 en el RIDE (§9.20)~~ ✅ 2026-07-10:
  `GeneradorCodigoBarras` (picqer/php-barcode-generator) como data-uri SVG
  bajo la clave de acceso, en los RIDE de todos los tipos.
- ~~Tipos faltantes: notaDebito, guiaRemision, liquidacionCompra~~ ✅
  2026-07-11: DTOs + `xmlArray` + plantilla RIDE + registro en Form Request
  y parser; `versionEsquema()` por tipo (05/06 en 1.0.0, 03 en 1.1.0).
  **Pendiente**: validarlos contra el SRI real (se construyeron desde la
  ficha, sin fixtures golden ni prueba de autorización real).

### Backlog abierto

- **Limpieza de Java/`sri.jar`**: retirar `JarXmlSigner`, el jar y su config
  ahora que el nativo es default y está validado (tras algo de rodaje).
- **Validar los 3 tipos nuevos** emitiendo uno de cada uno en el ambiente de
  pruebas del SRI.
- ~~Webhooks de notificación al autorizar (alternativa al polling)~~ ✅
  2026-07-12: fase 7b — ver §11 y su registro.
- ~~Documentación navegable de la API (Scalar en `/docs`)~~ ✅ 2026-07-11:
  Scalar bundled con Vite (`resources/js/docs.js`), público en `/docs`,
  sirve `docs/openapi.yaml` (actualizado con los 6 tipos); enlazado desde
  la página de Tokens del panel.
- **Emisión de prueba desde el panel** (formulario manual de factura).
- **Browser tests de Pest** para el panel (cazan bugs de UI como el
  shadowing de props ya sufrido).
- **Gestión de planes/facturación del servicio** (upgrade/downgrade, pagos).
- **Verificación de propiedad del RUC (anti-suplantación)** — ver §10.
- **Capa de integración partner/plataforma** (POS/ERPs que emiten en nombre
  de sus clientes) — ver §11. Eleva la prioridad de los webhooks (7b) y de
  la idempotencia de emisión (7c).

## 10. Diseño: verificación de propiedad del RUC (anti-suplantación)

Análisis de un hueco de seguridad del registro (2026-07-11). Pendiente de
implementar; se ancla desde el diseño de la tabla `contribuyentes`.

### El problema

Hoy el registro pide un RUC **autodeclarado, sin verificación y con unicidad
global inmediata** (`unique:contribuyentes,ruc`). Dos riesgos, muy distintos:

- **Emisión fraudulenta con firma ajena → en la práctica NO es posible.** La
  ficha (§11, error **39 "Firma electrónica del emisor no es válida"**)
  confirma que el SRI valida la firma contra el emisor: no se obtiene
  `AUTORIZADO` para un RUC que no se controla. Daño bajo.
- **Secuestro del RUC (squatting) → problema real.** Cualquiera reserva un RUC
  ajeno y, por la unicidad dura, el dueño real ya no puede registrarse. Es una
  denegación de registro trivial de ejecutar.

### Principio rector

La propiedad del RUC **no debe nacer del registro autodeclarado**, sino de una
verificación respaldada por el certificado/SRI. El certificado + el SRI son la
única prueba de control: nadie salvo el titular puede cargar un `.p12` que el
SRI vincule al RUC, ni obtener un `AUTORIZADO`.

### Modelo de dos ejes ortogonales

No mezclar propiedad del RUC con estado comercial:

| Eje | Responde | Estados |
|---|---|---|
| **A. Propiedad del RUC** | ¿controla este RUC? | `no_verificado` → `verificado` |
| **B. Estado comercial** | ¿cliente legítimo/activo? | `prueba` · `pagado_activo` · `moroso`… |

Un cliente que paga y aún no emite es `no_verificado` + `pagado_activo`: estado
**válido**, no un limbo. El pago es señal de legitimidad en un eje distinto.

### Mecanismos

1. **Unicidad diferida (defensa primaria del squatting).** El RUC solo se
   vuelve exclusivo cuando el contribuyente está `verificado`. Cuentas no
   verificadas no bloquean el RUC. **Nota MySQL** (no soporta índices únicos
   parciales): guardar `ruc` (no único) + `ruc_verificado` *nullable* con
   índice único, que se rellena solo al verificar (MySQL admite múltiples
   `NULL` en un índice único).
2. **Verificación por el SRI (universal, sin parsing).** El primer `AUTORIZADO`
   marca `verificado_en`. Delega en el SRI la relación cédula↔RUC↔representante
   legal; funciona para **todas** las CAs sin conocerlas.
   - **La verificación es el RESULTADO de emitir, no una puerta previa**: la
     emisión siempre se puede intentar (con certificado válido); el primer
     `AUTORIZADO` verifica. Así no hay círculo vicioso ni bloqueo.
3. **Verificación por identidad del certificado (acelerador incremental).**
   Al cargar el `.p12`, extraer la identificación del *subject* y exigir que
   coincida con el RUC (RUC = cédula+`001`, o RUC embebido). Verifica **sin
   emitir** — resuelve el caso del cliente pagado que aún no emite.
   - **No depende de tener certificados de todas las CAs**: se construye como
     registro extensible `CA → regla`, empezando por Security Data (el único
     que tenemos), con heurística CA-agnóstica como señal provisional y
     recolección de *subjects* (dato público) de uploads reales para sumar
     reglas con datos, no suposiciones. Si no reconocemos la CA, la cuenta se
     verifica igual por la vía del SRI (mecanismo 2).
4. **Expiración de cuentas no verificadas (higiene, NO defensa).** Comando
   programado `contribuyentes:prune-unverified` (patrón `sanctum:prune-expired`).
   - **Triple candado:** `no_verificado` **Y** sin comprobantes **Y** **sin plan
     pagado activo** **Y** más viejo que el TTL (configurable, ~14 días).
   - **Regla de oro:** ⚠️ nunca borrar una cuenta con algún comprobante
     autorizado (retención fiscal legal de 7 años en Ecuador).
   - Aviso previo por correo a mitad del TTL; nunca es castigo.
   - **No sustituye la unicidad diferida**: por sí sola no frena el squatting
     (hay ventana + re-registro keep-alive). Es limpieza sobre esa base.

### Manejo del cliente pagado que aún no emite

- **No se borra** (candado de plan pagado en el pruning).
- **No se bloquea** (la verificación es resultado de emitir, no requisito previo).
- **Puede verificarse ya** subiendo su certificado (mecanismo 3), o al primer
  `AUTORIZADO`. Como mucho, un *nudge* suave; nunca penalización.

### Orden de implementación sugerido

1. `verificado_en` + unicidad diferida + pruning con exclusión de pagados.
2. Verificación por primer `AUTORIZADO` **y** por match de certificado
   (Security Data primero), en paralelo.
3. Comando de expiración de no-verificadas (con el candado de plan pagado).

### Registro de la Fase 6b (2026-07-07)

- **Stack elegido por el usuario: Inertia v3 + Vue 3** (+ Tailwind 4 del
  esqueleto, plugin Vue en Vite). Sin librería de componentes.
- **Auth de panel por sesión**: login (throttle 6/min), registro que crea
  Contribuyente + primer usuario en transacción, logout.
- **Páginas** (`resources/js/Pages/`): `Auth/Login`, `Auth/Registro`,
  `Panel/Inicio` (stat tiles de consumo vs. cuota con medidor accesible,
  totales y últimas emisiones), `Panel/Comprobantes` (paginado + descargas
  RIDE/XML), `Panel/Tokens` (crear/revocar; token visible una sola vez vía
  flash), `Panel/Configuracion` (datos, certificado .p12 por upload, logo).
- `HandleInertiaRequests` comparte `auth.user`, `auth.contribuyente`
  (con flags de certificado/logo) y mensajes flash; aviso persistente si
  falta el certificado. Estados siempre con etiqueta + icono (nunca solo
  color) vía `EstadoBadge`.
- Descargas del panel reutilizan `DescargarRideController` (la comprobación
  de tenancy funciona igual con sesión) + endpoint XML propio.
- Seeder de planes base (gratis/emprendedor/empresa).
- Suite: 120 tests / 384 aserciones (16 nuevas de panel con
  `assertInertia`); PHPStan max limpio; assets compilando en Vite.

### Registro de la Fase 6a (2026-07-07)

- **Modelo multi-tenant** (decisiones del usuario: usuario∈1 contribuyente;
  certificado almacenado): `Contribuyente` (RUC único, razón social, logo,
  **certificado .p12 + clave cifrados** con casts `encrypted`, plan) ·
  `Plan` (cuota mensual, límite/minuto) · `User.contribuyente_id` ·
  `Comprobante.contribuyente_id` (reemplazó a `user_id`).
- **Auth Sanctum**: endpoints de comprobantes bajo `auth:sanctum`;
  `POST /api/v1/tokens` intercambia credenciales por token.
- **Certificado**: `PUT /api/v1/contribuyente/certificado` (cifrado en
  reposo, verificado por test). El payload de emisión ya no transporta
  `info.p12` (se ignora si viene) y **el Job ya no acarrea secretos** —
  resuelto el pendiente de la fase 4.
- **Tenancy estricta**: el RUC del payload debe ser el del contribuyente
  autenticado (422); consultas/RIDE de comprobantes ajenos responden 404.
- **Cuota y rate limit por plan**: 429 al agotar la cuota mensual; el
  limiter `api` usa `plan.limite_por_minuto` por contribuyente.
- **RIDE con logo del contribuyente** (data-uri embebido si existe).
- Suite: 104 tests / 292 aserciones; PHPStan max limpio.

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

---

## 11. Diseño: capa de integración partner/plataforma

Análisis (2026-07-11) para integrar el servicio con sistemas terceros que
emiten en nombre de **muchos** clientes finales (el caso concreto: un sistema
de inventario/POS propio cuyas facturas internas no tienen validez tributaria
hasta pasar por este servicio). Objetivo declarado: **mínima fricción** para
el cliente final que activa facturación electrónica.

### El problema

El onboarding actual asume autoservicio por contribuyente: registro en el
panel, carga del certificado, creación de token. Para una plataforma que
gestiona N clientes eso no funciona:

- **Fricción**: cada cliente final tendría que registrarse en *nuestro* panel,
  un producto que él no eligió (él compró el POS).
- **Credenciales compartidas**: `POST /v1/tokens` exige email/password de un
  User; la plataforma tendría que conocer o inventar credenciales de sus
  clientes. Inaceptable.
- **Sin aprovisionamiento programático**: no hay forma de crear un
  `Contribuyente` por API.
- **Sin webhooks**: el POS necesita enterarse del resultado (autorizado/
  devuelto) sin polling; ya estaba en el backlog, aquí se vuelve prerequisito.
- **Sin idempotencia**: los reintentos automáticos de un POS ante timeouts
  pueden duplicar emisiones.
- **Facturación**: el cliente de pago es la plataforma (revende o incluye el
  servicio), no el contribuyente final.

### Decisión central: modelo de confianza

| Opción | Cómo | Trade-off |
|---|---|---|
| A. Token por contribuyente entregado al partner | Al aprovisionar, se emite un token scoped al contribuyente; el partner guarda N tokens | Menor radio de daño por token, pero el partner gestiona N secretos y exige usuarios-máquina artificiales |
| **B. Credencial de partner + on-behalf-of (elegida)** | Una credencial de partner; cada request lleva `X-Contribuyente: {uuid}`; middleware resuelve la tenancy | Un solo secreto que rotar, cero fricción por cliente (patrón Stripe Connect). Radio de daño mayor → mitigar con abilities, rate limit por partner y auditoría |

La opción B sirve directamente al objetivo (fricción mínima) y es el patrón
estándar de plataformas. Implementación: modelo `Partner` con `HasApiTokens`
— **Sanctum ya soporta cualquier tokenable**, reutilizamos hashing,
abilities y `last_used_at` sin guard custom. Un middleware
`ResolverContribuyenteDelPartner` valida que el uuid del header pertenezca al
partner (404 si no) y lo expone donde hoy los endpoints leen
`$request->contribuyente()`, de modo que **la API v1 de emisión no cambia de
contrato**: mismo pipeline, misma tenancy estricta, mismos endpoints.

### Dos planos

```
Plano de gestión   /api/partner/v1/…     credencial de partner (sola)
  POST   contribuyentes                  aprovisionar cliente final
  GET    contribuyentes                  listar gestionados + consumo
  PUT    contribuyentes/{uuid}/certificado
  POST   webhooks / GET webhooks/{id}/entregas

Plano de emisión   /api/v1/…  (existente, sin cambios de contrato)
  credencial de partner + X-Contribuyente: {uuid}
  (los tokens de usuario directos siguen funcionando igual)
```

### Requerimientos

**Funcionales**

1. **Entidad Partner** con credenciales API (hasheadas, rotables, revocables)
   y rate limit propio.
2. **Aprovisionamiento**: `POST /partner/v1/contribuyentes` (RUC, razón
   social, dirección…) crea el Contribuyente con `partner_id`, **sin User**.
   Idempotente por `(partner, ruc)`: repetir la llamada devuelve el existente.
3. **On-behalf-of** sobre la API v1 completa (emitir, consultar, reintentar,
   RIDE, certificado) vía `X-Contribuyente`.
4. **Webhooks firmados** (HMAC por endpoint, reintentos con backoff vía queue,
   registro de entregas consultable): `comprobante.autorizado`,
   `comprobante.devuelto`, `comprobante.fallido`, `certificado.por_vencer`.
   Se construyen genéricos: también sirven a cuentas directas.
5. **Idempotencia de emisión**: header `Idempotency-Key`; se persiste clave +
   huella del payload + respuesta; un reintento devuelve la respuesta
   original (o 409 si la huella difiere).
6. **Trazabilidad del partner**: `external_id` (+ `metadata` json) del sistema
   origen en el comprobante, consultable y devuelto en webhooks — el POS
   reconcilia contra sus propios ids.
7. **Certificado con dos vías**: (a) el partner lo sube por API on-behalf;
   (b) *fase posterior*: **enlace de onboarding hospedado** — URL firmada y
   temporal donde el cliente final sube su .p12 directamente con nosotros,
   sin que la clave privada pase por el partner (menos responsabilidad para
   la plataforma, argumento de venta).
8. **Conflicto de RUC**: si el RUC ya está **verificado** en otra cuenta
   (directa o de otro partner) → 409; la unicidad diferida del §10 hace que
   cuentas no verificadas no bloqueen. Flujo de vinculación con
   consentimiento del dueño: fase posterior.
9. **Facturación a nivel partner**: el partner es el cliente de pago; cuota
   mensual agrupada (pool) en su plan, con límite opcional por contribuyente
   gestionado. Los contribuyentes gestionados no requieren `plan_id` propio.
10. **Panel de partner** (fase posterior): contribuyentes gestionados,
    consumo, estado de certificados y de entregas de webhooks.

**No funcionales**: tenancy estricta partner↔contribuyente (404 ante uuid
ajeno, como hoy); auditoría de acciones del partner; el ambiente
(pruebas/producción) sigue viniendo en el payload — un partner puede probar
extremo a extremo contra el ambiente de pruebas del SRI sin infraestructura
extra; superficie partner documentada en el OpenAPI/Scalar.

### Interacción con §10 (verificación de RUC)

Sin cambios de fondo: un contribuyente aprovisionado nace `no_verificado`,
se verifica por certificado (mecanismo 3) o por primer `AUTORIZADO`
(mecanismo 2). El candado del pruning por "plan pagado" se extiende a
"gestionado por partner activo". La unicidad diferida es justamente lo que
permite aprovisionar sin fricción sin abrir la puerta al squatting.

### Fases propuestas

| Fase | Contenido | Resultado |
|---|---|---|
| **7a. Núcleo partner** ✅ | Modelo `Partner` (tokenable Sanctum), plano de gestión (aprovisionar/listar contribuyentes, certificado on-behalf), middleware on-behalf sobre API v1, `external_id`, rate limit por partner | El POS aprovisiona un cliente y emite en su nombre con una sola credencial |
| **7b. Webhooks** ✅ | Endpoints por partner y por contribuyente, firma HMAC, reintentos, registro de entregas | Fin del polling; sirve también a cuentas directas |
| **7c. Idempotencia** ✅ | `Idempotency-Key` en emisión (partner y directos) | Reintentos de POS seguros |
| **7d. Onboarding fino** ✅ | Enlace hospedado de certificado, vinculación de RUC existente con consentimiento, panel de partner, cuotas pool con sublímites | Fricción y responsabilidad mínimas para el partner |

7a es autosuficiente para la primera integración real (el POS puede hacer
polling como hoy); 7b/7c la vuelven robusta en producción; 7d es pulido
comercial.

### Registro de la Fase 7a (2026-07-12)

- **Modelo de confianza**: `Partner` tokenable de Sanctum (extiende
  `Authenticatable`, como User); alta por CLI (`partner:crear`, imprime el
  token inicial) y rotación (`partner:token --revocar`). Guard `partner`
  (driver sanctum) + provider `partners` en `config/auth.php`: habilita
  `auth:partner` a futuro y le da a Larastan el tipo `User|Partner` en
  `$request->user()`.
- **Tenancy on-behalf**: middleware `ResolverContribuyente` como única
  fuente de verdad del "contribuyente actual" (User → el suyo; Partner →
  cabecera `X-Contribuyente`, 400 si falta, 404 si el uuid no es de un
  gestionado suyo). Lo usan la API v1 (middleware en el grupo) **y** el
  panel (fallback por sesión). La API v1 no cambió de contrato: los
  tokens de usuario directo funcionan igual.
- **Plano de gestión** `/api/partner/v1` (middleware `SoloPartners`, 403
  para tokens de usuario): `POST /contribuyentes` (idempotente por RUC
  dentro del partner: 200 con el existente; 409 si el RUC es de otra
  cuenta — la vinculación queda para 7d) y `GET /contribuyentes` (consumo
  del mes, estado del certificado, filtro por RUC).
- **Cuota pool**: `Contribuyente::agotoCuotaMensual()` delega en el
  partner cuando `partner_id` no es null (`partners.cuota_mensual`
  nullable = ilimitada); rate limit del limiter `api` por partner
  (`partners.limite_por_minuto`). Los gestionados no llevan plan propio.
- **Trazabilidad**: `external_id` + `metadata` (json) opcionales en la
  emisión, persistidos en el registro y expuestos en `ComprobanteResource`;
  nuevo `GET /api/v1/comprobantes` con filtros `external_id`/`estado`
  (recupera emisiones cuando el integrador perdió la respuesta).
- **Certificado on-behalf**: el `PUT /api/v1/contribuyente/certificado`
  existente funciona con partner + cabecera (verificado por test).
- OpenAPI/Scalar actualizado (sección de partners, cabecera, external_id,
  listados). Suite: 200 tests / 642 aserciones (29 nuevas de 7a);
  PHPStan max limpio.
- **Pendiente (fases siguientes)**: webhooks (7b), `Idempotency-Key` (7c),
  enlace de onboarding hospedado del certificado + vinculación de RUC +
  panel de partner (7d).

### Registro de la Fase 7b (2026-07-12)

- **Modelo**: `WebhookEndpoint` (suscriptor **polimórfico**: un Partner
  recibe los eventos de todos sus gestionados; un Contribuyente, solo los
  suyos; secreto `whsec_…` cifrado en reposo, eventos suscritos en json,
  flag activo) + `WebhookEntrega` (registro consultable por intento:
  estado pendiente/entregada/fallida, código HTTP, error, payload).
- **Eventos** (`EventoWebhook`): `comprobante.autorizado` / `.devuelto` /
  `.no_autorizado` / `.fallido` (mapeados desde el estado final en
  `RegistroDeEmision::completar/fallar/fallarPorErrorTecnico` — este
  último centraliza el `failed()` del job asíncrono, antes inline) y
  `certificado.por_vencer` (comando diario
  `webhooks:certificados-por-vencer`, umbrales 30/7/1 días configurables
  en `sri.webhooks`, programado 08:00).
- **Entrega** (`EnviarWebhookJob`): POST JSON con `X-Evento`, `X-Entrega`
  y firma `X-Firma: v1=HMAC_SHA256(secreto, "{timestamp}.{cuerpo}")` +
  `X-Firma-Timestamp` (verificable y anti-replay). 5 intentos con backoff
  1 m/5 m/30 m/2 h; cada intento actualiza la entrega. El XML firmado no
  viaja: el integrador lo descarga por la API.
- **Gestión** (trait `GestionaWebhooks` compartido): CRUD + entregas en
  `/api/v1/webhooks` (suscriptor = contribuyente actual, funciona
  on-behalf con `X-Contribuyente`) y `/api/partner/v1/webhooks`
  (suscriptor = partner). El secreto solo viaja en la respuesta de
  creación; un endpoint ajeno responde 404.
- OpenAPI/Scalar: sección de webhooks con guía de verificación de firma,
  4 rutas nuevas + espejo partner, schemas de endpoint/entrega/payload.
- Suite: 224 tests / 727 aserciones (24 nuevas de 7b); PHPStan max limpio.
- **Pendiente (fases siguientes)**: `Idempotency-Key` (7c), onboarding
  hospedado del certificado + vinculación de RUC + panel de partner (7d).

### Registro de la Fase 7c (2026-07-13)

- **Middleware `ManejarIdempotencia`** en `POST /comprobantes` y
  `POST /comprobantes/{id}/reintentar` (opt-in por cabecera
  `Idempotency-Key`, sin cabecera no interviene). Modelo
  `ClaveIdempotencia` (`claves_idempotencia`): clave única por
  contribuyente + huella sha256 de `método|URI|cuerpo` (la URI ata
  `?async=1`) + respuesta y código HTTP guardados.
- **Semántica**: misma clave+huella → respuesta original byte a byte con
  `Idempotency-Replayed: true` (también los 422 de negocio: un devuelto
  reintentado no crea otro registro); misma clave+otra huella → 409;
  original en curso (respuesta null, ventana 90 s) → 409; en-curso
  huérfana pasada la ventana → se libera y reprocesa; solo se guardan
  desenlaces deterministas (ante 5xx/401/403/429 la clave queda libre).
  Carrera cubierta por el constraint único (create atómico → 409).
- **Expiración**: TTL 24 h (`sri.idempotencia`, configurable) vía
  `MassPrunable` + `model:prune` diario programado.
- OpenAPI: parámetro `Idempotency-Key` en emisión/reintento + sección de
  uso. Suite: 238 tests / 773 aserciones (14 nuevas); PHPStan max limpio.
- **Pendiente (7d)**: onboarding hospedado del certificado, vinculación
  de RUC verificado, panel de partner, cuotas pool con sublímites.

### Registro de la Fase 7d (2026-07-13)

- **Enlace hospedado de certificado**: `POST /partner/v1/contribuyentes/
  {uuid}/enlace-certificado` (y botón en el panel de partner) genera una
  URL firmada temporal (`sri.certificados.enlace_ttl_horas`, 72 h). La
  página pública (`Certificado/Subir`, middleware `signed` — la firma
  cubre GET y POST) permite al cliente final subir su .p12 + clave
  directamente al servicio: la clave privada nunca pasa por el partner.
- **Sublímites pool**: `contribuyentes.limite_mensual` (nullable) acota a
  un gestionado dentro de la cuota pool; se acepta en el aprovisionamiento
  y en el nuevo `PATCH /partner/v1/contribuyentes/{uuid}` (null lo quita).
- **Vinculación de RUC existente**: modelo `Vinculacion` (pendiente/
  aprobada/rechazada). El partner solicita por API (`POST/GET
  /partner/v1/vinculaciones`, idempotente en pendiente; 404 RUC no
  registrado, 409 ya gestionado) o desde su panel; el dueño la resuelve
  en Configuración (aprobar asigna `partner_id` → on-behalf + cuota pool;
  el panel del dueño muestra aviso de cuenta gestionada). Sin emails en
  esta versión.
- **Panel de partner**: credenciales opcionales en `partners`
  (email/password nullable, comando `partner:credenciales`), guard de
  sesión `partner-web` + `redirectGuestsTo` por rutas (los dos paneles no
  se cruzan: verificado por tests en ambos sentidos). Páginas Inertia
  (`PartnerPanel/`, layout esmeralda): Login, Inicio (consumo pool),
  Contribuyentes (+ enlace de certificado), Webhooks (endpoints +
  registro de entregas), Vinculaciones (solicitar/estado), Tokens
  (rotación, visible una vez).
- OpenAPI: PATCH contribuyentes, enlace-certificado, vinculaciones,
  `limite_mensual`/`limiteMensual` y schema Vinculacion.
- Suite: 272 tests / 922 aserciones (34 nuevas); PHPStan max limpio;
  assets compilados (npm run build).
- **La fase 7 (capa partner/plataforma, §11) queda completa.** Backlog
  nuevo sugerido: evento de webhook `vinculacion.resuelta` (hoy el
  partner consulta por GET), y aviso por correo al dueño cuando llega
  una solicitud de vinculación.

---

## 12. Plan: integración real del POS (UltimatePOS) y prueba de punta a punta

Plan (2026-07-13) para conectar el POS (`../pos`, UltimatePOS sobre
Laravel 12, multi-negocio) con la capa partner (§11) y validar la
integración contra el **ambiente de pruebas del SRI**. El POS hoy no
tiene ningún código de facturación electrónica: el plan incluye construir
su lado cliente mínimo y luego probar.

### Hallazgos del POS que anclan el diseño

| Concepto SRI/FE | En UltimatePOS |
|---|---|
| Contribuyente (RUC) | `Business` — multi-negocio: cada negocio con FE activa se aprovisiona como contribuyente gestionado. RUC en `business.tax_number_1` (verificar formato al implementar) |
| Establecimiento / punto de emisión | `BusinessLocation` (p. ej. `001`/`001` por location, configurable) |
| Factura | `Transaction` (type `sell`, status `final`) con `invoice_no` propio |
| Detalles | `TransactionSellLine` |
| Impuestos | `TaxRate` → tabla de mapeo a códigos SRI (IVA 15% = codigo 2 / codigoPorcentaje 4…) |
| Comprador | `Contact` (`tax_number` → tipoIdentificacion 04/05/06/07) |
| Punto de enganche | Evento existente `SellCreatedOrModified` → listener encolado |

### Decisiones a tomar antes de codificar (lado POS)

1. **Secuencial SRI**: NO reutilizar `invoice_no` (formato libre del POS).
   Contador propio de 9 dígitos por (business, location) en la tabla de
   integración, o un `InvoiceScheme` numérico dedicado. El secuencial es
   único por serie ante el SRI: la fuente debe ser transaccional.
2. **Dónde vive el código**: módulo nwidart (`Modules/FacturacionEcuador`)
   vs. `app/Services/FacturacionElectronica`. Propuesta: app/ simple para
   el piloto; módulo si se comercializa.
3. **Alcance del piloto**: solo `factura`. Notas de crédito
   (`sell_return`) y retenciones: fase posterior.

### Fase A — Lado POS: datos y cliente HTTP

- Migración `fe_comprobantes` en el POS: `transaction_id`, `business_id`,
  `fe_uuid`, `estado`, `clave_acceso`, `secuencial`, `mensajes` (json),
  timestamps. + Config por negocio: `fe_activo`, `fe_contribuyente_uuid`.
- `config/services.php` → `facturacion`: `base_url` (https://fe.test/api),
  `token` (partner), `webhook_secret`, `timeout`.
- Cliente HTTP (`FacturacionClient`): aprovisionar contribuyente, emitir
  (`?async=1`, cabeceras `X-Contribuyente` + `Idempotency-Key: venta-{id}`,
  `external_id`), consultar por id/external_id, descargar RIDE.
- **Mapper** `Transaction → payload factura` (el trabajo fino): totales
  como string con 2 decimales, fecha `dd/mm/aaaa`, detalles con impuestos
  por línea, `totalConImpuestos` agregado, comprador desde `Contact`.
- Listener encolado de `SellCreatedOrModified` (solo `status=final` y
  negocio con FE activa) → job `EmitirFacturaElectronicaJob` (reintentos
  con backoff; la idempotencia del lado FE lo hace seguro).

### Fase B — Lado POS: webhook receiver y UI mínima

- `POST /webhooks/facturacion` (sin CSRF, público): verifica
  `X-Firma` (HMAC del cuerpo crudo + timestamp, tolerancia 5 min,
  `hash_equals`), localiza por `datos.externalId` / `datos.id`, actualiza
  `fe_comprobantes` (estado, clave de acceso, mensajes). Responde 2xx
  rápido (procesar en job si crece).
- UI mínima: badge de estado FE en la vista de la venta + botón RIDE
  (proxy autenticado hacia `GET /comprobantes/{id}/ride`) + reintento
  manual para devueltos (corrige datos → `POST /{id}/reintentar`).
- Comando de reconciliación `fe:reconciliar`: ventas con FE pendiente y
  sin webhook en N minutos → consulta por `external_id` (red de seguridad
  si el webhook se perdió).

### Fase C — Plumbing local de punta a punta (fe.test ↔ pos.test)

Ambos servidos por Herd; los webhooks server-to-server entre `.test`
funcionan localmente.

1. En fe: `partner:crear "UltimatePOS"` (+ `partner:credenciales`),
   **queue worker activo** (`php artisan queue:work`) — con
   `QUEUE_CONNECTION=sync` el webhook saldría inline y distorsiona la
   prueba.
2. Desde el POS: aprovisionar un negocio de prueba, registrar el webhook
   (`https://pos.test/webhooks/facturacion`), guardar secreto/uuid.
3. Cargar el **certificado de prueba del repo fe** (no válido ante el
   SRI): emitir una venta → el SRI de pruebas la DEVOLVERÁ (error 39,
   firma inválida). Eso es deseable aquí: valida todo el plumbing
   (mapper, async, webhook `comprobante.devuelto` verificado, estado en
   el POS, reintento, replay de Idempotency-Key) sin tocar nada real.

### Fase D — Contra el SRI (ambiente de pruebas) con certificado real

Con el certificado real ya validado por el firmador nativo (2026-07-10):

1. Contribuyente con el RUC real + certificado real, `ambiente: '1'`.
2. **Checklist de casos** (cada uno verificado en POS, en fe y en el
   portal del SRI de pruebas):
   - [ ] Factura a consumidor final (identificación `07`).
   - [ ] Factura con cliente identificado (cédula/RUC) e IVA 15%.
   - [ ] Factura con descuento por línea.
   - [ ] Webhook `comprobante.autorizado` recibido, firma verificada,
         estado y clave de acceso en el POS.
   - [ ] RIDE descargado desde el POS (logo incluido).
   - [ ] Secuencial repetido a propósito → devuelto (error 45) →
         corrección y reintento reutilizando la clave (§5.10).
   - [ ] Timeout simulado en el POS → reintento con la misma
         `Idempotency-Key` → replay, sin duplicado.
   - [ ] Sublímite/cuota pool agotada → 429 manejado con gracia.
   - [ ] Reconciliación: apagar el receiver, emitir, comprobar que
         `fe:reconciliar` recupera el estado.
3. Registrar hallazgos del mapper (impuestos, redondeos, campos que el
   SRI observe) como fixtures/tests en el POS.

### Fase E — Endurecimiento pre-producción

- Alerta sobre entregas de webhook fallidas (panel de partner ya las
  muestra; añadir aviso activo si se acumulan).
- Switch por negocio a `ambiente: '2'` (producción) tras el piloto.
- Logs/trazas correlacionados por `external_id` en ambos lados.
- Rotación de token documentada (`partner:token --revocar`).
- Del lado fe: retirar el certificado de prueba del contribuyente piloto.

### Orden y tamaño

A y B son el grueso (1 sesión cada una, con tests de POS usando
`Http::fake`); C es una tarde con checklist; D depende del SRI (validar
en días distintos); E es previa al go-live. Las decisiones 1–3 conviene
fijarlas antes de empezar A.

### Activación/desactivación por negocio (añadido 2026-07-13)

La FE es **opt-in por negocio** y eso es un ciclo de vida, no un booleano:

- **Activar = onboarding guiado** (pantalla de ajustes del negocio en el
  POS, no solo un flag): valida el RUC (`tax_number_1`, 13 dígitos),
  aprovisiona el contribuyente (idempotente), asigna estab/ptoEmi y
  secuencial inicial por location, y resuelve el certificado (subida
  directa o **enlace hospedado** para no tocar la clave privada). El
  switch queda "activo" solo cuando todo lo anterior está completo; el
  estado del certificado se muestra ahí mismo (vencimiento incluido, con
  el webhook `certificado.por_vencer` alimentándolo).
- **Ventas con FE inactiva**: el listener las ignora y NO se emiten
  retroactivamente al activar (regla explícita; la FE es aditiva — la
  venta del POS sigue su vida normal con su `invoice_no`).
- **Desactivar** solo detiene emisiones nuevas: no borra nada, el
  historial de comprobantes y descargas de RIDE sigue disponible, y el
  webhook receiver sigue aceptando actualizaciones de comprobantes ya
  emitidos (uno en vuelo puede autorizarse después de desactivar).
- **Reactivar** reutiliza el mismo contribuyente (el aprovisionamiento
  idempotente por RUC lo garantiza) y el secuencial **continúa donde
  quedó** — nunca se reinicia el contador.
- Impacto en el checklist de la fase D: + activar un negocio desde cero
  por el flujo guiado; + desactivar con una emisión en vuelo y verificar
  que el webhook aún actualiza; + reactivar y comprobar continuidad del
  secuencial.

### Registro §12 — Fase A completada en el POS (2026-07-14)

- **Datos**: `fe_ajustes` (opt-in por negocio: activo, contribuyente_uuid,
  RUC/razón social/dirección explícitos, obligado_contabilidad, ambiente),
  `fe_puntos_emision` (serie 001-001 por location + contador de secuencial
  con `lockForUpdate`, nunca se reinicia), `fe_comprobantes` (venta ↔
  fe_uuid/estado/clave/serie; estados propios pendiente_envio /
  no_facturable / rechazada_api / error_envio + espejo de los del servicio).
- **Cliente** (`app/Services/FacturacionElectronica/FacturacionClient`):
  aprovisionar, emitir async (X-Contribuyente + Idempotency-Key =
  external_id `venta-{id}`), consultar por id/external_id, RIDE, enlace de
  certificado, registrar webhook. `FacturacionException` distingue
  definitivo (4xx) de transitorio (5xx/429 → retry).
- **Mapper** (`FacturaMapper`): formato SRI estricto (strings 2 decimales
  con punto — nunca el formato por-negocio del POS —, fecha dd/mm/aaaa),
  detalles con IVA por línea (mapeo `facturacion.iva.porcentajes`),
  comprador 04/05/06/07 (walk-in → consumidor final), y **verificación de
  cuadre** contra `final_total` (±0.02). Limitaciones deliberadas del
  piloto → `VentaNoFacturable` visible en el POS: impuesto/descuento a
  nivel de orden y grupos de impuestos.
- **Flujo**: listener encolado sobre `SellCreatedOrModified` existente
  (no-op sin FE activa; una venta ya emitida no se re-emite) → job con
  reintentos (secuencial asignado una sola vez y conservado).
- **CLI**: `fe:activar {business}` (aprovisiona + guarda ajustes + imprime
  enlace de certificado) y `fe:registrar-webhook`.
- Tests: 10 nuevos (`tests/Feature/FacturacionElectronica/`) con
  `Http::fake`; suite completa del POS en verde (1160 tests).
- **Siguiente**: fase B (webhook receiver + UI de estado en la venta +
  pantalla de activación + `fe:reconciliar`).

### Registro §12 — Fase B completada en el POS (2026-07-15)

- **Webhook receiver** `POST /webhook/facturacion` (público; `/webhook/*`
  ya estaba excluido de CSRF): verifica `X-Firma` (HMAC-SHA256 de
  `timestamp.cuerpo crudo`, `hash_equals`, tolerancia 5 min anti-replay),
  actualiza `fe_comprobantes` (localiza por fe_uuid con fallback a
  external_id) y `fe_ajustes.certificado_valido_hasta` (evento
  `certificado.por_vencer`). Eventos desconocidos se confirman con 2xx.
- **Reintento §5.10**: `FacturacionClient::reintentarFactura` + rama en el
  job — un comprobante devuelto/no_autorizado/fallido se reintenta contra
  `/{uuid}/reintentar` (misma clave y secuencial) en vez de re-emitir.
- **`fe:reconciliar`** (programado cada 15 min): enviados estancados →
  consulta y adopta el estado; nunca enviados (error_envio) → consulta por
  external_id (¿respuesta perdida?) y si no existe re-despacha la emisión.
- **UI** (`/facturacion-electronica`, Blade AdminLTE): listado por negocio
  con serie-secuencial, estado con color, clave/mensajes, RIDE (proxy
  autenticado) y botón Reenviar; pantalla de **ajustes/activación**
  (RUC/razón social/ambiente/obligado + switch activo con las reglas del
  ciclo de vida: activar aprovisiona idempotente, desactivar conserva
  todo, reactivar continúa el secuencial) + estado del certificado en
  vivo desde la API + generación del enlace hospedado. Sin entrada de
  menú todavía (URL directa, piloto).
- Tests: +18 (webhook con firma válida/inválida/expirada, reconciliación,
  reintento, UI con permisos y tenancy). Suite del POS completa en verde.
- **Siguiente**: fase C — plumbing local fe.test ↔ pos.test con el
  certificado de prueba (espera error 39) y luego fase D con el real.

### Backlog del lado POS (§12, post-piloto)

- **Cédula/RUC en rutas alternativas de creación de contactos** (2026-07-16):
  la validación vive en `ContactController`. ✅ 2026-07-22: cubierto también
  el **import CSV de contactos** (`ContactController@importContacts`, valida
  fila a fila con `validarCedulaRucParaFacturacion`). `ImportSalesController`
  crea contactos al vuelo **sin `tax_number`** (solo nombre/email/móvil): no
  hay identificación que validar ahí. El módulo Connector no está instalado
  físicamente (el `modules_statuses.json` lo lista, pero no hay `Modules/`):
  si algún día se instala, replicar la regla ahí también.
- ✅ **Dígito verificador de cédula (módulo 10) y RUC (módulo 11)**
  (2026-07-22): `App\Services\FacturacionElectronica\ValidadorIdentificacion`
  valida cédula (módulo 10 / Luhn), RUC de persona natural (cédula +
  establecimiento), sociedad privada (3er díg. 9, módulo 11) y pública
  (3er díg. 6, módulo 11), más provincia 01–24/30. Enganchado en el
  formulario, el modal y el import CSV de contactos. Atrapa en digitación
  los typos bien formados que el SRI devolvería. El pasaporte queda libre
  (sin dígito verificador).
- **Identificación del Exterior (tipo 08)** (diferido 2026-07-22): hoy toda
  identificación **no numérica** se factura como **06 (pasaporte)**. El
  `08` no es deducible del texto (06 y 08 son documentos libres sin dígito
  verificador) y el SRI **acepta ambos** sin rechazar, así que no bloquea
  la emisión. Para soportarlo haría falta una señal explícita del operador
  (un selector de tipo de identificación en el formulario, o reusar la
  bandera `is_export` a costa de acoplar "exportación" con "documento del
  exterior"). Se retoma si un cliente factura extranjeros con documento del
  exterior de forma habitual.
- ✅ **Descuento a nivel de orden desactivado** (2026-07-22; decisión
  2026-07-21: NO se prorratea — se elimina el caso de raíz). Nueva política
  `App\Services\FacturacionElectronica\DescuentoDeOrden`: (1) al activar FE
  (pantalla de ajustes o `fe:activar`) se fuerza el flag nativo
  `pos_settings.disable_discount = 1` y `default_sales_discount = 0`;
  (2) las vistas de venta (POS y formulario clásico) ocultan el descuento
  de orden y fuerzan sus campos a 0 cuando FE está activa — incluso al
  editar una venta antigua con descuento, que lo descarta al reabrirse;
  (3) **guard de servidor** en `SellPosController@store/@update`: una venta
  FINAL con FE activa y descuento de orden > 0 se rechaza con mensaje
  ("aplique el descuento en las líneas"); borradores/cotizaciones/órdenes
  quedan fuera; (4) el checkbox `disable_discount` de los ajustes POS se
  bloquea con nota mientras FE esté activa. La red `no_facturable` queda
  como último recurso. De paso: fechas relativas en
  `CreatesSells`/`CreatesPurchases` (la fecha fija 2026-06-20 salió de la
  ventana `transaction_edit_days` y rompía los tests de edición).
- **Corrección de una factura autorizada** (NC + refacturación): flujo
  guiado para anular y reemitir. (La emisión de notas de crédito por
  `sell_return` ya está ✅ 2026-07-20.)
- **Re-onboarding por cambio de RUC** (2026-07-22): el RUC quedó
  **bloqueado** en la pantalla de ajustes tras el aprovisionamiento
  (readonly + rechazo en servidor): cambiarlo desincronizaba el
  `contribuyente_uuid` y toda emisión fallaba con 422. Un cambio real de
  entidad legal (persona natural → sociedad…) es un contribuyente NUEVO:
  falta el flujo guiado (nuevo aprovisionamiento + enlace de certificado
  nuevo + reinicio de secuenciales en 1; el historial queda con el
  contribuyente viejo). Vía CLI ya posible: `fe:activar --ruc=NUEVO`
  aprovisiona y genera el enlace — solo faltaría reiniciar
  `fe_secuenciales`. Los cambios de razón social / dir. matriz sí se
  espejan al servicio (✅ 2026-07-22, PATCH contribuyentes al guardar
  ajustes, con fallo duro si el servicio no responde).
- ✅ **Entrada de menú + aviso activo de fallos** (2026-07-22): ítem
  "Facturación electrónica" en el sidebar (`AdminSidebarMenu`), visible
  solo para negocios que ya configuraron FE (existe `FeAjuste`) y con
  permiso `sell.view`. Badge rojo con el nº de comprobantes en estado
  terminal negativo (`FeComprobante::scopeConProblema` /
  `ESTADOS_CON_PROBLEMA`: no_facturable, rechazada_api, error_envio,
  devuelto, no_autorizado, fallido — excluye los en vuelo y autorizado).
  Antes las pantallas solo se alcanzaban por URL directa y los fallos solo
  se veían entrando al listado. **Atender**: como un estado terminal (p. ej.
  `no_facturable`) no cambia nunca, el badge contaría siempre ≥ 1; se añadió
  la columna `novedad_atendida_at` y la acción `fe.atender` (botón "Marcar
  atendido"/"Reabrir" en el listado) — el badge usa `scopeRequiereAtencion`
  (`conProblema` + `novedad_atendida_at IS NULL`) y un reenvío limpia
  `novedad_atendida_at` para re-armar el aviso si vuelve a fallar. Badge con estilos inline (las
  clases `tw-` en strings PHP se purgan del CSS compilado).
- ✅ **Estado FE en el listado de ventas** (2026-07-22): la columna de
  factura (`SellController@index`, `editColumn('invoice_no')`) muestra un
  badge coloreado con el estado del comprobante electrónico y, si está
  autorizado, un enlace al RIDE — solo para negocios con FE activa. Sin
  N+1: relación `Transaction::feComprobante` (hasOne tipo=factura)
  eager-loaded condicionalmente. Sigue el patrón de los badges que la
  columna ya acumula (devolución, suscripción, export). Iteración 2
  pendiente si se quiere **filtrar** por estado: columna dedicada.
- ✅ **Estado FE en el listado de devoluciones** (2026-07-22): mismo badge
  (estado + RIDE) en la columna de la devolución del listado de sell_return
  (`SellReturnController@index`), para la **nota de crédito**. Helper
  extraído a `App\Services\FacturacionElectronica\ComprobanteBadge` (compartido
  ventas/devoluciones; tooltip contextual "Factura" vs "Nota de crédito").
  Relación `Transaction::feNotaCredito` (hasOne tipo=notaCredito) eager-loaded
  condicionalmente. El RIDE sirve igual para NC (la ruta `fe.ride` solo exige
  autorizado, no distingue tipo).

### Registro §12 — Fases C y D completadas: PILOTO CERRADO (2026-07-16)

Circuito validado de punta a punta contra el **SRI real (ambiente de
pruebas)** con certificado real: venta en el POS → mapper → emisión
async on-behalf → firma XAdES nativa → recepción/autorización SRI →
webhook firmado → estado y RIDE en el POS.

**Checklist ejecutado** (todo en verde):
consumidor final 07 · cliente con cédula 05 · factura mixta 15%/0% ·
descuento por línea (base imponible y totalDescuento correctos, IVA por
línea) · webhook autorizado · RIDE descargado · secuencial repetido
(error 45) · replay de idempotencia (dispatch de venta autorizada = no-op
verificado) · activación guiada · certificado por enlace hospedado ×2 ·
descuento de orden → no_facturable · reconciliación real.
Pendiente opcional: 429 de cuota en E2E (cubierto por tests) y logo en RIDE.

**Hallazgos de las pruebas reales, todos resueltos con código + tests:**
1. URL del webhook plural/singular en el comando del POS.
2. Secuencial quemado (error 45) por pruebas previas → re-emisión
   automática con secuencial nuevo.
3. Clave con veredicto de firma registrado: el SRI NO re-evalúa un
   NO AUTORIZADO por error 39 aunque el XML llegue corregido → clave
   quemada, re-emisión automática (aprendizaje clave sobre §5.10: aplica
   a devueltos de recepción, no a rechazos de autorización por firma).
4. Webhooks entregados fuera de orden (reintento tardío pisó un
   autorizado) → guardia por fe_uuid en el receiver.
5. Cédula/RUC de clientes invisible en el POS (campo escondido en "Más
   información") → campo visible + validación obligatoria con FE activa.
6. RIDE con códigos crudos de impuesto → `TotalImpuestoData::etiqueta()`
   ("IVA 15%", "IVA 0%", ICE, IRBPNR) en las 4 plantillas.

### Registro §12 — Notas de crédito (2026-07-20)

Devoluciones del POS (`sell_return`) → nota de crédito electrónica
(codDoc 04). El servicio fe **no necesitó cambios**: sus DTOs y el RIDE
de nota de crédito ya estaban validados con golden fixtures.

- **Secuenciales por tipo de documento** (`fe_secuenciales`): el SRI
  numera cada codDoc por separado — factura `001-001-000000010` y NC
  `001-001-000000001` son series independientes. Reemplaza al contador
  único de `fe_puntos_emision`, migrando su valor a la fila `factura`
  (una tabla vacía habría re-emitido secuenciales ya registrados → 45).
  `fe_comprobantes` gana `tipo`; su `transaction_id` único sigue
  sirviendo porque la devolución es una Transaction propia.
- **`NotaCreditoMapper`**: los detalles NO salen de líneas del retorno
  (UltimatePOS no las crea) sino de `quantity_returned` en las líneas de
  la venta PADRE; el ítem se identifica con `codigoInterno` (la factura
  usa `codigoPrincipal`); `numDocModificado`/`fechaEmisionDocSustento`
  salen del comprobante autorizado de la venta. **`tarifa` se omite en
  el `totalImpuesto` de cabecera**: el esquema de la NC no la contempla
  (verificado contra el XML golden real autorizado), aunque sí va en el
  impuesto de cada línea.
- **Precondición**: la venta de origen debe tener factura electrónica
  AUTORIZADA — una NC modifica un documento existente. Sin ella la
  devolución queda `no_facturable` con la razón visible (y se
  auto-recupera si la factura se autoriza después).
- **Flujo**: evento nuevo `SellReturnCreatedOrModified` despachado en
  `SellReturnController@store` (el POS no tenía ninguno para
  devoluciones) → listener encolado → `EmitirNotaCreditoJob`.
  Webhooks, reconciliación, reenvío y RIDE funcionan igual (external_id
  `devolucion-{id}`).
- **Refactor**: base `ComprobanteMapper` y trait
  `EmiteComprobanteElectronico` con lo compartido (comprador, IVA,
  líneas, claves quemadas, envío/persistencia); el cliente pasó a
  `emitirComprobante`/`reintentarComprobante` parametrizados por tipo.
- **Trampa encontrada**: en UltimatePOS `return_parent()` es el hasOne
  *de la venta hacia su devolución*; el que apunta a la venta desde la
  devolución es `return_parent_sell()`.
- **Verificación cruzada**: el payload real del mapper se validó contra
  los DTOs de fe y se generó su XML — estructura idéntica al golden
  autorizado, sin tocar el SRI.
- Tests: +13 (13 de NC + webhook por `devolucion-{id}`). Suite completa
  del POS: 1200 tests / 2079 aserciones en verde.
- **Limitación del piloto**: una NC por devolución. Ampliar una
  devolución ya autorizada exigiría una nota adicional por la diferencia
  (backlog).
