<script setup>
import { reactive, ref, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { useCan } from '@/Composables/can'
import { dateTime } from '@/Composables/format'

const props = defineProps({ fulfillment: Object, statuses: Object, couriers: Array })
const { can } = useCan()
const canProcess = can('fulfillment.process')

const picked = reactive({})
function syncPicked() { props.fulfillment.items.forEach((i) => { picked[i.id] = i.qty_picked }) }
syncPicked()
watch(() => props.fulfillment.items, syncPicked)

const ship = useForm({ courier: props.couriers[0], tracking_number: '' })
const cancelForm = useForm({ reason: '' })
const showCancel = ref(false)

const post = (name, data = {}) => router.post(route(`warehouse.fulfillments.${name}`, props.fulfillment.id), data, { preserveScroll: true })
function savePicked() { router.put(route('warehouse.fulfillments.picked', props.fulfillment.id), { picked: { ...picked } }, { preserveScroll: true }) }
function pickAll() { props.fulfillment.items.forEach((i) => { picked[i.id] = i.quantity }); savePicked() }
function submitShip() { ship.post(route('warehouse.fulfillments.ship', props.fulfillment.id), { preserveScroll: true }) }
function submitCancel() { cancelForm.post(route('warehouse.fulfillments.cancel', props.fulfillment.id), { preserveScroll: true, onSuccess: () => { showCancel.value = false } }) }

const steps = [['pending', 'Menunggu'], ['picking', 'Diambil'], ['packed', 'Dikemas'], ['shipped', 'Dikirim'], ['delivered', 'Diterima']]
const stepIndex = () => steps.findIndex(([k]) => k === props.fulfillment.status)
const f = props.fulfillment
</script>

<template>
  <AppLayout>
    <Head :title="fulfillment.number" />
    <template #title>{{ fulfillment.number }}<span v-if="fulfillment.source_number" class="ml-2 font-sans text-sm font-normal text-ink-500">pesanan {{ fulfillment.source_number }}</span></template>
    <template #actions>
      <StatusBadge :status="fulfillment.status" :labels="statuses" />
      <button v-if="canProcess && ['pending', 'picking', 'packed'].includes(fulfillment.status)" class="btn-ghost btn-sm text-red-600" @click="showCancel = !showCancel">Batalkan</button>
    </template>

    <ol v-if="fulfillment.status !== 'cancelled'" class="mb-6 flex max-w-3xl items-center gap-2 text-sm">
      <template v-for="([key, label], idx) in steps" :key="key">
        <li class="flex items-center gap-2" :class="idx <= stepIndex() ? 'font-semibold text-ink-900 dark:text-ink-100' : 'text-ink-400'">
          <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs" :class="idx <= stepIndex() ? 'bg-seal-500 text-ink-950' : 'bg-ink-100 dark:bg-ink-800'">{{ idx + 1 }}</span>{{ label }}
        </li>
        <li v-if="idx < steps.length - 1" class="h-px flex-1 bg-ink-200 dark:bg-ink-700" aria-hidden="true"></li>
      </template>
    </ol>
    <div v-else class="mb-6 max-w-3xl rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
      Dibatalkan {{ dateTime(fulfillment.cancelled_at) }}: {{ fulfillment.cancel_reason }}
    </div>

    <form v-if="showCancel" class="card mb-6 max-w-3xl" @submit.prevent="submitCancel">
      <div class="card-body space-y-3">
        <label class="label">Alasan pembatalan</label>
        <input v-model="cancelForm.reason" class="input" required placeholder="Mis. barang rusak saat diambil" />
        <p v-if="cancelForm.errors.reason" class="error-text">{{ cancelForm.errors.reason }}</p>
        <p class="text-xs text-ink-500">Modul ini tidak mengembalikan stok otomatis; stok pesanan dikelola modul Penjualan. Jika perlu, lakukan penyesuaian stok.</p>
        <button class="btn-danger btn-sm" :disabled="cancelForm.processing">Batalkan tugas</button>
      </div>
    </form>

    <div class="grid gap-6 lg:grid-cols-3">
      <div class="space-y-6 lg:col-span-1">
        <dl class="card card-body space-y-3 text-sm">
          <div><dt class="text-ink-500">Penerima</dt><dd class="font-medium">{{ f.recipient_name ?? '-' }}</dd></div>
          <div><dt class="text-ink-500">Telepon</dt><dd>{{ f.recipient_phone ?? '-' }}</dd></div>
          <div><dt class="text-ink-500">Alamat</dt><dd class="whitespace-pre-line">{{ f.shipping_address ?? '-' }}</dd></div>
          <div><dt class="text-ink-500">Disiapkan dari</dt><dd>{{ f.warehouse?.name }}</dd></div>
          <div><dt class="text-ink-500">Petugas</dt><dd>{{ f.assignee?.name ?? '-' }}</dd></div>
          <div v-if="f.notes"><dt class="text-ink-500">Catatan</dt><dd>{{ f.notes }}</dd></div>
          <div v-if="f.tracking_number"><dt class="text-ink-500">Kurir &amp; resi</dt><dd>{{ f.courier }} · <span class="font-mono">{{ f.tracking_number }}</span></dd></div>
        </dl>
      </div>

      <div class="space-y-6 lg:col-span-2">
        <div class="card">
          <div class="card-header">
            <h2 class="text-sm font-semibold">Daftar ambil barang</h2>
            <button v-if="canProcess && fulfillment.status === 'picking'" class="btn-ghost btn-sm" @click="pickAll">Tandai semua sudah diambil</button>
          </div>
          <table class="data-table">
            <thead><tr><th>Produk</th><th class="text-right">Diminta</th><th class="w-32 text-right">Diambil</th></tr></thead>
            <tbody>
              <tr v-for="i in fulfillment.items" :key="i.id">
                <td>{{ i.product_name }}<div class="font-mono text-xs text-ink-500">{{ i.sku }}</div></td>
                <td class="num text-right">{{ i.quantity }}</td>
                <td class="text-right">
                  <input v-if="canProcess && fulfillment.status === 'picking'" v-model.number="picked[i.id]" type="number" min="0" :max="i.quantity" class="input text-right" />
                  <span v-else class="num" :class="i.qty_picked >= i.quantity ? 'text-emerald-600' : ''">{{ i.qty_picked }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="canProcess" class="card card-body space-y-4">
          <template v-if="fulfillment.status === 'pending'">
            <p class="text-sm text-ink-600 dark:text-ink-300">Mulai mengambil barang untuk pesanan ini. Tugas akan tercatat atas namamu.</p>
            <div><button class="btn-primary" @click="post('start')">Mulai ambil barang</button></div>
          </template>
          <template v-else-if="fulfillment.status === 'picking'">
            <p class="text-sm text-ink-600 dark:text-ink-300">Isi jumlah yang sudah diambil, simpan, lalu kemas bila semuanya lengkap.</p>
            <div class="flex gap-3"><button class="btn-secondary" @click="savePicked">Simpan jumlah diambil</button><button class="btn-primary" @click="post('pack')">Kemas pesanan</button></div>
          </template>
          <template v-else-if="fulfillment.status === 'packed'">
            <form class="grid gap-3 sm:grid-cols-3" @submit.prevent="submitShip">
              <div><label class="label">Kurir</label><select v-model="ship.courier" class="input"><option v-for="c in couriers" :key="c" :value="c">{{ c }}</option></select></div>
              <div class="sm:col-span-2"><label class="label">Nomor resi</label><input v-model="ship.tracking_number" class="input font-mono" required /><p v-if="ship.errors.tracking_number" class="error-text">{{ ship.errors.tracking_number }}</p></div>
              <div class="sm:col-span-3"><button class="btn-primary" :disabled="ship.processing">Serahkan ke kurir</button></div>
            </form>
          </template>
          <template v-else-if="fulfillment.status === 'shipped'">
            <p class="text-sm text-ink-600 dark:text-ink-300">Pesanan sudah diserahkan ke kurir. Tandai diterima setelah pelanggan menerima barang.</p>
            <div><button class="btn-accent" @click="post('deliver')">Tandai sudah diterima</button></div>
          </template>
        </div>

        <div class="card card-body text-xs text-ink-500">
          <p>Dibuat {{ dateTime(f.created_at) }}<span v-if="f.picked_at"> · dikemas {{ dateTime(f.packed_at) }}</span><span v-if="f.shipped_at"> · dikirim {{ dateTime(f.shipped_at) }}</span><span v-if="f.delivered_at"> · diterima {{ dateTime(f.delivered_at) }}</span></p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
