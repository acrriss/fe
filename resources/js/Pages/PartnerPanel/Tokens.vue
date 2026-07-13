<script setup>
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import PartnerLayout from '../../Layouts/PartnerLayout.vue';

defineProps({
    tokens: { type: Array, required: true },
});

const page = usePage();
const tokenRecienCreado = computed(() => page.props.flash.token);

const form = useForm({ nombre: '' });

const crear = () => form.post('/partner/tokens', { onSuccess: () => form.reset() });

const revocar = (id) => {
    if (confirm('¿Revocar este token? Las integraciones que lo usen dejarán de funcionar.')) {
        router.delete(`/partner/tokens/${id}`);
    }
};

const copiar = () => navigator.clipboard?.writeText(tokenRecienCreado.value);
</script>

<template>
    <Head title="Partners — Tokens API" />
    <PartnerLayout>
        <h1 class="mb-2 text-lg font-semibold text-gray-900">Tokens de API</h1>
        <p class="mb-6 text-sm text-gray-500">
            Su sistema usa estos tokens como <code class="rounded bg-gray-100 px-1">Bearer</code>
            contra <code class="rounded bg-gray-100 px-1">/api/partner/v1</code> y
            <code class="rounded bg-gray-100 px-1">/api/v1</code> (con la cabecera
            <code class="rounded bg-gray-100 px-1">X-Contribuyente</code>).
            <a href="/docs" target="_blank" class="font-medium text-emerald-600 hover:underline">Ver documentación →</a>
        </p>

        <div
            v-if="tokenRecienCreado"
            class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4"
        >
            <p class="mb-2 text-sm font-medium text-emerald-900">
                Copie su token ahora — no volverá a mostrarse:
            </p>
            <div class="flex items-center gap-2">
                <code class="flex-1 overflow-x-auto rounded-md bg-white px-3 py-2 font-mono text-xs">
                    {{ tokenRecienCreado }}
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

        <form class="mb-6 flex items-end gap-3" @submit.prevent="crear">
            <div class="flex-1">
                <label for="nombre" class="mb-1 block text-sm font-medium text-gray-700">
                    Nombre del token (p. ej. «POS producción»)
                </label>
                <input
                    id="nombre"
                    v-model="form.nombre"
                    type="text"
                    required
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none"
                />
                <p v-if="form.errors.nombre" class="mt-1 text-xs text-red-600">{{ form.errors.nombre }}</p>
            </div>
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
            >
                Crear token
            </button>
        </form>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">Nombre</th>
                        <th class="px-4 py-2 font-medium">Último uso</th>
                        <th class="px-4 py-2 font-medium">Creado</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="!tokens.length">
                        <td colspan="4" class="px-4 py-8 text-center text-gray-400">Sin tokens activos.</td>
                    </tr>
                    <tr v-for="token in tokens" :key="token.id">
                        <td class="px-4 py-2 font-medium text-gray-900">{{ token.nombre }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ token.ultimoUso ?? 'Nunca' }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ token.creado }}</td>
                        <td class="px-4 py-2 text-right">
                            <button
                                type="button"
                                class="text-xs text-red-600 hover:underline"
                                @click="revocar(token.id)"
                            >
                                Revocar
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PartnerLayout>
</template>
