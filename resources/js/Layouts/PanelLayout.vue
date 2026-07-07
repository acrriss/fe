<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const contribuyente = computed(() => page.props.auth.contribuyente);
const flash = computed(() => page.props.flash);

const enlaces = [
    { nombre: 'Inicio', ruta: '/panel' },
    { nombre: 'Comprobantes', ruta: '/panel/comprobantes' },
    { nombre: 'Tokens API', ruta: '/panel/tokens' },
    { nombre: 'Configuración', ruta: '/panel/configuracion' },
];

const esActivo = (ruta) =>
    ruta === '/panel' ? page.url === '/panel' : page.url.startsWith(ruta);
</script>

<template>
    <div class="min-h-full">
        <nav class="bg-white border-b border-gray-200">
            <div class="mx-auto max-w-6xl px-4 flex h-14 items-center justify-between">
                <div class="flex items-center gap-8">
                    <Link href="/panel" class="text-sm font-bold text-indigo-700">
                        Facturación SRI
                    </Link>
                    <div class="flex gap-1">
                        <Link
                            v-for="enlace in enlaces"
                            :key="enlace.ruta"
                            :href="enlace.ruta"
                            class="rounded-md px-3 py-1.5 text-sm"
                            :class="esActivo(enlace.ruta)
                                ? 'bg-indigo-50 font-medium text-indigo-700'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
                        >
                            {{ enlace.nombre }}
                        </Link>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <p class="text-xs font-medium text-gray-900">{{ user?.name }}</p>
                        <p class="text-xs text-gray-500">{{ contribuyente?.razon_social }}</p>
                    </div>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="rounded-md border border-gray-300 px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-50"
                    >
                        Salir
                    </Link>
                </div>
            </div>
        </nav>

        <div
            v-if="!contribuyente?.tiene_certificado"
            class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-center text-sm text-amber-800"
        >
            ⚠ Aún no ha cargado su certificado de firma —
            <Link href="/panel/configuracion" class="font-medium underline">configúrelo aquí</Link>
            para poder emitir comprobantes.
        </div>

        <div
            v-if="flash.exito"
            class="border-b border-emerald-200 bg-emerald-50 px-4 py-2 text-center text-sm text-emerald-800"
        >
            ✓ {{ flash.exito }}
        </div>

        <main class="mx-auto max-w-6xl px-4 py-8">
            <slot />
        </main>
    </div>
</template>
