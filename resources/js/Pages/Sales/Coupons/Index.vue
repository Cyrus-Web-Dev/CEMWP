<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { num, rupiah, dateOnly } from '@/Composables/format'

const props = defineProps({ coupons: Object })

const blank = () => ({
  code: '', name: '', type: 'percent', value: 10, min_subtotal: 0, max_discount: '', channel: 'all',
  starts_at: '', ends_at: '', usage_limit: '', usage_limit_per_customer: 1, is_active: true,
})
const toLocal = (v) => (v ? String(v).replace(' ', 'T').slice(0, 16) : '')
const clean = (d) => ({
  ...d, max_discount: d.max_discount === '' ? null : d.max_discount, starts_at: d.starts_at || null, ends_at: d.ends_at || null,
  usage_limit: d.usage_limit === '' ? null : d.usage_limit, usage_limit_per_customer: d.usage_limit_per_customer === '' ? null : d.usage_limit_per_customer,
})

const addForm = useForm(blank())
function add() { addForm.transform(clean).post(route('sales.coupons.store'), { preserveScroll: true, onSuccess: () => addForm.reset() }) }

const editingId = ref(null)
const editForm = useForm(blank())
function startEdit(c) {
  editingId.value = c.id
  Object.assign(editForm, {
    code: c.code, name: c.name, type: c.type, value: Number(c.value), min_subtotal: Number(c.min_subtotal), max_discount: c.max_discount ?? '',
    channel: c.channel, starts_at: toLocal(c.starts_at), ends_at: toLocal(c.ends_at), usage_limit: c.usage_limit ?? '',
    usage_limit_per_customer: c.usage_limit_per_customer ?? '', is_active: c.is_active,
  })
  editForm.clearErrors()
}
function saveEdit(c) { editForm.transform(clean).put(route('sales.coupons.update', c.id), { preserveScroll: true, onSuccess: () => { editingId.value = null } }) }
function remove(c) { if (confirm(`Hapus kupon ${c.code}?`)) router.delete(route('sales.coupons.destroy', c.id), { preserveScroll: true }) }
const channelLabel = { all: 'Semua saluran', pos: 'Kasir saja', online: 'Online saja' }
</script>

<template>
  <AppLayout>
    <Head title="Kupon" />
    <template #title>Kupon diskon</template>

    <div class="grid gap-6 xl:grid-cols-3">
      <div class="space-y-3 xl:col-span-2">
        <div v-for="c in coupons.data" :key="c.id" class="card">
          <div v-if="editingId !== c.id" class="card-body flex flex-wrap items-start justify-between gap-3">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono text-base font-semibold">{{ c.code }}</span>
                <span :class="c.is_active ? 'badge-green' : 'badge-slate'">{{ c.is_active ? 'Aktif' : 'Nonaktif' }}</span>
                <span class="badge-seal">{{ c.type === 'percent' ? `${num(c.value)}%` : rupiah(c.value) }}</span>
                <span v-if="c.customer" class="badge-amber">Khusus {{ c.customer.name }}</span>
              </div>
              <p class="mt-1 text-sm">{{ c.name }}</p>
              <p class="mt-1 text-xs text-ink-500">
                {{ channelLabel[c.channel] }} · min. belanja {{ rupiah(c.min_subtotal) }}<span v-if="c.max_discount"> · maks. potongan {{ rupiah(c.max_discount) }}</span>
                <span v-if="c.starts_at || c.ends_at"> · {{ dateOnly(c.starts_at) }} s/d {{ c.ends_at ? dateOnly(c.ends_at) : 'tanpa batas' }}</span>
              </p>
              <p class="text-xs text-ink-500">Dipakai {{ num(c.used_count) }}<span v-if="c.usage_limit"> dari {{ num(c.usage_limit) }}</span>x<span v-if="c.usage_limit_per_customer"> · maks. {{ c.usage_limit_per_customer }}x per pelanggan</span></p>
            </div>
            <div class="whitespace-nowrap"><button class="btn-ghost btn-sm" @click="startEdit(c)">Ubah</button><button class="btn-ghost btn-sm text-red-600" @click="remove(c)">Hapus</button></div>
          </div>

          <form v-else class="card-body grid gap-3 sm:grid-cols-2" @submit.prevent="saveEdit(c)">
            <div><label class="label">Kode</label><input v-model="editForm.code" class="input font-mono uppercase" required /><p v-if="editForm.errors.code" class="error-text">{{ editForm.errors.code }}</p></div>
            <div><label class="label">Nama</label><input v-model="editForm.name" class="input" required /></div>
            <div><label class="label">Jenis</label><select v-model="editForm.type" class="input"><option value="percent">Persen</option><option value="fixed">Nominal (Rp)</option></select></div>
            <div><label class="label">Nilai</label><input v-model.number="editForm.value" type="number" min="1" step="any" class="input text-right" required /><p v-if="editForm.errors.value" class="error-text">{{ editForm.errors.value }}</p></div>
            <div><label class="label">Min. belanja</label><input v-model.number="editForm.min_subtotal" type="number" min="0" class="input text-right" /></div>
            <div><label class="label">Maks. potongan (persen)</label><input v-model="editForm.max_discount" type="number" min="1" class="input text-right" :disabled="editForm.type !== 'percent'" /></div>
            <div><label class="label">Saluran</label><select v-model="editForm.channel" class="input"><option value="all">Semua</option><option value="pos">Kasir</option><option value="online">Online</option></select></div>
            <div class="flex items-end"><label class="flex items-center gap-2 text-sm"><input v-model="editForm.is_active" type="checkbox" class="rounded border-ink-300 text-seal-600 focus:ring-seal-500" /> Aktif</label></div>
            <div><label class="label">Mulai</label><input v-model="editForm.starts_at" type="datetime-local" class="input" /></div>
            <div><label class="label">Berakhir</label><input v-model="editForm.ends_at" type="datetime-local" class="input" /><p v-if="editForm.errors.ends_at" class="error-text">{{ editForm.errors.ends_at }}</p></div>
            <div><label class="label">Batas total pemakaian</label><input v-model="editForm.usage_limit" type="number" min="1" class="input text-right" placeholder="Tanpa batas" /></div>
            <div><label class="label">Batas per pelanggan</label><input v-model="editForm.usage_limit_per_customer" type="number" min="1" class="input text-right" placeholder="Tanpa batas" /></div>
            <div class="flex gap-2 sm:col-span-2"><button class="btn-accent btn-sm" :disabled="editForm.processing">Simpan</button><button type="button" class="btn-ghost btn-sm" @click="editingId = null">Batal</button></div>
          </form>
        </div>
        <p v-if="!coupons.data.length" class="text-sm text-ink-500">Belum ada kupon.</p>
        <Pagination :links="coupons.links" />
      </div>

      <form class="card self-start" @submit.prevent="add">
        <div class="card-header"><h2 class="text-sm font-semibold">Buat kupon</h2></div>
        <div class="card-body space-y-4">
          <div><label class="label">Kode</label><input v-model="addForm.code" class="input font-mono uppercase" placeholder="HEMAT10" required /><p v-if="addForm.errors.code" class="error-text">{{ addForm.errors.code }}</p></div>
          <div><label class="label">Nama</label><input v-model="addForm.name" class="input" required /><p v-if="addForm.errors.name" class="error-text">{{ addForm.errors.name }}</p></div>
          <div class="grid grid-cols-2 gap-3">
            <div><label class="label">Jenis</label><select v-model="addForm.type" class="input"><option value="percent">Persen</option><option value="fixed">Nominal</option></select></div>
            <div><label class="label">Nilai</label><input v-model.number="addForm.value" type="number" min="1" step="any" class="input text-right" required /><p v-if="addForm.errors.value" class="error-text">{{ addForm.errors.value }}</p></div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div><label class="label">Min. belanja</label><input v-model.number="addForm.min_subtotal" type="number" min="0" class="input text-right" /></div>
            <div><label class="label">Maks. potongan</label><input v-model="addForm.max_discount" type="number" min="1" class="input text-right" :disabled="addForm.type !== 'percent'" /></div>
          </div>
          <div><label class="label">Saluran</label><select v-model="addForm.channel" class="input"><option value="all">Semua</option><option value="pos">Kasir</option><option value="online">Online</option></select></div>
          <div class="grid grid-cols-2 gap-3">
            <div><label class="label">Mulai</label><input v-model="addForm.starts_at" type="datetime-local" class="input" /></div>
            <div><label class="label">Berakhir</label><input v-model="addForm.ends_at" type="datetime-local" class="input" /><p v-if="addForm.errors.ends_at" class="error-text">{{ addForm.errors.ends_at }}</p></div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div><label class="label">Batas total</label><input v-model="addForm.usage_limit" type="number" min="1" class="input text-right" placeholder="∞" /></div>
            <div><label class="label">Per pelanggan</label><input v-model="addForm.usage_limit_per_customer" type="number" min="1" class="input text-right" placeholder="∞" /></div>
          </div>
          <button class="btn-primary w-full" :disabled="addForm.processing">Buat kupon</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
