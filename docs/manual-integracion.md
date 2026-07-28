# Manual de integración — API de Facturación Electrónica SRI

Bienvenido. Esta guía está pensada para las personas que van a conectar un
sistema —un punto de venta, un ERP, una tienda en línea o una aplicación hecha
a medida— con nuestro microservicio de facturación electrónica del SRI de
Ecuador.

El objetivo del manual es explicarte **cómo integrarte y en qué orden conviene
hacerlo**, con el contexto necesario para que entiendas por qué cada pieza
funciona como funciona. Si lo que necesitas es la referencia exacta de un campo
concreto, la encontrarás en la especificación OpenAPI que publicamos en `/docs`
(el archivo crudo está disponible en `/docs/openapi.yaml`). En el caso poco
probable de que este manual y la especificación se contradigan, considera que
la especificación tiene la razón: se sirve desde el mismo despliegue que la API
y tenemos una prueba automatizada que la mantiene alineada con las rutas
reales.

A lo largo de todos los ejemplos usaremos `https://fe.test`, que es la dirección
del entorno local de desarrollo. Cuando trabajes contra tu propio despliegue,
sustitúyela por la URL que te hayamos indicado.

---

## 1. Qué hace el servicio por ti

Antes de escribir una sola línea de código, conviene que tengas claro dónde
termina tu responsabilidad y dónde empieza la nuestra. El servicio se encarga
de todo lo que ocurre entre el momento en que tu sistema registra una venta y
el momento en que existe un comprobante autorizado por el SRI:

```
tu sistema  ──payload JSON──▶  FE  ──▶ genera la clave de acceso
                                   ──▶ construye el XML de la ficha técnica
                                   ──▶ firma con XAdES-BES usando el .p12
                                   ──▶ envía a recepción del SRI
                                   ──▶ consulta la autorización
            ◀──resultado─────       ──▶ guarda el registro y prepara el RIDE
```

Dicho de otro modo: tú nos envías los datos del comprobante y nosotros nos
ocupamos del resto. No necesitas generar la clave de acceso, ni construir el
XML, ni firmarlo, ni comunicarte con los servicios del SRI. De hecho, si
incluyes los campos `codDoc` o `claveAcceso` en tu envío, el servicio los
ignorará deliberadamente, porque prefiere derivarlos él mismo a partir del tipo
de comprobante que estés emitiendo.

Hay tres conceptos que aparecerán una y otra vez en este manual, y que vale la
pena fijar desde el principio:

| Concepto | Qué representa |
|---|---|
| **Contribuyente** | Es el emisor del comprobante: un RUC junto con su certificado de firma. Todo comprobante pertenece necesariamente a un contribuyente. |
| **Usuario** | Es una persona con acceso al panel web y a la API de *su propio* contribuyente. |
| **Partner** | Es una plataforma (un POS, un ERP) que emite comprobantes en nombre de muchos contribuyentes distintos utilizando **una sola credencial**. |

---

## 2. Los dos modos de integración

Existen dos maneras de integrarse con nosotros, y lo primero que debes hacer es
identificar cuál de las dos te corresponde.

El **modo directo** es el adecuado cuando vas a integrar un único negocio, o
bien cuando prefieres que cada uno de tus clientes gestione sus propias
credenciales de forma independiente. En este modo existe un token por cada
contribuyente.

El **modo partner** está pensado para cuando tienes muchos clientes y necesitas
administrarlos con una sola credencial, dar de alta nuevos clientes
programáticamente y recibir los eventos de todos ellos en un único webhook.
Este modo requiere que te habilitemos una cuenta de partner, ya que no se trata
de un proceso de autoservicio: nace de una relación comercial previa.

Ahora bien, hay una buena noticia que conviene destacar: **la API de emisión es
exactamente la misma en ambos modos**. La única diferencia práctica es que el
partner añade una cabecera llamada `X-Contribuyente` para indicar en nombre de
quién está emitiendo. Esto significa que, si hoy construyes tu integración en
modo directo y en el futuro necesitas migrar al modo partner, el cambio se
reduce a incorporar esa cabecera a tus peticiones.

---

## 3. Cómo autenticarte

Todos los endpoints de la API se autentican mediante un token que debes enviar
en la cabecera `Authorization`, con el formato `Bearer {token}`. Utilizamos
Laravel Sanctum por debajo.

### Si eres un usuario directo

Para obtener tu token, intercambia tus credenciales en el endpoint de tokens:

```bash
curl -X POST https://fe.test/api/v1/tokens \
  -H 'Content-Type: application/json' \
  -d '{
    "email": "operaciones@miempresa.com",
    "password": "••••••••",
    "device_name": "ERP producción"
  }'
```

Si las credenciales son correctas, recibirás una respuesta `201` con el token
que usarás a partir de ese momento:

```json
{ "token": "17|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" }
```

Este token no caduca por sí solo; permanece válido hasta que alguien lo revoque
desde el panel web. El campo `device_name` es simplemente una etiqueta
descriptiva que te ayudará a identificarlo más adelante, así que te recomendamos
usar algo que reconozcas con facilidad el día que necesites revocarlo, como
"ERP producción" o "caja-03".

En caso de que las credenciales no sean correctas, recibirás una respuesta `422`.

### Si eres un partner

El token de partner se emite en el momento de abrir la relación comercial, de
modo que no existe un endpoint público para solicitarlo. Su uso es idéntico al
de un token de usuario, con la diferencia de que además te habilita el acceso
al plano de gestión que vive bajo `/api/partner/v1/…`. Si intentas acceder a
ese plano con un token de usuario directo, recibirás una respuesta `403`.

> **Trata el token como el secreto que es.** Cualquiera que lo posea puede
> emitir comprobantes en nombre del contribuyente. Por esta razón, nunca debes
> incluirlo en código que se ejecute en el cliente, como una aplicación móvil o
> el JavaScript de un navegador: las llamadas a esta API deben salir siempre
> desde tu servidor.

---

## 4. Cómo cargar el certificado de firma

Este paso se realiza **una sola vez por contribuyente** y debe completarse
antes de la primera emisión. El archivo `.p12` se almacena cifrado en reposo y,
una vez guardado, no vuelve a viajar por la red: las peticiones de emisión no
lo incluyen.

```bash
curl -X PUT https://fe.test/api/v1/contribuyente/certificado \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d "{\"p12\": \"$(base64 -i certificado.p12)\", \"clave\": \"mi-clave\"}"
```

Si el certificado es válido, recibirás una respuesta `204` sin contenido. Si
algo no está bien —porque la clave no logra abrir el archivo, porque el archivo
no es un `.p12` legítimo o porque el certificado ya venció— recibirás una
respuesta `422` explicando el motivo. El servicio admite tanto los certificados
`.p12` modernos como los que usan cifrado heredado (RC2 y 3DES), así que no
deberías tener problemas con certificados emitidos por cualquiera de las
entidades certificadoras autorizadas.

Si intentas emitir un comprobante sin haber cargado antes el certificado,
recibirás una respuesta `409` que te lo indicará.

> **Una recomendación importante para los partners.** Te sugerimos que no
> solicites a tus clientes el archivo `.p12` ni su contraseña a través de tus
> propios formularios. En su lugar, utiliza el enlace hospedado que explicamos
> en la sección 10.3, de manera que el cliente suba su certificado directamente
> a nuestro servicio. Así la clave privada nunca llega a pasar por tus sistemas,
> lo que reduce considerablemente tu responsabilidad sobre material criptográfico
> ajeno.

---

## 5. Cómo emitir un comprobante

La emisión se realiza contra el siguiente endpoint:

```
POST /api/v1/comprobantes
```

La **primera clave del cuerpo de la petición** es la que identifica qué tipo de
comprobante estás emitiendo. Admitimos los seis tipos contemplados por el SRI, y
cada uno de ellos va acompañado de sus propios bloques de información:

| Clave raíz | Bloques que la acompañan |
|---|---|
| `factura` | `infoFactura` junto con `detalles` |
| `notaCredito` | `infoNotaCredito` junto con `detalles` |
| `notaDebito` | `infoNotaDebito` junto con `motivos` |
| `comprobanteRetencion` | `infoCompRetencion` junto con `impuestos` |
| `guiaRemision` | `infoGuiaRemision` junto con `destinatarios` |
| `liquidacionCompra` | `infoLiquidacionCompra` junto con `detalles` |

Todos los tipos comparten el bloque `infoTributaria`. Ten presente dos
convenciones de formato que el SRI exige y que nosotros respetamos: los importes
se envían **como texto entrecomillado** (es decir, `"100.00"` y no `100.0`), y
las fechas se escriben en formato `dd/mm/aaaa`.

Veamos un ejemplo completo de emisión de una factura:

```bash
curl -X POST https://fe.test/api/v1/comprobantes \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: venta-8842' \
  -d '{
    "external_id": "venta-8842",
    "metadata": { "caja": "03", "vendedor": "María" },
    "factura": {
      "infoTributaria": {
        "ambiente": "1",
        "tipoEmision": "1",
        "razonSocial": "MI EMPRESA S.A.",
        "ruc": "0922596788001",
        "estab": "001",
        "ptoEmi": "001",
        "secuencial": "000000001",
        "dirMatriz": "Av. Principal 100"
      },
      "infoFactura": {
        "fechaEmision": "10/07/2026",
        "obligadoContabilidad": "SI",
        "tipoIdentificacionComprador": "05",
        "razonSocialComprador": "Juan Pérez",
        "identificacionComprador": "1713328506",
        "totalSinImpuestos": "100.00",
        "totalDescuento": "0.00",
        "totalConImpuestos": {
          "totalImpuesto": [
            { "codigo": "2", "codigoPorcentaje": "4", "baseImponible": "100.00", "tarifa": "15.00", "valor": "15.00" }
          ]
        },
        "importeTotal": "115.00",
        "moneda": "DOLAR"
      },
      "detalles": {
        "detalle": [
          {
            "codigoPrincipal": "SERV-01",
            "descripcion": "Servicio de consultoría",
            "cantidad": "1.00",
            "precioUnitario": "100.00",
            "descuento": "0.00",
            "precioTotalSinImpuesto": "100.00",
            "impuestos": {
              "impuesto": { "codigo": "2", "codigoPorcentaje": "4", "tarifa": "15.00", "baseImponible": "100.00", "valor": "15.00" }
            }
          }
        ]
      }
    }
  }'
```

Hay algunas reglas que el servicio aplica sobre el contenido que envías y que
conviene que conozcas de antemano:

- El campo `infoTributaria.ruc` **debe coincidir** con el RUC del contribuyente
  autenticado. Si envías uno distinto, recibirás una respuesta `422`. Esta
  restricción existe para garantizar que nadie pueda emitir comprobantes en
  nombre de otro contribuyente.
- El campo `ambiente` acepta el valor `"1"` para el ambiente de pruebas y el
  valor `"2"` para producción.
- El campo `secuencial` debe tener 9 dígitos y ser único dentro de cada serie,
  entendiendo por serie la combinación de `estab` y `ptoEmi`. La numeración
  corre por tu cuenta: el servicio no la genera automáticamente.
- Los campos `external_id` y `metadata` son enteramente tuyos. El servicio los
  almacena tal como los envías y te los devuelve sin modificar en cada consulta.
  Te recomendamos aprovecharlos para reconciliar los comprobantes contra tus
  propios registros, como explicamos en la sección 8.

### La modalidad síncrona, que es la predeterminada

Si no indicas lo contrario, la petición esperará a que el SRI resuelva antes de
devolverte una respuesta. Esto puede tardar varios segundos, dependiendo de
cómo se encuentren los servicios del SRI en ese momento. Cuando todo sale bien,
la respuesta tiene este aspecto:

```json
{
  "emitido": true,
  "id": "9f1c…",
  "tipo": "factura",
  "claveAcceso": "1007202601092259678800110010010000000011234567811",
  "autorizacion": {
    "estado": "AUTORIZADO",
    "numero": "1007202601…",
    "fecha": "2026-07-10T14:32:10-05:00",
    "mensajes": []
  },
  "xmlFirmado": "PD94bWwg…"
}
```

### La modalidad asíncrona, que se activa con `?async=1`

En esta modalidad, el servicio te responde `202` de forma inmediata,
entregándote el `id` del registro. El resultado definitivo lo recibirás más
tarde por webhook, o bien podrás consultarlo cuando quieras mediante
`GET /comprobantes/{id}`.

> **Nuestra recomendación para producción.** Si emites comprobantes dentro del
> flujo de una venta —es decir, con una persona esperando frente a una caja—
> utiliza la modalidad asíncrona. Con la modalidad síncrona, cualquier lentitud
> puntual de los servicios del SRI se traduce directamente en un cajero y un
> cliente esperando delante de una pantalla.

---

## 6. La validación de la identificación del comprador

Antes de firmar el comprobante y enviarlo al SRI, el servicio verifica que la
identificación del comprador sea válida, incluyendo la comprobación de su dígito
verificador. Lo hacemos así deliberadamente: es preferible que un error de
digitación se detecte en el momento de la petición, con un mensaje claro, a que
se convierta en un rechazo del SRI varios minutos después.

Estas son las reglas que aplicamos según el tipo de identificación declarado:

| Tipo declarado | Valor esperado | Qué comprobamos |
|---|---|---|
| `04` RUC | 13 dígitos | Que el código de provincia esté entre 01 y 24, o bien sea 30, y que el dígito verificador sea correcto. El algoritmo depende del tercer dígito: aplicamos **módulo 10** cuando está entre 0 y 5 (persona natural, calculándolo sobre la cédula base) y **módulo 11** cuando es 9 (sociedad privada) o 6 (sociedad pública). Además, el código de establecimiento no puede ser `000`. |
| `05` Cédula | 10 dígitos | Que el código de provincia esté entre 01 y 24, o sea 30; que el tercer dígito esté entre 0 y 5; y que el dígito verificador de **módulo 10** sea correcto. |
| `06` Pasaporte | Texto libre de 1 a 20 caracteres | Únicamente que no venga vacío, ya que este tipo de documento no incorpora dígito verificador. |
| `07` Consumidor final | Exactamente `9999999999999` | Que el valor sea precisamente ese, sin variaciones. |
| `08` Identificación del exterior | Texto libre de 1 a 20 caracteres | Únicamente que no venga vacío, por la misma razón que el pasaporte. |

Un principio que conviene tener muy presente: **el tipo que declaras es el que
manda**. Si indicas el tipo `05` pero envías un número de 13 dígitos, recibirás
una respuesta `422` incluso cuando esos 13 dígitos formen un RUC perfectamente
válido. El servicio no intenta adivinar tus intenciones ni corrige el contenido
que le envías, porque hacerlo significaría alterar silenciosamente un documento
tributario.

Estas mismas reglas se aplican a los demás campos de identificación que aparecen
en los distintos tipos de comprobante: `identificacionSujetoRetenido` en las
retenciones, `identificacionProveedor` en las liquidaciones de compra y
`rucTransportista` en las guías de remisión. Este último merece una aclaración,
porque su nombre resulta engañoso: aunque se llame "ruc", admite también una
cédula o un pasaporte, según lo que declares en el campo
`tipoIdentificacionTransportista` que lo acompaña.

Existe una única excepción, y es el campo `identificacionDestinatario` de la
guía de remisión. El esquema oficial del SRI no contempla un campo de tipo para
el destinatario, de modo que ahí no hay un tipo declarado en el que apoyarnos.
En ese caso concreto deducimos el algoritmo a partir de la forma del valor: si
tiene 10 dígitos lo tratamos como cédula, si tiene 13 lo tratamos como RUC, y
cualquier otra cosa la aceptamos como documento libre.

> **Una precisión sobre los RUC de persona natural.** Un RUC formado por una
> cédula más el código de establecimiento se valida con módulo 10 **y
> únicamente con módulo 10**; nunca pasa además por módulo 11. Los dos
> algoritmos producen resultados distintos sobre la misma base numérica, de
> manera que aplicar ambos rechazaría absolutamente todos los RUC de persona
> natural.

---

## 7. Cómo manejar los errores

Los errores que puedes encontrarte se agrupan en tres familias, y te conviene
tratarlas de forma distinta porque su origen y su solución son diferentes.

### 7.1 El contenido de la petición es inválido

Se manifiesta como una respuesta `422` y se produce **antes** de que el servicio
llegue a comunicarse con el SRI. Corresponde a datos que incumplen la ficha
técnica: un RUC mal formado, una fecha que no respeta el formato `dd/mm/aaaa`,
un tipo de identificación desconocido, un dígito verificador incorrecto o un
bloque de información ausente. El cuerpo de la respuesta incluye un objeto
`errors` con el detalle de cada problema.

Estos casos son, casi siempre, defectos de tu integración. Te recomendamos
registrarlos en tu sistema de logs junto con el payload que los provocó y
corregirlos en el código, porque reintentar la misma petición sin cambios
producirá exactamente el mismo resultado.

### 7.2 El SRI rechazó el comprobante

También se manifiesta como una respuesta `422`, pero el cuerpo tiene una forma
distinta:

```json
{
  "emitido": false,
  "id": "9f1c…",
  "etapa": "recepcion",
  "error": "El SRI devolvió el comprobante.",
  "mensajes": ["39: FIRMA INVALIDA", "…"],
  "claveAcceso": "1007…"
}
```

El campo `etapa` te indica en qué punto del proceso se produjo el fallo, y puede
tomar los valores `firma`, `recepcion`, `autorizacion` o
`autorizacion_pendiente`. **Guarda siempre el `id` que viene en la respuesta**:
el registro del comprobante existe en nuestro sistema aunque la emisión haya
fracasado, y ese identificador es precisamente lo que necesitarás para
reintentarlo.

El array `mensajes` contiene los textos tal como los devuelve el SRI, con su
código correspondiente. Durante la puesta en marcha de una integración hay dos
que aparecen con cierta frecuencia:

| Código | Qué significa | Qué deberías revisar |
|---|---|---|
| 39 | La firma es inválida | Que el certificado no esté vencido, que se haya cargado correctamente, y que no estés usando un certificado de pruebas contra el SRI de producción |
| 45 | La identificación del comprador es inválida | En condiciones normales ya no deberías llegar a ver este código, porque lo atajamos antes con el `422` que describimos en la sección 6 |

Para cualquier otro código, te sugerimos consultar la ficha técnica del SRI
usando el número que aparezca en el mensaje. Un consejo práctico: no traduzcas
ni ocultes estos mensajes en tu interfaz. Guárdalos tal como llegan, porque son
justamente lo que necesitará la persona que tenga que diagnosticar el caso.

### 7.3 Cómo reintentar un comprobante rechazado

```
POST /api/v1/comprobantes/{id}/reintentar
```

Cuando el SRI rechaza un comprobante, la forma correcta de proceder es corregir
el contenido y reenviarlo **reutilizando la misma clave de acceso y el mismo
secuencial** del intento anterior, tal como establece la ficha técnica del SRI
en su sección 5.10. Lo que no debes hacer es emitir un comprobante nuevo con un
secuencial nuevo, porque eso dejaría un hueco en tu numeración.

Estas son las condiciones del reintento:

- Solo puede aplicarse a comprobantes que se encuentren en estado `devuelto`,
  `no_autorizado` o `fallido`. Si el comprobante está en cualquier otro estado,
  recibirás una respuesta `409`.
- Los componentes que forman la clave de acceso —la fecha, el tipo de
  comprobante, el RUC, el ambiente, la serie y el secuencial— no pueden
  modificarse en el payload corregido. Si alguno cambia, recibirás una respuesta
  `422`.
- El reintento reutiliza el registro original, de modo que conserva el mismo
  `id` y **no consume cuota adicional**.
- Admite el parámetro `?async=1`, igual que la emisión.

### 7.4 Has alcanzado un límite

Se manifiesta como una respuesta `429` y puede deberse a dos causas distintas:
que hayas superado el límite de peticiones por minuto, o que se haya agotado la
cuota mensual. En ambos casos la solución es la misma, que es reintentar más
tarde. Si enviaste una cabecera `Idempotency-Key`, puedes hacerlo con total
tranquilidad: ante un `429` la clave queda liberada, tal como explicamos en la
sección 9.

---

## 8. Cómo evitar perder comprobantes

Hay un escenario que rompe integraciones con más frecuencia de la que
imaginarías: emites un comprobante, la conexión se corta antes de que llegue la
respuesta, y te quedas sin saber si el comprobante llegó a existir o no.
Ponemos a tu disposición tres herramientas para resolverlo, y te las
presentamos en el orden en que recomendamos usarlas:

1. **La cabecera `Idempotency-Key`**, que explicamos en detalle en la sección 9.
   Te permite repetir la petición y recibir la respuesta original.
2. **El campo `external_id`**, donde puedes guardar el identificador de la venta
   en tu propio sistema. Después podrás recuperar el comprobante consultando
   `GET /api/v1/comprobantes?external_id=venta-8842`.
3. **Los webhooks**, que describimos en la sección 12. Gracias a ellos el
   resultado llega a tu sistema aunque hayas perdido la respuesta original.

El listado de comprobantes admite los filtros `external_id` y `estado`, y
devuelve los resultados paginados de 25 en 25, ordenados con los más recientes
primero.

### Los estados por los que pasa un comprobante

| Estado | ¿Es final? | Qué significa |
|---|---|---|
| `pendiente` | No | El comprobante quedó registrado, pero todavía no se ha procesado. |
| `firmado` | No | El XML ya está firmado, pero aún no se ha enviado al SRI. |
| `recibido` | No | El SRI lo recibió correctamente; falta que resuelva la autorización. |
| `autorizado` | **Sí** | Todo salió bien. El comprobante tiene número de autorización y puedes descargar su RIDE. |
| `devuelto` | **Sí** | El SRI lo rechazó en la etapa de recepción. Corresponde corregirlo y reintentar. |
| `no_autorizado` | **Sí** | El SRI lo rechazó en la etapa de autorización. Corresponde corregirlo y reintentar. |
| `fallido` | **Sí** | Se produjo un error en el proceso interno, como un problema de firma o de red. Corresponde reintentar. |

Para ahorrarte el trabajo de mantener esta tabla en tu código, cada comprobante
incluye un campo booleano llamado `estadoFinal`. Cuando su valor es `false`,
significa simplemente que conviene volver a consultar más adelante.

---

## 9. La idempotencia, tu red de seguridad

Te recomendamos encarecidamente enviar la cabecera `Idempotency-Key` en todas
tus emisiones y en todos tus reintentos. El valor natural para esta cabecera es
el identificador de la venta en tu sistema:

```
Idempotency-Key: venta-8842
```

El comportamiento es el siguiente:

- Si repites la petición con **la misma clave y el mismo contenido**, recibirás
  la respuesta original acompañada de la cabecera `Idempotency-Replayed: true`,
  y el comprobante no se duplicará.
- Si usas **la misma clave con un contenido distinto**, recibirás una respuesta
  `409`, porque interpretamos que se trata de un error de programación.
- Si la petición original **todavía se está procesando**, también recibirás un
  `409`. En ese caso, espera unos segundos y vuelve a intentarlo.
- Las claves **expiran a las 24 horas** y son únicas dentro de cada
  contribuyente.
- Solo almacenamos los desenlaces de negocio. Ante una respuesta `5xx`, `401`,
  `403` o `429`, la clave queda liberada para que puedas reintentar sin
  obstáculos.

---

## 10. La integración de partners

Esta sección aplica únicamente si dispones de una cuenta de partner.

### 10.1 Cómo aprovisionar a un cliente

```bash
curl -X POST https://fe.test/api/partner/v1/contribuyentes \
  -H "Authorization: Bearer $TOKEN_PARTNER" \
  -H 'Content-Type: application/json' \
  -d '{
    "ruc": "0992479248001",
    "razon_social": "Cliente S.A.",
    "nombre_comercial": "MiCliente",
    "limite_mensual": 500
  }'
```

Recibirás una respuesta `201` cuando el contribuyente se cree, y una respuesta
`200` si ese RUC ya estaba aprovisionado previamente por ti, ya que la operación
es idempotente por RUC. El valor de `data.id` que viene en la respuesta es el
identificador único que deberás enviar en la cabecera `X-Contribuyente`, así que
te sugerimos guardarlo junto al registro del cliente en tu propia base de datos.

Si recibes una respuesta `409`, significa que ese RUC ya tiene una cuenta
directa en el servicio y, por tanto, no te corresponde aprovisionarlo. Ese caso
se resuelve mediante una solicitud de vinculación, que explicamos en la sección
10.4.

### 10.2 Cómo emitir en nombre de un cliente

Utilizas exactamente la misma API de emisión que hemos descrito, añadiendo una
sola cabecera:

```
X-Contribuyente: {uuid del contribuyente gestionado}
```

Esta cabecera se aplica a **todos** los endpoints que viven bajo `/api/v1/…`:
la emisión, las consultas, el listado, la descarga del RIDE, la carga del
certificado y la gestión de webhooks. Si la omites, recibirás una respuesta
`400` indicándote que falta. Y si envías un identificador que no corresponde a
uno de tus clientes gestionados, recibirás una respuesta `404`, porque desde tu
punto de vista un contribuyente ajeno simplemente no existe.

### 10.3 Cómo obtener el certificado del cliente sin manipular su clave privada

```bash
curl -X POST https://fe.test/api/partner/v1/contribuyentes/{uuid}/enlace-certificado \
  -H "Authorization: Bearer $TOKEN_PARTNER"
```

La respuesta te entrega una URL firmada y temporal:

```json
{ "url": "https://fe.test/certificado/9f1c…?signature=…", "expiraEn": "2026-07-12T10:00:00-05:00" }
```

Comparte esa dirección con tu cliente para que sea él quien suba su archivo
`.p12` y escriba su contraseña **directamente en nuestro servicio**. Puedes
regenerar el enlace tantas veces como necesites, y cada uno de ellos caduca por
su cuenta pasado el tiempo indicado.

Existe una alternativa, que consiste en usar
`PUT /api/v1/contribuyente/certificado` junto con la cabecera
`X-Contribuyente` en caso de que ya tengas el archivo en tu poder. Sin embargo,
te animamos a preferir el enlace hospedado siempre que sea posible, porque
reduce de forma significativa tu exposición a la custodia de claves privadas que
no te pertenecen.

### 10.4 Cómo vincular un RUC que ya tiene cuenta propia

Cuando el aprovisionamiento te devuelve un `409`, el camino a seguir es
solicitar una vinculación:

```bash
curl -X POST https://fe.test/api/partner/v1/vinculaciones \
  -H "Authorization: Bearer $TOKEN_PARTNER" \
  -d '{"ruc": "0992479248001"}'
```

Esto crea una solicitud que **el dueño de la cuenta deberá aprobar desde su
propio panel**. En el momento en que la apruebe, el contribuyente pasará a estar
gestionado por ti, con todo lo que ello implica: podrás emitir en su nombre y
sus emisiones consumirán tu cuota pool. A partir de ese momento, la consulta
mediante `GET` incluirá el identificador del contribuyente.

Las solicitudes pueden encontrarse en estado `pendiente`, `aprobada` o
`rechazada`, y la operación es idempotente mientras la solicitud siga pendiente.
Si recibes una respuesta `404`, significa que ese RUC no está registrado en el
servicio y que, por tanto, lo que correspondía era aprovisionarlo.

---

## 11. Cómo limitar la cuota de tus contribuyentes

Si administras varios clientes, tarde o temprano necesitarás controlar cuántos
comprobantes puede emitir cada uno. Esta sección explica cómo hacerlo y, sobre
todo, cómo se comporta el sistema una vez configurado.

### 11.1 El modelo de cuotas

Tu cuenta de partner dispone de una **cuota pool mensual** que comparten todos
tus contribuyentes gestionados. Sobre ese pool común, cada contribuyente puede
tener además un **sublímite propio**, que se define en el campo
`limite_mensual`.

La relación entre ambos es de acotación hacia abajo y nunca de ampliación. Dicho
con un ejemplo: si tu pool mensual es de 1.000 comprobantes y asignas a un
cliente un `limite_mensual` de 5.000, ese cliente seguirá topándose con el
límite de 1.000, porque el sublímite jamás puede superar lo que permite el pool.
Cuando el valor de `limite_mensual` es `null`, ese contribuyente no tiene
sublímite propio y lo único que lo restringe es la cuota pool compartida.

### 11.2 Cómo establecer el sublímite al dar de alta al cliente

Basta con incluir el campo `limite_mensual` en el momento del aprovisionamiento:

```bash
curl -X POST https://fe.test/api/partner/v1/contribuyentes \
  -H "Authorization: Bearer $TOKEN_PARTNER" \
  -H 'Content-Type: application/json' \
  -d '{
    "ruc": "0992479248001",
    "razon_social": "Cliente S.A.",
    "limite_mensual": 500
  }'
```

### 11.3 Cómo modificar el sublímite más adelante

Puedes ajustarlo en cualquier momento mediante una petición `PATCH`:

```bash
curl -X PATCH https://fe.test/api/partner/v1/contribuyentes/{uuid} \
  -H "Authorization: Bearer $TOKEN_PARTNER" \
  -H 'Content-Type: application/json' \
  -d '{"limite_mensual": 500}'
```

Para retirar el sublímite y dejar que el cliente solo esté acotado por el pool,
envía el valor `null` de forma explícita:

```bash
curl -X PATCH https://fe.test/api/partner/v1/contribuyentes/{uuid} \
  -H "Authorization: Bearer $TOKEN_PARTNER" \
  -H 'Content-Type: application/json' \
  -d '{"limite_mensual": null}'
```

Conviene que distingas bien estos dos casos, porque el servicio los trata de
manera diferente: enviar `null` **retira** el sublímite, mientras que **omitir**
la clave por completo deja el valor anterior intacto. Esta distinción es
deliberada y te permite actualizar la razón social de un cliente, por ejemplo,
sin tocar accidentalmente su cuota. El campo acepta números enteros con un valor
mínimo de 1.

### 11.4 Qué ocurre en el momento de emitir

Cuando llega una petición de emisión, el servicio evalúa los límites en
cascada, deteniéndose en el primero que se haya alcanzado:

1. Primero comprueba si ese contribuyente tiene un `limite_mensual` propio y si
   ya lo ha alcanzado. Si es así, la emisión se rechaza.
2. Si no tiene sublímite, o todavía no lo ha alcanzado, comprueba entonces si
   tú, como partner, has agotado tu cuota pool. Si es así, la emisión también se
   rechaza.

En cualquiera de los dos casos, la respuesta será un `429`.

### 11.5 Tres detalles que te evitarán sorpresas

**El conteo incluye los comprobantes rechazados.** El cálculo de las emisiones
del mes cuenta todos los registros creados dentro del mes calendario en curso,
sin filtrar por su estado final. Esto significa que un comprobante devuelto o
fallido consume cuota igualmente, porque el registro se crea en el momento de
emitir. Los reintentos, en cambio, no suman, ya que reutilizan el registro
original.

**El mensaje del `429` no distingue cuál fue la causa.** Las tres situaciones
posibles —el sublímite del contribuyente gestionado, la cuota pool del partner y
el plan de un contribuyente directo— devuelven hoy el mismo texto, que es *"La
cuota mensual del plan está agotada."*. Si piensas mostrar ese mensaje a tu
cliente, ten en cuenta que a partir de él no podrás deducir cuál de los tres
límites fue el que se alcanzó.

**No existe aviso previo ni webhook de cuota.** Si quieres adelantarte y avisar
a tu cliente antes de que se quede sin poder facturar, deberás consultar
periódicamente el listado de gestionados:

```bash
curl https://fe.test/api/partner/v1/contribuyentes \
  -H "Authorization: Bearer $TOKEN_PARTNER"
```

Cada elemento de la respuesta incluye los campos `emisionesDelMes` y
`limiteMensual`, que son los que necesitas para calcular cuánto margen le queda
a cada cliente. A día de hoy, esta consulta periódica es la única manera de
anticiparse a un `429`.

---

## 12. Los webhooks

Los webhooks son la alternativa recomendable a consultar el estado de forma
repetida, y resultan especialmente útiles cuando trabajas con la modalidad de
emisión asíncrona.

### Cómo registrar un endpoint

```bash
curl -X POST https://fe.test/api/v1/webhooks \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{
    "url": "https://mi-sistema.com/hooks/fe",
    "eventos": ["comprobante.autorizado", "comprobante.devuelto"]
  }'
```

La respuesta incluye el campo `secreto`, y es fundamental que entiendas que
**solo se muestra en esta ocasión**. Guárdalo en un lugar seguro, porque no
volveremos a mostrártelo y lo necesitarás para verificar la firma de cada
entrega.

Si eres partner, dispones además de `POST /api/partner/v1/webhooks`, que
registra un endpoint suscrito a los eventos de **todos** tus contribuyentes
gestionados. En ese caso, cada payload identifica a qué contribuyente
corresponde el evento.

### Los eventos disponibles

Son cinco: `comprobante.autorizado`, `comprobante.devuelto`,
`comprobante.no_autorizado`, `comprobante.fallido` y `certificado.por_vencer`.

Queremos llamar tu atención especialmente sobre el último. Suscribirte a
`certificado.por_vencer` es la manera más sencilla de evitar uno de los
incidentes más frustrantes y más caros que existen en este ámbito: un
certificado que caduca un viernes por la tarde y deja a un negocio sin poder
facturar durante todo el fin de semana.

### La forma del payload

```json
{
  "evento": "comprobante.autorizado",
  "publicadoEn": "2026-07-10T14:32:11-05:00",
  "contribuyente": { "id": "9f1c…", "ruc": "0922596788001" },
  "datos": { "id": "…", "tipo": "factura", "estado": "autorizado", "…": "…" }
}
```

El objeto `datos` sigue la misma estructura que el esquema `Comprobante`, con la
salvedad de que **no incluye el campo `xmlFirmado`**. El XML no viaja dentro del
evento por razones de tamaño; si lo necesitas, descárgalo a través de la API
usando el `id` que recibiste.

### Cómo verificar la firma

Cada entrega llega acompañada de las cabeceras `X-Evento`, `X-Entrega` (que
contiene el identificador de esa entrega concreta) y las dos que permiten
verificar su autenticidad:

```
X-Firma: v1={hmac_sha256(secreto, timestamp + "." + cuerpo_crudo)}
X-Firma-Timestamp: {unix}
```

Te pedimos que **verifiques siempre la firma**, que lo hagas sobre el cuerpo
crudo de la petición —es decir, antes de convertirlo a un objeto— y que emplees
una comparación en tiempo constante. Conviene además rechazar los timestamps
demasiado antiguos, ya que es la forma habitual de cortar los ataques de
repetición.

Así quedaría en PHP:

```php
$cuerpo = file_get_contents('php://input'); // crudo, sin json_decode todavía
$timestamp = $_SERVER['HTTP_X_FIRMA_TIMESTAMP'] ?? '';
$recibida = $_SERVER['HTTP_X_FIRMA'] ?? '';

if (abs(time() - (int) $timestamp) > 300) {
    http_response_code(400);
    exit;
}

$esperada = 'v1='.hash_hmac('sha256', "{$timestamp}.{$cuerpo}", $secreto);

if (! hash_equals($esperada, $recibida)) {
    http_response_code(401);
    exit;
}

http_response_code(200); // responde cuanto antes; el procesamiento va después
```

Y así en Node con Express, donde el detalle importante es usar `express.raw()`
en lugar de `express.json()` para tener acceso al cuerpo sin transformar:

```javascript
const crypto = require('crypto');

app.post('/hooks/fe', express.raw({ type: 'application/json' }), (req, res) => {
  const ts = req.get('X-Firma-Timestamp');
  const recibida = req.get('X-Firma') ?? '';

  if (Math.abs(Date.now() / 1000 - Number(ts)) > 300) return res.sendStatus(400);

  const esperada = 'v1=' + crypto
    .createHmac('sha256', secreto)
    .update(`${ts}.${req.body}`)
    .digest('hex');

  const a = Buffer.from(esperada);
  const b = Buffer.from(recibida);
  if (a.length !== b.length || !crypto.timingSafeEqual(a, b)) return res.sendStatus(401);

  res.sendStatus(200);
  procesarEnSegundoPlano(JSON.parse(req.body));
});
```

### Cómo funcionan la entrega y los reintentos

Responde con un código `2xx` **lo antes posible** y deja el procesamiento
pesado para un segundo plano. El servicio corta la conexión a los **10
segundos** y contabiliza ese corte como un intento fallido, de modo que no te
conviene hacer trabajo costoso antes de responder.

Cualquier respuesta que no sea `2xx` —y también los timeouts— provoca un
reintento con espera creciente: **1 minuto, 5 minutos, 30 minutos y 2 horas,
hasta un máximo de 5 intentos**. Agotados todos ellos, la entrega queda marcada
como `fallida`.

Es importante que tu endpoint sea **idempotente**: utiliza el valor de la
cabecera `X-Entrega` para descartar duplicados, porque una respuesta `2xx` que
tardó demasiado en llegar puede acabar reintentándose.

Para auditar lo ocurrido tienes disponible
`GET /api/v1/webhooks/{id}/entregas`, que devuelve cada intento con su `estado`,
el número de `intentos`, el `codigoHttp` recibido, el `error` si lo hubo y el
`payload` que se envió. Este endpoint es el primer lugar donde deberías mirar
cuando tengas la sensación de que los webhooks no están llegando.

---

## 13. La descarga del RIDE

```
GET /api/v1/comprobantes/{id}/ride
```

El RIDE es la representación impresa del documento electrónico, tal como la
define el Anexo 2 de la ficha técnica del SRI. Lo generamos en PDF a partir del
XML firmado y lo dejamos en caché después de la primera descarga, de modo que
las siguientes son inmediatas.

Si el comprobante todavía no ha sido autorizado, recibirás una respuesta `409`.
Y si el comprobante no existe, o su XML ya no está disponible, recibirás un
`404`.

---

## 14. Referencia rápida

### Endpoints de emisión, bajo `/api/v1`

| Método | Ruta | Para qué sirve |
|---|---|---|
| `POST` | `/tokens` | Obtener un token (solo usuarios directos) |
| `PUT` | `/contribuyente/certificado` | Cargar el archivo `.p12` |
| `POST` | `/comprobantes` | Emitir un comprobante (admite `?async=1`) |
| `GET` | `/comprobantes` | Listar emisiones (filtros `external_id` y `estado`) |
| `GET` | `/comprobantes/{id}` | Consultar el estado de una emisión |
| `POST` | `/comprobantes/{id}/reintentar` | Reenviar un comprobante rechazado |
| `GET` | `/comprobantes/{id}/ride` | Descargar el PDF |
| `GET`·`POST`·`DELETE` | `/webhooks`, `/webhooks/{id}` | Gestionar los endpoints de webhook |
| `GET` | `/webhooks/{id}/entregas` | Auditar las entregas realizadas |

### Endpoints de gestión de partners, bajo `/api/partner/v1`

| Método | Ruta | Para qué sirve |
|---|---|---|
| `POST` | `/contribuyentes` | Aprovisionar un cliente (idempotente por RUC) |
| `GET` | `/contribuyentes` | Listar los gestionados (admite filtro `ruc`) |
| `PATCH` | `/contribuyentes/{uuid}` | Editar los datos y el `limite_mensual` |
| `POST` | `/contribuyentes/{uuid}/enlace-certificado` | Generar el enlace hospedado de carga |
| `POST`·`GET` | `/vinculaciones` | Solicitar y consultar vinculaciones de RUC |
| `GET`·`POST`·`DELETE` | `/webhooks`, `/webhooks/{id}` | Webhooks globales del partner |

### Cabeceras

| Cabecera | Cuándo se usa | Qué efecto tiene |
|---|---|---|
| `Authorization: Bearer …` | En todas las peticiones | Autentica al emisor de la llamada |
| `Idempotency-Key` | En emisiones y reintentos | Evita duplicados durante 24 horas |
| `X-Contribuyente` | Solo con token de partner | Indica en nombre de quién actúas |

### Códigos de respuesta

| Código | Qué significa |
|---|---|
| `200` / `201` | La operación se completó correctamente |
| `202` | La emisión quedó encolada (modalidad `?async=1`) |
| `204` | El certificado se guardó correctamente |
| `400` | Falta la cabecera `X-Contribuyente` (usando token de partner) |
| `401` | No enviaste token, o el token no es válido |
| `403` | Usaste un token de usuario en el plano de partner |
| `404` | El recurso no existe, o pertenece a otro contribuyente |
| `409` | Falta el certificado · el estado no admite reintento · el RUC ya tiene otra cuenta · reutilizaste una clave de idempotencia con contenido distinto |
| `422` | El contenido es inválido, o el SRI devolvió o no autorizó el comprobante |
| `429` | Superaste el límite de peticiones, o se agotó la cuota mensual |

---

## 15. Lista de verificación antes de salir a producción

- [ ] Has probado el flujo completo en el **ambiente 1 (pruebas)** utilizando el
      certificado real.
- [ ] Has cambiado el campo `ambiente` a `"2"` en el payload al pasar a
      producción.
- [ ] Envías la cabecera `Idempotency-Key` en todas las emisiones y reintentos.
- [ ] Envías el campo `external_id` con el identificador de tu venta en todas
      las emisiones.
- [ ] Utilizas la modalidad **asíncrona** si emites dentro del flujo de una
      venta.
- [ ] Contemplas los siete estados posibles, o al menos te apoyas en el campo
      `estadoFinal`.
- [ ] Tienes implementado el flujo de reintento para los estados `devuelto`,
      `no_autorizado` y `fallido`.
- [ ] Tu endpoint de webhook **verifica la firma** empleando una comparación en
      tiempo constante.
- [ ] Tu endpoint de webhook es **idempotente** apoyándose en la cabecera
      `X-Entrega`.
- [ ] Estás suscrito al evento `certificado.por_vencer` y la alerta llega
      efectivamente a una persona.
- [ ] Tus secuenciales avanzan sin huecos ni repeticiones dentro de cada serie.
- [ ] El token no está en el código fuente ni en el control de versiones.
- [ ] Registras en tus logs los `422` de validación junto con el payload, para
      poder diagnosticarlos después.

---

## 16. Preguntas frecuentes

**¿Puedo enviar el certificado en cada emisión?**
No. El certificado se carga una sola vez y a partir de entonces el servicio lo
toma del almacén cifrado en cada emisión.

**¿Quién lleva el control del secuencial?**
Lo llevas tú. El servicio no lo genera automáticamente: se limita a validar que
tenga 9 dígitos y que la clave de acceso resultante no esté repetida.

**¿Un reintento consume cuota?**
No. El reintento reutiliza el registro original, de modo que no incrementa el
conteo del mes.

**Emití un comprobante y perdí la respuesta. ¿Debería emitirlo de nuevo?**
Si enviaste la cabecera `Idempotency-Key`, repite exactamente la misma petición
y recibirás la respuesta original sin duplicar nada. Si no la enviaste, búscalo
antes por `external_id` para comprobar si llegó a crearse.

**¿Puedo cambiar el RUC de un contribuyente?**
No, el RUC es inmutable, porque es lo que identifica al emisor ante el SRI. Un
RUC distinto constituye un contribuyente distinto.

**Uno de mis clientes ya usa el servicio por su cuenta y quiero gestionarlo yo.**
Envía una solicitud de vinculación, tal como describimos en la sección 10.4. Tu
cliente deberá aprobarla desde su propio panel.

**¿Dónde puedo consultar el contrato exacto de un campo concreto?**
En la especificación OpenAPI que publicamos en `/docs`.
