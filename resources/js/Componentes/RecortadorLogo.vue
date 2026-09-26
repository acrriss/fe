<script setup>
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
import { nextTick, onBeforeUnmount, ref } from 'vue';

/*
 * Encuadre del logo en el marco 2:1 del RIDE (600 × 300, como lo normaliza
 * el servidor). El marco es fijo y lo que se mueve y se amplía es la
 * imagen; lo que no cubra el marco queda transparente, así un logo
 * cuadrado cabe entero en vez de perder arriba y abajo.
 *
 * Emite `recortado` con el PNG como data-uri.
 */
const ANCHO = 600;
const ALTO = 300;

const emit = defineEmits(['recortado']);

const abierto = ref(false);
const fuente = ref(null);
const imagen = ref(null);
const inputArchivo = ref(null);
const errorArchivo = ref('');
let cropper = null;

const elegirArchivo = (evento) => {
    const archivo = evento.target.files?.[0];
    errorArchivo.value = '';

    if (!archivo) {
        return;
    }

    if (!['image/png', 'image/jpeg', 'image/webp'].includes(archivo.type)) {
        errorArchivo.value = 'El logo debe ser una imagen PNG, JPEG o WebP.';
        return;
    }

    const lector = new FileReader();
    lector.onload = () => abrir(lector.result);
    lector.readAsDataURL(archivo);
};

const abrir = async (dataUrl) => {
    fuente.value = dataUrl;
    abierto.value = true;
    await nextTick();

    cropper = new Cropper(imagen.value, {
        aspectRatio: ANCHO / ALTO,
        viewMode: 0,
        dragMode: 'move',
        autoCropArea: 0.9,
        cropBoxMovable: false,
        cropBoxResizable: false,
        toggleDragModeOnDblclick: false,
        ready: encajar,
    });
};

/** Imagen entera dentro del marco, centrada: el punto de partida. */
const encajar = () => {
    const marco = cropper.getCropBoxData();
    const { naturalWidth, naturalHeight, rotate = 0 } = cropper.getImageData();
    // girada 90° o 270°, la imagen ocupa en pantalla su alto como ancho
    const girada = Math.abs(rotate) % 180 === 90;
    const ancho = girada ? naturalHeight : naturalWidth;
    const alto = girada ? naturalWidth : naturalHeight;
    const escala = Math.min(marco.width / ancho, marco.height / alto);

    cropper.zoomTo(escala);
    // moveTo coloca el lienzo sin girar: se compensa la diferencia de ejes
    cropper.moveTo(
        marco.left + (marco.width - naturalWidth * escala) / 2,
        marco.top + (marco.height - naturalHeight * escala) / 2,
    );
};

const cerrar = () => {
    cropper?.destroy();
    cropper = null;
    abierto.value = false;
    fuente.value = null;

    if (inputArchivo.value) {
        inputArchivo.value.value = '';
    }
};

const aplicar = () => {
    const lienzo = cropper.getCroppedCanvas({
        width: ANCHO,
        height: ALTO,
        fillColor: 'transparent',
        imageSmoothingQuality: 'high',
    });

    emit('recortado', lienzo.toDataURL('image/png'));
    cerrar();
};

onBeforeUnmount(() => cropper?.destroy());
</script>

<template>
    <div>
        <input ref="inputArchivo" type="file" accept="image/png,image/jpeg,image/webp"
            class="w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700"
            @change="elegirArchivo" />
        <p v-if="errorArchivo" class="mt-1 text-xs text-red-600">{{ errorArchivo }}</p>

        <div v-if="abierto" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            role="dialog" aria-modal="true" aria-labelledby="titulo-recortador">
            <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl">
                <h2 id="titulo-recortador" class="mb-1 text-sm font-semibold text-gray-900">Encuadrar el logo</h2>
                <p class="mb-4 text-xs text-gray-500">
                    Arrastra la imagen y usa la rueda del ratón (o pellizca) para ampliarla. Lo que quede fuera
                    del logo dentro del marco será transparente.
                </p>

                <div class="h-80 overflow-hidden rounded-md bg-gray-100">
                    <img ref="imagen" :src="fuente" alt="" class="block max-w-full" />
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <button type="button" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
                        aria-label="Ampliar" @click="cropper.zoom(0.1)">＋ Ampliar</button>
                    <button type="button" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
                        aria-label="Reducir" @click="cropper.zoom(-0.1)">－ Reducir</button>
                    <button type="button" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
                        @click="cropper.rotate(90)">↻ Girar</button>
                    <button type="button" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
                        @click="encajar">Encajar entero</button>

                    <div class="ml-auto flex gap-2">
                        <button type="button" class="rounded-md px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                            @click="cerrar">Cancelar</button>
                        <button type="button" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                            @click="aplicar">Aplicar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
