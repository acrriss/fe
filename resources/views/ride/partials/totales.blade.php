{{--
    Tabla de totales en el orden del Anexo 2: subtotal de cada tarifa,
    subtotal sin impuestos, descuento, importe de cada impuesto y total.

    La propina no aparece en el ejemplo del Anexo 2; va justo antes del
    total porque no forma parte de ninguna base imponible (§9.19 permite
    imprimir datos adicionales).

    @var array<int, App\Sri\Data\TotalImpuestoData> $totalConImpuestos
    @var string $totalSinImpuestos
    @var string|null $totalDescuento  solo en la factura, como en el Anexo 2
    @var string|null $propina
    @var string $etiquetaTotal
    @var string $total
--}}
@php($desglose = new App\Sri\Ride\DesgloseImpuestosRide($totalConImpuestos))

<table class="totales mt">
    @foreach ($desglose->subtotalesPorTarifa() as $linea)
        <tr>
            <td class="etiqueta">{{ $linea['etiqueta'] }}</td>
            <td class="num">{{ $linea['valor'] }}</td>
        </tr>
    @endforeach
    <tr class="subtotal">
        <td>Subtotal sin impuestos</td>
        <td class="num">{{ $totalSinImpuestos }}</td>
    </tr>
    @if (($totalDescuento ?? null) !== null)
        <tr>
            <td class="etiqueta">Descuento</td>
            <td class="num">{{ $totalDescuento }}</td>
        </tr>
    @endif
    @foreach ($desglose->impuestosConValor() as $linea)
        <tr>
            <td class="etiqueta">{{ $linea['etiqueta'] }}</td>
            <td class="num">{{ $linea['valor'] }}</td>
        </tr>
    @endforeach
    @if (($propina ?? null) !== null)
        <tr>
            <td class="etiqueta">Propina</td>
            <td class="num">{{ $propina }}</td>
        </tr>
    @endif
    <tr class="total-final">
        <td>{{ $etiquetaTotal }}</td>
        <td class="num">{{ $total }}</td>
    </tr>
</table>
