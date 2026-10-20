<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { useCan } from '@/Composables/can'
import { num } from '@/Composables/format'

const props = defineProps({ locations: Array, types: Object })
const { can } = useCan()

const blank = () => ({ code: '', name: '', type: 'warehouse', address: '', is_active: true, is_default: false })
const addForm = useForm(blank())
function add() {
  addForm.post(route('warehouse.locations.store'), { preserveScroll: true, onSuccess: () => addForm.reset() })
}

const editingId = ref(null)
const editForm = useForm(blank())
function startEdit(l) {
  editingId.value = l.id
  Object.assign(editForm, { code: l.code, name: l.name, type: l.type, address: l.address ?? '', is_active: l.is_active, is_default: l.is_default })
  editForm.clearErrors()
}
function saveEdit(l) {
  editForm.put(route('warehouse.locations.update', l.id), { preserveScroll: true, onSuccess: () => { editingId.value = null } })
}
function remove(l) {
  if (confirm(`Hapus lokasi "${l.name}"?`)) router.delete(route('warehouse.locations.destroy', l.id), { preserveScroll: true })
}
</script>

<template>
  <AppLayout>
    <Head title="Lokasi stok" />
    <template #title>Lokasi stok</template>

    <div class="grid gap-6 lg:grid-cols-3">
      <div class="space-y-3 lg:col-span-2">
        <div v-for="l in locations" :key="l.id" class="card">
          <div v-if="editingId !== l.id" class="card-body flex flex-wrap items-start justify-between gap-3">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium">{{ l.name }}</span>
                <span class="font-mono text-xs text-ink-500">{{ l.code }}</span>
                <span class="badge-slate">{{ types[l.type] ?? l.type }}</span>
                <span v-if="l.is_default" class="badge-seal">Bawaan</span>
                <span v-if="!l.is_active" class="badge-red">Nonaktif</span>
              </div>
              <p v-if="l.address" class="mt-1 text-sm text-ink-500">{{ l.address }}</p>
              <p class="num mt-1 text-sm text-ink-500">{{ num(l.units_on_hand ?? 0) }} unit · {{ l.inventories_count }} produk tercatat</p>
            </div>
            <div v-if="can('warehouses.manage')" class="whitespace-nowrap">
              <button class="btn-ghost btn-sm" @click="startEdit(l)">Ubah</button>
              <button class="btn-ghost btn-sm text-red-600" @click="remove(l)">Hapus</button>
            </div>
          </div>

          <form v-else class="card-body grid gap-3 sm:grid-cols-2" @submit.prevent="saveEdit(l)">
            <div><label class="label">Kode</label><input v-model="editForm.code" class="input font-mono" required /><p v-if="editForm.errors.code" class="error-text">{{ editForm.errors.code }}</p></div>
            <div><label class="label">Nama</label><input v-model="editForm.name" class="input" required /><p v-if="editForm.errors.name" class="error-text">{{ editForm.errors.name }}</p></div>
            <div><label class="label">Jenis</label><select v-model="editForm.type" class="input"><option v-for="(label, key) in types" :key="key" :value="key">{{ label }}</option></select></div>
            <div><label class="label">Alamat</label><input v-model="editForm.address" class="input" /></div>
            <label class="flex items-center gap-2 text-sm"><input v-model="editForm.is_active" type="checkbox" class="rounded border-ink-300 text-seal-600 focus:ring-seal-500" /> Aktif</label>
            <label class="flex items-center gap-2 text-sm"><input v-model="editForm.is_default" type="checkbox" class="rounded border-ink-300 text-seal-600 focus:ring-seal-500" /> Lokasi bawaan</label>
            <div class="flex gap-2 sm:col-span-2">
              <button class="btn-accent btn-sm" :disabled="editForm.processing">Simpan</button>
              <button type="button" class="btn-ghost btn-sm" @click="editingId = null">Batal</button>
            </div>
          </form>
        </div>
        <p v-if="!locations.length" class="text-sm text-ink-500">Belum ada lokasi stok.</p>
      </div>

      <form v-if="can('warehouses.manage')" class="card self-start" @submit.prevent="add">
        <div class="card-header"><h2 class="text-sm font-semibold">Tambah lokasi</h2></div>
        <div class="card-body space-y-4">
          <div><label class="label">Kode</label><input v-model="addForm.code" class="input font-mono" placeholder="GD-02" required /><p v-if="addForm.errors.code" class="error-text">{{ addForm.errors.code }}</p></div>
          <div><label class="label">Nama</label><input v-model="addForm.name" class="input" required /><p v-if="addForm.errors.name" class="error-text">{{ addForm.errors.name }}</p></div>
          <div><label class="label">Jenis</label><select v-model="addForm.type" class="input"><option v-for="(label, key) in types" :key="key" :value="key">{{ label }}</option></select></div>
          <div><label class="label">Alamat</label><input v-model="addForm.address" class="input" /></div>
          <label class="flex items-center gap-2 text-sm"><input v-model="addForm.is_default" type="checkbox" class="rounded border-ink-300 text-seal-600 focus:ring-seal-500" /> Jadikan lokasi bawaan</label>
          <button class="btn-primary w-full" :disabled="addForm.processing">Tambah lokasi</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
