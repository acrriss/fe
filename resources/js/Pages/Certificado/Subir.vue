<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    contribuyente: { type: Object, required: true },
    url_guardar: { type: String, required: true },
});

const page = usePage();
const exito = computed(() => page.props.flash.exito);

const form = useForm({
    certificado: null,
    clave: '',
});

const guardar = () => form.post(props.url_guardar, { onSuccess: () => form.reset() });
</script>

<template>
    <Head title="Cargar certificado de firma" />
    <div class="flex min-h-screen items-center justify-center px-4 py-8">
        <div class="w-full max-w-lg">
            <h1 class="mb-1 text-center text-xl font-bold text-indigo-700">Facturación SRI</h1>
            <p class="mb-6 text-center text-sm text-gray-500">Carga segura del certificado de firma</p>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="mb-5 rounded-md bg-gray-50 p-3 text-sm text-gray-700">
                    <p class="font-medium">{{ contribuyente.razon_social }}</p>
                    <p class="font-mono text-xs text-gray-500">RUC {{ contribuyente.ruc }}</p>
                </div>

                <div
                    v-if="exito"
                    class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800"
                >
                    ✓ {{ exito }}
                </div>

                <p v-if="contribuyente.tiene_certificado" class="mb-4 text-xs text-emerald-600">
                    ✓ Ya hay un certificado configurado
                    <template v-if="contribuyente.valido_hasta">(válido hasta {{ contribuyente.valido_hasta }})</template>.
                    Si sube uno nuevo, reemplazará al actual.
                </p>

                <p class="mb-5 text-sm text-gray-600">
                    Suba aquí su certificado de firma electrónica (archivo <strong>.p12</strong>)
                    y su clave. Se validan y almacenan <strong>cifrados</strong> directamente en el
                    servicio de facturación: su proveedor no tiene acceso a ellos.
                </p>

                <form class="space-y-4" @submit.prevent="guardar">
                    <div>
                        <label for="certificado" class="mb-1 block text-sm font-medium text-gray-700">Archivo .p12</label>
                        <input id="certificado" type="file" accept=".p12,.pfx" required
                            class="w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700"
                            @input="form.certificado = $event.target.files[0]" />
                        <p v-if="form.errors.certificado" class="mt-1 text-xs text-red-600">{{ form.errors.certificado }}</p>
                    </div>
                    <div>
                        <label for="clave" class="mb-1 block text-sm font-medium text-gray-700">Clave del certificado</label>
                        <input id="clave" v-model="form.clave" type="password" required
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                        <p v-if="form.errors.clave" class="mt-1 text-xs text-red-600">{{ form.errors.clave }}</p>
                    </div>
                    <button type="submit" :disabled="form.processing"
                        class="w-full rounded-md bg-indigo-600 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                        {{ form.processing ? 'Validando…' : 'Cargar certificado' }}
                    </button>
                </form>
            </div>

            <p class="mt-4 text-center text-xs text-gray-400">
                Este enlace es personal y expira automáticamente. Si ya expiró, pida uno nuevo a su proveedor.
            </p>
        </div>
    </div>
</template>
