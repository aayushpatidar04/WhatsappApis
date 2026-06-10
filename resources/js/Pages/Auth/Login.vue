<template>
    <div class="min-h-screen bg-gradient-to-br from-[#0F1B2D] to-[#1A3A5C] flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <!-- Logo -->
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-green-500 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                    <svg class="w-9 h-9 text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z" />
                        <path
                            d="M12 0C5.373 0 0 5.373 0 12c0 2.126.556 4.121 1.523 5.854L.057 23.882l6.186-1.438A11.945 11.945 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.894a9.893 9.893 0 01-5.031-1.369l-.361-.214-3.741.981.998-3.648-.235-.374A9.868 9.868 0 012.106 12C2.106 6.58 6.58 2.106 12 2.106c5.419 0 9.894 4.474 9.894 9.894 0 5.419-4.475 9.894-9.894 9.894z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-white">WhatsApp API Platform</h1>
                <p class="text-slate-400 mt-1 text-sm">Sign in to your account</p>
            </div>

            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-2xl p-8">
                <form @submit.prevent="submit" class="space-y-5">
                    <!-- Email -->
                    <div>
                        <label class="form-label">Email address</label>
                        <input v-model="form.email" type="email" autocomplete="email" class="form-input"
                            :class="{ 'border-red-400': errors.email }" placeholder="you@example.com" required />
                        <p v-if="errors.email" class="form-error">{{ errors.email }}</p>
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="form-label">Password</label>
                        <div class="relative">
                            <input v-model="form.password" :type="showPass ? 'text' : 'password'"
                                autocomplete="current-password" class="form-input pr-10"
                                :class="{ 'border-red-400': errors.password }" placeholder="••••••••" required />
                            <button type="button"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                @click="showPass = !showPass">
                                <EyeIcon v-if="!showPass" class="w-4 h-4" />
                                <EyeSlashIcon v-else class="w-4 h-4" />
                            </button>
                        </div>
                        <p v-if="errors.password" class="form-error">{{ errors.password }}</p>
                    </div>

                    <!-- Remember me -->
                    <div class="flex items-center gap-2">
                        <input id="remember" v-model="form.remember" type="checkbox"
                            class="rounded border-gray-300 text-blue-600" />
                        <label for="remember" class="text-sm text-gray-600 select-none">Keep me signed in</label>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="btn-primary w-full justify-center py-3" :disabled="loading">
                        <svg v-if="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        {{ loading ? 'Signing in…' : 'Sign in' }}
                    </button>
                </form>

                <!-- Demo credentials hint (remove in production) -->
                <!-- <div class="mt-6 p-4 bg-blue-50 rounded-xl text-xs text-blue-700 space-y-1">
                    <p class="font-semibold">Demo credentials:</p>
                    <p>Super Admin: superadmin@waplatform.com</p>
                    <p>Master Admin: admin@demoagency.com</p>
                    <p>User: user@demoagency.com</p>
                    <p class="text-blue-500">Password: as seeded</p>
                </div> -->
            </div>

            <p class="text-center text-slate-500 text-xs mt-6">
                © {{ new Date().getFullYear() }} WhatsApp API Platform. All rights reserved.
            </p>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline'

const showPass = ref(false)
const loading = ref(false)

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

const errors = reactive({})

const submit = () => {
    loading.value = true
    Object.keys(errors).forEach(k => delete errors[k])

    form.post(route('login.store'), {
        onError: (errs) => Object.assign(errors, errs),
        onFinish: () => { loading.value = false },
    })
}
</script>