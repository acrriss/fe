<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import PartnerLayout from '../../Layouts/PartnerLayout.vue';

defineProps({
    vinculaciones: { type: Object, required: true },
});

const form = useForm({ ruc: '' });

const solicitar = () => form.post('/partner/vinculaciones', { onSuccess: () => form.reset() });

const estiloEstado = (estado) => ({
    pendiente: 'bg-amber-50 text-amber-800 ring-amber-600/20',
    aprobada: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    rechazada: 'bg-red-50 text-red-700 ring-red-600/20',
}[estado] ?? 'bg-gray-50 text-gray-600 ring-gray-500/20');
</script>

<template>
    <Head title="Partners — Vinculaciones" />
    <PartnerLayout>
        <h1 class="mb-2 text-lg font-semibold text-gray-900">Vinculaciones</h1>
        <p class="mb-6 text-sm text-gray-500">
            Para gestionar un RUC que <strong>ya tiene cuenta directa</strong> en el servicio,
            solicite la vinculación: el dueño de la cuenta la aprueba desde su panel y desde
            entonces usted puede emitir a su nombre.
        </p>

        <form class="mb-6 flex items-end gap-3" @submit.prevent="solicitar">
            <div class="w-72">
                <label for="ruc" class="mb-1 block text-sm font-medium text-gray-700">RUC del cliente</label>
                <input
                    id="ruc"
                    v-model="form.ruc"
                    type="text"
                    inputmode="numeric"
                    maxlength="13"
                    required
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none"
                />
                <p v-if="form.errors.ruc" class="mt-1 text-xs text-red-600">{{ form.errors.ruc }}</p>
            </div>
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
            >
                Solicitar vinculación
            </button>
        </form>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">RUC</th>
                        <th class="px-4 py-2 font-medium">Razón social</th>
                        <th class="px-4 py-2 font-medium">Estado</th>
                        <th class="px-4 py-2 font-medium">Solicitada</th>
                        <th class="px-4 py-2 font-medium">Resuelta</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="!vinculaciones.data.length">
                        <td colspan="5" class="px-4 py-8 text-center text-gray-400">Sin solicitudes de vinculación.</td>
                    </tr>
                    <tr v-for="vinculacion in vinculaciones.data" :key="vinculacion.id">
                        <td class="px-4 py-2 tabular-nums">{{ vinculacion.ruc }}</td>
                        <td class="px-4 py-2 font-medium text-gray-900">{{ vinculacion.razonSocial }}</td>
                        <td class="px-4 py-2">
                            <span
                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                                :class="estiloEstado(vinculacion.estado)"
                            >
                                {{ vinculacion.estado }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-gray-500">{{ vinculacion.solicitadaEn }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ vinculacion.resueltaEn ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="vinculaciones.last_page > 1" class="mt-4 flex justify-center gap-1" aria-label="Paginación">
            <template v-for="enlace in vinculaciones.links" :key="enlace.label">
                <Link
                    v-if="enlace.url"
                    :href="enlace.url"
                    class="rounded-md px-3 py-1.5 text-sm"
                    :class="enlace.active ? 'bg-emerald-600 text-white' : 'text-gray-600 hover:bg-gray-100'"
                    v-html="enlace.label"
                />
                <span v-else class="px-3 py-1.5 text-sm text-gray-300" v-html="enlace.label" />
            </template>
        </nav>
    </PartnerLayout>
</template>
