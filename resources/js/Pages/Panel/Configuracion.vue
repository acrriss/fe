<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';

const props = defineProps({
    contribuyente: { type: Object, required: true },
    certificado: { type: Object, default: null },
    logo_url: { type: String, default: null },
    vinculaciones_pendientes: { type: Array, default: () => [] },
    partner: { type: String, default: null },
});

const aprobarVinculacion = (id) => {
    if (confirm('¿Aprobar? El partner podrá emitir comprobantes a tu nombre y sus emisiones consumirán la cuota del partner.')) {
        router.post(`/panel/vinculaciones/${id}/aprobar`);
    }
};

const rechazarVinculacion = (id) => router.post(`/panel/vinculaciones/${id}/rechazar`);

const datos = useForm({
    razon_social: props.contribuyente.razon_social,
    nombre_comercial: props.contribuyente.nombre_comercial,
    dir_matriz: props.contribuyente.dir_matriz,
});

const formCertificado = useForm({
    certificado: null,
    clave: '',
});

const logo = useForm({ logo: null });

const guardarDatos = () => datos.put('/panel/configuracion');

const guardarCertificado = () =>
    formCertificado.put('/panel/configuracion/certificado', {
        onSuccess: () => formCertificado.reset(),
    });

const guardarLogo = () => logo.post('/panel/configuracion/logo', { onSuccess: () => logo.reset() });
</script>

<template>
    <Head title="Configuración" />
    <PanelLayout>
        <h1 class="mb-6 text-lg font-semibold text-gray-900">Configuración del contribuyente</h1>

        <section
            v-if="vinculaciones_pendientes.length"
            class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-6"
        >
            <h2 class="mb-1 text-sm font-semibold text-amber-900">Solicitudes de vinculación</h2>
            <p class="mb-4 text-xs text-amber-800">
                Estas plataformas piden emitir comprobantes a tu nombre (por ejemplo, tu sistema
                de punto de venta). Aprueba solo si reconoces al proveedor.
            </p>
            <ul class="space-y-3">
                <li
                    v-for="vinculacion in vinculaciones_pendientes"
                    :key="vinculacion.id"
                    class="flex items-center justify-between gap-4 rounded-md bg-white p-3"
                >
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ vinculacion.partner }}</p>
                        <p class="text-xs text-gray-500">Solicitada el {{ vinculacion.solicitada_en }}</p>
                    </div>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700"
                            @click="aprobarVinculacion(vinculacion.id)"
                        >
                            Aprobar
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-50"
                            @click="rechazarVinculacion(vinculacion.id)"
                        >
                            Rechazar
                        </button>
                    </div>
                </li>
            </ul>
        </section>

        <div
            v-if="partner"
            class="mb-6 rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-600"
        >
            Esta cuenta es gestionada por el partner <strong class="text-gray-900">{{ partner }}</strong>:
            puede emitir comprobantes a tu nombre y sus emisiones consumen la cuota del partner.
        </div>

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
                    <p class="mb-2 text-xs" :class="contribuyente.tiene_certificado ? 'text-emerald-600' : 'text-amber-600'">
                        {{ contribuyente.tiene_certificado
                            ? '✓ Certificado configurado (se almacena cifrado)'
                            : '⚠ Sin certificado: no puede emitir todavía' }}
                    </p>
                    <dl v-if="certificado" class="mb-4 space-y-1 rounded-md bg-gray-50 p-3 text-xs text-gray-600">
                        <div><dt class="inline font-medium">Titular:</dt> <dd class="inline">{{ certificado.titular }}</dd></div>
                        <div><dt class="inline font-medium">Emitido por:</dt> <dd class="inline">{{ certificado.emisor }}</dd></div>
                        <div>
                            <dt class="inline font-medium">Válido hasta:</dt>
                            <dd class="inline" :class="{ 'font-semibold text-red-600': certificado.vencido, 'font-semibold text-amber-600': certificado.por_vencer }">
                                {{ certificado.valido_hasta }}
                                <template v-if="certificado.vencido"> — ✕ VENCIDO: cargue uno nuevo</template>
                                <template v-else-if="certificado.por_vencer"> — ⚠ vence pronto</template>
                            </dd>
                        </div>
                    </dl>

                    <form class="space-y-4" @submit.prevent="guardarCertificado">
                        <div>
                            <label for="certificado" class="mb-1 block text-sm font-medium text-gray-700">Archivo .p12</label>
                            <input id="certificado" type="file" accept=".p12,.pfx" required
                                class="w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700"
                                @input="formCertificado.certificado = $event.target.files[0]" />
                            <p v-if="formCertificado.errors.certificado" class="mt-1 text-xs text-red-600">{{ formCertificado.errors.certificado }}</p>
                        </div>
                        <div>
                            <label for="clave" class="mb-1 block text-sm font-medium text-gray-700">Clave del certificado</label>
                            <input id="clave" v-model="formCertificado.clave" type="password" required
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                            <p v-if="formCertificado.errors.clave" class="mt-1 text-xs text-red-600">{{ formCertificado.errors.clave }}</p>
                        </div>
                        <button type="submit" :disabled="formCertificado.processing"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                            {{ contribuyente.tiene_certificado ? 'Reemplazar certificado' : 'Cargar certificado' }}
                        </button>
                    </form>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6">
                    <h2 class="mb-1 text-sm font-semibold text-gray-900">Logo para el RIDE</h2>
                    <p class="mb-4 text-xs text-gray-500">
                        {{ contribuyente.tiene_logo ? 'Logo actual:' : 'PNG o JPG, máx. 1 MB.' }}
                    </p>
                    <div v-if="logo_url" class="mb-4 inline-block rounded-md border border-gray-200 bg-gray-50 p-3">
                        <img :src="logo_url" alt="Logo del contribuyente" class="max-h-16 max-w-60 object-contain" />
                    </div>

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
