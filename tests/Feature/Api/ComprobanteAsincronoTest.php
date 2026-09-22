<?php

use App\Jobs\ProcesarComprobanteJob;
use App\Models\Comprobante;
use App\Models\Contribuyente;
use App\Sri\Contracts\SriGateway;
use App\Sri\Contracts\XmlSigner;
use App\Sri\Data\Factura\FacturaData;
use App\Sri\Enums\EstadoComprobante;
use App\Sri\Firma\FakeXmlSigner;
use App\Sri\Gateways\FakeSriGateway;
use App\Sri\Pipeline\EmitirComprobante;
use App\Sri\Registro\RegistroDeEmision;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->gateway = new FakeSriGateway;
    $this->app->instance(SriGateway::class, $this->gateway);
    $this->app->instance(XmlSigner::class, new FakeXmlSigner);
    config()->set('sri.autorizacion.espera_ms', 0);
    Storage::fake();
});

/**
 * Registro pendiente perteneciente a un contribuyente con certificado.
 */
function registro_pendiente(): Comprobante
{
    return Comprobante::factory()
        ->for(Contribuyente::factory()->conCertificado(), 'contribuyente')
        ->create();
}

/**
 * El job que la emisión asíncrona acaba de encolar, para ejecutarlo a mano
 * (Queue::fake() debe estar activo).
 */
function procesar_job_encolado(): ProcesarComprobanteJob
{
    $encolados = [];

    Queue::assertPushed(ProcesarComprobanteJob::class, function (ProcesarComprobanteJob $job) use (&$encolados): bool {
        $encolados[] = $job;

        return true;
    });

    return $encolados[0];
}

describe('emisión asíncrona', function () {
    it('encola el job y responde 202 con el id para consultar', function () {
        actuar_como_contribuyente();
        Queue::fake();

        $respuesta = $this->postJson(
            route('api.v1.comprobantes.emitir', ['async' => 1]),
            payload_emision('factura'),
        );

        $respuesta->assertStatus(202)
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.tipo', 'factura')
            ->assertJsonStructure(['data' => ['id', 'estado', 'secuencial']]);

        Queue::assertPushed(ProcesarComprobanteJob::class, 1);

        $registro = Comprobante::where('uuid', $respuesta->json('data.id'))->first();
        expect($registro)->not->toBeNull()
            ->and($registro->estado)->toBe(EstadoComprobante::Pendiente);
    });

    it('el job lee el certificado del contribuyente y completa el registro', function () {
        $registro = registro_pendiente();

        new ProcesarComprobanteJob(
            registro: $registro,
            dataClass: FacturaData::class,
            payloadComprobante: payload_comprobante('factura'),
        )->handle(app(EmitirComprobante::class), app(RegistroDeEmision::class));

        $registro->refresh();

        expect($registro->estado)->toBe(EstadoComprobante::Autorizado)
            ->and($registro->clave_acceso)->toMatch('/^\d{49}$/')
            ->and($registro->numero_autorizacion)->not->toBeNull()
            ->and($registro->xml_path)->not->toBeNull();

        Storage::assertExists($registro->xml_path);
        expect(Storage::get($registro->xml_path))->toContain('<factura id="comprobante"');
    });

    it('el job registra la devolución como fallo de negocio (sin reintentos)', function () {
        $this->gateway->devolverComprobantes();
        $registro = registro_pendiente();

        new ProcesarComprobanteJob(
            registro: $registro,
            dataClass: FacturaData::class,
            payloadComprobante: payload_comprobante('factura'),
        )->handle(app(EmitirComprobante::class), app(RegistroDeEmision::class));

        $registro->refresh();

        expect($registro->estado)->toBe(EstadoComprobante::Devuelto)
            ->and($registro->clave_acceso)->toMatch('/^\d{49}$/') // §5.10: la clave se conserva para reintentar
            ->and(implode(' ', $registro->mensajes))->toContain('ERROR SECUENCIAL REGISTRADO');
    });

    it('failed() marca el registro como fallido ante errores técnicos', function () {
        $registro = registro_pendiente();

        new ProcesarComprobanteJob(
            registro: $registro,
            dataClass: FacturaData::class,
            payloadComprobante: payload_comprobante('factura'),
        )->failed(new RuntimeException('SRI caído'));

        expect($registro->refresh()->estado)->toBe(EstadoComprobante::Fallido);
    });

    it('el job viaja cifrado en la cola', function () {
        expect(ProcesarComprobanteJob::class)->toImplement(ShouldBeEncrypted::class);
    });
});

describe('consulta de estado', function () {
    it('devuelve el estado de una emisión pendiente', function () {
        $contribuyente = actuar_como_contribuyente();
        $registro = Comprobante::factory()->create(['contribuyente_id' => $contribuyente->id]);

        $this->getJson(route('api.v1.comprobantes.mostrar', $registro))
            ->assertSuccessful()
            ->assertJsonPath('data.id', $registro->uuid)
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.estadoFinal', false)
            ->assertJsonMissingPath('data.xmlFirmado');
    });

    it('devuelve autorización y XML cuando está autorizado', function () {
        $contribuyente = actuar_como_contribuyente();
        $registro = Comprobante::factory()->autorizado()->create(['contribuyente_id' => $contribuyente->id]);
        Storage::put($path = "comprobantes/{$registro->clave_acceso}.xml", '<factura>firmada</factura>');
        $registro->update(['xml_path' => $path]);

        $respuesta = $this->getJson(route('api.v1.comprobantes.mostrar', $registro));

        $respuesta->assertSuccessful()
            ->assertJsonPath('data.estado', 'autorizado')
            ->assertJsonPath('data.estadoFinal', true)
            ->assertJsonPath('data.autorizacion.numero', $registro->numero_autorizacion);

        expect(base64_decode($respuesta->json('data.xmlFirmado')))->toBe('<factura>firmada</factura>');
    });

    it('responde 404 para un id desconocido', function () {
        actuar_como_contribuyente();

        $this->getJson(route('api.v1.comprobantes.mostrar', 'no-existe'))
            ->assertNotFound();
    });
});

describe('persistencia del flujo síncrono', function () {
    it('registra la emisión autorizada a nombre del contribuyente', function () {
        $contribuyente = actuar_como_contribuyente();

        $respuesta = $this->postJson(route('api.v1.comprobantes.emitir'), payload_emision('factura'));

        $respuesta->assertSuccessful();

        $registro = Comprobante::where('uuid', $respuesta->json('id'))->first();

        expect($registro->contribuyente_id)->toBe($contribuyente->id)
            ->and($registro->estado)->toBe(EstadoComprobante::Autorizado)
            ->and($registro->clave_acceso)->toBe($respuesta->json('claveAcceso'))
            ->and($registro->importe_total)->toBe('115.00');

        Storage::assertExists($registro->xml_path);
    });

    it('registra la emisión devuelta con sus mensajes', function () {
        actuar_como_contribuyente();
        $this->gateway->devolverComprobantes();

        $respuesta = $this->postJson(route('api.v1.comprobantes.emitir'), payload_emision('factura'));

        $respuesta->assertUnprocessable();

        $registro = Comprobante::where('uuid', $respuesta->json('id'))->first();

        expect($registro->estado)->toBe(EstadoComprobante::Devuelto)
            ->and($registro->mensajes)->not->toBeEmpty();
    });
});

/*
 * §16: el POS imprime el ticket al cobrar, mucho antes de que el SRI
 * resuelva. La clave de acceso no depende del SRI —solo de datos que el
 * payload ya trae—, así que se calcula y se entrega al encolar.
 */
describe('la clave de acceso se entrega al encolar', function () {
    it('el 202 ya trae la clave, y queda persistida en el registro', function () {
        actuar_como_contribuyente();
        Queue::fake();

        $respuesta = $this->postJson(
            route('api.v1.comprobantes.emitir', ['async' => 1]),
            payload_emision('factura'),
        );

        $clave = $respuesta->assertStatus(202)->json('data.claveAcceso');

        expect($clave)->toMatch('/^\d{49}$/');

        $registro = Comprobante::where('uuid', $respuesta->json('data.id'))->first();
        expect($registro->clave_acceso)->toBe($clave);
    });

    it('el job emite con LA MISMA clave que se entregó', function (string $tipo) {
        actuar_como_contribuyente();
        Queue::fake();

        $respuesta = $this->postJson(
            route('api.v1.comprobantes.emitir', ['async' => 1]),
            payload_emision($tipo),
        );

        $clave = $respuesta->assertStatus(202)->json('data.claveAcceso');

        procesar_job_encolado()->handle(app(EmitirComprobante::class), app(RegistroDeEmision::class));

        $registro = Comprobante::where('uuid', $respuesta->json('data.id'))->first()->refresh();

        expect($registro->estado)->toBe(EstadoComprobante::Autorizado)
            ->and($registro->clave_acceso)->toBe($clave)
            // lo que el cliente imprimió es lo que viaja en el XML firmado
            ->and(Storage::get($registro->xml_path))->toContain("<claveAcceso>{$clave}</claveAcceso>");
    })->with(['factura', 'notaCredito', 'notaDebito', 'comprobanteRetencion', 'guiaRemision', 'liquidacionCompra']);

    /*
     * Antes, el job se despachaba sin clave y la sorteaba él: un reintento
     * técnico (timeout del SRI que quizá sí llegó) generaba un código
     * numérico nuevo y, con él, DOS claves distintas para el mismo
     * secuencial.
     */
    it('un reintento del job no cambia la clave', function () {
        actuar_como_contribuyente();
        Queue::fake();

        $respuesta = $this->postJson(
            route('api.v1.comprobantes.emitir', ['async' => 1]),
            payload_emision('factura'),
        );

        $clave = $respuesta->json('data.claveAcceso');
        $job = procesar_job_encolado();

        expect($job->claveAcceso)->toBe($clave);

        $job->handle(app(EmitirComprobante::class), app(RegistroDeEmision::class));
        $primera = Comprobante::where('uuid', $respuesta->json('data.id'))->first()->clave_acceso;

        $job->handle(app(EmitirComprobante::class), app(RegistroDeEmision::class));
        $segunda = Comprobante::where('uuid', $respuesta->json('data.id'))->first()->clave_acceso;

        expect($primera)->toBe($clave)->and($segunda)->toBe($clave);
    });

    it('el flujo síncrono también nace con la clave persistida', function () {
        actuar_como_contribuyente();

        $respuesta = $this->postJson(route('api.v1.comprobantes.emitir'), payload_emision('factura'));

        $registro = Comprobante::where('uuid', $respuesta->json('id'))->first();

        expect($registro->clave_acceso)->toBe($respuesta->json('claveAcceso'));
    });
});
