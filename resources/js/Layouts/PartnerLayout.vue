<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const partner = computed(() => page.props.auth.partner);
const flash = computed(() => page.props.flash);

const enlaces = [
    { nombre: 'Inicio', ruta: '/partner' },
    { nombre: 'Contribuyentes', ruta: '/partner/contribuyentes' },
    { nombre: 'Webhooks', ruta: '/partner/webhooks' },
    { nombre: 'Vinculaciones', ruta: '/partner/vinculaciones' },
    { nombre: 'Tokens API', ruta: '/partner/tokens' },
];

const esActivo = (ruta) =>
    ruta === '/partner' ? page.url === '/partner' : page.url.startsWith(ruta);
</script>

<template>
    <div class="min-h-full">
        <nav class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-4">
                <div class="flex items-center gap-8">
                    <Link href="/partner" class="text-sm font-bold text-emerald-700">
                        Facturación SRI · Partners
                    </Link>
                    <div class="flex gap-1">
                        <Link
                            v-for="enlace in enlaces"
                            :key="enlace.ruta"
                            :href="enlace.ruta"
                            class="rounded-md px-3 py-1.5 text-sm"
                            :class="esActivo(enlace.ruta)
                                ? 'bg-emerald-50 font-medium text-emerald-700'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
                        >
                            {{ enlace.nombre }}
                        </Link>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <p class="text-xs font-medium text-gray-900">{{ partner?.nombre }}</p>
                        <p class="text-xs text-gray-500">{{ partner?.email }}</p>
                    </div>
                    <Link
                        href="/partner/logout"
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
