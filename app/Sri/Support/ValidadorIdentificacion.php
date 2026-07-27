<?php

namespace App\Sri\Support;

use App\Sri\Enums\TipoIdentificacion;
use App\Sri\Exceptions\DatoInvalido;

/**
 * Valida cédulas y RUC ecuatorianos según los algoritmos del SRI: módulo 10
 * (Luhn) para la cédula y módulo 11 para las sociedades. Atrapa en la puerta
 * del API los typos bien formados que el SRI devolvería como error 39/45.
 *
 * Pasaporte (06), consumidor final (07) e identificación del exterior (08)
 * no tienen dígito verificador: se validan solo por forma.
 */
final class ValidadorIdentificacion
{
    private const string CONSUMIDOR_FINAL = '9999999999999';

    /**
     * Longitud máxima del XSD del SRI para los documentos sin dígito
     * verificador (pasaporte e identificación del exterior).
     */
    private const int LONGITUD_MAXIMA_DOCUMENTO_LIBRE = 20;

    /** @var array<int, int> */
    private const array COEFICIENTES_MODULO_10 = [2, 1, 2, 1, 2, 1, 2, 1, 2];

    /** @var array<int, int> */
    private const array COEFICIENTES_SOCIEDAD_PRIVADA = [4, 3, 2, 7, 6, 5, 4, 3, 2];

    /** @var array<int, int> */
    private const array COEFICIENTES_SOCIEDAD_PUBLICA = [3, 2, 7, 6, 5, 4, 3, 2];

    /**
     * Valida el par tipo/valor. El tipo declarado manda: si dice cédula, se
     * valida como cédula, sin inferir ni reescribir el payload.
     *
     * @throws DatoInvalido
     */
    public static function validar(TipoIdentificacion $tipo, string $valor, string $campo): void
    {
        match ($tipo) {
            TipoIdentificacion::Ruc => self::validarRuc($valor, $campo),
            TipoIdentificacion::Cedula => self::validarCedula($valor, $campo),
            TipoIdentificacion::ConsumidorFinal => self::validarConsumidorFinal($valor, $campo),
            TipoIdentificacion::Pasaporte,
            TipoIdentificacion::IdentificacionExterior => self::validarDocumentoLibre($valor, $campo),
        };
    }

    /**
     * Valida el par tipo/identificación tal como viene en el payload crudo,
     * desde el hook `prepareForPipeline()` de un DTO. Es ahí y no en un
     * `#[WithCast]` porque el cast de un campo no ve a sus hermanos.
     *
     * Pasar `$campoTipo` como null aplica la inferencia por forma, para el
     * destinatario de la guía de remisión.
     *
     * Un campo ausente o nulo se valida como cadena vacía: los seis campos de
     * identificación del dominio son obligatorios, y sin esto un valor vacío
     * llegaba al constructor tipado del DTO y reventaba con un 500. Ojo: el
     * middleware `ConvertEmptyStringsToNull` convierte "" en null antes de
     * llegar aquí.
     *
     * Un tipo desconocido sí se deja pasar a propósito: el cast del enum del
     * DTO ya lo reporta como 422.
     *
     * @param  array<string, mixed>  $properties
     *
     * @throws DatoInvalido
     */
    public static function validarEnPayload(array $properties, ?string $campoTipo, string $campoValor): void
    {
        $valor = data_get($properties, $campoValor);
        $identificacion = is_scalar($valor) ? (string) $valor : '';

        if ($campoTipo === null) {
            self::validarSinTipo($identificacion, $campoValor);

            return;
        }

        $tipoDeclarado = data_get($properties, $campoTipo);
        $tipo = is_scalar($tipoDeclarado)
            ? TipoIdentificacion::tryFrom((string) $tipoDeclarado)
            : null;

        if ($tipo instanceof TipoIdentificacion) {
            self::validar($tipo, $identificacion, $campoValor);
        }
    }

    /**
     * Valida una identificación sin tipo declarado, infiriéndolo de la forma.
     * Sirve solo para el destinatario de la guía de remisión: su XSD no tiene
     * un campo `tipoIdentificacionDestinatario` que pueda mandar.
     *
     * Cualquier valor que no sea de 10 ni de 13 dígitos se trata como
     * documento libre, para no rechazar pasaportes numéricos.
     *
     * @throws DatoInvalido
     */
    public static function validarSinTipo(string $valor, string $campo): void
    {
        if (preg_match('/^\d{13}$/', $valor) === 1) {
            if ($valor !== self::CONSUMIDOR_FINAL) {
                self::validarRuc($valor, $campo);
            }

            return;
        }

        if (preg_match('/^\d{10}$/', $valor) === 1) {
            self::validarCedula($valor, $campo);

            return;
        }

        self::validarDocumentoLibre($valor, $campo);
    }

    /**
     * Dígito verificador de módulo 10 (Luhn) de los nueve primeros dígitos
     * de una cédula. Siempre existe: el módulo 10 no tiene casos imposibles.
     */
    public static function digitoVerificadorModulo10(string $primerosNueve): int
    {
        $suma = 0;

        foreach (self::COEFICIENTES_MODULO_10 as $posicion => $coeficiente) {
            $producto = (int) $primerosNueve[$posicion] * $coeficiente;
            $suma += $producto > 9 ? $producto - 9 : $producto;
        }

        return (10 - ($suma % 10)) % 10;
    }

    /**
     * Dígito verificador de módulo 11 para RUC de sociedad.
     *
     * Devuelve null cuando el residuo es 1: en ese caso el dígito saldría 10,
     * que no cabe en una posición, y la base es sencillamente imposible.
     *
     * @param  array<int, int>  $coeficientes
     */
    public static function digitoVerificadorModulo11(string $base, array $coeficientes): ?int
    {
        $suma = 0;

        foreach ($coeficientes as $posicion => $coeficiente) {
            $suma += (int) $base[$posicion] * $coeficiente;
        }

        $digito = 11 - ($suma % 11);

        return match ($digito) {
            11 => 0,
            10 => null,
            default => $digito,
        };
    }

    /**
     * @throws DatoInvalido
     */
    private static function validarCedula(string $valor, string $campo): void
    {
        if (preg_match('/^\d{10}$/', $valor) !== 1) {
            throw DatoInvalido::porFormato($campo, 'una cédula de 10 dígitos', $valor);
        }

        self::validarProvincia($valor, $campo);

        if ((int) $valor[2] > 5) {
            throw DatoInvalido::porFormato($campo, 'una cédula válida (el tercer dígito debe estar entre 0 y 5)', $valor);
        }

        if ((int) $valor[9] !== self::digitoVerificadorModulo10($valor)) {
            throw DatoInvalido::porFormato($campo, 'una cédula con dígito verificador válido', $valor);
        }
    }

    /**
     * El tercer dígito decide el tipo de contribuyente y, con él, el
     * algoritmo: 0-5 persona natural, 6 sociedad pública, 9 sociedad privada.
     *
     * @throws DatoInvalido
     */
    private static function validarRuc(string $valor, string $campo): void
    {
        if (preg_match('/^\d{13}$/', $valor) !== 1) {
            throw DatoInvalido::porFormato($campo, 'un RUC de 13 dígitos', $valor);
        }

        self::validarProvincia($valor, $campo);

        $tercerDigito = (int) $valor[2];

        match (true) {
            $tercerDigito <= 5 => self::validarRucPersonaNatural($valor, $campo),
            $tercerDigito === 6 => self::validarRucSociedadPublica($valor, $campo),
            $tercerDigito === 9 => self::validarRucSociedadPrivada($valor, $campo),
            default => throw DatoInvalido::porFormato($campo, 'un RUC válido (el tercer dígito debe estar entre 0 y 6, o ser 9)', $valor),
        };
    }

    /**
     * RUC de persona natural: la cédula base más el establecimiento. La base
     * se valida por la misma ruta que una cédula suelta.
     *
     * @throws DatoInvalido
     */
    private static function validarRucPersonaNatural(string $valor, string $campo): void
    {
        self::validarCedula(substr($valor, 0, 10), $campo);
        self::validarEstablecimiento(substr($valor, 10, 3), $valor, $campo);
    }

    /**
     * Sociedad privada: dígito verificador en la décima posición, seguido de
     * tres dígitos de establecimiento.
     *
     * @throws DatoInvalido
     */
    private static function validarRucSociedadPrivada(string $valor, string $campo): void
    {
        $esperado = self::digitoVerificadorModulo11(substr($valor, 0, 9), self::COEFICIENTES_SOCIEDAD_PRIVADA);

        if ($esperado === null || (int) $valor[9] !== $esperado) {
            throw DatoInvalido::porFormato($campo, 'un RUC de sociedad privada con dígito verificador válido', $valor);
        }

        self::validarEstablecimiento(substr($valor, 10, 3), $valor, $campo);
    }

    /**
     * Sociedad pública: dígito verificador en la novena posición, seguido de
     * cuatro dígitos de establecimiento.
     *
     * @throws DatoInvalido
     */
    private static function validarRucSociedadPublica(string $valor, string $campo): void
    {
        $esperado = self::digitoVerificadorModulo11(substr($valor, 0, 8), self::COEFICIENTES_SOCIEDAD_PUBLICA);

        if ($esperado === null || (int) $valor[8] !== $esperado) {
            throw DatoInvalido::porFormato($campo, 'un RUC de sociedad pública con dígito verificador válido', $valor);
        }

        self::validarEstablecimiento(substr($valor, 9, 4), $valor, $campo);
    }

    /**
     * El establecimiento numera los locales del contribuyente: 001, 002…
     * Solo el cero está prohibido.
     *
     * @throws DatoInvalido
     */
    private static function validarEstablecimiento(string $establecimiento, string $valor, string $campo): void
    {
        if ((int) $establecimiento === 0) {
            throw DatoInvalido::porFormato($campo, 'un RUC con código de establecimiento distinto de cero', $valor);
        }
    }

    /**
     * Las dos primeras cifras son el código de provincia: 01-24, más el 30
     * de los ecuatorianos registrados en el exterior.
     *
     * @throws DatoInvalido
     */
    private static function validarProvincia(string $valor, string $campo): void
    {
        $provincia = (int) substr($valor, 0, 2);

        if (($provincia < 1 || $provincia > 24) && $provincia !== 30) {
            throw DatoInvalido::porFormato($campo, 'una identificación con código de provincia válido (01-24 o 30)', $valor);
        }
    }

    /**
     * @throws DatoInvalido
     */
    private static function validarConsumidorFinal(string $valor, string $campo): void
    {
        if ($valor !== self::CONSUMIDOR_FINAL) {
            throw DatoInvalido::porFormato($campo, 'exactamente '.self::CONSUMIDOR_FINAL.' para consumidor final', $valor);
        }
    }

    /**
     * Pasaporte e identificación del exterior: documentos sin dígito
     * verificador, donde lo único exigible es que no vengan vacíos.
     *
     * @throws DatoInvalido
     */
    private static function validarDocumentoLibre(string $valor, string $campo): void
    {
        $documento = trim($valor);

        if ($documento === '' || mb_strlen($documento) > self::LONGITUD_MAXIMA_DOCUMENTO_LIBRE) {
            throw DatoInvalido::porFormato(
                $campo,
                'un documento de 1 a '.self::LONGITUD_MAXIMA_DOCUMENTO_LIBRE.' caracteres',
                $valor,
            );
        }
    }
}
