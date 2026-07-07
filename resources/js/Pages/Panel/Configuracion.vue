<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';

const props = defineProps({
    contribuyente: { type: Object, required: true },
});

const datos = useForm({
    razon_social: props.contribuyente.razon_social,
    nombre_comercial: props.contribuyente.nombre_comercial,
    dir_matriz: props.contribuyente.dir_matriz,
});

const certificado = useForm({
    certificado: null,
    clave: '',
});

const logo = useForm({ logo: null });

const guardarDatos = () => datos.put('/panel/configuracion');

const guardarCertificado = () =>
    certificado.put('/panel/configuracion/certificado', {
        onSuccess: () => certificado.reset(),
    });

const guardarLogo = () => logo.post('/panel/configuracion/logo', { onSuccess: () => logo.reset() });
</script>

<template>
    <Head title="Configuración" />
    <PanelLayout>
        <h1 class="mb-6 text-lg font-semibold text-gray-900">Configuración del contribuyente</h1>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-gray-200 bg-white p-6">
                <h2 class="mb-1 text-sm font-semibold text-gray-900">Datos de identificación</h2>
                <p class="mb-4 text-xs text-gray-500">
                    RUC: <span class="font-mono">{{ contribuyente.ruc }}</span>
                    <span v-if="contribuyente.plan"> · Plan {{ contribuyente.plan }}</span>
                </p>

                <form class="space-y-4" @submit.prevent="guardarDatos">
                    <div>
                        <label for="razon_social" class="mb-1 block text-sm font-medium text-gray-700">Razón social</label>
                        <input id="razon_social" v-model="datos.razon_social" type="text" required
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                        <p v-if="datos.errors.razon_social" class="mt-1 text-xs text-red-600">{{ datos.errors.razon_social }}</p>
                    </div>
                    <div>
                        <label for="nombre_comercial" class="mb-1 block text-sm font-medium text-gray-700">Nombre comercial</label>
                        <input id="nombre_comercial" v-model="datos.nombre_comercial" type="text"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                    </div>
                    <div>
                        <label for="dir_matriz" class="mb-1 block text-sm font-medium text-gray-700">Dirección matriz</label>
                        <input id="dir_matriz" v-model="datos.dir_matriz" type="text"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                    </div>
                    <button type="submit" :disabled="datos.processing"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                        Guardar datos
                    </button>
                </form>
            </section>

            <div class="space-y-6">
                <section class="rounded-xl border border-gray-200 bg-white p-6">
                    <h2 class="mb-1 text-sm font-semibold text-gray-900">Certificado de firma (.p12)</h2>
                    <p class="mb-4 text-xs" :class="contribuyente.tiene_certificado ? 'text-emerald-600' : 'text-amber-600'">
                        {{ contribuyente.tiene_certificado
                            ? '✓ Certificado configurado (se almacena cifrado)'
                            : '⚠ Sin certificado: no puede emitir todavía' }}
                    </p>

                    <form class="space-y-4" @submit.prevent="guardarCertificado">
                        <div>
                            <label for="certificado" class="mb-1 block text-sm font-medium text-gray-700">Archivo .p12</label>
                            <input id="certificado" type="file" accept=".p12,.pfx" required
                                class="w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700"
                                @input="certificado.certificado = $event.target.files[0]" />
                            <p v-if="certificado.errors.certificado" class="mt-1 text-xs text-red-600">{{ certificado.errors.certificado }}</p>
                        </div>
                        <div>
                            <label for="clave" class="mb-1 block text-sm font-medium text-gray-700">Clave del certificado</label>
                            <input id="clave" v-model="certificado.clave" type="password" required
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                            <p v-if="certificado.errors.clave" class="mt-1 text-xs text-red-600">{{ certificado.errors.clave }}</p>
                        </div>
                        <button type="submit" :disabled="certificado.processing"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                            {{ contribuyente.tiene_certificado ? 'Reemplazar certificado' : 'Cargar certificado' }}
                        </button>
                    </form>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6">
                    <h2 class="mb-1 text-sm font-semibold text-gray-900">Logo para el RIDE</h2>
                    <p class="mb-4 text-xs text-gray-500">
                        {{ contribuyente.tiene_logo ? 'Logo cargado.' : 'PNG o JPG, máx. 1 MB.' }}
                    </p>

                    <form class="flex items-end gap-3" @submit.prevent="guardarLogo">
                        <div class="flex-1">
                            <input id="logo" type="file" accept="image/png,image/jpeg" required
                                class="w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700"
                                @input="logo.logo = $event.target.files[0]" />
                            <p v-if="logo.errors.logo" class="mt-1 text-xs text-red-600">{{ logo.errors.logo }}</p>
                        </div>
                        <button type="submit" :disabled="logo.processing"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                            Subir
                        </button>
                    </form>
                </section>
            </div>
        </div>
    </PanelLayout>
</template>
