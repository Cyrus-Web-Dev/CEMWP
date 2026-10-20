<script setup>
import { computed } from 'vue'
import ShopLayout from '@/Layouts/ShopLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { num, rupiah } from '@/Composables/format'

const props = defineProps({ lines: Array, subtotal: Number })

function setQty(l, v) {
  const q = Math.max(0, Math.floor(Number(v) || 0))
  router.patch(route('shop.cart.update', l.product_id), { quantity: q }, { preserveScroll: true })
}
const remove = (l) => router.delete(route('shop.cart.remove', l.product_id), { preserveScroll: true })
const short = (l) => l.available !== null && l.available < l.quantity
const hasShortage = computed(() => props.lines.some(short))
</script>

<template>
  <ShopLayout>
    <Head title="Keranjang" />
    <h1 class="mb-6 font-serif text-2xl font-semibold">Keranjang belanja</h1>

    <div v-if="!lines.length" class="rounded-xl border border-dashed border-[#D9CBB5] py-16 text-center">
      <p class="text-[#7A6A58]">Keranjangmu masih kosong.</p>
      <Link :href="route('shop.home')" class="btn-primary mt-4 inline-flex">Mulai belanja</Link>
    </div>

    <div v-else class="grid gap-6 lg:grid-cols-3">
      <ul class="divide-y divide-[#E7DCC8] rounded-xl border border-[#E7DCC8] bg-white lg:col-span-2">
        <li v-for="l in lines" :key="l.product_id" class="flex flex-wrap items-center gap-4 p-4">
          <div class="min-w-0 flex-1">
            <Link :href="route('shop.product', l.product_id)" class="font-medium hover:underline">{{ l.name }}</Link>
            <p class="num text-sm text-[#7A6A58]">{{ rupiah(l.unit_price) }}</p>
            <p v-if="short(l)" class="text-xs text-red-600">Stok tersisa {{ l.available }}. Kurangi jumlahnya.</p>
          </div>
          <div class="flex items-center gap-1">
            <button class="btn-secondary btn-sm px-2" @click="setQty(l, l.quantity - 1)">−</button>
            <input :value="l.quantity" type="number" min="0" class="input w-16 px-1 text-center" @change="setQty(l, $event.target.value)" />
            <button class="btn-secondary btn-sm px-2" @click="setQty(l, l.quantity + 1)">+</button>
          </div>
          <p class="num w-28 text-right font-semibold">{{ rupiah(l.line_total) }}</p>
          <button class="text-[#A89880] hover:text-red-600" title="Hapus" @click="remove(l)">&times;</button>
        </li>
      </ul>

      <aside class="self-start rounded-xl border border-[#E7DCC8] bg-white p-5">
        <h2 class="font-serif text-lg font-semibold">Ringkasan</h2>
        <p class="mt-4 flex justify-between text-sm"><span class="text-[#7A6A58]">Subtotal ({{ num(lines.reduce((s, l) => s + l.quantity, 0)) }} barang)</span><span class="num font-semibold">{{ rupiah(subtotal) }}</span></p>
        <p class="mt-1 text-xs text-[#7A6A58]">Ongkir dan kupon dihitung di langkah berikutnya.</p>
        <Link :href="route('shop.checkout')" class="btn-primary mt-5 w-full py-3" :class="hasShortage ? 'pointer-events-none opacity-50' : ''">Lanjut ke checkout</Link>
      </aside>
    </div>
  </ShopLayout>
</template>
