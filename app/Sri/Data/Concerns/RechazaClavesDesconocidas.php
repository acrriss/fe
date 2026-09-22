<?php

namespace App\Sri\Data\Concerns;

use App\Sri\Exceptions\DatoInvalido;

/**
 * Convierte en error 422 lo que laravel-data descartaría en silencio: una
 * clave que el DTO no declara.
 *
 * Sin esto, un `codigoAuxiliar` mal escrito —o un campo del SRI que aún no
 * soportamos— se ignoraba sin avisar y el comprobante se autorizaba
 * incompleto; el integrador creía cumplir y el fallo aparecía en una
 * auditoría meses después. Es la contrapartida de que el payload siga el
 * formato del SRI: cada clave debe significar algo.
 *
 * Se invoca al FINAL de prepareForPipeline, cuando los wrappers
 * `{detalles: {detalle: X}}` ya están normalizados a propiedades.
 */
trait RechazaClavesDesconocidas
{
    /**
     * Claves que el payload puede traer y el DTO ignora a propósito (no son
     * propiedades suyas). Los DTOs que las tengan sobrescriben este método.
     *
     * @return list<string>
     */
    protected static function clavesIgnoradas(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     *
     * @throws DatoInvalido
     */
    protected static function soloClavesConocidas(array $properties): array
    {
        $admitidas = [...self::propiedades(), ...static::clavesIgnoradas()];

        $desconocidas = array_diff(array_keys($properties), $admitidas);

        if ($desconocidas !== []) {
            sort($admitidas);

            throw new DatoInvalido(sprintf(
                'El bloque «%s» no reconoce %s. Se admiten: %s.',
                self::nombreEnPayload(),
                count($desconocidas) === 1
                    ? 'la clave «'.reset($desconocidas).'»'
                    : 'las claves «'.implode('», «', $desconocidas).'»',
                implode(', ', $admitidas),
            ));
        }

        return $properties;
    }

    /**
     * Propiedades públicas del DTO: las promovidas en el constructor y las
     * declaradas por sus clases base (infoTributaria, infoAdicional…).
     *
     * @return list<string>
     */
    private static function propiedades(): array
    {
        $reflexion = new \ReflectionClass(static::class);

        return array_map(
            fn (\ReflectionProperty $propiedad): string => $propiedad->getName(),
            $reflexion->getProperties(\ReflectionProperty::IS_PUBLIC),
        );
    }

    /**
     * Nombre del bloque tal como viaja en el payload: InfoFacturaData →
     * «infoFactura», DetalleData → «detalle».
     */
    private static function nombreEnPayload(): string
    {
        $corto = (new \ReflectionClass(static::class))->getShortName();

        return lcfirst((string) preg_replace('/Data$/', '', $corto));
    }
}
