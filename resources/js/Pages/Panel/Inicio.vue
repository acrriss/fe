<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import EstadoBadge from '../../Componentes/EstadoBadge.vue';

const props = defineProps({
    consumo: { type: Object, required: true },
    totales: { type: Object, required: true },
    ultimos: { type: Object, required: true },
});

const porcentajeCuota = computed(() => {
    if (!props.consumo.cuotaMensual) return null;
    return Math.min(100, Math.round((props.consumo.emisionesDelMes / props.consumo.cuotaMensual) * 100));
});
</script>

<template>
    <Head title="Inicio" />
    <PanelLayout>
        <h1 class="mb-6 text-lg font-semibold text-gray-900">Resumen</h1>

        <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-5">
                <p class="text-sm text-gray-500">Emisiones este mes</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums text-gray-900">
                    {{ consumo.emisionesDelMes }}<span
                        v-if="consumo.cuotaMensual"
                        class="text-base font-normal text-gray-400"
                    > / {{ consumo.cuotaMensual }}</span>
                </p>
                <template v-if="porcentajeCuota !== null">
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100" role="meter"
                        :aria-valuenow="porcentajeCuota" aria-valuemin="0" aria-valuemax="100"
                        :aria-label="`Consumo de cuota: ${porcentajeCuota}%`">
                        <div class="h-full rounded-full bg-indigo-500" :style="{ width: `${porcentajeCuota}%` }" />
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ porcentajeCuota }}% de la cuota del plan {{ consumo.plan }}
                    </p>
                </template>
                <p v-else class="mt-2 text-xs text-gray-500">Sin plan asignado — sin límite de cuota</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5">
                <p class="text-sm text-gray-500">Autorizados (total)</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums text-gray-900">{{ totales.autorizados }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5">
                <p class="text-sm text-gray-500">Con fallos (total)</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums text-gray-900">{{ totales.fallidos }}</p>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <h2 class="mb-3 text-sm font-semibold text-gray-700">Últimas emisiones</h2>
            <Link href="/panel/comprobantes" class="text-sm text-indigo-600 hover:underline">Ver todas</Link>
        </div>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">Tipo</th>
                        <th class="px-4 py-2 font-medium">Secuencial</th>
                        <th class="px-4 py-2 font-medium">Estado</th>
                        <th class="px-4 py-2 text-right font-medium">Importe</th>
                        <th class="px-4 py-2 font-medium">Fecha</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="!ultimos.data.length">
                        <td colspan="5" class="px-4 py-8 text-center text-gray-400">
                            Aún no hay emisiones. Use la API para emitir su primer comprobante.
                        </td>
                    </tr>
                    <tr v-for="comprobante in ultimos.data" :key="comprobante.id">
                        <td class="px-4 py-2 capitalize">{{ comprobante.tipo }}</td>
                        <td class="px-4 py-2 tabular-nums">{{ comprobante.secuencial }}</td>
                        <td class="px-4 py-2"><EstadoBadge :estado="comprobante.estado" /></td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ comprobante.importeTotal ?? '—' }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ comprobante.emitidoEn }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PanelLayout>
</template>
