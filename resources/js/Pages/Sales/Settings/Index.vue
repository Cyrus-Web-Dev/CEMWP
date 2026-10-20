<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { rupiah } from '@/Composables/format'

const props = defineProps({ paymentMethods: Array, shippingMethods: Array, kinds: Object, midtransReady: Boolean })

// ---------- Metode pembayaran ----------
const pBlank = () => ({ code: '', name: '', kind: 'manual', for_pos: false, for_online: true, requires_reference: false, is_active: true, sort_order: 10, instructions: '' })
const pAdd = useForm(pBlank())
const pEditId = ref(null)
const pEdit = useForm(pBlank())
const pClean = (d) => ({ ...d, instructions: d.instructions || null })
function pStart(m) { pEditId.value = m.id; Object.assign(pEdit, { code: m.code, name: m.name, kind: m.kind, for_pos: m.for_pos, for_online: m.for_online, requires_reference: m.requires_reference, is_active: m.is_active, sort_order: m.sort_order, instructions: m.instructions ?? '' }); pEdit.clearErrors() }
const pSave = (m) => pEdit.transform(pClean).put(route('sales.settings.payment.update', m.id), { preserveScroll: true, onSuccess: () => { pEditId.value = null } })
const pCreate = () => pAdd.transform(pClean).post(route('sales.settings.payment.store'), { preserveScroll: true, onSuccess: () => pAdd.reset() })
const pDelete = (m) => { if (confirm(`Hapus metode ${m.name}?`)) router.delete(route('sales.settings.payment.destroy', m.id), { preserveScroll: true }) }

// ---------- Metode pengiriman ----------
const sBlank = () => ({ courier: '', service: 'Reguler', is_pickup: false, base_cost: 0, cost_per_kg: 0, free_over: '', min_days: '', max_days: '', is_active: true, sort_order: 10 })
const sAdd = useForm(sBlank())
const sEditId = ref(null)
const sEdit = useForm(sBlank())
const sClean = (d) => ({ ...d, free_over: d.free_over === '' ? null : d.free_over, min_days: d.min_days === '' ? null : d.min_days, max_days: d.max_days === '' ? null : d.max_days })
function sStart(m) { sEditId.value = m.id; Object.assign(sEdit, { courier: m.courier, service: m.service, is_pickup: m.is_pickup, base_cost: Number(m.base_cost), cost_per_kg: Number(m.cost_per_kg), free_over: m.free_over ?? '', min_days: m.min_days ?? '', max_days: m.max_days ?? '', is_active: m.is_active, sort_order: m.sort_order }); sEdit.clearErrors() }
const sSave = (m) => sEdit.transform(sClean).put(route('sales.settings.shipping.update', m.id), { preserveScroll: true, onSuccess: () => { sEditId.value = null } })
const sCreate = () => sAdd.transform(sClean).post(route('sales.settings.shipping.store'), { preserveScroll: true, onSuccess: () => sAdd.reset() })
const sDelete = (m) => { if (confirm(`Hapus metode ${m.courier} ${m.service}?`)) router.delete(route('sales.settings.shipping.destroy', m.id), { preserveScroll: true }) }
const chk = 'rounded border-ink-300 text-seal-600 focus:ring-seal-500'
</script>

<template>
  <AppLayout>
    <Head title="Pengaturan penjualan" />
    <template #title>Pengaturan penjualan</template>

    <h2 class="mb-3 font-serif text-lg font-semibold">Metode pembayaran</h2>
    <div class="mb-10 grid gap-6 xl:grid-cols-3">
      <div class="space-y-3 xl:col-span-2">
        <div v-for="m in paymentMethods" :key="m.id" class="card">
          <div v-if="pEditId !== m.id" class="card-body flex flex-wrap items-start justify-between gap-3">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium">{{ m.name }}</span><span class="font-mono text-xs text-ink-500">{{ m.code }}</span>
                <span class="badge-slate">{{ kinds[m.kind] }}</span>
                <span v-if="m.for_pos" class="badge-seal">Kasir</span><span v-if="m.for_online" class="badge-seal">Online</span>
                <span :class="m.is_active ? 'badge-green' : 'badge-slate'">{{ m.is_active ? 'Aktif' : 'Nonaktif' }}</span>
              </div>
              <p v-if="m.instructions" class="mt-1 text-sm text-ink-500">{{ m.instructions }}</p>
              <p v-if="m.kind === 'gateway' && !midtransReady" class="mt-1 text-xs text-amber-600">MIDTRANS_SERVER_KEY belum diisi di .env, jadi belum bisa diaktifkan.</p>
            </div>
            <div class="whitespace-nowrap"><button class="btn-ghost btn-sm" @click="pStart(m)">Ubah</button><button class="btn-ghost btn-sm text-red-600" @click="pDelete(m)">Hapus</button></div>
          </div>
          <form v-else class="card-body grid gap-3 sm:grid-cols-2" @submit.prevent="pSave(m)">
            <div><label class="label">Kode</label><input v-model="pEdit.code" class="input font-mono uppercase" required /><p v-if="pEdit.errors.code" class="error-text">{{ pEdit.errors.code }}</p></div>
            <div><label class="label">Nama</label><input v-model="pEdit.name" class="input" required /></div>
            <div><label class="label">Jenis</label><select v-model="pEdit.kind" class="input"><option v-for="(l, k) in kinds" :key="k" :value="k">{{ l }}</option></select></div>
            <div><label class="label">Urutan</label><input v-model.number="pEdit.sort_order" type="number" min="0" class="input" /></div>
            <div class="flex flex-wrap gap-4 sm:col-span-2 text-sm">
              <label class="flex items-center gap-2"><input v-model="pEdit.for_pos" type="checkbox" :class="chk" /> Kasir</label>
              <label class="flex items-center gap-2"><input v-model="pEdit.for_online" type="checkbox" :class="chk" /> Online</label>
              <label class="flex items-center gap-2"><input v-model="pEdit.requires_reference" type="checkbox" :class="chk" /> Wajib no. referensi</label>
              <label class="flex items-center gap-2"><input v-model="pEdit.is_active" type="checkbox" :class="chk" /> Aktif</label>
            </div>
            <div class="sm:col-span-2"><label class="label">Instruksi untuk pembeli (mis. nomor rekening)</label><input v-model="pEdit.instructions" class="input" /></div>
            <div class="flex gap-2 sm:col-span-2"><button class="btn-accent btn-sm" :disabled="pEdit.processing">Simpan</button><button type="button" class="btn-ghost btn-sm" @click="pEditId = null">Batal</button></div>
          </form>
        </div>
      </div>

      <form class="card self-start" @submit.prevent="pCreate">
        <div class="card-header"><h2 class="text-sm font-semibold">Tambah metode pembayaran</h2></div>
        <div class="card-body space-y-3">
          <div><label class="label">Kode</label><input v-model="pAdd.code" class="input font-mono uppercase" placeholder="OVO" required /><p v-if="pAdd.errors.code" class="error-text">{{ pAdd.errors.code }}</p></div>
          <div><label class="label">Nama</label><input v-model="pAdd.name" class="input" required /></div>
          <div><label class="label">Jenis</label><select v-model="pAdd.kind" class="input"><option v-for="(l, k) in kinds" :key="k" :value="k">{{ l }}</option></select></div>
          <div class="flex flex-wrap gap-4 text-sm">
            <label class="flex items-center gap-2"><input v-model="pAdd.for_pos" type="checkbox" :class="chk" /> Kasir</label>
            <label class="flex items-center gap-2"><input v-model="pAdd.for_online" type="checkbox" :class="chk" /> Online</label>
            <label class="flex items-center gap-2"><input v-model="pAdd.requires_reference" type="checkbox" :class="chk" /> Wajib no. ref</label>
          </div>
          <div><label class="label">Instruksi</label><input v-model="pAdd.instructions" class="input" /></div>
          <button class="btn-primary w-full" :disabled="pAdd.processing">Tambah</button>
        </div>
      </form>
    </div>

    <h2 class="mb-3 font-serif text-lg font-semibold">Metode pengiriman</h2>
    <p class="mb-3 max-w-3xl text-sm text-ink-600 dark:text-ink-300">Ongkir = tarif dasar + tarif per kg (dibulatkan ke atas, minimal 1 kg). Tarif ini sederhana dan ditentukan sendiri; integrasi tarif kurir real-time bisa ditambahkan kelak.</p>
    <div class="grid gap-6 xl:grid-cols-3">
      <div class="space-y-3 xl:col-span-2">
        <div v-for="m in shippingMethods" :key="m.id" class="card">
          <div v-if="sEditId !== m.id" class="card-body flex flex-wrap items-start justify-between gap-3">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium">{{ m.is_pickup ? 'Ambil di toko' : `${m.courier} ${m.service}` }}</span>
                <span v-if="m.is_pickup" class="badge-seal">Ambil sendiri</span><span :class="m.is_active ? 'badge-green' : 'badge-slate'">{{ m.is_active ? 'Aktif' : 'Nonaktif' }}</span>
              </div>
              <p v-if="!m.is_pickup" class="mt-1 text-sm text-ink-500">{{ rupiah(m.base_cost) }} + {{ rupiah(m.cost_per_kg) }}/kg<span v-if="m.free_over"> · gratis di atas {{ rupiah(m.free_over) }}</span><span v-if="m.max_days != null"> · {{ m.min_days ?? 0 }}-{{ m.max_days }} hari</span></p>
            </div>
            <div class="whitespace-nowrap"><button class="btn-ghost btn-sm" @click="sStart(m)">Ubah</button><button class="btn-ghost btn-sm text-red-600" @click="sDelete(m)">Hapus</button></div>
          </div>
          <form v-else class="card-body grid gap-3 sm:grid-cols-2" @submit.prevent="sSave(m)">
            <div><label class="label">Kurir</label><input v-model="sEdit.courier" class="input" required /></div>
            <div><label class="label">Layanan</label><input v-model="sEdit.service" class="input" required /></div>
            <div><label class="label">Tarif dasar</label><input v-model.number="sEdit.base_cost" type="number" min="0" class="input text-right" /></div>
            <div><label class="label">Tarif per kg</label><input v-model.number="sEdit.cost_per_kg" type="number" min="0" class="input text-right" /></div>
            <div><label class="label">Gratis ongkir di atas</label><input v-model="sEdit.free_over" type="number" min="0" class="input text-right" placeholder="Tidak ada" /></div>
            <div class="grid grid-cols-2 gap-2"><div><label class="label">Min. hari</label><input v-model="sEdit.min_days" type="number" min="0" class="input" /></div><div><label class="label">Maks. hari</label><input v-model="sEdit.max_days" type="number" min="0" class="input" /><p v-if="sEdit.errors.max_days" class="error-text">{{ sEdit.errors.max_days }}</p></div></div>
            <div class="flex gap-4 text-sm sm:col-span-2"><label class="flex items-center gap-2"><input v-model="sEdit.is_pickup" type="checkbox" :class="chk" /> Ambil di toko</label><label class="flex items-center gap-2"><input v-model="sEdit.is_active" type="checkbox" :class="chk" /> Aktif</label></div>
            <div class="flex gap-2 sm:col-span-2"><button class="btn-accent btn-sm" :disabled="sEdit.processing">Simpan</button><button type="button" class="btn-ghost btn-sm" @click="sEditId = null">Batal</button></div>
          </form>
        </div>
      </div>

      <form class="card self-start" @submit.prevent="sCreate">
        <div class="card-header"><h2 class="text-sm font-semibold">Tambah metode pengiriman</h2></div>
        <div class="card-body space-y-3">
          <div><label class="label">Kurir</label><input v-model="sAdd.courier" class="input" placeholder="SiCepat" required /><p v-if="sAdd.errors.courier" class="error-text">{{ sAdd.errors.courier }}</p></div>
          <div><label class="label">Layanan</label><input v-model="sAdd.service" class="input" required /></div>
          <div class="grid grid-cols-2 gap-3">
            <div><label class="label">Tarif dasar</label><input v-model.number="sAdd.base_cost" type="number" min="0" class="input text-right" /></div>
            <div><label class="label">Per kg</label><input v-model.number="sAdd.cost_per_kg" type="number" min="0" class="input text-right" /></div>
          </div>
          <div><label class="label">Gratis ongkir di atas</label><input v-model="sAdd.free_over" type="number" min="0" class="input text-right" placeholder="Tidak ada" /></div>
          <label class="flex items-center gap-2 text-sm"><input v-model="sAdd.is_pickup" type="checkbox" :class="chk" /> Ambil di toko</label>
          <button class="btn-primary w-full" :disabled="sAdd.processing">Tambah</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
