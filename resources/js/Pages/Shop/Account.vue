<script setup>
import ShopLayout from '@/Layouts/ShopLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { rupiah, dateTime } from '@/Composables/format'

defineProps({ orders: Object, profile: Object })
const logout = () => router.post(route('shop.logout'))
</script>

<template>
  <ShopLayout>
    <Head title="Akunku" />
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
      <div><h1 class="font-serif text-2xl font-semibold">Halo, {{ profile.name }}</h1><p class="text-sm text-[#7A6A58]">{{ profile.email }}<span v-if="profile.phone"> · +{{ profile.phone }}</span></p></div>
      <button class="btn-secondary btn-sm" @click="logout">Keluar</button>
    </div>

    <h2 class="mb-3 font-serif text-lg font-semibold">Pesananku</h2>
    <div class="overflow-x-auto rounded-xl border border-[#E7DCC8] bg-white">
      <table class="data-table">
        <thead><tr><th>Nomor</th><th>Tanggal</th><th class="text-right">Total</th><th>Status</th></tr></thead>
        <tbody>
          <tr v-for="o in orders.data" :key="o.number">
            <td><Link :href="route('shop.orders.show', o.number)" class="font-mono font-medium hover:underline">{{ o.number }}</Link></td>
            <td class="text-sm text-[#7A6A58]">{{ dateTime(o.placed_at) }}</td>
            <td class="num text-right">{{ rupiah(o.grand_total) }}</td>
            <td class="text-sm">{{ o.status_label }}<span class="block text-xs text-[#7A6A58]">{{ o.payment_status_label }}</span></td>
          </tr>
          <tr v-if="!orders.data.length"><td colspan="4" class="py-10 text-center text-[#7A6A58]">Belum ada pesanan. <Link :href="route('shop.home')" class="underline">Mulai belanja</Link></td></tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="orders.links" />
  </ShopLayout>
</template>
