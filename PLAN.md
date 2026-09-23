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

- ~~**Golden master (Fase 0):** capturar, desde los `exampleBody*.json` del legado,
  la **clave de acceso** y el **XML** que el código actual produce, como snapshots
  de referencia.~~ **Retirado el 2026-09-20** (ver registro más abajo): el
  sistema superó al legado y los snapshots pasaron de red de seguridad a
  lastre. Hoy el XML y la clave se prueban por invariantes de la ficha y por
  roundtrip sobre lo que el propio sistema genera; los payloads viven en
  `tests/Payloads.php`.
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
| **0. Red de seguridad** ✅ → retirada | Fixtures golden-master (XML + clave de acceso) desde los `exampleBody*.json`; documentar la estructura real de cada tipo | Cumplió su función durante las fases 1-6; retirada el 2026-09-20 (`fixtures/golden/` y `tools/golden/` eliminados) |
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
  ficha, sin prueba de autorización real).

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

### Registro del retiro de los golden (2026-09-20)

Decisión: dejar de usar el legado como oráculo. Los fixtures ataban el
sistema a un payload de 2022 (IVA 12 %, `codDoc` erróneo en NC/retención,
bloque `info` con el .p12) y a la serialización exacta de `ArrayToXml`, y
ya habían forzado parches: `phpunit.xml` vaciaba `SRI_RUC_PROVEEDOR` para
no romper el byte a byte, `ConstruirXmlTest` corregía el golden con
`str_replace`, y `comprobante_autorizado_con_xml()` tenía un caso especial
porque las tres claves golden eran idénticas.

Qué los reemplaza (cuatro commits, suite en verde en cada uno):

- **`tests/Payloads.php`**: un builder por tipo (los seis) con datos
  vigentes; `payload_emision()`, `payload_comprobante()`,
  `comprobante_de_prueba()`, `xml_de_prueba()`, `clave_acceso_de_prueba()`.
  Los 14 tests que usaban el golden solo como dato de muestra migraron
  mecánicamente.
- **Clave de acceso**: composición campo a campo según la Tabla 1 de la
  ficha y el ejemplo oficial de módulo 11 (§5.2: `41261533` → 6), más los
  casos borde 11 → 0 y 10 → 1 contrastados con una implementación
  independiente en el test.
- **XML**: invariantes estructurales sobre los seis tipos (raíz, `id`,
  `version`, orden `infoTributaria → info<Tipo> → cuerpo`, `codDoc`
  derivado y coherente con la clave, importes literales, wrappers con 1 y N
  elementos). El SRI valida contra .xsd (§5.1), no bytes.
- **Parser y pipeline**: roundtrip `render(parse(render(dto))) ===
  render(dto)` para los seis tipos; el pipeline se contrasta con lo que el
  VO y `ConstruirXml` producen por separado.
- `GoldenFixturesTest` eliminado; `fixtures/golden/` y `tools/golden/`
  borrados del árbol (quedan en el historial).

Lo que se pierde, asumido: la evidencia byte a byte contra lo que el SRI
autorizó al legado. Ya hay comprobantes reales autorizados por el sistema
nuevo (§12, §13). Si algún día hace falta un oráculo externo, el correcto
son los .xsd de la ficha validando el XML generado, no un snapshot.

`legacy/` (212 archivos, 16 MB) salió del árbol en el commit siguiente, sin
tag: el último commit que lo contiene es `b99aff6` (`git log -- legacy/`
lo localiza). La carpeta puede seguir en disco porque guarda certificados y
credenciales reales no versionados; `.gitignore` la ignora entera.

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
- ✅ **Corrección de una factura autorizada** (2026-07-24, NC +
  refacturación). Una factura autorizada no se edita ni se borra: el
  documento existe ante el SRI aunque el POS cambie su copia. La única
  corrección por web service es anularla con una nota de crédito por el
  total y volver a facturar (la solicitud de anulación en el portal exige
  la aceptación del receptor: trámite fuera de banda).
  (1) **Guards** en `App\Services\FacturacionElectronica\ComprobanteInmutable`:
  `SellPosController@update/@edit`, `SellController@edit` y
  `TransactionUtil::deleteSale` (una sola puerta, cubre también
  `ImportSalesController`) rechazan editar/eliminar una venta con factura
  ante el SRI. Antes se podía editar (el listener no re-emite → POS y SRI
  divergían en silencio) y borrar (con `onDelete cascade` desaparecía la
  fila del comprobante).
  (2) **Anulación guiada** (`CorreccionFacturaController`, rutas
  `fe.corregir*`): pantalla con la factura, motivo obligatorio (viaja al
  `motivo` de la NC como "Anulacion de la factura 001-001-NNNNNNNNN: …") y
  confirmación; crea la **devolución total** vía `addSellReturn` (stock y
  cuenta del cliente consistentes) y dispara `EmitirNotaCreditoJob` por el
  evento habitual. Tabla nueva `fe_correcciones` (venta_id único,
  devolucion_id, venta_reemplazo_id, motivo) para distinguir la anulación
  por error de una devolución comercial sin tocar `transactions`; el
  estado no se guarda, se deriva de la NC y de `venta_reemplazo_id`.
  (3) **Refacturación**: con la NC **autorizada** (antes no: quedarían dos
  facturas vivas), copia la venta como **borrador** —sin heredar
  `quantity_returned` ni los puntos de recompensa— y lleva al operador a
  editarlo; al finalizarlo se emite la factura nueva por el flujo normal.
  Bloqueos: doble anulación, venta con devoluciones previas (duplicaría el
  crédito) y líneas con sub-unidades (el importe no cuadraría). Fuera de
  alcance: anulación parcial y el reembolso del dinero (queda como saldo a
  favor, se liquida con las herramientas nativas). Enlace "Corregir
  factura" en el listado FE y en el de ventas, donde además desaparecen
  Editar y Eliminar. Tests: +19. Suite del POS completa en verde (1272).
  De paso: `TransactionUtil::canBeEdited()` casteaba mal la ventana de
  edición. Llega de la sesión como **string** (`BusinessController@update`
  cachea el modelo del negocio con los datos del formulario) y Carbon 3
  exige `int|float` en `addDays()`: `TypeError` en **cualquier** pantalla
  de edición de transacción (ventas, compras, transferencias). Lo destapó
  la refacturación, que redirige a `SellPosController@edit`.
- ✅ **Devoluciones con nota de crédito emitida, congeladas** (2026-07-24):
  el lado simétrico del ítem anterior, que quedaba abierto.
  `SellReturnController@destroy` borraba una devolución sin mirar su NC y,
  por `onDelete cascade`, desaparecía la fila del comprobante —y con la FK
  de `fe_correcciones.devolucion_id`, el enlace factura → NC → factura
  corregida, dejando la venta como si nunca se hubiera anulado mientras
  ante el SRI seguía anulada. Editarla (`add` → `store` → `addSellReturn`,
  que **actualiza**) descuadraba el importe devuelto respecto a la nota,
  porque `EmitirNotaCreditoElectronica` no re-emite si ya hay `fe_uuid`
  —esto cubre también la ampliación de una devolución autorizada, que
  `EmitirNotaCreditoJob` documentaba como no soportada sin que nada la
  impidiera. Guards en `destroy`, `add`, `store` y ocultación de
  Editar/Eliminar en el listado de devoluciones.
  **Criterio único** para ambos documentos, en
  `ComprobanteInmutable` + `FeComprobante::ESTADOS_ANTE_EL_SRI`
  (`pendiente`, `recibido`, `autorizado`) y `existeAnteElSri()`: lista
  positiva a propósito, no la negación de `ESTADOS_CON_PROBLEMA`
  —`pendiente_envio` es local (aún no salió) y los estados con problema
  representan documentos que el SRI **no** tiene, donde borrar o reintentar
  es la salida legítima. Los estados en vuelo bloquean porque el desenlace
  es desconocido: si se borra y luego llega la autorización, el documento
  queda huérfano. Contrapartida: un comprobante atascado en vuelo (webhook
  perdido) congela su transacción hasta que `fe:reconciliar` resuelva el
  estado real. De paso el criterio se unificó en las facturas (antes solo
  bloqueaba `autorizado`); en el listado de ventas, una factura en vuelo
  oculta Editar/Eliminar pero **no** ofrece "Corregir factura": no se anula
  lo que aún no existe. Tests: +11. Suite del POS en verde (1287).
- ✅ **FE desactivada: qué sigue aplicando** (2026-07-24). Un negocio que
  **nunca** activó FE no tiene comprobantes, así que ninguna restricción de
  venta/devolución le afecta: editar y borrar funcionan como en UltimatePOS
  de fábrica. Pero desactivar FE **después** de emitir no libera nada: los
  guards (`ComprobanteInmutable`) no consultan `FeAjuste.activo`, solo la
  existencia del comprobante, porque el SRI conserva el documento aunque el
  negocio deje de emitir (la propia pantalla de ajustes ya avisa de que el
  historial se conserva). Los listados sí miraban `activo`, así que Editar y
  Eliminar reaparecían para que el servidor los rechazara: ahora la UI usa
  **`$fe_con_historial`** (existe `FeAjuste`, igual que el ítem del menú) y
  coincide con el servidor; un negocio sin FE no paga ni el eager load.
  `$fe_activa` se conserva donde sí toca: los badges de estado y la oferta
  de "Corregir factura", que exige emitir la nota que anula. De paso, hueco
  cerrado en el servidor: `mensajeDeRechazoDeAnulacion` ahora exige
  `listoParaEmitir()` — sin FE activa la anulación devolvía la mercadería y
  registraba la corrección, pero la nota nunca se emitía: la factura quedaba
  anulada en el POS y viva ante el SRI. Tests: +3 (1290).
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

### Registro §12 — XML autorizado en la API (2026-09-15)

El POS entrega al comprador el comprobante por correo y necesita adjuntar
el XML, que hasta ahora solo estaba en el panel web. Nuevo endpoint
`GET /v1/comprobantes/{uuid}/xml` (`DescargarXmlController`), con las
mismas guardas que el RIDE: 404 entre contribuyentes, 409 si no está
autorizado, 404 si el XML ya no está en disco.

- **Devuelve el XML envuelto**, no el firmado a secas:
  `App\Sri\Support\XmlAutorizado` construye el nodo `<autorizacion>` del
  SRI (`estado`, `numeroAutorizacion`, `fechaAutorizacion`, `ambiente` y el
  comprobante en CDATA con su propia declaración XML). El XML firmado por
  sí solo **no acredita la autorización**: el número y la fecha que otorga
  el SRI viven en columnas del registro, no dentro del XML, y el software
  receptor lo rechaza al importarlo. Es también el formato que devuelve la
  consulta pública del SRI.
- `numeroAutorizacion` cae a la clave de acceso cuando el SRI no devolvió
  uno propio (esquema offline: son el mismo valor).
- El helper `comprobante_autorizado_con_xml` pasa a `tests/Pest.php` para
  compartirlo entre las descargas de RIDE y XML.

#### ✅ Decidido (2026-09-16): el panel entrega también el autorizado

La API y el panel divergían: el panel servía el XML **firmado sin envolver**.
Se unificó — el panel **reutiliza `DescargarXmlController`**, igual que ya
hacía con `DescargarRideController` para el RIDE. Un solo controlador, mismas
guardas, imposible que vuelvan a divergir.

Se descartó ofrecer **ambas descargas** etiquetadas. Razones, tras revisar qué
hace el mercado ecuatoriano (Contífico, Factuplan, Tu Facturero, FacturaHero,
Ecuafact):

- El patrón universal es **dos botones, XML y RIDE**. Ningún sistema expone al
  usuario la distinción firmado/autorizado: el XML que ofrecen es el
  autorizado, y varios lo generan **solo** cuando el comprobante ya lo está.
- El **XML autorizado es el documento legal**; el RIDE sin número de
  autorización no tiene validez. Entregar el firmado le daba al contribuyente
  un archivo que **no cumple su obligación de conservarlo 7 años**.
- Cuando un receptor pierde el comprobante, la vía estándar es pedir al emisor
  que **reenvíe el XML**: lo que espera es el autorizado.

Cambios de comportamiento asumidos:

- El panel ahora responde **409** si el comprobante no está autorizado. No
  quita nada alcanzable: `Pages/Panel/Comprobantes.vue` ya mostraba los
  enlaces solo con `estado === 'autorizado'`; el backend simplemente pasa a
  coincidir con la pantalla. Y envolver en `<autorizacion><estado>AUTORIZADO`
  algo sin autorizar habría sido mentir.
- El archivo descargado pasa de `comprobante-{clave}.xml` a `{clave}.xml`,
  como el de la API y como nombra el portal del SRI.
- El XML firmado a secas deja de estar expuesto en ningún endpoint. Sigue en
  `storage` para depurar o re-firmar, que es un artefacto interno y no algo
  que el contribuyente pida.

## 13. RUC del proveedor en los comprobantes (Resolución NAC-DGERCGC26-00000027)

El SRI creó el registro de proveedores de sistemas de facturación electrónica
(R.O. 335-5S, 28-jul-2026). Su **Art. 5** obliga al emisor a incluir el RUC de
su proveedor en la información adicional del comprobante; la Transitoria
Tercera da 60 días calendario (26-sep-2026).

### Decisiones tomadas (2026-09-17)

- **Lo inyecta `fe`, no el POS ni el partner.** `fe` construye y firma: si el
  campo lo pone el servicio, todos sus integradores cumplen sin hacer nada, y
  un cliente con POS casero no tiene que enterarse de la resolución.
- **Formato confirmado** contra ejemplo del SRI:
  `<campoAdicional nombre="RUC Proveedor">…</campoAdicional>`, con esa
  capitalización exacta. **También debe salir en el RIDE**, como fila del
  bloque de información adicional (junto a Teléfono/Email).
- **Opcional por configuración**: sin `ruc_proveedor` resuelto no se emite el
  nodo. Mantiene los golden byte a byte, respeta al emisor con sistema propio
  (que no tiene proveedor que declarar) y permite desplegar antes de activar.
- **Quién consta**: el criterio es *quién tiene el contrato de facturación
  electrónica con el emisor*. Partner que revende bajo su marca → el del
  partner; contribuyente directo o cliente del POS → el de `fe`
  (0993205451001). Por eso `ruc_proveedor` es por partner, **sin valor por
  defecto silencioso**: se decide en el alta.
- **Dos columnas distintas en `partners`**: `ruc` (quién es el partner
  legalmente, nullable — un partner extranjero no tiene) y `ruc_proveedor`
  (lo que va en cada factura). Solo coinciden cuando el partner declara el
  suyo.
- **Validar con `Ruc::fromString()`, no con regex.** `infoAdicional` es texto
  libre para el SRI: nadie valida ese RUC en recepción, así que un dígito mal
  tecleado se autoriza en silencio en cada factura durante meses. El dígito
  verificador en el alta es la única defensa.
- **Sin autoservicio de partners.** Se mantiene la decisión de §11: un partner
  es una relación comercial que se abre a mano (`partner:crear`, ahora
  interactivo). La cláusula la acepta el partner desde el panel o la API;
  aceptarla el operador por CLI no prueba nada.
- **La puerta va en la emisión, no en el panel**: un partner puede emitir sin
  entrar nunca a la UI.

### ✅ Fase 1 — en producción (2026-09-18)

`infoAdicional` completo: `CampoAdicionalData` + soporte en la base
`ComprobanteData` (los seis tipos lo heredan), etapa `AgregarRucProveedor`
en el pipeline, extracción en `ComprobanteXmlParser` y bloque nuevo en
`ride/base.blade.php`. Activado con `SRI_RUC_PROVEEDOR=0993205451001`.

Verificado de punta a punta contra el ambiente de pruebas del SRI: factura
emitida y autorizada con el campo, visible en el RIDE y en el XML
autorizado que se entrega al comprador.

- **Respuesta a la pregunta que quedaba abierta**: se emitió una segunda
  factura con `SRI_RUC_PROVEEDOR` vacío y **el SRI la autorizó igual**. El
  campo NO se valida en recepción; es un requisito formal que se audita
  después. El 26-sep no era un acantilado, y el interruptor de
  configuración es una válvula de seguridad real.
- **Trampa encontrada**: `json_encode()` sobre SimpleXML descarta los
  atributos de un elemento que además tiene texto, así que
  `<campoAdicional nombre="X">v</campoAdicional>` llegaba al parser como
  `"v"` y el nombre se perdía. Como el RIDE se genera parseando el XML
  almacenado, habría salido con filas sin etiqueta. Se extrae a mano.
- `phpunit.xml` fija `SRI_RUC_PROVEEDOR` vacío: el test que reproduce el
  golden byte a byte leía la variable del entorno del desarrollador y
  pasaba solo mientras estuviera sin definir.
- De paso, al medir el peso de los adjuntos: `barryvdh/laravel-dompdf`
  apaga el subsetting de fuentes que dompdf trae activado, y cada RIDE
  incrustaba DejaVu Sans entera (863 KB → 29 KB al activarlo).

### ⏳ Fases 2-5 — pendientes (≈4 días)

**No las dispara una fecha, las dispara un cliente**: hoy, con el RUC
global, todos los comprobantes cumplen. Solo hacen falta cuando entre un
partner que declare **su propio** RUC en vez del nuestro.

- **Fase 2 · Datos del partner y puerta** (1,5 d) — columnas `ruc` y
  `ruc_proveedor` en `partners`; `partner:crear` interactivo que pregunta
  el RUC propio antes para que la elección del declarado sea una selección
  entre dos valores reales; validación con `Ruc::fromString()`; puerta en
  la emisión (409 sin RUC o sin cláusula aceptada).
- **Fase 3 · Declaración y cláusula** (1-1,5 d) — tabla
  `partner_declaraciones` append-only con el RUC declarado, versión y hash
  del texto aceptado, quién/IP/user-agent y `origen` (panel·api·cli);
  cláusula versionada en el repo obligando al partner a registrarse ante
  el SRI con su CIIU.
- **Fase 4 · Panel del partner** (1 d) — declarar, leer y aceptar; aviso
  persistente mientras esté pendiente; cambiar el RUC exige volver a
  aceptar y abre fila nueva.
- **Fase 5 · API** (0,5 d) — `GET`/`PUT /partner/v1/declaracion` con
  `acepta: true` obligatorio, para el partner que no quiere tocar la UI.

### ⏳ Pendiente: `partner:rectificar-declaracion`

Comando para corregir el `ruc_proveedor` de un partner que no responde o no
tiene acceso al panel. **No se implementa aún**: hoy hay un solo partner y es
propio, y el backfill se hace mejor desde el panel (queda con `origen: panel`,
que es el registro bueno).

Se retoma en cuanto aparezca el primer caso real. Su razón de ser NO es el
backfill —eso lo cubren el panel (§4) y la API (§5)— sino ser **el único
camino fuera de la UI que sigue escribiendo la fila de declaración**: sin él,
alguien corregirá el RUC con `tinker` escribiendo directo en la columna y
romperá el rastro de la tabla cuya única razón de existir es el rastro.

Debe registrar `origen: cli` y un `--motivo`, y avisar en pantalla de que una
rectificación por CLI no sustituye la aceptación del partner.

## 14. Anexos 21–26 de la ficha técnica 2.34 (leyendas y campos obligatorios)

La ficha 2.34 (jul-2026) trae dos anexos nuevos (25 §2 placa, 26 RUC
proveedor); los anexos 21–24 existían desde 2020–2024 pero `fe` no cubría
ninguno, y **la API descartaba en silencio** cualquiera de esos campos si un
cliente los enviaba (laravel-data ignora claves desconocidas).

Regla que ordena todo el bloque: **lo que es del emisor lo configura una vez
y lo inyecta `fe`; lo que es de la transacción lo manda el cliente en el
payload.** El cliente nunca duplica leyendas del emisor en el JSON (422).

| Anexo | Requisito | Dónde va | Estado |
|---|---|---|---|
| 21 | `<agenteRetencion>` nº resolución (≤8 dígitos, sin ceros a la izq.) + "Contribuyente Especial" en RIDE | `infoTributaria` tras `dirMatriz`; `contribuyenteEspecial` en el bloque info* | ✅ 2026-09-20 |
| 22 | `<contribuyenteRimpe>` leyenda literal RIMPE / Negocio Popular | `infoTributaria` tras `agenteRetencion` | ✅ 2026-09-20 |
| 23 | `<codigoAuxiliar>` Tabla 31 (materiales de construcción) | `detalle` tras `codigoPrincipal` | ✅ 2026-09-20 (cierra también 25 §1) |
| 24 | `campoAdicional nombre="Gran Contribuyente"` | `infoAdicional` (factura, liquidación, NC, ND) | ✅ 2026-09-22 |
| 25 | §1 `codigoAuxiliar` H492001/H492002 · §2 `<placa>` Tabla 33 | `detalle` · `infoFactura` tras `moneda` | ✅ 2026-09-22 |
| 26 | `campoAdicional nombre="RUC Proveedor"` | `infoAdicional` | ✅ §13 |
| — | Guardia: 422 ante claves desconocidas en todo el payload | DTOs (`prepareForPipeline`) | ✅ 2026-09-22 |

Cada anexo cierra con: código + tests en `fe`, `docs/openapi.yaml`, y un
bloque "Recomendaciones para `../pos`" (Consumo de API · UI) escrito con el
contrato ya definitivo, para que la sesión que trabaje en el POS lo ejecute
sin releer la ficha.

### ✅ Registro §14 — Anexo 21: Agente de retención (+ contribuyente especial) (2026-09-20)

Infraestructura compartida por los anexos 21, 22 y 24, ya montada:

- **`LeyendasEmisor`** (value object): normaliza y valida las designaciones
  (`agenteRetencion` numérico ≤8 dígitos, ceros a la izquierda fuera;
  `contribuyenteEspecial` alfanumérico 3–13). Cadena vacía = no designado.
- **`contribuyentes`**: columnas `agente_retencion_resolucion` y
  `contribuyente_especial_resolucion`; `Contribuyente::leyendasEmisor()`.
- **`EmisionEnCurso`** lleva `leyendas` (por defecto ninguna, así los tests
  de pipeline existentes no cambian); ambos flujos (síncrono y job) las
  pasan desde el contribuyente.
- **Etapa `AgregarLeyendasEmisor`** en el pipeline, antes de
  `AgregarRucProveedor`, con `rechazarSiVieneEnElPayload()` enganchada en
  `EmitirComprobanteRequest`: mismo criterio que el RUC del proveedor.
- **DTOs**: `InfoTributariaData::agenteRetencion` (último tag, tras
  `dirMatriz`). `contribuyenteEspecial` vive en la nueva base abstracta
  `BloqueInfoData` de los seis bloques info*, y cada uno lo emite donde lo
  pone su formato: justo antes de `obligadoContabilidad` en cinco tipos y
  justo después en la guía de remisión. `ComprobanteData::bloqueInfo()`
  da acceso genérico (lo usa la etapa y el RIDE).
- **RIDE** (`base.blade.php`, caja del emisor): "Contribuyente Especial
  Nro." y "Agente de Retención Resolución No.", como el ejemplo 2 del anexo.
- **Entrada**: Form Requests nuevos (`ActualizarConfiguracionRequest`,
  `AprovisionarContribuyenteRequest`, `ActualizarContribuyenteRequest`)
  con el trait `ValidaLeyendasEmisor`; de paso los tres controladores
  dejan la validación inline. Panel: bloque "Designaciones del SRI" en
  `Configuracion.vue`. Resource de partner expone
  `agenteRetencionResolucion` / `contribuyenteEspecialResolucion`.
- **Tests**: `LeyendasEmisorTest` (orden de tags en los seis tipos,
  XML idéntico sin designaciones, rechazo en payload, roundtrip parser,
  RIDE, normalización); endpoint (leyendas en el XML emitido + 422 si
  vienen en el payload); partner (configurar, borrar con null, formatos
  inválidos, aprovisionar); panel (guardar, mostrar, rechazar).
- **Hallazgo**: el Anexo 21 sigue diciendo "entre `<regimenMicroempresas>`
  y `</infoTributaria>`"; esa etiqueta se derogó con el RIMPE (v2.21). El
  orden real es `dirMatriz → agenteRetencion → contribuyenteRimpe`
  (Anexo 22 lo confirma: "entre `<agenteRetencion>` y `</infoTributaria>`").
- Pendiente operativo: `php artisan migrate` en cada entorno.

#### Recomendaciones para `../pos` — Anexo 21

**Consumo de API**

- No tocar `FacturaMapper` ni `NotaCreditoMapper`: la leyenda la inyecta
  `fe`. Si el POS manda `infoTributaria.agenteRetencion` o
  `infoFactura.contribuyenteEspecial`, recibe **422** con
  `errors.comprobante` = "El campo «agenteRetencion» lo fija la
  configuración del contribuyente…".
- Añadir a `FacturacionClient` un `actualizarContribuyente(uuid, datos)`
  → `PATCH /api/partner/v1/contribuyentes/{uuid}` con
  `agente_retencion_resolucion` y `contribuyente_especial_resolucion`
  (string o `null`; `null` borra). El aprovisionamiento (`POST
  …/contribuyentes`) acepta los mismos campos. La respuesta los devuelve
  como `data.agenteRetencionResolucion` / `data.contribuyenteEspecialResolucion`,
  ya normalizados (sin ceros a la izquierda).
- Reenviarlos en cada guardado de `fe_ajustes` (idempotente); los ceros
  a la izquierda los quita `fe`, el POS no necesita normalizar.
- Errores 422 posibles sobre esos campos: `agente_retencion_resolucion`
  (letras, >8 dígitos, solo ceros) y `contribuyente_especial_resolucion`
  (<3 o >13 caracteres, símbolos). Mostrarlos junto al campo.
- Test con `Http::fake`: (a) el payload de emisión **no** contiene
  `agenteRetencion` ni `contribuyenteEspecial`; (b) guardar ajustes con
  designaciones dispara el PATCH con las claves snake_case.

**UI**

- `fe_ajustes`: columnas `agente_retencion_resolucion` (string 8,
  nullable) y `contribuyente_especial_resolucion` (string 13, nullable);
  `FeGuardarAjustesRequest`: `['nullable','string','max:8']` /
  `['nullable','string','max:13']` (el formato fino lo valida `fe`).
- `facturacion_electronica/ajustes.blade.php`: nueva sección
  **"Designaciones del SRI"** con un input opcional por designación
  —"Agente de retención · resolución No." y "Contribuyente especial ·
  resolución No."; son designaciones independientes, cada una con su
  propio número de resolución, y el negocio puede tener una, ambas o
  ninguna— y texto de ayuda:
  "Solo si el SRI le ha designado. El número de resolución se imprime
  como leyenda en cada comprobante; déjelo vacío si no aplica."
  Placeholders `6498` / `5368`; `inputmode="numeric"` en el primero.
- Bajo la sección, un resumen de solo lectura "Leyendas que saldrán en
  sus comprobantes" leído de la respuesta del PATCH/GET de `fe`, para que
  el negocio vea lo que va a imprimir el SRI antes de emitir.
- Si el POS imprime tickets propios además del RIDE, añadir en la
  plantilla de impresión "Agente de Retención Resolución No. X" y
  "Contribuyente Especial Nro. X" leyendo de `fe_ajustes` (la norma habla
  del comprobante, no solo del XML).

### ✅ Registro §14 — Anexo 22: RIMPE (2026-09-20)

Montado sobre la infraestructura del Anexo 21, sin piezas nuevas de
arquitectura:

- **`RegimenRimpe`** (enum): valor = clave de API/BD (`rimpe`,
  `negocio_popular`); `leyenda()` devuelve el texto literal de la ficha
  ("CONTRIBUYENTE RÉGIMEN RIMPE", 27 caracteres; "CONTRIBUYENTE NEGOCIO
  POPULAR - RÉGIMEN RIMPE", 45). Los tests fijan la longitud exacta para
  que nadie "corrija" un espacio o una tilde.
- Columna `regimen_rimpe` (cast al enum, null = régimen general);
  `LeyendasEmisor::regimenRimpe` + `leyendaRimpe()`;
  `InfoTributariaData::contribuyenteRimpe` cierra el bloque tras
  `agenteRetencion` (o tras `dirMatriz` si el emisor no es agente de
  retención); rechazo en payload; RIDE: leyenda en negrita al pie de la
  caja del emisor (ejemplos 3 y 5 del anexo).
- Panel: select "Régimen RIMPE" con la leyenda resultante bajo el campo;
  el select manda `''` para "no aplica" y `fe` lo guarda como null.
- API de partner: `regimen_rimpe` en aprovisionar/actualizar y
  `regimenRimpe` en el resource; OpenAPI actualizado.

#### Recomendaciones para `../pos` — Anexo 22

**Consumo de API**

- Mismo `PATCH /api/partner/v1/contribuyentes/{uuid}` del Anexo 21, con
  un campo más: `regimen_rimpe` = `"rimpe"` | `"negocio_popular"` |
  `null`. La respuesta lo devuelve como `data.regimenRimpe`.
- 422 con `errors.regimen_rimpe` si llega otro valor (p. ej. `"rise"`,
  régimen ya derogado). No enviar la leyenda, solo la clave.
- Test negativo con `Http::fake`: el payload de emisión no contiene
  `infoTributaria.contribuyenteRimpe`; si el POS lo manda, 422 con
  `errors.comprobante` = "El campo «contribuyenteRimpe» lo fija la
  configuración del contribuyente…".

**UI**

- `fe_ajustes.regimen_rimpe` (string 20, nullable) y en
  `FeGuardarAjustesRequest`: `['nullable', Rule::in(['rimpe',
  'negocio_popular'])]`.
- En la sección "Designaciones del SRI" de `ajustes.blade.php`, un
  select "Régimen RIMPE" con tres opciones: "No aplica (régimen general)"
  (valor vacío → enviar `null`), "RIMPE (emprendedor)", "RIMPE negocio
  popular". Bajo el select, mostrar la leyenda que se imprimirá según la
  opción elegida, para que el negocio la reconozca del RUC que le entregó
  el SRI.
- Ticket propio del POS (si lo hay): imprimir la leyenda literal al pie
  de los datos del emisor, en mayúsculas y sin abreviar —el texto es
  requisito, no decoración—; leerla de `fe_ajustes` con el mismo mapa
  clave → leyenda que usa `fe`.

### ✅ Registro §14 — Anexo 23: código auxiliar (materiales de construcción) (2026-09-20)

Primer anexo de la otra familia: **dato de la transacción**, lo manda el
cliente en el payload y `fe` lo emite tal cual. Cierra de paso el
Anexo 25 §1 (transporte comercial), que usa el mismo campo con otros
códigos.

- `DetalleData` gana el segundo par de códigos: `codigoAuxiliar`
  (factura, liquidación) y `codigoAdicional` (nota de crédito), emitidos
  justo tras `codigoPrincipal`/`codigoInterno`, antes de `descripcion`.
  Cada tipo emite solo su par; sin valor no hay tag.
- **Hallazgo**: el Anexo 23 dice "comprobantes de venta y documentos
  complementarios … en el campo `<codigoAuxiliar>`", pero el formato
  XML de la nota de crédito no tiene ese tag: su segundo código se
  llama `<codigoAdicional>`. Se documenta el mapeo en OpenAPI.
- El contenido **no se valida** en `fe`: la ficha da la tabla de
  códigos, pero el campo es alfanumérico libre de 25 en el XSD y el SRI
  lo audita después. Validarlo aquí obligaría a redesplegar `fe` cada
  vez que el SRI amplíe la tabla.
- Builders de prueba: `payload_factura(codigoAuxiliar:)`,
  `payload_nota_credito(codigoAdicional:)`,
  `payload_liquidacion(codigoAuxiliar:)`. Tests: `CodigoAuxiliarTest`
  (posición del tag por tipo, ausencia sin valor, roundtrip) y endpoint.
- OpenAPI: Tablas 31 y 32 transcritas como referencia en la descripción
  del payload.

#### Recomendaciones para `../pos` — Anexo 23 (y 25 §1)

**Consumo de API**

- `ComprobanteMapper::detalle()` añade al payload del ítem
  `codigoAuxiliar` (factura) o `codigoAdicional` (nota de crédito, vía
  `NotaCreditoMapper`) **solo cuando el producto tiene código**; nunca
  cadena vacía. El nombre del campo sigue al de `$campoCodigo`:
  `codigoPrincipal` → `codigoAuxiliar`, `codigoInterno` → `codigoAdicional`.
- Enviar el código tal cual, en mayúsculas y sin espacios (`F010101`,
  `H492001`); `fe` no lo normaliza ni lo valida.
- Tests del mapper con `Http::fake`: producto con código → el detalle
  lleva el campo con el nombre correcto según el tipo; producto sin
  código → el detalle no lleva la clave.

**UI**

- Columna `fe_codigo_auxiliar` (string 25, nullable) en `products`, en
  vez de reutilizar `product_custom_field1..4` (ya los usan los negocios
  para otras cosas y no tienen semántica).
- En `product/create.blade.php` y `edit.blade.php`, dentro del bloque
  de datos fiscales, un select "Código SRI de actividad regulada"
  agrupado por `<optgroup>`: "Materiales de construcción (Tabla 31)" con
  los 18 códigos F01xxxx y su descripción; "Transporte comercial
  (Tabla 32)" con H492001 / H492002; y una opción "Otro…" que descubre un
  input libre de hasta 25 caracteres. Vacío = no aplica.
- Para ferreterías con catálogos grandes, asignación **por categoría**
  con herencia al producto (`categories.fe_codigo_auxiliar`, el producto
  hereda si el suyo está vacío) y la posibilidad de sobreescribir por
  producto.
- Importador de productos: columna opcional `fe_codigo_auxiliar`.
- En el listado de productos, filtro "con código SRI" para revisar
  qué parte del catálogo está clasificada.

### ✅ Registro §14 — Catálogo de códigos auxiliares (2026-09-22)

Respuesta a "¿validamos los códigos de la Tabla 31 como los únicos
permitidos?". **No**, y la razón vale para cualquier tabla futura:

- `codigoAuxiliar` **no es un campo de estos anexos**: es el código
  auxiliar de uso general del emisor (barras, SKU). Los ejemplos de la
  ficha en todos los formatos XML son `1234D56789-A`, `SER003`, `001`,
  `0011`. Los Anexos 23 y 25 §1 lo sobrecargan, no lo reservan. Cerrarlo
  rompería a todo cliente que lo use para su código de barras.
- Y más de fondo: **`fe` no puede saber cuándo la regla aplica**. La
  obligación es por ítem ("cada ítem que corresponda a la actividad"), no
  por emisor —una ferretería vende cemento y herramientas—, así que una
  validación no distingue "faltó el código" de "no aplica", y no detecta
  el caso que importa (ítem regulado sin código).

En su lugar, el catálogo como **dato**: `GET /api/v1/catalogos` y
`GET /api/v1/catalogos/codigos-auxiliares`.

- `App\Sri\Catalogos\CodigosAuxiliares`: fuente única de las Tablas 31
  (18 códigos) y 32 (2), con anexo, tabla, base legal, fecha de
  obligatoriedad y **`tagXml` por tipo de comprobante** — el dato que
  evita la trampa del Anexo 23 (la NC llama `codigoAdicional` a lo que la
  factura llama `codigoAuxiliar`).
- **Público y cacheable**: son datos de una norma publicada, sin nada del
  contribuyente; el POS puede cargarlo antes de tener credenciales. `ETag`
  + `max-age=86400`; revalidar con `If-None-Match` devuelve 304.
- OpenAPI deja de transcribir la tabla a mano (duplicación que ya podía
  divergir) y remite al endpoint.
- **Hallazgo**: la ficha no cita resolución para los códigos de transporte
  (§1); la `NAC-DGERCGC26-00000024` respalda solo el §2 (placa). El
  catálogo devuelve `baseLegal: null` ahí en vez de inventarla.
- De paso: `DocsTest` ahora **parsea** el YAML del spec. Se editaba a mano
  y ningún test detectaba que dejara de ser válido —se rompió al añadir
  este endpoint (coma sin comillas en un mapa inline) y la suite seguía en
  verde—; el visor de docs habría quedado en blanco en producción.

#### Recomendaciones para `../pos` — catálogo

**Consumo de API**

- `FacturacionClient::catalogoCodigosAuxiliares()` → `GET
  /api/v1/catalogos/codigos-auxiliares`, **sin token** (no pasar el
  Bearer: el endpoint es público y así funciona en el alta, antes de
  configurar credenciales).
- Cachear con `Cache::remember(..., now()->addDay())` guardando también el
  `ETag`; revalidar con `If-None-Match` y tratar `304` como "sigue
  válido". Si la petición falla, **servir la copia cacheada**: el
  formulario de producto no debe depender de que `fe` esté disponible.
- Sembrar la caché con una copia local del JSON para el primer arranque
  sin red.
- No hardcodear los 20 códigos en el POS: el objetivo del endpoint es que
  ampliar la tabla no obligue a desplegar cada integrador.

**UI**

- El select "Código SRI de actividad regulada" del formulario de producto
  se construye desde el catálogo: un `<optgroup>` por `grupo.nombre`, las
  opciones con `codigo — descripcion`, más "Otro…" con input libre (el
  campo admite cualquier código auxiliar propio).
- Mostrar `obligatorioDesde` como nota bajo el grupo cuando exista
  ("obligatorio desde el 01/11/2025") y `baseLegal` como referencia
  cuando la ficha la cite.
- En la pantalla de ajustes de FE, un enlace "Ver catálogo del SRI" que
  abra la lista vigente, para que el negocio contraste con lo que le pide
  su contador.

### ✅ Registro §14 — Anexo 24: Gran Contribuyente (2026-09-22)

Último de la familia "dato del emisor": misma etapa
`AgregarLeyendasEmisor`, pero el destino es `infoAdicional`, no un tag
propio.

- **Formato resuelto con el Ejemplo 1 del anexo** (pág. 132): la
  especificación dice que el atributo `nombre` lleva «la leyenda "Gran
  Contribuyente" y el número de resolución», lo que se leía como que
  ambos van en el atributo. El ejemplo lo aclara:
  `<campoAdicional nombre="Gran Contribuyente">NAC-GCFOIOC21-00000868-E</campoAdicional>`
  — nombre = leyenda, contenido = resolución. Idéntico a "RUC Proveedor".
- **Alcance por tipo**: el anexo lo exige en «comprobantes de venta, notas
  de crédito y notas de débito». Se emite en factura, **liquidación de
  compra** (es comprobante de venta, Reglamento de Comprobantes de Venta
  art. 1), nota de crédito y nota de débito; **no** en retención ni guía
  de remisión. Corrige el boceto inicial de §14, que decía "solo factura,
  NC y ND".
- Columna `gran_contribuyente_resolucion` (300, el tope del campo
  adicional); formato alfanumérico con guiones (el ejemplo los lleva).
- **Refactor de paso**: el "añadir campo adicional si no está, con el tope
  de 15 del esquema" vivía dentro de `AgregarRucProveedor`. Se subió a
  `ComprobanteData::agregarCampoAdicional()` / `tieneCampoAdicional()`,
  que ahora usan las dos etapas; el segundo caso lo habría duplicado.
- Orden resultante en `infoAdicional`: los campos del emisor, luego
  "Gran Contribuyente", luego "RUC Proveedor" (hay test).
- RIDE: sale solo, por el bloque de información adicional que ya existía.

#### Recomendaciones para `../pos` — Anexo 24

**Consumo de API**

- Un campo más en el mismo `PATCH /api/partner/v1/contribuyentes/{uuid}`:
  `gran_contribuyente_resolucion` (string ≤300 o `null`). Respuesta:
  `data.granContribuyenteResolucion`.
- 422 con `errors.gran_contribuyente_resolucion` si trae símbolos
  distintos de guion (p. ej. `NAC/2021`).
- **Cuidado con el presupuesto de campos adicionales**: el esquema admite
  15 por comprobante y `fe` ocupa hasta 2 (`Gran Contribuyente` y
  `RUC Proveedor`). Si el POS añade Teléfono/Email/Dirección del cliente,
  cuente con **13** como máximo; pasarse devuelve 422 con el mensaje "No
  cabe el campo «…»".
- El POS **no** debe enviar un `campoAdicional` llamado `Gran
  Contribuyente`: se rechaza con 422, igual que `RUC Proveedor`.

**UI**

- `fe_ajustes.gran_contribuyente_resolucion` (string 300, nullable), en la
  misma sección "Designaciones del SRI", con placeholder
  `NAC-GCFOIOC21-00000868-E` y la nota de que aparece en la información
  adicional de facturas, liquidaciones y notas de crédito y débito.
- No hace falta tocar la plantilla del ticket: a diferencia de las
  leyendas de los Anexos 21 y 22, esta viaja en información adicional y
  el RIDE la imprime sola.

### ✅ Registro §14 — Anexo 25 §2: placa del vehículo (2026-09-22)

Segundo de los dos requisitos realmente nuevos de la ficha 2.34 (el otro
es el 26, ya en producción). **Dato de la transacción**, como el Anexo 23:
viaja en el payload.

- `Placa` (value object, como `Ruc`/`Secuencial`): normaliza a mayúsculas,
  quita espacios y guiones y **rellena con el cero** de la Tabla 33 cuando
  la placa trae tres dígitos (`abc-123` → `ABC0123`). Esa regla la aplica
  el servicio: es formato del SRI, no algo que el integrador deba recordar.
- `InfoFacturaData::placa` (opcional, con `ValueObjectCast`), emitida tras
  `moneda`. La ficha la ubica «entre los tags moneda y formas de pago»;
  nuestro formato de factura no emite `pagos`, así que cierra el bloque.
- Validación estricta `^[A-Z]{3}[0-9]{4}$` tras normalizar, con 422 y un
  mensaje que cita el formato. **Decisión revisable**: la Tabla 33 solo
  contempla tres letras + cuatro dígitos, pero si aparece un operador real
  con una placa fuera de ese patrón (vehículos especiales, diplomáticos)
  el estricto le impide emitir; se relaja en cuanto haya un caso.
- Solo en factura: el anexo habla de las facturas que la operadora emite a
  sus clientes. La `placa` de la guía de remisión es otro campo, con otro
  formato (texto libre ≤20), y no se toca — hay test que lo fija.

#### Recomendaciones para `../pos` — Anexo 25 §2

**Consumo de API**

- `FacturaMapper` incluye `infoFactura.placa` cuando la venta la tiene.
  **No hace falta normalizar en el POS**: `fe` pasa a mayúsculas, quita
  separadores y añade el cero de relleno. Enviarla tal como la teclearon.
- 422 con `errors.comprobante` si no encaja en la Tabla 33; el mensaje
  cita el formato esperado, se puede mostrar tal cual al cajero.
- Si el negocio tiene rol *operadora* y la venta no trae placa, cortar
  antes con `VentaNoFacturable` ("falta la placa del vehículo") en vez de dejar que `fe` responda 422: el mensaje llega al
  cajero en el momento de la venta y no en el job.

**UI**

- `fe_ajustes.rol_transporte`: **tres estados**, no un booleano —
  *no aplica* · *operadora* · *socio o accionista*. La Tabla 32 distingue
  los dos roles y no tienen las mismas obligaciones: la operadora factura
  a su cliente con `H492001` **y placa**; el socio o accionista factura a
  su operadora con `H492002` **sin placa** (la ficha exige la placa solo
  en «las facturas emitidas por parte de las operadoras … a los
  clientes»). Con un booleano, al socio se le pediría una placa que no
  necesita o no vería su código.
- El rol elegido preselecciona el código auxiliar correspondiente en los
  productos de servicio de transporte (enlaza con el Anexo 23).
- Solo en el rol *operadora*, campo "Placa del vehículo" en la pantalla de venta
  (columna `fe_placa` en `transactions`), con `text-transform: uppercase`,
  `maxlength` 8 y ayuda "ABC1234". Autocompletar con las últimas placas
  usadas por ese cliente: en una operadora, el mismo vehículo se repite.
- Si el POS gestiona una flota, mejor un selector de vehículos
  (`fe_vehiculos`: placa + alias) que un campo libre: evita erratas que
  luego obligan a anular la factura.
- Mostrar la placa en el ticket y en el detalle de la venta, para que el
  cajero verifique antes de emitir. **Es una elección, no un requisito**:
  ver «La placa y el RIDE» más abajo.

#### La placa y el RIDE — la ficha NO la exige impresa (revisado 2026-09-23)

Pregunta que surgió al implementar el ticket de §16: ¿hay que imprimir la
placa en la representación impresa? **No.**

- **Anexo 25 §2.1 habla solo del XML**: «Para ello se deberá incluir el tag
  placa en la estructura del XML, entre los tags moneda y formas de pago».
  Ni una palabra sobre el RIDE.
- **Argumento interno de la propia ficha, que es el decisivo.** Los anexos
  que quieren el dato impreso traen su propio ejemplo de formato RIDE:

  | Anexo | Requisito | ¿Ejemplo de formato RIDE? |
  |---|---|---|
  | 21 | Agente de retención | ✅ Ejemplo 2 |
  | 22 | RIMPE | ✅ Ejemplos 3 y 5 |
  | 23 | Código auxiliar (construcción) | ❌ |
  | 24 | Gran contribuyente | ✅ Ejemplo 2 |
  | **25** | **Código auxiliar + placa** | ❌ |
  | 26 | RUC del proveedor | ✅ Ejemplo 2 |

  El SRI dibujó dónde va cada dato en el RIDE exactamente para los cuatro
  que quiere impresos, y no lo hizo para los dos que son datos de la
  transacción. No es un olvido: es el patrón del documento.
- **§9.19 lo permite igualmente**: «Se podrán imprimir datos adicionales en
  el RIDE conforme lo requiera el contribuyente».

Las coberturas de la resolución NAC-DGERCGC26-00000024 (origen del
requisito, nota al pie 17 de la ficha) dicen todas «el campo *placa* de la
factura electrónica» y ninguna menciona la representación impresa.
*Límite de la revisión:* no se pudo leer el articulado literal de la
resolución —el PDF del SRI es un escaneo sin texto extraíble— así que esto
se apoya en la ficha más fuentes secundarias coincidentes.

**Decisión (2026-09-23): se imprime en los dos documentos.** En el ticket
del POS y en el RIDE PDF de `fe` (`ride/factura.blade.php`, cuarta celda
del bloque del comprador, solo si la factura la lleva). El ticket y el PDF
del mismo comprobante no pueden decir cosas distintas, que es justo el
principio sobre el que se construyó §16.

Cuesta una línea, solo en facturas de operadoras, y le identifica el
servicio al pasajero. Si algún día estorba en las 40 columnas del ticket,
se puede quitar de ambos sin ningún riesgo de cumplimiento — pero de
ambos, no de uno.

Tests en `PlacaTest`: aparece en el RIDE cuando la factura la lleva, y no
se dibuja la celda cuando no.

**Dos confirmaciones que trajo la misma revisión** (notas del Anexo 2),
las dos validan decisiones ya tomadas en §16:

- «El número de la clave de acceso corresponde al número de autorización»
  → imprimir la clave como número de autorización en el esquema offline.
- «Los RIDE que se descarguen del portal web del SRI contendrán hora y
  fecha de autorización, dicha información **no es obligatoria** registrarla
  en el RIDE generado por los emisores» → la fecha de autorización es
  opcional en nuestro ticket, que es como la tratamos.

### ✅ Registro §14 — Guardia contra el descarte silencioso (2026-09-22)

Cierra la §14 atacando el problema de fondo que destapó la revisión: **la
API descartaba en silencio cualquier clave que el DTO no declarase**.
laravel-data las ignora, así que un `codigoAusiliar` mal tecleado —o un
campo del SRI aún no soportado— desaparecía sin aviso y el comprobante se
autorizaba incompleto; el integrador creía cumplir y el fallo salía en una
auditoría meses después. Así llevábamos años con los Anexos 21-25.

- Trait `RechazaClavesDesconocidas` en `app/Sri/Data/Concerns/`: compara
  las claves del payload contra las propiedades públicas del DTO (por
  reflexión, así que no hay lista que mantener) y lanza `DatoInvalido` con
  las desconocidas **y las admitidas**, para distinguir errata de campo no
  soportado. El nombre del bloque se deriva de la clase (`InfoFacturaData`
  → «infoFactura»).
- **Enganche**: al final de `prepareForPipeline` de cada DTO, cuando los
  wrappers `{detalles: {detalle: X}}` ya están normalizados. La recursión
  sale gratis —laravel-data invoca el de cada DTO anidado—, así que cubre
  todo el árbol (raíz, info*, detalle, impuesto, totalImpuesto, pago,
  motivo, destinatario, campoAdicional) sin duplicar el conocimiento de
  los wrappers en un walker aparte.
- **Excepción explícita**: `infoTributaria.codDoc` se acepta y se descarta
  (`clavesIgnoradas()`), porque viene en el formato del SRI, muchos
  integradores lo envían y la ficha manda derivarlo del tipo. Sin esta
  excepción la guardia habría roto a todo el que lo manda.
- Verificado que no rompe lo existente: el payload real del POS encaja
  exactamente con los DTOs, y hay test de que el XML que genera el sistema
  se relee con la guardia activa (el RIDE se produce releyendo el XML
  almacenado; un falso positivo habría roto la descarga de RIDE de
  comprobantes ya emitidos).
- **Cambio de contrato**: documentado en `docs/openapi.yaml` con su fecha.

#### Recomendaciones para `../pos` — guardia

**Consumo de API**

- Un 422 con «no reconoce la clave» es **un error del integrador, no del
  usuario**: registrarlo con nivel `error` y tratarlo como definitivo (no
  reintentar), igual que el resto de 4xx en `FacturacionException`.
- El mensaje lista las claves admitidas del bloque: pegarlo tal cual en el
  log ahorra abrir la documentación.
- **Test de contrato en el POS**: comparar el conjunto de claves que emite
  cada mapper contra el esquema de `docs/openapi.yaml` (descargable en
  `/docs/openapi.yaml`), para que un campo mal escrito falle en el CI del
  POS y no en producción.
- Al añadir campos nuevos al payload (p. ej. `placa`, `codigoAuxiliar`),
  ya no hace falta desplegar a ciegas: si `fe` no lo soporta todavía, lo
  dice.

**UI**

- Nada que cambiar. El error es de integración: debe verse en los logs y
  en el detalle del comprobante fallido, no en la pantalla de venta.

## 15. Implementación en el POS de los Anexos 21–26

Las recomendaciones de §14 llevadas a `../pos`. Cuatro fases (decidido
2026-09-22: se hacen las cuatro aunque todavía no haya un negocio de
construcción ni de transporte). Los Anexos 21, 22 y 24 van juntos: en el
POS son cuatro campos del mismo formulario con el mismo espejado, y
separarlos obligaría a tocar los mismos cinco archivos tres veces.

El POS no tiene Pint ni PHPStan: la puerta de calidad es su suite PHPUnit
(estilo clásico `test_...`, no Pest).

### ✅ Fase A — Designaciones del emisor (Anexos 21, 22 y 24) (2026-09-22)

- Migración `fe_ajustes`: `agente_retencion_resolucion`,
  `contribuyente_especial_resolucion`, `regimen_rimpe`,
  `gran_contribuyente_resolucion`.
- `FeGuardarAjustesRequest`: reglas de forma (longitud, `Rule::in` del
  régimen desde la constante `REGIMENES_RIMPE`, que también alimenta el
  select). **El formato fino no se duplica**: lo valida `fe`, que es quien
  construye el XML, y devuelve 422 por campo.
- `FacturacionElectronicaController`: constante `CAMPOS_DESIGNACION` +
  `designacionesSri()` (vacío → null) y `camposEspejados()`. Este último
  sustituye los `if` campo a campo que ya había para razón social y
  dirección: con nueve campos eran nueve condicionales calcados.
- Las designaciones viajan también en el **aprovisionamiento**, no solo en
  la actualización: un negocio que las configura al activar la FE no
  necesita un segundo guardado para que lleguen.
- Vista: sección "Designaciones del SRI" en el formulario del
  contribuyente, con los tres inputs y el select de régimen.
- Tests (`FeDesignacionesSriTest`, 10): guardar, vaciar a null, régimen
  desconocido, longitud, espejado al cambiar, espejado del borrado como
  null, **no espejar si nada cambió**, envío al aprovisionar, el payload de
  emisión sin leyendas, y el formulario mostrándolas.

**Decisión: no se tocan las plantillas de recibo.** La recomendación de
§14 (Anexos 21 y 22) decía imprimir las leyendas también en el ticket
propio del POS si existía. Al mirarlo: son 10+ plantillas genéricas de
UltimatePOS (classic, slim, elegant, detailed…), compartidas con
instalaciones de cualquier país y sin ninguna noción de FE. El comprobante
fiscal es el RIDE que genera `fe`, y ahí las leyendas ya salen. Modificar
diez plantillas upstream para duplicar un requisito que ya se cumple es
mal negocio; se revisa si un negocio entrega el ticket del POS como si
fuera el comprobante.

### ✅ Fase B — Código del ítem (Anexos 23 y 25 §1) (2026-09-22)

- Migración `products.fe_codigo_auxiliar` (25, nullable). **Columna propia,
  no un `product_custom_field*`**: esos los usan los negocios con etiquetas
  configurables y sin semántica fija, así que apropiarse de uno rompería
  instalaciones existentes.
- `ComprobanteMapper::codigoDeActividadRegulada()`: añade el tag solo si el
  producto trae código, y **deriva el nombre del `$campoCodigo` que el
  mapper ya recibía** (`codigoPrincipal` → `codigoAuxiliar`,
  `codigoInterno` → `codigoAdicional`), sin parámetro nuevo.
- `FacturacionClient::catalogoCodigosAuxiliares()`: pide el catálogo
  **sin token** (el endpoint es público) y con `If-None-Match`.
  `CatalogoCodigosAuxiliares` lo cachea 24 h, revalida con ETag y degrada
  en dos escalones: copia cacheada → semilla local. El formulario de
  producto no puede caerse porque `fe` no responda, y como el campo admite
  texto libre, el peor caso es que falte una opción del desplegable.
- Formulario de producto (`create`/`edit`): partial
  `product.partials.fe_codigo_sri` con un `<optgroup>` por grupo del
  catálogo y opción "Otro (código propio)…" que descubre el input libre.
  Solo aparece si el negocio tiene la FE activa: para los demás es ruido.
- `ProductController`: el campo entra en los `$form_fields` de `store` y
  `update`, y `codigosSriParaFormulario()` resuelve las opciones.
- Tests (`FeCodigoAuxiliarTest`, 9): código en factura, códigos de
  transporte, producto sin código, código en blanco, **nota de crédito con
  `codigoAdicional`**, catálogo desde el servicio, caída del servicio →
  semilla, no repetir la petición mientras la copia siga vigente, y
  persistencia del campo.

### ✅ Fase C — Transporte comercial (Anexo 25) (2026-09-22)

- Migración: `fe_ajustes.rol_transporte` (20, nullable) y
  `transactions.fe_placa` (8, nullable).
- **El rol es de tres estados**, como se corrigió en §14: `FeAjuste`
  expone `ROLES_TRANSPORTE`, `exigePlaca()` (solo la operadora) y
  `codigoTransporte()` (`H492001` / `H492002`), así la Tabla 32 vive en un
  único sitio.
- `PlacaDeVenta`, al estilo de `EmisionPorVenta`: `aplicaEn()` decide si se
  pinta el campo, `guardar()` persiste en mayúsculas y vacío → null.
  **No valida el formato**: lo hace `fe` con la Tabla 33 y duplicarlo aquí
  lo dejaría desincronizado.
- La placa **sí se persiste**, a diferencia de la decisión de emitir (que
  solo viaja por el evento): el comprobante se construye después, en el
  job, y hace falta al reintentar.
- `FacturaMapper`: `validarPlaca()` corta con `VentaNoFacturable` si el
  negocio es operadora y la venta no la trae —el aviso le llega al cajero
  con la venta delante, no como comprobante fallido horas después— y
  `infoFactura.placa` viaja tal cual se tecleó.
- UI: selector de rol en los ajustes, explicando qué implica cada uno;
  campo "Placa del vehículo" en la pantalla de venta (crear y editar),
  visible solo para la operadora.
- **`createSellTransaction()` no se toca**: es upstream de UltimatePOS y
  lleva su propia lista de campos. La placa se guarda desde el controlador
  justo después, como ya hace la FE con lo suyo.
- Tests (`FePlacaTransporteTest`, 10): placa de la operadora, envío sin
  normalizar, corte sin placa, socio sin placa, negocio sin rol, códigos
  de cada rol, guardado en mayúsculas y borrado, no guardar si no es
  operadora, y el rol en los ajustes con su validación.

### ✅ Fase D — Endurecimiento del contrato (2026-09-22)

El 422 por clave desconocida ya se trataba bien (`esDefinitivo()` no
reintenta, `resolverRechazoDefinitivo()` lo guarda como `rechazada_api` con
los mensajes, que `FacturacionException::mensajes()` saca de `errors.*`).
Lo que faltaba era que se viera y que no pudiera ocurrir.

- **`Log::warning` en el rechazo definitivo** (`EmiteComprobanteElectronico`)
  con `transaction_id`, tipo, código HTTP y mensajes. El registro del
  comprobante lo mira el negocio; el log lo mira quien mantiene la
  integración, que es de quien depende arreglar una errata en un mapper.
  No se clasifica por el texto del mensaje —distinguir "dato del cliente"
  de "bug nuestro" por substring sería frágil—: se registra todo rechazo
  definitivo.
- **`ContratoConElServicioTest`**: construye el payload real de una factura
  con `FacturaMapper` y comprueba, contra los esquemas de
  `../fe/docs/openapi.yaml`, que cada bloque solo usa claves documentadas y
  que no falta ninguna obligatoria. Si el repo del servicio no está a mano,
  `markTestSkipped` (no es un defecto del POS que no esté clonado).
  **Verificado que falla de verdad**: con una clave inventada en el mapper,
  el test la señala por nombre.

#### En `fe`: el contrato documentado, atado a los DTOs

El test anterior destapó que `docs/openapi.yaml` solo describía
`infoTributaria`, y sin los campos nuevos. Desde que una clave desconocida
responde 422, ese esquema dejó de ser orientativo: es la única lista de la
que un integrador puede deducir qué enviar.

- Esquemas nuevos: `InfoTributaria`, `InfoFactura`, `InfoNotaCredito`,
  `Detalle`, `Impuesto`, `TotalImpuesto`, `CampoAdicional`, todos con
  `additionalProperties: false` —que es la guardia dicha en el lenguaje del
  esquema— y los campos del emisor marcados `readOnly` con la nota de que
  enviarlos da 422.
- `ContratoOpenApiTest` compara cada esquema con las propiedades del DTO
  **por reflexión**: si alguien añade un campo al DTO y no al YAML, el
  campo existe pero nadie sabe que existe; si lo quita del DTO y no del
  YAML, quien lo envíe recibe un 422 documentado como válido. Ahora
  cualquiera de las dos cosas rompe la suite.

### Estado de §15

Las cuatro fases cerradas. Commits en `../pos`: `3d0eeda` (A), `65bf422` y
`38a55ea` (B), `e88effd` (C) y el de esta fase; en `fe`, `092db11`.
Migraciones del POS corridas el 2026-09-22 (`fe_ajustes`,
`products`, `transactions`).

---

## 16. El ticket del POS como RIDE (TM-U220B)

§15 cerró con una decisión explícita: *no se tocan las plantillas de
recibo*, porque son 10+ plantillas genéricas de UltimatePOS y el
comprobante fiscal es el RIDE que genera `fe`. Y con una condición para
revisarla: *«se revisa si un negocio entrega el ticket del POS como si
fuera el comprobante»*.

Es el caso. El diseño `tmu220b` **no vino con UltimatePOS**: es propio, y
es el ticket que el cliente se lleva. §16 revisa aquella decisión **solo
para esa plantilla**; las de upstream siguen intactas.

**Objetivo (2026-09-22):** que el cliente reciba en el momento del cobro
un ticket cuyos datos coincidan exactamente con los que verá si consulta
el comprobante en el portal del SRI.

Referencia: dos tickets reales de emisores grandes del Ecuador
(Procafecol/Juan Valdez y Fybeca), que marcan el estándar de facto de lo
que un ticket-RIDE imprime.

### El obstáculo

El ticket se imprime al cobrar; la clave de acceso llegaba mucho después.

1. `fe` emite en dos modalidades y el POS usa la asíncrona
   (`FacturacionClient.php:67` → `POST /v1/comprobantes?async=1`). En esa
   rama, `ProcesaEmisiones` encola el job y responde 202 con
   `ComprobanteResource` — pero el registro se creó en
   `RegistroDeEmision::crear()`, que **no escribe `clave_acceso`**: solo la
   escriben `completar()` y `fallar()`, al final del pipeline. El 202 sale
   con `claveAcceso: null`.
2. En el POS, `EmitirFacturaElectronica` es `ShouldQueue` y la fila de
   `fe_comprobantes` nace *dentro* del job. Al imprimir no existe ni la
   fila.

La clave, sin embargo, **no depende de nada externo**: `GenerarClaveAcceso`
la calcula con `fechaEmision`, `codDoc`, RUC, ambiente, estab, ptoEmi,
secuencial y un código numérico aleatorio. Ni red, ni certificado, ni SRI.
Se genera dentro del pipeline por orden de las etapas, no por necesidad.

### Decisiones tomadas (2026-09-22)

**1. La clave de acceso se genera antes de encolar, no dentro del job.**
`ProcesarComprobanteJob` **ya acepta** una clave preexistente
(`ProcesarComprobanteJob.php:45`), porque es lo que necesitan los
reintentos de §5.10, y `GenerarClaveAcceso` valida que su prefijo
corresponda al comprobante. Se reutiliza esa maquinaria para el primer
envío: no se inventa ningún concepto.

*Efecto secundario que vale por sí solo:* hoy el job se despacha con
`claveAcceso: null` y tiene `$tries = 3`. Un fallo técnico **después** de
que el SRI recibió el documento haría que el reintento sortee un código
numérico nuevo → clave distinta para el mismo secuencial. Fijarla al crear
el registro lo cierra.

**2. La emisión del POS pasa a ser síncrona, con respaldo en cola.** Con
`?async=1`, `fe` responde en cuanto genera la clave: no espera al SRI. Eso
convierte la llamada en cuestión de milisegundos y hace viable emitir
dentro de la petición de venta. Verificado antes de decidirlo:

- `SellCreatedOrModified::dispatch()` ocurre **después** del `DB::commit()`
  (`SellPosController.php:672-674`): no se sostiene ninguna transacción
  abierta durante la llamada HTTP.
- Y **antes** de `receiptContent()` (~línea 708): el ticket ya ve el
  comprobante. En el camino `is_save_and_print` la impresión es una
  petición aparte, así que también llega a tiempo.
- `FePuntoEmision::siguienteSecuencial()` ya reserva con `lockForUpdate`.
- `FacturacionClient` ya manda `Idempotency-Key`: si el intento en línea
  expiró pero llegó, el reintento en cola recibe la misma respuesta.

**3. Los totales del ticket son los que viajaron en el XML.** Hoy el ticket
imprime `$receipt_details->taxes` y `->total`, que agrupa por los impuestos
del POS; el XML lleva el desglose que arma `FacturaMapper` por tarifa del
SRI. El importe total ya cuadra (`validarCuadre` lo valida contra
`final_total`), pero el desglose puede presentarse distinto — y el objetivo
de §16 es justamente que ticket y portal del SRI muestren el mismo número.
Se persiste el desglose emitido y el ticket imprime ese.

**4. Si `fe` no responde a tiempo, el ticket lo dice.** Sale con todas las
leyendas del emisor y los datos del cliente, y en lugar del bloque de
autorización una línea «COMPROBANTE EN PROCESO DE EMISIÓN — se enviará a su
correo electrónico». La emisión sigue en cola y la reimpresión posterior
trae el RIDE completo. Descartado bloquear la impresión: dejar al cliente
esperando por un fallo de red es lo contrario del objetivo.

**5. En el esquema offline, el número de autorización es la clave de
acceso.** El ticket de Fybeca lo rotula literalmente
«Autorizacion/Clave de acceso/Esquema Offline». Con la clave ya hay RIDE
válido al cobrar; la fecha real de autorización (que llega por webhook) es
un extra para las reimpresiones, no un bloqueante.

**6. Sin código de barras.** La TM-U220B es de impacto y no lo reproduce.

**7. La placa se imprime por elección, no por obligación.** La ficha la
exige en el XML y no dice nada del RIDE; ver «La placa y el RIDE» en el
registro del Anexo 25 (§14).

### Fases

**Fase 1 — `fe`: la clave de acceso en la respuesta inmediata.**
Extraer la generación a un estático de `GenerarClaveAcceso` (mismo patrón
que `AgregarLeyendasEmisor::agregar()`, para que la regla viva en un solo
sitio); generarla antes de `RegistroDeEmision::crear()` y persistirla ahí,
para las dos modalidades; la rama async la pasa al job por el parámetro que
ya existe. `docs/openapi.yaml`: el 202 pasa a garantizar `claveAcceso`.
El POS no necesita ningún cambio para empezar a guardarla —
`EmiteComprobanteElectronico.php:150` ya la lee de la respuesta.

**Fase 2 — `pos`: emisión en línea con respaldo.**
`EmitirFacturaElectronica` deja de ser `ShouldQueue` y despacha con
`dispatchSync` y timeout corto; ante cualquier fallo, `dispatch()` normal.
Migración: `numero_autorizacion` y `autorizado_en` en `fe_comprobantes`,
guardados en los tres puntos que hoy los descartan (webhook,
`SincronizaEstadoRemoto`, job), y el desglose emitido para la decisión 3.

**Fase 3 — `pos`: los datos del RIDE en un solo objeto.**
`DatosSriDelTicket` reúne emisor, designaciones, comprobante, totales y
placa, y responde también *qué falta* (sin FE activa / en proceso / no
emitido). Se engancha con **una línea** en `TransactionUtil::getReceiptDetails`
(`$output['sri'] = …`), que es upstream: así queda disponible también para
la nota de crédito de `SellReturnController`.

**Fase 4 — `pos`: el ticket.**
Partial propio `sale_pos/receipts/partials/fe_sri.blade.php`, incluido
desde `tmu220b`. Los 49 dígitos de la clave parten en dos líneas de 40
columnas. Cierra con la línea de verificación: consultar el comprobante en
`srienlinea.sri.gob.ec` con cédula/RUC y la clave de acceso.

**Fase 5 — tests.**
En `fe`: el 202 trae una clave válida de 49 dígitos; es la misma que queda
autorizada; un reintento del job no la cambia.
En `pos`: `fe` lento → aviso en el ticket y job en cola; respuesta normal →
ticket con clave; sin FE activa → el partial no imprime nada; nota de
crédito; con placa; con las cuatro designaciones.

### Notas registradas (no se implementan aquí)

**`<pagos>` falta en `fe`, y la ficha lo exige.** La ficha marca
`<pagos><pago><formaPago>` como *Obligatorio* en factura (junto a `<total>`,
y `<plazo>`/`<unidadTiempo>` cuando corresponda; `formaPago` conforme a la
Tabla 24). `fe` **tiene** `PagoData` y lo usa en `InfoNotaDebitoData` y
`InfoLiquidacionCompraData`, pero `InfoFacturaData` no lo declara, y
`FacturaMapper` (POS) no lo envía. Toca de lleno el objetivo de §16: el
ticket imprimiría «Forma de pago: TARJETA DE DÉBITO» y el documento del SRI
no tendría forma de pago alguna — exactamente la discrepancia que §16
quiere eliminar.
*Cómo implementarlo:* en `fe`, añadir `pagos` a `InfoFacturaData`
reutilizando `PagoData` y su normalización de wrapper
(`Payload::lista(data_get($properties, 'pagos.pago'))`), emitido entre
`<moneda>` y `<valorRetIva>`; documentarlo en `docs/openapi.yaml` (el
guardia de claves desconocidas de §14 hace que enviarlo antes de eso
devuelva 422). En el POS, mapear los `payment_lines` de la venta a los
códigos de la Tabla 24 en `FacturaMapper`, con un valor por defecto
configurable por método de pago del POS.

**✅ La ruta ESC/POS no pasa por el blade** (resuelto 2026-09-23).

El diagnóstico de la nota era incompleto: no es que «lo formatee el
navegador». Con `receipt_printer_type = 'printer'` el POS manda el recibo
en JSON por WebSocket a `ws://127.0.0.1:6441` (`public/js/printer.js:2`,
`pos.js:2683`), **un servidor de impresión externo** que lo maqueta con los
campos que él conoce. El bloque del SRI no está entre ellos y no se puede
añadir desde este código.

Así que la nota original —«que el formateador los pinte»— no era viable.
La solución es no usar esa ruta cuando el ticket tiene que ser un
comprobante: `DatosSriDelTicket::exigePlantilla()` y un `&&` en
`SellPosController`. Entre respetar la impresora configurada y entregar un
comprobante válido, manda lo segundo.

La condición es deliberadamente estrecha —hay datos del SRI **y** el diseño
es uno de `DISENOS_CON_RIDE` (hoy solo `tmu220b`)—: forzar la plantilla a
un diseño que no lleva el bloque no le daría nada y solo le quitaría su
impresora.

Tests (`TicketRideTest`, 3 más): el RIDE se renderiza aunque el local
imprima por ESC/POS; sin datos del SRI el local sigue por ESC/POS; un
diseño sin el bloque también. Verificado que el primero falla al quitar el
guard. Ojo al escribirlos: `receiptContent` **inicializa todas las claves**
del recibo (`SellPosController.php:793-798`), así que afirmar sobre la
presencia de `printer_config` o `data` no distingue nada; lo que distingue
es el valor de `print_type`.

**Queda abierto el caso de la nota de crédito.** `SellReturnController`
tiene la misma bifurcación, pero su recibo es `sell_return.receipt`, una
plantilla distinta que no incluye los parciales del SRI: forzarla no
imprimiría el bloque. Si las devoluciones deben entregar un RIDE impreso,
es trabajo aparte: llevar el bloque a esa plantilla (o darle un diseño
propio) y recién entonces aplicarle el mismo guard.

### ✅ Fase 1 — La clave de acceso en la respuesta inmediata (2026-09-22)

- `GenerarClaveAcceso::para()`: la generación sale de `__invoke` a un
  estático (mismo patrón que `AgregarLeyendasEmisor::agregar()`), con las
  dos reglas juntas —respetar una clave previa validando su prefijo, o
  generar una nueva—. La etapa del pipeline queda de tres líneas.
- `RegistroDeEmision::crear()` recibe la clave y la persiste al nacer el
  registro, no al completarlo. Un único llamador, así que el parámetro va
  obligatorio: un registro ya no existe sin su clave.
- `EmitirComprobanteController` la calcula antes de crear el registro y la
  pasa a `procesarEmision()`.
- **`ProcesaEmisiones` no cambió**: ya pasaba `$claveAcceso?->value` al job
  y ya devolvía `ComprobanteResource`, que ya exponía `claveAcceso`. La
  maquinaria de §5.10 sirvió tal cual para el primer envío.
- `docs/openapi.yaml`: la introducción, la respuesta `202` y el campo
  `claveAcceso` del esquema `Comprobante` dicen que la clave existe desde
  el registro y que el 202 la trae.

Tests (`ComprobanteAsincronoTest`, 9 nuevos): el 202 trae la clave y queda
persistida; **el job emite con LA MISMA clave, comprobado contra el
`<claveAcceso>` del XML firmado, en los seis tipos de comprobante**; un
reintento del job no la cambia; el flujo síncrono también nace con ella.

Verificado que 8 de los 9 fallan si se quita la persistencia (el noveno es
el del flujo síncrono, donde `completar()` ya la escribía al final).

`composer quality` verde: Pint, PHPStan nivel max sin errores, 552 tests.

**El POS no necesitó ningún cambio**: `EmiteComprobanteElectronico.php:150`
ya leía `claveAcceso` de la respuesta y la guardaba.

### ✅ Fase 2 — Emisión en línea con respaldo (2026-09-22, `../pos`)

- Migración `fe_comprobantes`: `numero_autorizacion`, `autorizado_en` y
  `totales`.
- `FeComprobante::autorizacionDesde()`: traduce el bloque `autorizacion` de
  la representación del servicio. Lo comparten los **tres** caminos por los
  que esa representación llega —respuesta de la emisión, reconciliación y
  webhook—, que antes la descartaban. Cada campo cae al valor ya guardado:
  su ausencia significa «sin novedad», nunca «se perdió la autorización».
- `EmiteComprobanteElectronico::totalesEmitidos()`: guarda el desglose tal
  como viajó en el XML. **No se recalcula al imprimir**: el producto, su
  impuesto o la propia venta pueden cambiar después de emitido, y entonces
  el ticket dejaría de coincidir con el documento del SRI.
- `EmisionInmediata` + los dos listeners dejan de ser `ShouldQueue`.
- `facturacion.timeout_en_linea` (4 s, frente a los 15 de la cola): en la
  caja hay un cliente esperando.

**La nota de crédito también emite en línea.** El plan solo nombraba la
factura, pero el cliente de una devolución se lleva su ticket igual, el
mecanismo es el mismo `EmisionInmediata::despachar()`, y dejarla asíncrona
la condenaba a imprimir «en proceso» siempre.

**Tres cosas que aparecieron al implementar:**

1. `dispatch_sync` de un job `ShouldQueue` **no** ejecuta directamente:
   pasa por la cola en la conexión `sync` (`Dispatcher.php:95-99`). Por eso
   `Queue::fake()` se lo traga y los tests afirman con
   `Bus::assertDispatchedSync`.
2. El `dispatch()` de respaldo necesitaba **su propio** try/catch: con la
   cola en `sync` vuelve a ejecutar el trabajo ahí mismo, y su excepción
   habría subido hasta una venta que ya estaba cobrada. Lo encontró el test
   del servicio caído.
3. Verificado que `BusFake::assertDispatched` y `assertNotDispatched`
   cubren también los despachos síncronos (líneas 131 y 192): las
   aserciones que ya existían sobre el listener siguen valiendo, no se
   debilitaron en silencio.

Tests (`EmisionEnLineaTest`, 9): el job se despacha en sincrónico; los
listeners ya no son `ShouldQueue`; la venta sale con su clave de acceso;
se guarda el desglose del XML; con el servicio caído la venta se cobra
igual, redirige limpio y el comprobante no queda ante el SRI; el fallo se
registra y se reencola; webhook y reconciliación guardan número y fecha de
autorización; el timeout en línea es menor que el de la cola.

Suite del POS completa en verde: **1661 tests**.

### ✅ Fase 3 — Los datos del RIDE en un solo objeto (2026-09-22, `../pos`)

`DatosSriDelTicket::para(?Transaction): ?self` reúne emisor, designaciones,
documento, comprador, totales y placa. Devuelve null cuando no hay nada que
imprimir (negocio sin FE, o venta despachada solo con su ticket) y expone
`enProceso()` cuando hay comprobante pero todavía no clave de acceso.

Enganchado con **una sola línea** en `TransactionUtil::getReceiptDetails`
(`$output['sri'] = …`), que es código upstream. Así queda disponible también
para la nota de crédito de `SellReturnController`, que llama al mismo método.

**El comprador NO sale del contacto del POS.** Es el hallazgo de la fase:
en una venta de mostrador el XML lleva «CONSUMIDOR FINAL / 9999999999999»
(`ComprobanteMapper::comprador()`), mientras que `$receipt_details->customer_name`
diría «Consumidor Final» o «Walk-In Customer» y el tax number iría vacío. No
es deriva ocasional: pasaría en **cada** venta de mostrador, que es la
mayoría. Por eso `comprador()` y `direccionLocation()` pasan a públicos
estáticos y el ticket usa exactamente la misma derivación que el XML.

- `FeAjuste::LEYENDAS_RIMPE` + `leyendaRimpe()`: los literales exactos del
  Anexo 22, que deben coincidir con los que el enum `RegimenRimpe` de `fe`
  pone en el XML. Duplicados a propósito —el ticket lo imprime el POS— y
  fijados por un test que compara la cadena completa.
- `claveAccesoEnLineas(40)` parte los 49 dígitos en el servicio, no en la
  plantilla: el navegador no debe elegir dónde cortar.
- `subtotalesPorTarifa()` arma «Subtotal 15%» / «IVA 15%» desde el desglose
  guardado en la Fase 2, no desde los impuestos del POS.

Tests (`DatosSriDelTicketTest`, 11): los dos casos de «nada que imprimir»;
número, clave, ambiente y obligado a contabilidad; la clave partida en dos
líneas que reconstruyen el original; el aviso de «en proceso» con las
leyendas igualmente presentes; solo las designaciones configuradas, con el
literal RIMPE exacto; consumidor final y comprador identificado; los
totales del XML; la placa solo si el negocio es operadora; y el objeto
llegando de verdad dentro de `getReceiptDetails`.

Suite del POS completa en verde: **1672 tests**.

**Limitación anotada:** si el contacto se renombra después de emitido, una
reimpresión mostrará el nombre nuevo y el SRI el viejo. Se persistió el
desglose de totales (Fase 2) pero no el bloque del comprador; si aparece el
caso, es otra columna en `fe_comprobantes`.

### ✅ Fase 4 — El ticket (2026-09-22, `../pos`)

Cinco parciales propios en `sale_pos/receipts/partials/fe/` —`emisor`,
`documento`, `comprador`, `totales` y `verificacion`—, incluidos desde
`tmu220b`. El plan hablaba de uno solo, pero los bloques van a sitios
distintos del ticket (cabecera, tras el encabezado, zona del cliente,
resumen de impuestos y pie) y un único archivo habría exigido inventar un
mecanismo de secciones. Cada uno se autoprotege con `@if($sri)`, así que
las inclusiones son de una línea. **Las plantillas de upstream no se
tocaron.**

`$sri` se resuelve una sola vez al abrir el ticket y los parciales lo
heredan por ámbito.

**Dos decisiones que salieron de mirar el ticket renderizado, no del plan:**

1. **El resumen de impuestos del POS cede el sitio al del XML.** Era el
   punto exacto que la decisión 3 quería proteger: el del POS agrupa por
   los impuestos propios, no por las tarifas del SRI. El resto del bloque
   de totales (descuentos, pagos, vueltos) se queda como estaba.
2. **El bloque de cliente del POS se suprime cuando hay datos del SRI.**
   No es solo duplicación: una venta sin cédula va al SRI como «CONSUMIDOR
   FINAL» aunque el POS conozca el nombre del contacto, así que el ticket
   estaría **contradiciendo** al documento que pretende ayudar a comparar.
   Queda la identidad del XML más el teléfono del contacto —que no es
   identidad y no puede contradecir nada—, igual que lo imprime Procafecol.

Tests (`TicketRideTest`, 9): se renderiza la plantilla de verdad y se
afirma sobre el texto impreso. Clave partida en dos líneas de 40 que
reconstruyen el original (y la clave entera NO aparece de corrido);
identificación del documento; las cuatro leyendas; comprador y totales del
XML; que el nombre del contacto no aparece contradiciendo al documento; la
línea de verificación; el aviso de «en proceso» sin invitar a verificar
algo que aún no existe; la placa solo en operadoras; y —importante— que un
negocio **sin** facturación electrónica sigue imprimiendo su ticket de
siempre.

Suite del POS completa en verde: **1681 tests**.

#### Correcciones al imprimir el ticket de verdad (2026-09-23)

- **El RUC salía dos veces.** UltimatePOS imprime su propio `tax_number_1`
  en la cabecera, que en Ecuador es el mismo RUC del bloque del SRI. Se
  omite **solo cuando repite el RUC**, comparando por dígitos (el POS
  puede tenerlo con puntos o guiones); si el negocio usa esa línea para
  otra cosa, se respeta.
- **El número del comprobante ante el SRI encabeza los datos** de la
  transacción, donde antes solo estaba el interno del POS. El interno se
  conserva debajo para conciliar, como hacen los RIDE de los emisores
  grandes (ORDEN, FAC…). Se quitó del bloque del título para no repetirlo.

*Al escribir el test:* contar apariciones del RUC en el ticket da 2 aunque
el arreglo funcione, porque **la clave de acceso embebe el RUC** (posiciones
11-23) y se imprime partida en líneas de 40. Hay que descontar sus trozos
antes de contar.

**Etiquetas en inglés (`INVOICE`, `Total Paid`…):** no son código. Salen de
`invoice_layouts` y se editan en `/invoice-layouts/{id}/edit`. Lo que sí es
código son los valores por defecto que siembra
`BusinessUtil::newBusinessDefaultResources()` (`BusinessUtil.php:77`), en
inglés: un negocio nuevo vuelve a nacer con ellos.

#### Fuera de §16: el desfase horario de Carbon 3 (2026-09-23)

Apareció mirando el ticket —una venta de las 12:00 se imprimía a las
17:00— pero no era del ticket. Carbon 3 cambió `createFromTimestamp()`
para devolver UTC por defecto; en Carbon 2 usaba la zona de la aplicación,
así que el patrón `createFromTimestamp(strtotime($date))` corría toda
fecha mostrada el offset del huso. De noche cambiaba también el **día**
(una venta de las 20:00 del 23 se mostraba como 24/09), con lo que eso
implica para los reportes por fecha.

Afectaba a `Util::format_date()` y a las tres directivas Blade
(`@format_date`, `@format_time`, `@format_datetime`): todo el sistema, no
una pantalla. Commit aparte en `../pos` (`0fc7d28`), con tres tests de
regresión en `UtilDateTest` —hora local, venta nocturna y timestamp Unix—
verificados contra el código anterior.

**Es código upstream de UltimatePOS**, así que se pierde si una
actualización sobrescribe `Util.php` o `AppServiceProvider.php`.

### Estado de §16

Fases 1 a 4 cerradas, más la nota de la ruta ESC/POS. Queda la Fase 5
(tests), que se fue haciendo dentro de cada fase: lo pendiente es probar en
una impresora real, y decidir si la nota de crédito también debe imprimir
su RIDE. Descartados en la revisión:
el código de barras de la clave (la TM-U220B no lo reproduce; se revisa si
se adopta un diseño térmico) y la marca «ORIGINAL ADQUIRIENTE» que imprime
Fybeca (la ficha no la exige y el POS no tiene el concepto de copia).
