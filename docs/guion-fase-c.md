# Guion — Fase C: plumbing local de punta a punta (fe.test ↔ pos.test)

> Objetivo: validar TODO el circuito de integración en tu máquina —
> venta en el POS → emisión async → SRI de pruebas → webhook firmado →
> estado en el POS — usando el **certificado de prueba del repo**.
>
> Con ese certificado el SRI de pruebas **rechazará la autorización con
> error 39 (firma inválida)**. Eso es lo esperado y lo que queremos:
> ejercita el camino completo, incluido un desenlace de rechazo, sin
> tocar nada real. La fase D repite esto con el certificado real y
> termina en AUTORIZADO.
>
> Requisitos: ambos sitios servidos por Herd (`https://fe.test`,
> `https://pos.test`), MySQL corriendo, e internet (el SRI de pruebas
> es un servicio real).

---

## 0 · Preparar el servicio fe (terminal 1)

```bash
cd ~/Herd/fe
php artisan migrate            # por si hay migraciones pendientes
php artisan queue:work --tries=5
```

Deja este **worker corriendo** durante toda la sesión: procesa tanto la
emisión asíncrona (`ProcesarComprobanteJob`) como el envío de webhooks
(`EnviarWebhookJob`). Si el worker no corre, todo se queda en
`pendiente` — es el primer sospechoso si "no pasa nada".

## 1 · Crear el partner y su token (terminal 2)

```bash
cd ~/Herd/fe
php artisan partner:crear "UltimatePOS Local"
```

- Copia el **token** que imprime (solo se muestra una vez).
- Opcional, para ver el panel de partner en el navegador:
  `php artisan partner:credenciales ultimatepos-local tu@correo.com`
  → luego entra en https://fe.test/partner/login

## 2 · Configurar el POS

En `~/Herd/pos/.env` añade:

```dotenv
FACTURACION_BASE_URL=https://fe.test/api
FACTURACION_API_TOKEN=<el token del paso 1>
```

```bash
cd ~/Herd/pos
php artisan migrate
php artisan config:clear
```

*(El POS tiene `QUEUE_CONNECTION=sync`: el job de emisión corre dentro
de la petición de la venta. Es válido para el piloto — la emisión es
async del lado fe y responde 202 en milisegundos.)*

## 3 · Registrar el webhook del POS

```bash
cd ~/Herd/pos
php artisan fe:registrar-webhook
```

- Registra `https://pos.test/webhook/facturacion` y muestra el
  **secreto** (una sola vez). Añádelo al `.env` del POS:

```dotenv
FACTURACION_WEBHOOK_SECRET=whsec_...
```

```bash
php artisan config:clear
```

✅ **Checkpoint**: en https://fe.test/partner/login → Webhooks debe
aparecer el endpoint activo.

## 4 · Activar la facturación electrónica del negocio piloto

Opción UI (recomendada, prueba la pantalla nueva): entra al POS como
admin del negocio y abre **https://pos.test/facturacion-electronica/ajustes**.
Completa RUC (13 dígitos — puede ser el RUC real del negocio; con el
certificado de prueba el SRI devolverá igual), razón social, ambiente
**Pruebas**, marca **activa** y guarda.

Opción CLI equivalente:

```bash
php artisan fe:activar 1 --ruc=0992223334001 --razon-social="MI NEGOCIO S.A."
```

✅ **Checkpoint**: la pantalla de ajustes muestra "Sin certificado" y el
negocio aparece en el panel de partner de fe (Contribuyentes).

## 5 · Cargar el certificado de PRUEBA

1. En la pantalla de ajustes pulsa **"Generar enlace de carga de
   certificado"** (o toma la URL que imprimió `fe:activar`).
2. Abre el enlace (es la página pública firmada de fe).
3. Sube `~/Herd/fe/tests/Fixtures/certificado-prueba.p12` con la clave
   `clave-prueba`.

✅ **Checkpoint**: la página confirma "Certificado de firma guardado" y
la pantalla de ajustes del POS pasa a "✓ Certificado configurado".

## 6 · La venta de prueba (el momento de la verdad)

1. En el POS: **POS → nueva venta** del negocio piloto, cualquier
   producto, **finalizar** (venta al contado, sin descuento de orden).
2. Observa el terminal del worker de fe: debe procesar
   `ProcesarComprobanteJob` y, unos segundos después,
   `EnviarWebhookJob`.
3. Abre **https://pos.test/facturacion-electronica**:

| Momento | Estado esperado |
|---|---|
| Justo tras la venta | `pendiente` (el 202 llegó, fe está trabajando) |
| Tras ~10–30 s (SRI + webhook) | **`no_autorizado`** con **error 39: FIRMA INVALIDA / cadena de confianza** |

Ese `no_autorizado` **es el éxito de la fase C**: la venta viajó al
servicio, el servicio la firmó y la envió al SRI real de pruebas, la
recepción la aceptó, la **autorización** la rechazó por el certificado
dummy (la firma se valida en esa fase, no en recepción), y el webhook
firmado trajo el desenlace de vuelta hasta la tabla del POS.

> Si en su lugar ves `devuelto` con **error 45 (SECUENCIAL REGISTRADO)**:
> ese secuencial ya se usó en el SRI de pruebas (p. ej. pruebas manuales
> previas con el mismo RUC). Ajusta el contador
> (`fe_secuenciales.ultimo_secuencial` de la fila `tipo = factura`) al
> último usado y pulsa **Reenviar** — el job detecta el error 45 y
> re-emite con secuencial nuevo automáticamente.
>
> **Clave quemada por firma**: un NO AUTORIZADO por error 39 deja el
> veredicto registrado en el SRI para esa clave — reintentarla devuelve
> el mismo error aunque ya hayas corregido el certificado (verificado
> empíricamente). El job también lo detecta: **Reenviar** re-emite con
> clave y secuencial nuevos.

✅ **Checkpoints cruzados**:
- Panel fe (contribuyente o partner): el comprobante aparece `devuelto`
  con su clave de acceso.
- Panel partner → Webhooks → entregas: la entrega `comprobante.devuelto`
  con estado **entregada** y código 200.
- El secuencial en el POS es `001-001-000000001`.

## 7 · Ejercitar el resto del circuito

**Reenviar (reintento §5.10)** — en el listado del POS pulsa
**Reenviar** sobre el `no_autorizado`. Resultado esperado: vuelve a
quedar `no_autorizado` (el certificado sigue siendo de prueba), pero
verifica en el panel de fe que **reutilizó la misma clave de acceso y
secuencial** (el mismo comprobante, no uno nuevo). Este mismo botón es
el puente a la fase D: con el certificado real cargado, ese reintento
termina en AUTORIZADO.

**Reconciliación** — simula un webhook perdido:

```bash
cd ~/Herd/pos
php artisan tinker --execute '\App\FeComprobante::latest("id")->first()->update(["estado" => "pendiente"]);'
php artisan fe:reconciliar --minutos=0
```

Resultado esperado: "Reconciliados: 1 actualizados…" y el estado vuelve
a `devuelto` consultando a fe.

**Idempotencia** — re-despacha la emisión de la misma venta:

```bash
php artisan tinker --execute '\App\Jobs\EmitirFacturaElectronicaJob::dispatch(\App\FeComprobante::latest("id")->first()->transaction_id);'
```

Resultado esperado: NO se crea un segundo comprobante en fe (el job
detecta el `fe_uuid` y reintenta el existente; y si no lo tuviera, la
`Idempotency-Key` devolvería la emisión original).

**Venta no facturable** — haz una venta con **descuento a nivel de
orden**: debe quedar `no_facturable` con la razón visible, sin llamar
al servicio.

## 8 · Checklist de cierre de la fase C

- [ ] Venta finalizada → `pendiente` → `devuelto` (error 39) sin tocar nada a mano
- [ ] Webhook verificado (entrega `entregada` en el panel de partner)
- [ ] Reenviar reutiliza clave/secuencial
- [ ] `fe:reconciliar` recupera un estado desincronizado
- [ ] Re-despachar la emisión no duplica
- [ ] Venta con descuento de orden → `no_facturable`
- [ ] Negocio SIN FE activa: sus ventas ni se inmutan

## Problemas típicos

| Síntoma | Causa probable |
|---|---|
| Todo se queda en `pendiente` | El worker de fe no está corriendo (paso 0) |
| `cURL error 60` (SSL) desde el POS | La CA de Herd no está confiada para PHP; ejecuta `herd trust` o usa `FACTURACION_BASE_URL=http://fe.test/api` |
| Webhook responde 503 | Falta `FACTURACION_WEBHOOK_SECRET` en el `.env` del POS (+ `config:clear`) |
| Webhook responde 401 | El secreto no coincide (¿registraste el webhook dos veces? el secreto es el del ÚLTIMO registro; borra el viejo en el panel de partner) |
| 422 "RUC no corresponde al contribuyente" | El RUC de los ajustes del POS no es el aprovisionado; corrígelo en la pantalla de ajustes |
| La venta no genera nada | El negocio no está activo o falta certificado (`listoParaEmitir`), o la venta no quedó `final` |
| "La clave es incorrecta" al subir el .p12 | La clave del certificado de prueba es `clave-prueba` |

## Después de esto → Fase D

Idéntico circuito con el **certificado real**: generas un nuevo enlace,
el titular sube su .p12 real, y la misma venta termina en **AUTORIZADO**
con RIDE descargable. Ahí se corre el checklist completo del §12
(consumidor final, cliente con cédula, secuencial duplicado, cuotas…).

---

## Anexo · Probar notas de crédito (devoluciones)

La nota de crédito **modifica una factura ya autorizada**, así que el
requisito previo es tener una venta con su factura en estado
`autorizado` en `/facturacion-electronica`.

1. **Registrar la devolución** en el POS: *Ventas → Listar ventas → (fila
   de la venta) → Devolución de venta*, indica las cantidades a devolver
   y guarda. Basta con devolver una parte: la nota acredita solo lo
   devuelto.
2. **Observar el worker de fe**: procesa la emisión y luego el webhook,
   igual que una factura.
3. **Verificar en `/facturacion-electronica`**: aparece una fila nueva
   con la etiqueta **Nota de crédito**, su propio secuencial
   (`001-001-000000001` — serie independiente de las facturas) y estado
   `autorizado`.
4. **Descargar el RIDE** de la nota: debe mostrar el documento
   modificado (`001-001-00000000X` de la factura), el motivo y solo las
   líneas devueltas.

**Casos que conviene ejercitar**

- [ ] Devolución **total** → `valorModificacion` = total de la factura.
- [ ] Devolución **parcial** → solo las unidades devueltas, con su IVA.
- [ ] Devolución de una venta **sin factura electrónica** (negocio que
      activó FE después) → queda `no_facturable` con la razón visible,
      sin llamar al servicio.
- [ ] Comprobar que el secuencial de facturas **no** se ve afectado por
      las notas (series independientes).

**Limitación del piloto**: se emite una sola nota por devolución. Si la
devolución se amplía después de que su nota fue autorizada, la
diferencia requeriría una nota adicional — no soportado (queda en el
backlog del §12).
