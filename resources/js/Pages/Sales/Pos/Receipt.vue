<script setup>
import { onMounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { num, rupiah, dateTime } from '@/Composables/format'

const props = defineProps({ order: Object, store: Object })
const o = props.order
const printNow = () => window.print()

onMounted(() => {
  // ?print=1 -> langsung buka dialog cetak
  if (new URLSearchParams(window.location.search).get('print') === '1') setTimeout(() => window.print(), 300)
})
</script>

<template>
  <AppLayout>
    <Head :title="`Struk ${o.number}`" />
    <template #title>Struk {{ o.number }}</template>
    <template #actions>
      <button class="btn-primary btn-sm" @click="printNow">Cetak struk</button>
      <Link :href="route('sales.pos.index')" class="btn-secondary btn-sm">Transaksi baru</Link>
    </template>

    <div class="mx-auto w-full max-w-[22rem] rounded-md border border-ink-200 bg-white p-5 font-mono text-[13px] leading-snug text-black shadow-sm print:max-w-none print:border-0 print:p-0 print:shadow-none dark:border-ink-700">
      <div class="text-center">
        <p class="text-base font-bold">{{ store.name }}</p>
        <p v-if="store.address">{{ store.address }}</p>
        <p v-if="store.phone">Telp {{ store.phone }}</p>
      </div>
      <hr class="my-2 border-dashed border-black" />
      <p>No   : {{ o.number }}</p>
      <p>Waktu: {{ dateTime(o.placed_at) }}</p>
      <p v-if="o.cashier">Kasir: {{ o.cashier.name }}</p>
      <p v-if="o.customer_name">Plgn : {{ o.customer_name }}</p>
      <hr class="my-2 border-dashed border-black" />

      <div v-for="i in o.items" :key="i.id" class="mb-1">
        <p>{{ i.name }}</p>
        <p class="flex justify-between"><span>{{ num(i.quantity) }} x {{ num(i.unit_price) }}</span><span>{{ num(i.unit_price * i.quantity) }}</span></p>
        <p v-if="Number(i.discount) > 0" class="flex justify-between"><span>  diskon</span><span>-{{ num(i.discount) }}</span></p>
      </div>

      <hr class="my-2 border-dashed border-black" />
      <p class="flex justify-between"><span>Subtotal</span><span>{{ num(o.subtotal) }}</span></p>
      <p v-if="Number(o.discount_total) > 0" class="flex justify-between"><span>Diskon{{ o.coupon_code ? ` (${o.coupon_code})` : '' }}</span><span>-{{ num(o.discount_total) }}</span></p>
      <p v-if="Number(o.tax_total) > 0" class="flex justify-between"><span>PPN</span><span>{{ num(o.tax_total) }}</span></p>
      <p class="flex justify-between text-base font-bold"><span>TOTAL</span><span>{{ num(o.grand_total) }}</span></p>
      <hr class="my-2 border-dashed border-black" />
      <p v-for="p in o.payments" :key="p.id" class="flex justify-between">
        <span>{{ p.method_name }}{{ p.reference ? ` (${p.reference})` : '' }}</span><span>{{ num(p.tendered ?? p.amount) }}</span>
      </p>
      <p v-if="Number(o.change_due) > 0" class="flex justify-between font-bold"><span>Kembali</span><span>{{ num(o.change_due) }}</span></p>
      <p v-if="o.returns?.length" class="mt-2 text-center">*** ada retur pada transaksi ini ***</p>
      <hr class="my-2 border-dashed border-black" />
      <p class="text-center">{{ store.footer }}</p>
    </div>
  </AppLayout>
</template>
