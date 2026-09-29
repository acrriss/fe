{{--
    Cuadro «Forma de pago / Valor» del Anexo 2 (factura, nota de débito y
    liquidación de compra): una fila por <pago>, con el código y el nombre
    de la Tabla 24.

    El plazo no sale en el ejemplo del Anexo 2; se imprime cuando viene
    porque identifica la venta a crédito (§9.19 permite datos adicionales).

    @var array<int, App\Sri\Data\PagoData> $pagos
--}}
@if ($pagos !== [])
    <table class="tabla formas-pago mt">
        <thead>
            <tr>
                <th>Forma de pago</th>
                <th class="num">Valor</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pagos as $pago)
                <tr>
                    <td>
                        {{ $pago->formaPago }} - {{ mb_strtoupper(App\Sri\Catalogos\FormasPago::nombre($pago->formaPago) ?? '') }}
                        @if ($pago->plazo !== null)
                            <br><span class="etiqueta">Plazo: {{ $pago->plazo }} {{ $pago->unidadTiempo }}</span>
                        @endif
                    </td>
                    <td class="num">{{ $pago->total }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
