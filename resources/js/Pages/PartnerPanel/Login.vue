<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
});

const enviar = () => form.post('/partner/login');
</script>

<template>
    <Head title="Partners — Iniciar sesión" />
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-sm">
            <h1 class="mb-1 text-center text-xl font-bold text-emerald-700">Facturación SRI</h1>
            <p class="mb-6 text-center text-sm text-gray-500">Panel de partners</p>

            <form class="space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm" @submit.prevent="enviar">
                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-gray-700">Correo</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autofocus
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none"
                    />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                </div>
                <div>
                    <label for="password" class="mb-1 block text-sm font-medium text-gray-700">Contraseña</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none"
                    />
                </div>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-md bg-emerald-600 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                >
                    Entrar
                </button>
            </form>

            <p class="mt-4 text-center text-sm text-gray-500">
                El acceso lo entrega el equipo del servicio al abrir la relación de partner.
            </p>
        </div>
    </div>
</template>
