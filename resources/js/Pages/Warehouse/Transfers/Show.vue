<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Head, router } from '@inertiajs/vue3'
import { useCan } from '@/Composables/can'
import { num, dateTime } from '@/Composables/format'

const props = defineProps({ transfer: Object, available: Object, statuses: Object })
const { can } = useCan()

const shortages = props.transfer.items.filter((i) => props.transfer.status === 'draft' && (props.available[i.product_id] ?? 0) < i.quantity)

function act(name, message) {
  if (confirm(message)) router.post(route(`warehouse.transfers.${name}`, props.transfer.id), {}, { preserveScroll: true })
}
</script>

<template>
  <AppLayout>
    <Head :title="transfer.number" />
    <template #title>{{ transfer.number }}</template>
    <template #actions>
      <template v-if="transfer.status === 'draft' && can('transfers.manage')">
        <button class="btn-accent btn-sm" @click="act('ship', 'Kirim transfer sekarang? Stok langsung dikurangi dari lokasi asal.')">Kirim</button>
        <button class="btn-ghost btn-sm text-red-600" @click="act('cancel', 'Batalkan draf transfer ini?')">Batalkan</button>
      </template>
      <template v-if="transfer.status === 'in_transit'">
        <button v-if="can('transfers.receive')" class="btn-accent btn-sm" @click="act('receive', 'Konfirmasi semua barang sudah tiba? Stok akan ditambahkan ke lokasi tujuan.')">Terima di tujuan</button>
        <button v-if="can('transfers.manage')" class="btn-ghost btn-sm text-red-600" @click="act('cancel', 'Batalkan transfer? Stok dikembalikan ke lokasi asal.')">Batalkan</button>
      </template>
    </template>

    <div v-if="shortages.length" class="mb-6 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
      Stok tersedia di lokasi asal tidak cukup untuk: {{ shortages.map((s) => s.product?.name).join(', ') }}. Transfer tidak bisa dikirim sampai stok mencukupi.
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <div class="card self-start">
        <div class="card-header"><h2 class="text-sm font-semibold">Ringkasan</h2><StatusBadge :status="transfer.status" :labels="statuses" /></div>
        <dl class="card-body space-y-3 text-sm">
          <div><dt class="text-ink-500">Dari</dt><dd class="font-medium">{{ transfer.from?.name }}</dd></div>
          <div><dt class="text-ink-500">Ke</dt><dd class="font-medium">{{ transfer.to?.name }}</dd></div>
          <div><dt class="text-ink-500">Dikirim</dt><dd>{{ transfer.shipped_at ? dateTime(transfer.shipped_at) : '-' }}</dd></div>
          <div><dt class="text-ink-500">Diterima</dt><dd>{{ transfer.received_at ? dateTime(transfer.received_at) : '-' }}</dd></div>
          <div><dt class="text-ink-500">Dibuat oleh</dt><dd>{{ transfer.creator?.name ?? '-' }}</dd></div>
          <div v-if="transfer.notes"><dt class="text-ink-500">Catatan</dt><dd>{{ transfer.notes }}</dd></div>
        </dl>
      </div>

      <div class="card lg:col-span-2">
        <table class="data-table">
          <thead><tr><th>Produk</th><th class="text-right">Jumlah</th><th v-if="transfer.status === 'draft'" class="text-right">Tersedia di asal</th><th v-if="transfer.status === 'received'" class="text-right">Diterima</th></tr></thead>
          <tbody>
            <tr v-for="i in transfer.items" :key="i.id">
              <td>{{ i.product?.name }}<div class="font-mono text-xs text-ink-500">{{ i.product?.sku }}</div></td>
              <td class="num text-right">{{ num(i.quantity) }} {{ i.product?.unit }}</td>
              <td v-if="transfer.status === 'draft'" class="num text-right" :class="(available[i.product_id] ?? 0) < i.quantity ? 'font-semibold text-red-600' : ''">{{ num(available[i.product_id] ?? 0) }}</td>
              <td v-if="transfer.status === 'received'" class="num text-right">{{ num(i.qty_received) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
