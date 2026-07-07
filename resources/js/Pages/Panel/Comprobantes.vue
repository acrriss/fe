<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import EstadoBadge from '../../Componentes/EstadoBadge.vue';

defineProps({
    comprobantes: { type: Object, required: true },
});
</script>

<template>
    <Head title="Comprobantes" />
    <PanelLayout>
        <h1 class="mb-6 text-lg font-semibold text-gray-900">Comprobantes</h1>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">Tipo</th>
                        <th class="px-4 py-2 font-medium">Secuencial</th>
                        <th class="px-4 py-2 font-medium">Estado</th>
                        <th class="px-4 py-2 font-medium">Clave de acceso</th>
                        <th class="px-4 py-2 text-right font-medium">Importe</th>
                        <th class="px-4 py-2 font-medium">Fecha</th>
                        <th class="px-4 py-2 font-medium">Descargas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="!comprobantes.data.length">
                        <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                            Sin comprobantes todavía.
                        </td>
                    </tr>
                    <tr v-for="comprobante in comprobantes.data" :key="comprobante.id" class="hover:bg-gray-50">
                        <td class="px-4 py-2">{{ comprobante.tipo }}</td>
                        <td class="px-4 py-2 tabular-nums">{{ comprobante.secuencial }}</td>
                        <td class="px-4 py-2"><EstadoBadge :estado="comprobante.estado" /></td>
                        <td class="max-w-45 truncate px-4 py-2 font-mono text-xs text-gray-500" :title="comprobante.claveAcceso">
                            {{ comprobante.claveAcceso ?? '—' }}
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ comprobante.importeTotal ?? '—' }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ comprobante.emitidoEn }}</td>
                        <td class="px-4 py-2">
                            <template v-if="comprobante.estado === 'autorizado'">
                                <a
                                    :href="`/panel/comprobantes/${comprobante.id}/ride`"
                                    class="text-indigo-600 hover:underline"
                                >RIDE</a>
                                <span class="text-gray-300"> · </span>
                                <a
                                    :href="`/panel/comprobantes/${comprobante.id}/xml`"
                                    class="text-indigo-600 hover:underline"
                                >XML</a>
                            </template>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="comprobantes.last_page > 1" class="mt-4 flex justify-center gap-1" aria-label="Paginación">
            <template v-for="enlace in comprobantes.links" :key="enlace.label">
                <Link
                    v-if="enlace.url"
                    :href="enlace.url"
                    class="rounded-md px-3 py-1.5 text-sm"
                    :class="enlace.active ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100'"
                    v-html="enlace.label"
                />
                <span v-else class="px-3 py-1.5 text-sm text-gray-300" v-html="enlace.label" />
            </template>
        </nav>
    </PanelLayout>
</template>
