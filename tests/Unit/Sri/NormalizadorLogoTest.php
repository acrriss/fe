<?php

use App\Sri\Exceptions\DatoInvalido;
use App\Sri\Ride\NormalizadorLogo;

/*
 * La ficha no fija formato para el logo (Tabla 11, fila 10: «Imagen —
 * Opcional»): el formato único es nuestro. Todo logo sale como PNG de
 * 600 × 300 con la imagen entera encajada y el sobrante transparente.
 */

function normalizar_logo(string $contenido): GdImage
{
    $png = (new NormalizadorLogo)->normalizar($contenido);

    expect(getimagesizefromstring($png)[2])->toBe(IMAGETYPE_PNG);

    return imagecreatefromstring($png);
}

function alfa_en(GdImage $imagen, int $x, int $y): int
{
    return (imagecolorat($imagen, $x, $y) >> 24) & 0x7F;
}

/**
 * Cabecera PNG mínima que declara unas dimensiones sin contener los
 * píxeles: getimagesize solo lee el IHDR.
 */
function png_que_declara(int $ancho, int $alto): string
{
    $ihdr = 'IHDR'.pack('NNCCCCC', $ancho, $alto, 8, 6, 0, 0, 0);

    return "\x89PNG\r\n\x1a\n".pack('N', 13).$ihdr.pack('N', crc32($ihdr));
}

it('entrega siempre un PNG de 600 × 300: :dataset', function (int $ancho, int $alto) {
    $logo = normalizar_logo(imagen_de_prueba($ancho, $alto));

    expect([imagesx($logo), imagesy($logo)])->toBe([NormalizadorLogo::ANCHO, NormalizadorLogo::ALTO]);
})->with([
    'ya en 2:1' => [600, 300],
    'más grande' => [1800, 900],
    'pequeño' => [120, 60],
    'cuadrado' => [500, 500],
    'muy horizontal' => [1200, 200],
]);

it('encaja un logo cuadrado sin recortarlo: los lados quedan transparentes', function () {
    $logo = normalizar_logo(imagen_de_prueba(500, 500));

    expect(alfa_en($logo, 300, 150))->toBe(0) // centro: la imagen, opaca
        ->and(alfa_en($logo, 10, 150))->toBe(127) // izquierda: relleno
        ->and(alfa_en($logo, 590, 150))->toBe(127); // derecha: relleno
});

it('encaja un logo muy horizontal sin recortarlo: arriba y abajo quedan transparentes', function () {
    $logo = normalizar_logo(imagen_de_prueba(1200, 200));

    expect(alfa_en($logo, 300, 150))->toBe(0)
        ->and(alfa_en($logo, 300, 5))->toBe(127)
        ->and(alfa_en($logo, 300, 295))->toBe(127)
        // a lo ancho llena el lienzo entero
        ->and(alfa_en($logo, 2, 150))->toBe(0);
});

it('acepta :dataset', function (string $tipo) {
    expect(imagesx(normalizar_logo(imagen_de_prueba(400, 200, $tipo))))->toBe(600);
})->with(['png', 'jpeg', 'webp']);

it('rechaza un GIF', function () {
    (new NormalizadorLogo)->normalizar(imagen_de_prueba(400, 200, 'gif'));
})->throws(DatoInvalido::class, 'PNG, JPEG o WebP');

it('rechaza lo que no es una imagen', function (string $contenido) {
    (new NormalizadorLogo)->normalizar($contenido);
})->with([
    'texto' => 'no soy una imagen',
    'vacío' => '',
    'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>',
])->throws(DatoInvalido::class, 'PNG, JPEG o WebP');

it('rechaza un archivo de más de 2 MB', function () {
    (new NormalizadorLogo)->normalizar(str_repeat('a', NormalizadorLogo::MAXIMO_BYTES + 1));
})->throws(DatoInvalido::class, '2 MB');

it('rechaza una imagen de demasiados píxeles antes de descomprimirla', function () {
    (new NormalizadorLogo)->normalizar(png_que_declara(10_000, 10_000));
})->throws(DatoInvalido::class, 'demasiado grande');

it('rechaza una imagen dañada', function () {
    (new NormalizadorLogo)->normalizar(png_que_declara(100, 50));
})->throws(DatoInvalido::class, 'dañado');
