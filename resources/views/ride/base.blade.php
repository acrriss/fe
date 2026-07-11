<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>RIDE {{ $registro->clave_acceso }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; padding: 16px; }
        table { width: 100%; border-collapse: collapse; }
        .marco { border: 1px solid #444; border-radius: 4px; padding: 8px; margin-bottom: 8px; }
        .cabecera td { vertical-align: top; }
        .cabecera .col { width: 50%; }
        .doc-titulo { font-size: 13px; font-weight: bold; }
        .etiqueta { color: #555; }
        .clave { font-size: 8px; letter-spacing: 0.5px; word-break: break-all; }
        h2 { font-size: 10px; margin: 0 0 4px; }
        .tabla th { background: #eee; border: 1px solid #999; padding: 3px 4px; text-align: left; }
        .tabla td { border: 1px solid #bbb; padding: 3px 4px; vertical-align: top; }
        .num { text-align: right; }
        .totales { width: 45%; margin-left: 55%; }
        .totales td { border: 1px solid #bbb; padding: 3px 4px; }
        .totales .total-final { font-weight: bold; background: #eee; }
        .mt { margin-top: 8px; }
    </style>
</head>
<body>
    <table class="cabecera">
        <tr>
            <td class="col">
                <div class="marco">
                    @isset($logo)
                        <img src="{{ $logo }}" style="max-height: 60px; margin-bottom: 6px;" alt="">
                    @endisset
                    <div class="doc-titulo">{{ $comprobante->infoTributaria->razonSocial }}</div>
                    @if ($comprobante->infoTributaria->nombreComercial)
                        <div>{{ $comprobante->infoTributaria->nombreComercial }}</div>
                    @endif
                    <div class="mt">
                        <span class="etiqueta">Dirección matriz:</span>
                        {{ $comprobante->infoTributaria->dirMatriz }}
                    </div>
                    @yield('emisor-extra')
                </div>
            </td>
            <td class="col">
                <div class="marco">
                    <div><span class="etiqueta">RUC:</span> <strong>{{ $comprobante->infoTributaria->ruc }}</strong></div>
                    <div class="doc-titulo mt">{{ $comprobante::tipo()->etiqueta() }}</div>
                    <div><span class="etiqueta">No.</span> {{ $comprobante->infoTributaria->numeroCompleto() }}</div>
                    <div class="mt">
                        <div class="etiqueta">NÚMERO DE AUTORIZACIÓN</div>
                        <div class="clave">{{ $registro->numero_autorizacion ?? $registro->clave_acceso }}</div>
                    </div>
                    @if ($registro->autorizado_en)
                        <div class="mt">
                            <span class="etiqueta">Fecha y hora de autorización:</span>
                            {{ $registro->autorizado_en->format('d/m/Y H:i:s') }}
                        </div>
                    @endif
                    <div><span class="etiqueta">Ambiente:</span> {{ $comprobante->infoTributaria->ambiente->etiqueta() }}</div>
                    <div><span class="etiqueta">Emisión:</span> NORMAL</div>
                    <div class="mt">
                        <div class="etiqueta">CLAVE DE ACCESO</div>
                        @isset($codigoBarras)
                            <img src="{{ $codigoBarras }}" alt="" style="width: 100%; height: 38px; display: block;">
                        @endisset
                        <div class="clave">{{ $registro->clave_acceso }}</div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    @yield('cuerpo')
</body>
</html>
