# Version Control

No hagas commits automáticamente, siempre debes consultarme primero.

# Idioma y naming

- El dominio se escribe en español con el vocabulario de la ficha técnica del
  SRI (comprobante, emisión, clave de acceso, contribuyente). Tests y mensajes
  de error también en español.
- Evita pares de nombres confundibles (misma raíz + mismo sustantivo, p. ej.
  EmisionComprobante vs EmitirComprobante). El estado se nombra por su rol
  (EmisionEnCurso), la acción como caso de uso (EmitirComprobante).

# Arquitectura

- `app/Sri/` es el módulo de dominio: toda la lógica de facturación vive ahí,
  nunca en controllers, jobs ni requests. Los controllers son invocables y
  delgados; lógica compartida entre controllers va en `Concerns/` (traits).
- El pipeline `EmitirComprobante` es el ÚNICO camino de emisión: el flujo
  síncrono y el asíncrono lo comparten. Una nueva etapa = una Action invocable
  con `__invoke(EmisionEnCurso $emision, Closure $next)`.
- Todo servicio externo (SOAP del SRI, firmador, PDF) vive detrás de un
  contract en `app/Sri/Contracts/` con implementación real + fake. Los fakes
  se inyectan con `$this->app->instance()` en los tests.
- Los Form Requests construyen los DTOs tipados y convierten CUALQUIER error
  de dominio (DatoInvalido, CertificadoInvalido, excepciones de laravel-data)
  en ValidationException 422 — un payload inválido jamás produce un 500.
- Los modelos exponen uuid como id público (el autoincremental nunca sale de
  la BD); estados con enums PHP; `$guarded = []`.

# Reglas del dominio SRI

- `codDoc` y `claveAcceso` SIEMPRE se derivan del tipo/servidor: se ignoran si
  vienen en el payload. La clave de acceso solo se reutiliza en reintentos
  (ficha §5.10) y validando que su prefijo determinista no cambió.
- Los importes viajan como strings ("11.20") y las fechas como dd/mm/aaaa;
  no convertir a float jamás.
- El XML del SRI representa colecciones como wrapper {detalles: {detalle: X}}
  donde X puede ser objeto o lista: normalizar siempre con `Payload::lista()`.
- La ficha técnica (ficha-tecnica.pdf, local) es la fuente de verdad; citar
  sección al implementar reglas (p. ej. "§5.10").
- Multi-tenancy estricta: recursos de otro contribuyente responden 404 (no
  403); el RUC del payload debe coincidir con el contribuyente autenticado.

# Testing

- `fixtures/golden/` es la red de seguridad del refactor: NUNCA se modifican
  esos archivos. La construcción de XML y claves de acceso debe reproducirlos
  byte a byte (ver ConstruirXmlTest, ComprobanteXmlParserTest).
- Jamás golpear los servidores reales del SRI en tests: usar FakeSriGateway y
  FakeXmlSigner.
- Helpers canónicos en tests/Pest.php: golden_path(), golden_input(),
  golden_payload(), actuar_como_contribuyente(), p12_de_prueba(). Úsalos en
  vez de reconstruir payloads o auth a mano.
- Tests que requieren binarios externos (java, openssl) deben saltarse
  limpiamente con markTestSkipped cuando el binario no existe.
- El certificado de prueba es tests/Fixtures/certificado-prueba.p12
  (clave: "clave-prueba"); existe variante -legacy.p12 para el caso RC2/3DES.

# Calidad

- `composer quality` (pint + PHPStan nivel max + tests) debe pasar antes de
  dar por terminado cualquier cambio.
- PHPStan max sin baseline ni ignores: usar accessors tipados
  (`config()->string()`, `$request->string()->toString()`), guards sobre
  mixed, y docblocks con genéricos. No castear mixed directamente.

# Seguridad

- Secretos (clave del .p12) jamás en argv de procesos hijos (usar env:),
  jamás en logs ni en mensajes de error de la API. Marcar parámetros con
  #[SensitiveParameter].
- Jobs que transporten datos sensibles implementan ShouldBeEncrypted.
- Secretos en BD siempre con cast `encrypted`.
- `legacy/` es referencia de solo lectura: nunca modificarlo ni desplegarlo.

# API

- Cambió el contrato → actualizar docs/openapi.yaml en el mismo cambio.
- Acciones sobre recursos (reintentar, anular…) son POST a sub-ruta, no PUT:
  PUT se reserva para reemplazos idempotentes de representación.
- La API responde JSON siempre; errores con mensajes accionables y, cuando
  aplique, la etapa del fallo y los mensajes del SRI.
- Cuando haya algún cambio que afecte el uso de los clientes de la API, asegúrate de actualizar la documentación.

# Frontend (Inertia + Vue)

- En `<script setup>` NUNCA declarar variables locales con el mismo nombre de
  un prop: lo sombrean silenciosamente en el template (bug ya sufrido en
  Configuracion.vue). Prefijo `form` para formularios (formCertificado).
- Estados siempre con etiqueta + icono, nunca solo color (EstadoBadge).
- Tras cambiar archivos .vue, correr `npm run build` (o avisarme si usas
  `npm run dev`).
