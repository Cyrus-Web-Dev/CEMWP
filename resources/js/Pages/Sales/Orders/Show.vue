<script setup>
import { ref, reactive } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { useCan } from '@/Composables/can'
import { num, rupiah, dateTime } from '@/Composables/format'

const props = defineProps({ order: Object, returnable: Object, statuses: Object, paymentLabels: Object, refundMethods: Object })
const { can } = useCan()
const o = props.order

const cls = {
  awaiting_payment: 'badge-amber', processing: 'badge-seal', shipped: 'badge-seal', completed: 'badge-green',
  cancelled: 'badge-red', expired: 'badge-slate', needs_review: 'badge-red',
}

const manualPayment = o.payments.find((p) => p.kind === 'manual' && ['pending', 'expired', 'failed'].includes(p.status))
const canConfirm = can('orders.manage') && manualPayment && ['awaiting_payment', 'expired', 'cancelled'].includes(o.status)
const canCancel = can('orders.manage') && ['awaiting_payment', 'processing', 'needs_review'].includes(o.status)
const canRefund = can('orders.refund') && ['completed', 'processing', 'shipped'].includes(o.status) && ['paid', 'partially_refunded'].includes(o.payment_status)

const confirm1 = useForm({ reference: '' })
const cancelForm = useForm({ reason: '' })
const showCancel = ref(false)
const showReturn = ref(false)

const qty = reactive({})
o.items.forEach((i) => { qty[i.id] = 0 })
const returnForm = useForm({ quantities: qty, reason: '', refund_method: 'cash', restock: true })

function submitReturn() { returnForm.post(route('sales.orders.returns', o.id), { preserveScroll: true, onSuccess: () => { showReturn.value = false } }) }
function markRefunded() { if (confirm('Konfirmasi dana sudah dikembalikan ke pelanggan?')) router.post(route('sales.orders.refunded', o.id), {}, { preserveScroll: true }) }
</script>

<template>
  <AppLayout>
    <Head :title="o.number" />
    <template #title>{{ o.number }}</template>
    <template #actions>
      <span :class="cls[o.status] ?? 'badge-slate'">{{ statuses[o.status] ?? o.status }}</span>
      <Link v-if="o.channel === 'pos'" :href="route('sales.pos.receipt', o.id)" class="btn-secondary btn-sm">Struk</Link>
      <button v-if="canRefund" class="btn-secondary btn-sm" @click="showReturn = !showReturn">Retur barang</button>
      <button v-if="canCancel" class="btn-ghost btn-sm text-red-600" @click="showCancel = !showCancel">Batalkan</button>
    </template>

    <div v-if="o.status === 'needs_review'" class="mb-6 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
      <strong>Perlu ditinjau:</strong> pembayaran sudah masuk tetapi barang tidak bisa dipenuhi (stok habis atau dibatalkan gudang).
      Batalkan pesanan lalu proses refund, atau tambah stok dan hubungi pelanggan.
    </div>
    <div v-if="o.payment_status === 'refund_pending'" class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
      <span>Pesanan dibatalkan setelah dibayar. Kembalikan dana ke pelanggan secara manual, lalu tandai selesai.</span>
      <button v-if="can('orders.manage')" class="btn-accent btn-sm" @click="markRefunded">Dana sudah dikembalikan</button>
    </div>

    <form v-if="canConfirm" class="card mb-6 max-w-3xl" @submit.prevent="confirm1.post(route('sales.orders.confirm-payment', o.id), { preserveScroll: true })">
      <div class="card-body flex flex-wrap items-end gap-3">
        <div class="min-w-[14rem] flex-1"><label class="label">Konfirmasi pembayaran masuk ({{ manualPayment.method_name }})</label><input v-model="confirm1.reference" class="input" placeholder="No. referensi / nama pengirim (opsional)" /></div>
        <button class="btn-accent" :disabled="confirm1.processing">Konfirmasi lunas</button>
      </div>
    </form>

    <form v-if="showCancel" class="card mb-6 max-w-3xl" @submit.prevent="cancelForm.post(route('sales.orders.cancel', o.id), { preserveScroll: true })">
      <div class="card-body space-y-3">
        <label class="label">Alasan pembatalan</label>
        <input v-model="cancelForm.reason" class="input" required />
        <p v-if="cancelForm.errors.reason" class="error-text">{{ cancelForm.errors.reason }}</p>
        <p class="text-xs text-ink-500">Stok dikembalikan / dilepas, kupon dipulihkan, dan tugas gudang dibatalkan. Pesanan yang sudah dikirim tidak bisa dibatalkan (gunakan retur).</p>
        <button class="btn-danger btn-sm" :disabled="cancelForm.processing">Batalkan pesanan</button>
      </div>
    </form>

    <form v-if="showReturn" class="card mb-6 max-w-3xl" @submit.prevent="submitReturn">
      <div class="card-header"><h2 class="text-sm font-semibold">Retur barang</h2></div>
      <div class="card-body space-y-4">
        <table class="data-table">
          <thead><tr><th>Barang</th><th class="text-right">Bisa diretur</th><th class="w-28 text-right">Jumlah retur</th></tr></thead>
          <tbody>
            <tr v-for="i in o.items" :key="i.id">
              <td>{{ i.name }}</td><td class="num text-right">{{ returnable[i.id] }}</td>
              <td class="text-right"><input v-model.number="returnForm.quantities[i.id]" type="number" min="0" :max="returnable[i.id]" :disabled="!returnable[i.id]" class="input text-right" /></td>
            </tr>
          </tbody>
        </table>
        <div class="grid gap-4 sm:grid-cols-2">
          <div><label class="label">Alasan</label><input v-model="returnForm.reason" class="input" required /><p v-if="returnForm.errors.reason" class="error-text">{{ returnForm.errors.reason }}</p></div>
          <div><label class="label">Pengembalian dana</label><select v-model="returnForm.refund_method" class="input"><option v-for="(l, k) in refundMethods" :key="k" :value="k">{{ l }}</option></select></div>
        </div>
        <label class="flex items-center gap-2 text-sm"><input v-model="returnForm.restock" type="checkbox" class="rounded border-ink-300 text-seal-600 focus:ring-seal-500" /> Kembalikan barang ke stok (hapus centang bila barang rusak)</label>
        <p class="text-xs text-ink-500">Ongkir dan pajak tidak ikut dikembalikan. Refund tunai membutuhkan shift kasir yang terbuka.</p>
        <button class="btn-accent" :disabled="returnForm.processing">Catat retur</button>
      </div>
    </form>

    <div class="grid gap-6 lg:grid-cols-3">
      <div class="space-y-6">
        <dl class="card card-body space-y-3 text-sm">
          <div><dt class="text-ink-500">Waktu</dt><dd>{{ dateTime(o.placed_at) }}</dd></div>
          <div><dt class="text-ink-500">Saluran</dt><dd>{{ o.channel === 'pos' ? `Kasir (${o.warehouse?.name})` : 'Toko online' }}</dd></div>
          <div v-if="o.cashier"><dt class="text-ink-500">Kasir</dt><dd>{{ o.cashier.name }}</dd></div>
          <div><dt class="text-ink-500">Pelanggan</dt><dd>{{ o.customer_name ?? 'Umum' }}<span class="block text-xs text-ink-500">{{ o.customer_phone }} {{ o.customer_email }}</span></dd></div>
          <div><dt class="text-ink-500">Pembayaran</dt><dd>{{ paymentLabels[o.payment_status] ?? o.payment_status }}</dd></div>
          <template v-if="o.channel === 'ecommerce'">
            <div><dt class="text-ink-500">Pengiriman</dt><dd>{{ o.shipping_label }}</dd></div>
            <div><dt class="text-ink-500">Penerima</dt><dd>{{ o.recipient_name }} · {{ o.recipient_phone }}<span class="block whitespace-pre-line text-ink-500">{{ o.shipping_address }}<span v-if="o.shipping_city">, {{ o.shipping_city }}</span></span></dd></div>
            <div v-if="o.tracking_number"><dt class="text-ink-500">Resi</dt><dd>{{ o.courier }} · <span class="font-mono">{{ o.tracking_number }}</span></dd></div>
            <div v-if="o.expires_at && o.status === 'awaiting_payment'"><dt class="text-ink-500">Batas bayar</dt><dd>{{ dateTime(o.expires_at) }}</dd></div>
          </template>
          <div v-if="o.notes"><dt class="text-ink-500">Catatan</dt><dd>{{ o.notes }}</dd></div>
          <div v-if="o.cancel_reason"><dt class="text-ink-500">Alasan batal</dt><dd>{{ o.cancel_reason }}</dd></div>
        </dl>

        <div class="card">
          <div class="card-header"><h2 class="text-sm font-semibold">Riwayat status</h2></div>
          <ol class="card-body space-y-3 text-sm">
            <li v-for="h in o.histories" :key="h.id">
              <span class="font-medium">{{ statuses[h.to_status] ?? h.to_status }}</span>
              <span class="block text-xs text-ink-500">{{ dateTime(h.created_at) }}<span v-if="h.note"> · {{ h.note }}</span></span>
            </li>
          </ol>
        </div>
      </div>

      <div class="space-y-6 lg:col-span-2">
        <div class="card">
          <table class="data-table">
            <thead><tr><th>Barang</th><th class="text-right">Harga</th><th class="text-right">Jumlah</th><th class="text-right">Diskon</th><th class="text-right">Subtotal</th></tr></thead>
            <tbody>
              <tr v-for="i in o.items" :key="i.id">
                <td>{{ i.name }}<div class="font-mono text-xs text-ink-500">{{ i.sku }}</div></td>
                <td class="num text-right">{{ rupiah(i.unit_price) }}</td><td class="num text-right">{{ num(i.quantity) }}</td>
                <td class="num text-right text-ink-500">{{ Number(i.discount) ? rupiah(i.discount) : '-' }}</td><td class="num text-right">{{ rupiah(i.line_total) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr><td colspan="4" class="text-right text-sm text-ink-500">Subtotal</td><td class="num text-right">{{ rupiah(o.subtotal) }}</td></tr>
              <tr v-if="Number(o.discount_total)"><td colspan="4" class="text-right text-sm text-ink-500">Diskon {{ o.coupon_code ? `(${o.coupon_code})` : '' }}</td><td class="num text-right">−{{ rupiah(o.discount_total) }}</td></tr>
              <tr v-if="Number(o.shipping_cost)"><td colspan="4" class="text-right text-sm text-ink-500">Ongkir</td><td class="num text-right">{{ rupiah(o.shipping_cost) }}</td></tr>
              <tr v-if="Number(o.tax_total)"><td colspan="4" class="text-right text-sm text-ink-500">PPN</td><td class="num text-right">{{ rupiah(o.tax_total) }}</td></tr>
              <tr><td colspan="4" class="text-right font-semibold">Total</td><td class="num text-right font-semibold">{{ rupiah(o.grand_total) }}</td></tr>
            </tfoot>
          </table>
        </div>

        <div class="card">
          <div class="card-header"><h2 class="text-sm font-semibold">Pembayaran</h2></div>
          <table class="data-table">
            <thead><tr><th>Metode</th><th>Status</th><th>Referensi</th><th class="text-right">Jumlah</th></tr></thead>
            <tbody>
              <tr v-for="p in o.payments" :key="p.id">
                <td>{{ p.method_name }}</td><td class="text-sm">{{ p.status }}</td><td class="font-mono text-xs">{{ p.reference ?? '-' }}</td>
                <td class="num text-right">{{ rupiah(p.amount) }}<span v-if="p.tendered && Number(p.tendered) !== Number(p.amount)" class="block text-xs text-ink-500">diterima {{ rupiah(p.tendered) }}</span></td>
              </tr>
              <tr v-if="!o.payments.length"><td colspan="4" class="text-ink-500">Tanpa pembayaran (total Rp 0).</td></tr>
            </tbody>
          </table>
        </div>

        <div v-if="o.returns.length" class="card">
          <div class="card-header"><h2 class="text-sm font-semibold">Retur</h2></div>
          <ul class="divide-y divide-ink-100 dark:divide-ink-800">
            <li v-for="r in o.returns" :key="r.id" class="px-5 py-3 text-sm">
              <span class="font-mono font-medium">{{ r.number }}</span> · {{ rupiah(r.refund_amount) }} · {{ r.reason }}
              <span class="block text-xs text-ink-500">{{ dateTime(r.created_at) }} oleh {{ r.user?.name }} · {{ r.restocked ? 'barang kembali ke stok' : 'barang tidak dikembalikan ke stok' }}</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
