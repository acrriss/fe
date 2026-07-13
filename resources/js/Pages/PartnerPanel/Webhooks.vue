<script setup>
import { Head } from '@inertiajs/vue3';
import PartnerLayout from '../../Layouts/PartnerLayout.vue';

defineProps({
    endpoints: { type: Array, required: true },
    entregas: { type: Array, required: true },
});

const estiloEntrega = (estado) => ({
    entregada: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    pendiente: 'bg-gray-50 text-gray-600 ring-gray-500/20',
    fallida: 'bg-red-50 text-red-700 ring-red-600/20',
}[estado] ?? 'bg-gray-50 text-gray-600 ring-gray-500/20');
</script>

<template>
    <Head title="Partners — Webhooks" />
    <PartnerLayout>
        <h1 class="mb-2 text-lg font-semibold text-gray-900">Webhooks</h1>
        <p class="mb-6 text-sm text-gray-500">
            La gestión de endpoints se hace por la API
            (<code class="rounded bg-gray-100 px-1">POST /api/partner/v1/webhooks</code>);
            aquí puede verificar su estado y depurar las entregas.
            <a href="/docs" target="_blank" class="font-medium text-emerald-600 hover:underline">Ver documentación →</a>
        </p>

        <h2 class="mb-3 text-sm font-semibold text-gray-700">Endpoints registrados</h2>
        <div class="mb-8 overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">URL</th>
                        <th class="px-4 py-2 font-medium">Eventos</th>
                        <th class="px-4 py-2 font-medium">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="!endpoints.length">
                        <td colspan="3" class="px-4 py-8 text-center text-gray-400">
                            Sin endpoints registrados: sus sistemas dependen del polling.
                        </td>
                    </tr>
                    <tr v-for="endpoint in endpoints" :key="endpoint.id">
                        <td class="px-4 py-2 font-mono text-xs text-gray-900">{{ endpoint.url }}</td>
                        <td class="px-4 py-2 text-xs text-gray-500">{{ endpoint.eventos.join(', ') }}</td>
                        <td class="px-4 py-2">
                            <span
                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                                :class="endpoint.activo
                                    ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'
                                    : 'bg-gray-50 text-gray-600 ring-gray-500/20'"
                            >
                                {{ endpoint.activo ? '✓ activo' : '— inactivo' }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h2 class="mb-3 text-sm font-semibold text-gray-700">Últimas entregas</h2>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">Evento</th>
                        <th class="px-4 py-2 font-medium">Estado</th>
                        <th class="px-4 py-2 text-right font-medium">Intentos</th>
                        <th class="px-4 py-2 font-medium">HTTP</th>
                        <th class="px-4 py-2 font-medium">Error</th>
                        <th class="px-4 py-2 font-medium">Fecha</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="!entregas.length">
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400">Aún no hay entregas.</td>
                    </tr>
                    <tr v-for="entrega in entregas" :key="entrega.id">
                        <td class="px-4 py-2 font-mono text-xs">{{ entrega.evento }}</td>
                        <td class="px-4 py-2">
                            <span
                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                                :class="estiloEntrega(entrega.estado)"
                            >
                                {{ entrega.estado }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ entrega.intentos }}</td>
                        <td class="px-4 py-2 tabular-nums">{{ entrega.codigoHttp ?? '—' }}</td>
                        <td class="max-w-64 truncate px-4 py-2 text-xs text-gray-500" :title="entrega.error">
                            {{ entrega.error ?? '—' }}
                        </td>
                        <td class="px-4 py-2 text-gray-500">{{ entrega.creadoEn }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PartnerLayout>
</template>
