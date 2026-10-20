<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

defineProps({ mustChange: Boolean })

const user = computed(() => usePage().props.auth.user)
const profile = useForm({ name: user.value.name })
const pwd = useForm({ current_password: '', password: '', password_confirmation: '' })

function savePassword() {
  pwd.put(route('profile.password'), { preserveScroll: true, onSuccess: () => pwd.reset() })
}
</script>

<template>
  <AppLayout>
    <Head title="Profil" />
    <template #title>Profil saya</template>

    <div v-if="mustChange" class="mb-6 max-w-2xl rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
      Kamu memakai password sementara. Ganti password di bawah sebelum melanjutkan.
    </div>

    <div class="grid max-w-4xl gap-6 lg:grid-cols-2">
      <form class="card" @submit.prevent="profile.patch(route('profile.update'), { preserveScroll: true })">
        <div class="card-header"><h2 class="text-sm font-semibold">Data akun</h2></div>
        <div class="card-body space-y-4">
          <div><label class="label">Email</label><input :value="user.email" disabled class="input opacity-70" /></div>
          <div>
            <label class="label">Nama</label>
            <input v-model="profile.name" class="input" required />
            <p v-if="profile.errors.name" class="error-text">{{ profile.errors.name }}</p>
          </div>
          <div><label class="label">Role</label><p class="text-sm">{{ (user.roles ?? []).join(', ') }}</p></div>
          <button class="btn-primary" :disabled="profile.processing">Simpan nama</button>
        </div>
      </form>

      <form class="card" @submit.prevent="savePassword">
        <div class="card-header"><h2 class="text-sm font-semibold">Ganti password</h2></div>
        <div class="card-body space-y-4">
          <div>
            <label class="label">Password saat ini</label>
            <input v-model="pwd.current_password" type="password" autocomplete="current-password" class="input" required />
            <p v-if="pwd.errors.current_password" class="error-text">{{ pwd.errors.current_password }}</p>
          </div>
          <div>
            <label class="label">Password baru</label>
            <input v-model="pwd.password" type="password" autocomplete="new-password" class="input" required />
            <p v-if="pwd.errors.password" class="error-text">{{ pwd.errors.password }}</p>
            <p class="mt-1 text-xs text-ink-500">Minimal 8 karakter, huruf besar dan kecil, ada angka.</p>
          </div>
          <div>
            <label class="label">Ulangi password baru</label>
            <input v-model="pwd.password_confirmation" type="password" autocomplete="new-password" class="input" required />
          </div>
          <button class="btn-accent" :disabled="pwd.processing">Ganti password</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
