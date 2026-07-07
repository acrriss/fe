<script setup>
import { computed } from 'vue';

const props = defineProps({
    estado: { type: String, required: true },
});

// estado siempre con etiqueta visible: el color solo refuerza, nunca es
// el único portador de la información
const estilos = {
    autorizado: { clase: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', icono: '✓' },
    pendiente: { clase: 'bg-gray-50 text-gray-600 ring-gray-500/20', icono: '…' },
    recibido: { clase: 'bg-sky-50 text-sky-700 ring-sky-600/20', icono: '…' },
    firmado: { clase: 'bg-sky-50 text-sky-700 ring-sky-600/20', icono: '…' },
    devuelto: { clase: 'bg-amber-50 text-amber-800 ring-amber-600/20', icono: '⚠' },
    no_autorizado: { clase: 'bg-red-50 text-red-700 ring-red-600/20', icono: '✕' },
    fallido: { clase: 'bg-red-50 text-red-700 ring-red-600/20', icono: '✕' },
};

const estilo = computed(() => estilos[props.estado] ?? estilos.pendiente);
const etiqueta = computed(() => props.estado.replace('_', ' '));
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
        :class="estilo.clase"
    >
        {{ estilo.icono }} {{ etiqueta }}
    </span>
</template>
