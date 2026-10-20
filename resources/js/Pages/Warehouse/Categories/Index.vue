<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { useCan } from '@/Composables/can'

const props = defineProps({ categories: Array })
const { can } = useCan()

const addForm = useForm({ name: '', parent_id: '' })
function add() {
  addForm.transform((d) => ({ ...d, parent_id: d.parent_id === '' ? null : d.parent_id }))
    .post(route('warehouse.categories.store'), { preserveScroll: true, onSuccess: () => addForm.reset() })
}

const editingId = ref(null)
const editForm = useForm({ name: '', parent_id: '' })
function startEdit(c) {
  editingId.value = c.id
  editForm.name = c.name
  editForm.parent_id = c.parent_id ?? ''
  editForm.clearErrors()
}
function saveEdit(c) {
  editForm.transform((d) => ({ ...d, parent_id: d.parent_id === '' ? null : d.parent_id }))
    .put(route('warehouse.categories.update', c.id), { preserveScroll: true, onSuccess: () => { editingId.value = null } })
}
function remove(c) {
  if (confirm(`Hapus kategori "${c.name}"? Produk di dalamnya menjadi tanpa kategori.`)) router.delete(route('warehouse.categories.destroy', c.id), { preserveScroll: true })
}
</script>

<template>
  <AppLayout>
    <Head title="Kategori" />
    <template #title>Kategori produk</template>

    <div class="grid gap-6 lg:grid-cols-3">
      <div class="card lg:col-span-2">
        <table class="data-table">
          <thead><tr><th>Nama</th><th>Induk</th><th class="text-right">Produk</th><th v-if="can('categories.manage')"></th></tr></thead>
          <tbody>
            <tr v-for="c in categories" :key="c.id">
              <template v-if="editingId === c.id">
                <td>
                  <input v-model="editForm.name" class="input" />
                  <p v-if="editForm.errors.name" class="error-text">{{ editForm.errors.name }}</p>
                </td>
                <td>
                  <select v-model="editForm.parent_id" class="input">
                    <option value="">Tanpa induk</option>
                    <option v-for="p in categories.filter((x) => x.id !== c.id)" :key="p.id" :value="p.id">{{ p.name }}</option>
                  </select>
                </td>
                <td></td>
                <td class="whitespace-nowrap text-right">
                  <button class="btn-accent btn-sm" :disabled="editForm.processing" @click="saveEdit(c)">Simpan</button>
                  <button class="btn-ghost btn-sm" @click="editingId = null">Batal</button>
                </td>
              </template>
              <template v-else>
                <td class="font-medium">{{ c.name }}</td>
                <td class="text-sm text-ink-500">{{ c.parent?.name ?? '-' }}</td>
                <td class="num text-right">{{ c.products_count }}</td>
                <td v-if="can('categories.manage')" class="whitespace-nowrap text-right">
                  <button class="btn-ghost btn-sm" @click="startEdit(c)">Ubah</button>
                  <button class="btn-ghost btn-sm text-red-600" @click="remove(c)">Hapus</button>
                </td>
              </template>
            </tr>
            <tr v-if="!categories.length"><td colspan="4" class="py-8 text-center text-ink-500">Belum ada kategori.</td></tr>
          </tbody>
        </table>
      </div>

      <form v-if="can('categories.manage')" class="card self-start" @submit.prevent="add">
        <div class="card-header"><h2 class="text-sm font-semibold">Tambah kategori</h2></div>
        <div class="card-body space-y-4">
          <div><label class="label">Nama</label><input v-model="addForm.name" class="input" required /><p v-if="addForm.errors.name" class="error-text">{{ addForm.errors.name }}</p></div>
          <div>
            <label class="label">Induk (opsional)</label>
            <select v-model="addForm.parent_id" class="input"><option value="">Tanpa induk</option><option v-for="p in categories" :key="p.id" :value="p.id">{{ p.name }}</option></select>
          </div>
          <button class="btn-primary w-full" :disabled="addForm.processing">Tambah</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
