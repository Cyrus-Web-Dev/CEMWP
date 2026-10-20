<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { useCan } from '@/Composables/can'
import { useFilters } from '@/Composables/useFilters'

const props = defineProps({ suppliers: Array, filters: Object })
const { can } = useCan()
const f = useFilters('warehouse.suppliers.index', { q: props.filters.q ?? '' })

const blank = () => ({ code: '', name: '', contact_name: '', phone: '', email: '', address: '', is_active: true })
const addForm = useForm(blank())
function add() {
  addForm.post(route('warehouse.suppliers.store'), { preserveScroll: true, onSuccess: () => addForm.reset() })
}

const editingId = ref(null)
const editForm = useForm(blank())
function startEdit(s) {
  editingId.value = s.id
  Object.assign(editForm, {
    code: s.code, name: s.name, contact_name: s.contact_name ?? '', phone: s.phone ?? '', email: s.email ?? '', address: s.address ?? '', is_active: s.is_active,
  })
  editForm.clearErrors()
}
function saveEdit(s) {
  editForm.put(route('warehouse.suppliers.update', s.id), { preserveScroll: true, onSuccess: () => { editingId.value = null } })
}
function remove(s) {
  if (confirm(`Hapus supplier "${s.name}"?`)) router.delete(route('warehouse.suppliers.destroy', s.id), { preserveScroll: true })
}
</script>

<template>
  <AppLayout>
    <Head title="Supplier" />
    <template #title>Supplier</template>

    <div class="grid gap-6 lg:grid-cols-3">
      <div class="space-y-3 lg:col-span-2">
        <input v-model="f.q" class="input max-w-sm" placeholder="Cari kode atau nama supplier…" />

        <div v-for="s in suppliers" :key="s.id" class="card">
          <div v-if="editingId !== s.id" class="card-body flex flex-wrap items-start justify-between gap-3">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium">{{ s.name }}</span>
                <span class="font-mono text-xs text-ink-500">{{ s.code }}</span>
                <span v-if="!s.is_active" class="badge-red">Nonaktif</span>
              </div>
              <p class="mt-1 text-sm text-ink-500">
                <span v-if="s.contact_name">{{ s.contact_name }} · </span>{{ s.phone || 'tanpa telepon' }}<span v-if="s.email"> · {{ s.email }}</span>
              </p>
              <p v-if="s.address" class="text-sm text-ink-500">{{ s.address }}</p>
              <p class="text-xs text-ink-500">{{ s.purchase_orders_count }} PO</p>
            </div>
            <div v-if="can('suppliers.manage')" class="whitespace-nowrap">
              <button class="btn-ghost btn-sm" @click="startEdit(s)">Ubah</button>
              <button class="btn-ghost btn-sm text-red-600" @click="remove(s)">Hapus</button>
            </div>
          </div>

          <form v-else class="card-body grid gap-3 sm:grid-cols-2" @submit.prevent="saveEdit(s)">
            <div><label class="label">Kode</label><input v-model="editForm.code" class="input font-mono" required /><p v-if="editForm.errors.code" class="error-text">{{ editForm.errors.code }}</p></div>
            <div><label class="label">Nama</label><input v-model="editForm.name" class="input" required /><p v-if="editForm.errors.name" class="error-text">{{ editForm.errors.name }}</p></div>
            <div><label class="label">Kontak</label><input v-model="editForm.contact_name" class="input" /></div>
            <div><label class="label">Telepon</label><input v-model="editForm.phone" class="input" /></div>
            <div><label class="label">Email</label><input v-model="editForm.email" type="email" class="input" /><p v-if="editForm.errors.email" class="error-text">{{ editForm.errors.email }}</p></div>
            <div><label class="label">Alamat</label><input v-model="editForm.address" class="input" /></div>
            <label class="flex items-center gap-2 text-sm"><input v-model="editForm.is_active" type="checkbox" class="rounded border-ink-300 text-seal-600 focus:ring-seal-500" /> Aktif</label>
            <div class="flex gap-2 sm:col-span-2">
              <button class="btn-accent btn-sm" :disabled="editForm.processing">Simpan</button>
              <button type="button" class="btn-ghost btn-sm" @click="editingId = null">Batal</button>
            </div>
          </form>
        </div>
        <p v-if="!suppliers.length" class="text-sm text-ink-500">Belum ada supplier yang cocok.</p>
      </div>

      <form v-if="can('suppliers.manage')" class="card self-start" @submit.prevent="add">
        <div class="card-header"><h2 class="text-sm font-semibold">Tambah supplier</h2></div>
        <div class="card-body space-y-4">
          <div><label class="label">Kode</label><input v-model="addForm.code" class="input font-mono" placeholder="SUP-001" required /><p v-if="addForm.errors.code" class="error-text">{{ addForm.errors.code }}</p></div>
          <div><label class="label">Nama</label><input v-model="addForm.name" class="input" required /><p v-if="addForm.errors.name" class="error-text">{{ addForm.errors.name }}</p></div>
          <div><label class="label">Kontak</label><input v-model="addForm.contact_name" class="input" /></div>
          <div><label class="label">Telepon</label><input v-model="addForm.phone" class="input" /></div>
          <div><label class="label">Email</label><input v-model="addForm.email" type="email" class="input" /><p v-if="addForm.errors.email" class="error-text">{{ addForm.errors.email }}</p></div>
          <div><label class="label">Alamat</label><input v-model="addForm.address" class="input" /></div>
          <button class="btn-primary w-full" :disabled="addForm.processing">Tambah supplier</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
