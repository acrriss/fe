import { createApiReference } from '@scalar/api-reference';
// En modo bundled (no CDN) Scalar no autoinyecta sus estilos: hay que
// importar la hoja de estilos explícitamente.
import '@scalar/api-reference/style.css';

// Documentación pública de la API, renderizada con Scalar sobre el
// OpenAPI que sirve el backend en /docs/openapi.yaml.
createApiReference('#app', {
    url: '/docs/openapi.yaml',
    theme: 'default',
    hideDownloadButton: false,
    metaData: {
        title: 'API de Facturación Electrónica SRI',
    },
});
