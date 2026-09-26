<?php

namespace App\Sri\Ride;

use App\Sri\Exceptions\DatoInvalido;
use GdImage;

/**
 * Deja cualquier logo en el formato único del RIDE: un PNG de 600 × 300
 * (2:1) con la imagen encajada entera y el sobrante transparente.
 *
 * La ficha solo dice «Logo del emisor — Imagen — Opcional» (Tabla 11,
 * fila 10): el formato es decisión nuestra. Se estandariza porque el logo
 * va incrustado en cada RIDE, que viaja adjunto en cada correo al
 * comprador; y porque así el RIDE lo pinta siempre en el mismo hueco.
 * 600 × 300 da ~300 DPI a los 60 px de alto con que se imprime.
 *
 * El recortador del panel y del POS ya entregan este formato; normalizar
 * igualmente es lo que garantiza el resultado para cualquier integrador.
 */
final class NormalizadorLogo
{
    public const int ANCHO = 600;

    public const int ALTO = 300;

    /** Tope del archivo recibido, antes de normalizar. */
    public const int MAXIMO_BYTES = 2 * 1024 * 1024;

    /** Tope de píxeles de la imagen recibida: GD la descomprime entera en memoria. */
    private const int MAXIMO_PIXELES = 25_000_000;

    /** @var list<int> */
    private const array TIPOS_ADMITIDOS = [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP];

    /**
     * @return string el PNG normalizado
     *
     * @throws DatoInvalido si no es una imagen PNG, JPEG o WebP utilizable
     */
    public function normalizar(string $contenido): string
    {
        if (strlen($contenido) > self::MAXIMO_BYTES) {
            throw new DatoInvalido('El logo no puede pesar más de 2 MB.');
        }

        $original = $this->abrir($contenido);

        $lienzo = imagecreatetruecolor(self::ANCHO, self::ALTO);
        imagealphablending($lienzo, false);
        imagesavealpha($lienzo, true);
        imagefill($lienzo, 0, 0, (int) imagecolorallocatealpha($lienzo, 255, 255, 255, 127));
        imagealphablending($lienzo, true);

        $anchoOriginal = imagesx($original);
        $altoOriginal = imagesy($original);
        $escala = min(self::ANCHO / $anchoOriginal, self::ALTO / $altoOriginal);
        $ancho = max(1, (int) round($anchoOriginal * $escala));
        $alto = max(1, (int) round($altoOriginal * $escala));

        imagecopyresampled(
            $lienzo,
            $original,
            intdiv(self::ANCHO - $ancho, 2),
            intdiv(self::ALTO - $alto, 2),
            0,
            0,
            $ancho,
            $alto,
            $anchoOriginal,
            $altoOriginal,
        );

        ob_start();
        imagepng($lienzo, null, 9);

        return (string) ob_get_clean();
    }

    private function abrir(string $contenido): GdImage
    {
        $info = $contenido === '' ? false : @getimagesizefromstring($contenido);

        if ($info === false || ! in_array($info[2], self::TIPOS_ADMITIDOS, true)) {
            throw new DatoInvalido('El logo debe ser una imagen PNG, JPEG o WebP.');
        }

        if ($info[0] * $info[1] > self::MAXIMO_PIXELES) {
            throw new DatoInvalido('El logo es demasiado grande: redúcelo a menos de 25 megapíxeles.');
        }

        // libpng avisa de una imagen truncada con un E_WARNING que `@` no
        // silencia ante el manejador de Laravel: el fallo ya se reporta
        // como DatoInvalido, así que el aviso se descarta
        set_error_handler(fn (): bool => true);

        try {
            $imagen = imagecreatefromstring($contenido);
        } finally {
            restore_error_handler();
        }

        if ($imagen === false) {
            throw new DatoInvalido('El logo está dañado o no se puede leer.');
        }

        return $imagen;
    }
}
