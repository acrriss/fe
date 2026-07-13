<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PartnerLayout from '../../Layouts/PartnerLayout.vue';

defineProps({
    contribuyentes: { type: Object, required: true },
});

const page = usePage();
const enlaceGenerado = computed(() => page.props.flash.enlace_certificado);

const generarEnlace = (id) => router.post(`/partner/contribuyentes/${id}/enlace-certificado`);

const copiar = () => navigator.clipboard?.writeText(enlaceGenerado.value);
</script>

<template>
    <Head title="Partners — Contribuyentes" />
    <PartnerLayout>
        <h1 class="mb-2 text-lg font-semibold text-gray-900">Contribuyentes gestionados</h1>
        <p class="mb-6 text-sm text-gray-500">
            El alta de nuevos clientes se hace por la API
            (<code class="rounded bg-gray-100 px-1">POST /api/partner/v1/contribuyentes</code>).
            Desde aquí puede generar el <strong>enlace de carga de certificado</strong> para
            compartirlo con su cliente: él sube su .p12 directamente al servicio.
        </p>

        <div
            v-if="enlaceGenerado"
            class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4"
        >
            <p class="mb-2 text-sm font-medium text-emerald-900">
                Comparta este enlace con su cliente (expira automáticamente):
            </p>
            <div class="flex items-center gap-2">
                <code class="flex-1 overflow-x-auto rounded-md bg-white px-3 py-2 font-mono text-xs">
                    {{ enlaceGenerado }}
                </code>
                <button
                    type="button"
                    class="rounded-md bg-emerald-600 px-3 py-2 text-xs font-medium text-white hover:bg-emerald-700"
                    @click="copiar"
                >
                    Copiar
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">RUC</th>
                        <th class="px-4 py-2 font-medium">Razón social</th>
                        <th class="px-4 py-2 text-right font-medium">Emisiones del mes</th>
                        <th class="px-4 py-2 font-medium">Certificado</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="!contribuyentes.data.length">
                        <td colspan="5" class="px-4 py-8 text-center text-gray-400">
                            Aún no gestiona contribuyentes. Aprovisione el primero por la API.
                        </td>
                    </tr>
                    <tr v-for="contribuyente in contribuyentes.data" :key="contribuyente.id">
                        <td class="px-4 py-2 tabular-nums">{{ contribuyente.ruc }}</td>
                        <td class="px-4 py-2 font-medium text-gray-900">{{ contribuyente.razonSocial }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ contribuyente.emisionesDelMes }}<span
                                v-if="contribuyente.limiteMensual"
                                class="text-gray-400"
                            > / {{ contribuyente.limiteMensual }}</span>
                        </td>
                        <td class="px-4 py-2">
                            <span
                                v-if="!contribuyente.certificado.configurado"
                                class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-600/20"
                            >
                                ⚠ sin certificado
                            </span>
                            <span
                                v-else-if="contribuyente.certificado.vencido"
                                class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/20"
                            >
                                ✕ vencido
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20"
                            >
                                ✓ hasta {{ contribuyente.certificado.validoHasta }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <button
                                type="button"
                                class="text-xs text-emerald-600 hover:underline"
                                @click="generarEnlace(contribuyente.id)"
                            >
                                Generar enlace de certificado
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="contribuyentes.last_page > 1" class="mt-4 flex justify-center gap-1" aria-label="Paginación">
            <template v-for="enlace in contribuyentes.links" :key="enlace.label">
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
