<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { num, rupiah, dateTime } from '@/Composables/format'

defineProps({ shift: Object, summary: Object })
</script>

<template>
  <AppLayout>
    <Head title="Rekap shift" />
    <template #title>Rekap shift · {{ shift.user?.name }}</template>
    <template #actions><Link :href="route('sales.shifts.index')" class="btn-secondary btn-sm">Kembali</Link></template>

    <div class="grid max-w-4xl gap-6 lg:grid-cols-2">
      <dl class="card card-body space-y-3 text-sm">
        <div><dt class="text-ink-500">Outlet</dt><dd>{{ shift.warehouse?.name }}</dd></div>
        <div><dt class="text-ink-500">Dibuka</dt><dd>{{ dateTime(shift.opened_at) }}</dd></div>
        <div><dt class="text-ink-500">Ditutup</dt><dd>{{ shift.closed_at ? dateTime(shift.closed_at) : 'Masih berjalan' }}</dd></div>
        <div><dt class="text-ink-500">Jumlah transaksi</dt><dd class="num">{{ num(summary.orders_count) }}</dd></div>
        <div><dt class="text-ink-500">Total penjualan</dt><dd class="num text-lg font-semibold">{{ rupiah(summary.sales_total) }}</dd></div>
        <div v-if="summary.discount_total > 0"><dt class="text-ink-500">Total diskon</dt><dd class="num">{{ rupiah(summary.discount_total) }}</dd></div>
        <div v-if="summary.returns_total > 0"><dt class="text-ink-500">Total retur</dt><dd class="num text-red-600">{{ rupiah(summary.returns_total) }}</dd></div>
      </dl>

      <div class="space-y-6">
        <div class="card">
          <div class="card-header"><h2 class="text-sm font-semibold">Per metode pembayaran</h2></div>
          <table class="data-table">
            <tbody>
              <tr v-for="m in summary.by_method" :key="m.code"><td>{{ m.name }} <span class="text-xs text-ink-500">({{ m.count }}x)</span></td><td class="num text-right">{{ rupiah(m.total) }}</td></tr>
              <tr v-if="!summary.by_method.length"><td class="text-ink-500">Belum ada pembayaran.</td></tr>
            </tbody>
          </table>
        </div>

        <div class="card">
          <div class="card-header"><h2 class="text-sm font-semibold">Rekonsiliasi kas laci</h2></div>
          <dl class="card-body space-y-2 text-sm">
            <div class="flex justify-between"><dt>Kas awal</dt><dd class="num">{{ rupiah(summary.opening_cash) }}</dd></div>
            <div class="flex justify-between"><dt>+ Penjualan tunai</dt><dd class="num">{{ rupiah(summary.cash_sales) }}</dd></div>
            <div class="flex justify-between"><dt>− Refund tunai</dt><dd class="num">{{ rupiah(summary.cash_refunds) }}</dd></div>
            <div class="flex justify-between border-t border-ink-100 pt-2 font-semibold dark:border-ink-800"><dt>Seharusnya ada</dt><dd class="num">{{ rupiah(shift.expected_cash ?? summary.expected_cash) }}</dd></div>
            <template v-if="shift.status === 'closed'">
              <div class="flex justify-between"><dt>Dihitung kasir</dt><dd class="num">{{ rupiah(shift.counted_cash) }}</dd></div>
              <div class="flex justify-between font-semibold" :class="Number(shift.difference) === 0 ? 'text-emerald-600' : 'text-red-600'"><dt>Selisih</dt><dd class="num">{{ Number(shift.difference) > 0 ? '+' : '' }}{{ num(shift.difference) }}</dd></div>
              <p v-if="shift.notes" class="text-ink-500">Catatan: {{ shift.notes }}</p>
            </template>
          </dl>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
