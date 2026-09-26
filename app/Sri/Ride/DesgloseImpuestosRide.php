<?php

namespace App\Sri\Ride;

use App\Sri\Data\TotalImpuestoData;

/**
 * Las filas de la tabla de totales del RIDE, tal como viajaron en
 * <totalConImpuestos>: un grupo por tarifa, con su base y su valor.
 *
 * El Anexo 2 las ordena en dos bloques separados por el subtotal general
 * —primero la base de cada tarifa («Subtotal 15%»), después el importe de
 * cada impuesto («IVA 15%»)— y así se lee la suma de arriba abajo. No se
 * calcula nada: el RIDE tiene que decir exactamente lo que dice el XML.
 */
final readonly class DesgloseImpuestosRide
{
    /**
     * @param  array<int, TotalImpuestoData>  $totalConImpuestos
     */
    public function __construct(private array $totalConImpuestos) {}

    /**
     * La base imponible de cada tarifa. Solo las que trae el comprobante:
     * el Anexo 2 permite «visualizar solo los subtotales que fueron
     * llenados».
     *
     * @return list<array{etiqueta: string, valor: string}>
     */
    public function subtotalesPorTarifa(): array
    {
        return array_map(
            fn (TotalImpuestoData $impuesto): array => [
                'etiqueta' => "Subtotal {$impuesto->etiqueta()}",
                'valor' => $impuesto->baseImponible,
            ],
            array_values($this->totalConImpuestos),
        );
    }

    /**
     * El importe de cada impuesto que suma algo. Las tarifas que valen
     * cero —0%, exento, no objeto— se omiten: «IVA 0% 0.00» no dice nada
     * que su subtotal no diga ya. Se filtra por importe y no por código
     * para que una categoría nueva de valor cero no exija tocar esto.
     *
     * @return list<array{etiqueta: string, valor: string}>
     */
    public function impuestosConValor(): array
    {
        $impuestos = array_filter(
            $this->totalConImpuestos,
            fn (TotalImpuestoData $impuesto): bool => ! self::esCero($impuesto->valor),
        );

        return array_map(
            fn (TotalImpuestoData $impuesto): array => [
                'etiqueta' => $impuesto->etiqueta(),
                'valor' => $impuesto->valor,
            ],
            array_values($impuestos),
        );
    }

    /**
     * Comparación decimal sobre la cadena: el importe jamás pasa por float.
     */
    private static function esCero(string $importe): bool
    {
        return is_numeric($importe) && bccomp($importe, '0', 6) === 0;
    }
}
