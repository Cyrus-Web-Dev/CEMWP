<script setup>
import { watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { useCan } from '@/Composables/can'
import { num, rupiah, dateOnly, dateTime } from '@/Composables/format'

const props = defineProps({ order: Object, statuses: Object, requestKey: String })
const { can } = useCan()

const total = props.order.items.reduce((s, i) => s + i.qty_ordered * Number(i.unit_cost), 0)
const receivable = () => ['ordered', 'partial'].includes(props.order.status)

function freshQuantities() {
  const q = {}
  props.order.items.forEach((i) => { q[i.id] = i.qty_ordered - i.qty_received })
  return q
}
const recv = useForm({ request_key: props.requestKey, notes: '', quantities: freshQuantities() })

// Setelah penerimaan berhasil server memberi token & sisa pesanan baru; form harus ikut disegarkan.
watch(() => props.requestKey, () => {
  recv.request_key = props.requestKey
  recv.quantities = freshQuantities()
  recv.notes = ''
})

function submitReceive() {
  recv.post(route('warehouse.purchase-orders.receive', props.order.id), { preserveScroll: true })
}
function act(name, message) {
  if (!message || confirm(message)) router.post(route(`warehouse.purchase-orders.${name}`, props.order.id), {}, { preserveScroll: true })
}
</script>

<template>
  <AppLayout>
    <Head :title="order.number" />
    <template #title>{{ order.number }}</template>
    <template #actions>
      <template v-if="can('purchasing.manage')">
        <Link v-if="order.status === 'draft'" :href="route('warehouse.purchase-orders.edit', order.id)" class="btn-secondary btn-sm">Ubah</Link>
        <button v-if="order.status === 'draft'" class="btn-accent btn-sm" @click="act('place', 'Tandai PO ini sudah dipesan ke supplier? Setelah itu PO tidak bisa diubah.')">Tandai dipesan</button>
        <button v-if="['draft', 'ordered'].includes(order.status)" class="btn-ghost btn-sm text-red-600" @click="act('cancel', 'Batalkan PO ini?')">Batalkan</button>
        <button v-if="order.status === 'partial'" class="btn-secondary btn-sm" @click="act('close', 'Tutup PO? Sisa barang yang belum datang dianggap tidak akan diterima.')">Tutup PO</button>
      </template>
    </template>

    <div class="grid gap-6 lg:grid-cols-3">
      <div class="card self-start">
        <div class="card-header"><h2 class="text-sm font-semibold">Ringkasan</h2><StatusBadge :status="order.status" :labels="statuses" /></div>
        <dl class="card-body space-y-3 text-sm">
          <div><dt class="text-ink-500">Supplier</dt><dd class="font-medium">{{ order.supplier?.name }}</dd></div>
          <div><dt class="text-ink-500">Dikirim ke</dt><dd>{{ order.warehouse?.name }}</dd></div>
          <div><dt class="text-ink-500">Dipesan</dt><dd>{{ dateOnly(order.ordered_at) }}</dd></div>
          <div><dt class="text-ink-500">Estimasi tiba</dt><dd>{{ dateOnly(order.expected_at) }}</dd></div>
          <div><dt class="text-ink-500">Total nilai</dt><dd class="num font-semibold">{{ rupiah(total) }}</dd></div>
          <div v-if="order.notes"><dt class="text-ink-500">Catatan</dt><dd>{{ order.notes }}</dd></div>
          <div><dt class="text-ink-500">Dibuat oleh</dt><dd>{{ order.creator?.name ?? '-' }}</dd></div>
          <div v-if="order.receipts?.length">
            <dt class="text-ink-500">Penerimaan</dt>
            <dd><Link v-for="r in order.receipts" :key="r.id" :href="route('warehouse.receipts.show', r.id)" class="mr-2 block font-mono text-xs hover:underline">{{ r.number }} · {{ dateTime(r.received_at) }}</Link></dd>
          </div>
        </dl>
      </div>

      <div class="space-y-6 lg:col-span-2">
        <form class="card" @submit.prevent="submitReceive">
          <div class="card-header">
            <h2 class="text-sm font-semibold">Barang</h2>
            <span v-if="receivable() && can('purchasing.receive')" class="text-xs text-ink-500">Isi jumlah yang diterima sekarang</span>
          </div>
          <div class="overflow-x-auto">
            <table class="data-table">
              <thead>
                <tr><th>Produk</th><th class="text-right">Dipesan</th><th class="text-right">Sudah diterima</th><th class="text-right">Harga</th><th v-if="receivable() && can('purchasing.receive')" class="w-32 text-right">Terima sekarang</th></tr>
              </thead>
              <tbody>
                <tr v-for="i in order.items" :key="i.id">
                  <td>{{ i.product?.name }}<div class="font-mono text-xs text-ink-500">{{ i.product?.sku }}</div></td>
                  <td class="num text-right">{{ num(i.qty_ordered) }}</td>
                  <td class="num text-right" :class="i.qty_received >= i.qty_ordered ? 'text-emerald-600' : ''">{{ num(i.qty_received) }}</td>
                  <td class="num text-right">{{ rupiah(i.unit_cost) }}</td>
                  <td v-if="receivable() && can('purchasing.receive')" class="text-right">
                    <input v-model.number="recv.quantities[i.id]" type="number" min="0" :max="i.qty_ordered - i.qty_received" step="1" class="input text-right" :disabled="i.qty_received >= i.qty_ordered" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-if="receivable() && can('purchasing.receive')" class="card-body border-t border-ink-100 dark:border-ink-800">
            <label class="label">Catatan penerimaan</label>
            <input v-model="recv.notes" class="input" placeholder="Opsional, mis. 2 dus penyok" />
            <p v-if="recv.errors.quantities" class="error-text">{{ recv.errors.quantities }}</p>
            <button class="btn-primary mt-4" :disabled="recv.processing">Terima barang &amp; tambah stok</button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
