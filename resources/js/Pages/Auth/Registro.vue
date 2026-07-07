<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    razon_social: '',
    ruc: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const enviar = () => form.post('/registro');
</script>

<template>
    <Head title="Registro" />
    <div class="flex min-h-screen items-center justify-center px-4 py-8">
        <div class="w-full max-w-md">
            <h1 class="mb-1 text-center text-xl font-bold text-indigo-700">Registre su empresa</h1>
            <p class="mb-6 text-center text-sm text-gray-500">
                Cree la cuenta de su contribuyente y su primer usuario
            </p>

            <form class="space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm" @submit.prevent="enviar">
                <fieldset class="space-y-4">
                    <legend class="text-xs font-semibold uppercase tracking-wide text-gray-400">Contribuyente</legend>
                    <div>
                        <label for="razon_social" class="mb-1 block text-sm font-medium text-gray-700">Razón social</label>
                        <input id="razon_social" v-model="form.razon_social" type="text" required
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                        <p v-if="form.errors.razon_social" class="mt-1 text-xs text-red-600">{{ form.errors.razon_social }}</p>
                    </div>
                    <div>
                        <label for="ruc" class="mb-1 block text-sm font-medium text-gray-700">RUC (13 dígitos)</label>
                        <input id="ruc" v-model="form.ruc" type="text" required maxlength="13" pattern="\d{13}"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                        <p v-if="form.errors.ruc" class="mt-1 text-xs text-red-600">{{ form.errors.ruc }}</p>
                    </div>
                </fieldset>

                <fieldset class="space-y-4">
                    <legend class="text-xs font-semibold uppercase tracking-wide text-gray-400">Usuario administrador</legend>
                    <div>
                        <label for="name" class="mb-1 block text-sm font-medium text-gray-700">Nombre</label>
                        <input id="name" v-model="form.name" type="text" required
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label for="email" class="mb-1 block text-sm font-medium text-gray-700">Correo</label>
                        <input id="email" v-model="form.email" type="email" required
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="password" class="mb-1 block text-sm font-medium text-gray-700">Contraseña</label>
                            <input id="password" v-model="form.password" type="password" required
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                            <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
                        </div>
                        <div>
                            <label for="password_confirmation" class="mb-1 block text-sm font-medium text-gray-700">Confirmación</label>
                            <input id="password_confirmation" v-model="form.password_confirmation" type="password" required
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                        </div>
                    </div>
                </fieldset>

                <button type="submit" :disabled="form.processing"
                    class="w-full rounded-md bg-indigo-600 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                    Crear cuenta
                </button>
            </form>

            <p class="mt-4 text-center text-sm text-gray-500">
                ¿Ya tiene cuenta?
                <Link href="/login" class="font-medium text-indigo-600 hover:underline">Inicie sesión</Link>
            </p>
        </div>
    </div>
</template>
