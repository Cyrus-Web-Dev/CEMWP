<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'

const props = defineProps({ users: Array, roles: Array })

const me = computed(() => usePage().props.auth.user)
const canManage = computed(() => (me.value.permissions ?? []).includes('users.manage'))

const form = useForm({ name: '', email: '', password: '', role: props.roles.includes('warehouse-staff') ? 'warehouse-staff' : props.roles[0] })
function create() {
  form.post(route('users.store'), { preserveScroll: true, onSuccess: () => form.reset('name', 'email', 'password') })
}

function changeRole(u, role) {
  router.put(route('users.role', u.id), { role }, { preserveScroll: true })
}
function toggle(u) {
  const verb = u.status === 'active' ? 'menonaktifkan' : 'mengaktifkan'
  if (confirm(`Yakin ${verb} akun ${u.email}?`)) router.post(route('users.toggle-active', u.id), {}, { preserveScroll: true })
}

const resetId = ref(null)
const resetForm = useForm({ password: '' })
function submitReset(u) {
  resetForm.put(route('users.password', u.id), { preserveScroll: true, onSuccess: () => { resetId.value = null; resetForm.reset() } })
}
</script>

<template>
  <AppLayout>
    <Head title="Pengguna" />
    <template #title>Pengguna</template>

    <div class="grid gap-6 xl:grid-cols-3">
      <div class="card xl:col-span-2">
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead><tr><th>Nama</th><th>Role</th><th>Status</th><th>Login terakhir</th><th v-if="canManage"></th></tr></thead>
            <tbody>
              <template v-for="u in users" :key="u.id">
                <tr>
                  <td>
                    <div class="font-medium">{{ u.name }}</div>
                    <div class="text-xs text-ink-500">{{ u.email }}<span v-if="u.must_change_password" class="ml-1 text-amber-600">· wajib ganti password</span></div>
                  </td>
                  <td>
                    <select v-if="canManage && u.id !== me.id" :value="u.role" class="input py-1" @change="changeRole(u, $event.target.value)">
                      <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                    </select>
                    <span v-else class="text-sm">{{ u.role ?? '-' }}</span>
                  </td>
                  <td><StatusBadge :status="u.status" :labels="{ active: 'Aktif', inactive: 'Nonaktif' }" /></td>
                  <td class="text-xs text-ink-500">{{ u.last_login_at ?? '-' }}</td>
                  <td v-if="canManage" class="whitespace-nowrap text-right">
                    <button class="btn-ghost btn-sm" @click="resetId = resetId === u.id ? null : u.id">Reset password</button>
                    <button v-if="u.id !== me.id" class="btn-ghost btn-sm" @click="toggle(u)">{{ u.status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                  </td>
                </tr>
                <tr v-if="resetId === u.id">
                  <td colspan="5" class="bg-ink-50 dark:bg-ink-800/40">
                    <form class="flex flex-wrap items-center gap-3" @submit.prevent="submitReset(u)">
                      <input v-model="resetForm.password" type="text" class="input max-w-xs" placeholder="Password sementara baru" required />
                      <button class="btn-accent btn-sm" :disabled="resetForm.processing">Pasang</button>
                      <span v-if="resetForm.errors.password" class="error-text">{{ resetForm.errors.password }}</span>
                    </form>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <form v-if="canManage" class="card self-start" @submit.prevent="create">
        <div class="card-header"><h2 class="text-sm font-semibold">Tambah akun staf</h2></div>
        <div class="card-body space-y-4">
          <div><label class="label">Nama</label><input v-model="form.name" class="input" required /><p v-if="form.errors.name" class="error-text">{{ form.errors.name }}</p></div>
          <div><label class="label">Email</label><input v-model="form.email" type="email" class="input" required /><p v-if="form.errors.email" class="error-text">{{ form.errors.email }}</p></div>
          <div>
            <label class="label">Password sementara</label>
            <input v-model="form.password" type="text" class="input" required />
            <p v-if="form.errors.password" class="error-text">{{ form.errors.password }}</p>
            <p class="mt-1 text-xs text-ink-500">Pengguna wajib menggantinya saat login pertama.</p>
          </div>
          <div>
            <label class="label">Role</label>
            <select v-model="form.role" class="input"><option v-for="r in roles" :key="r" :value="r">{{ r }}</option></select>
            <p v-if="form.errors.role" class="error-text">{{ form.errors.role }}</p>
          </div>
          <button class="btn-primary w-full" :disabled="form.processing">Buat akun</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
