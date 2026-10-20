<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { num, rupiah, dateTime } from '@/Composables/format'

const props = defineProps({ receipt: Object })
const total = props.receipt.items.reduce((s, i) => s + i.quantity * Number(i.unit_cost), 0)
</script>

<template>
  <AppLayout>
    <Head :title="receipt.number" />
    <template #title>{{ receipt.number }}</template>

    <div class="grid gap-6 lg:grid-cols-3">
      <dl class="card card-body space-y-3 self-start text-sm">
        <div><dt class="text-ink-500">Waktu</dt><dd>{{ dateTime(receipt.received_at) }}</dd></div>
        <div><dt class="text-ink-500">Lokasi</dt><dd>{{ receipt.warehouse?.name }}</dd></div>
        <div><dt class="text-ink-500">Supplier</dt><dd>{{ receipt.supplier?.name ?? '-' }}</dd></div>
        <div>
          <dt class="text-ink-500">PO</dt>
          <dd><Link v-if="receipt.purchase_order" :href="route('warehouse.purchase-orders.show', receipt.purchase_order.id)" class="font-mono hover:underline">{{ receipt.purchase_order.number }}</Link><span v-else>Tanpa PO</span></dd>
        </div>
        <div><dt class="text-ink-500">Diterima oleh</dt><dd>{{ receipt.receiver?.name ?? '-' }}</dd></div>
        <div v-if="receipt.notes"><dt class="text-ink-500">Catatan</dt><dd>{{ receipt.notes }}</dd></div>
      </dl>

      <div class="card lg:col-span-2">
        <table class="data-table">
          <thead><tr><th>Produk</th><th class="text-right">Jumlah</th><th class="text-right">Harga pokok</th><th class="text-right">Subtotal</th></tr></thead>
          <tbody>
            <tr v-for="i in receipt.items" :key="i.id">
              <td>{{ i.product?.name }}<div class="font-mono text-xs text-ink-500">{{ i.product?.sku }}</div></td>
              <td class="num text-right">{{ num(i.quantity) }} {{ i.product?.unit }}</td>
              <td class="num text-right">{{ rupiah(i.unit_cost) }}</td>
              <td class="num text-right">{{ rupiah(i.quantity * Number(i.unit_cost)) }}</td>
            </tr>
          </tbody>
          <tfoot><tr><td colspan="3" class="text-right text-sm font-semibold">Total</td><td class="num text-right font-semibold">{{ rupiah(total) }}</td></tr></tfoot>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
