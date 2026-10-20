<script setup>
import ShopLayout from '@/Layouts/ShopLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { useFilters } from '@/Composables/useFilters'
import { rupiah } from '@/Composables/format'

const props = defineProps({ products: Object, filters: Object, categories: Array })
const shopName = usePage().props.shop?.name
const f = useFilters('shop.home', { q: props.filters.q ?? '', category_id: props.filters.category_id ?? '' })

function add(p) { router.post(route('shop.cart.add'), { product_id: p.id, quantity: 1 }, { preserveScroll: true }) }
const soldOut = (p) => p.stock !== null && p.stock <= 0
const initials = (n) => n.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase()
</script>

<template>
  <ShopLayout>
    <Head :title="shopName" />

    <section class="mb-8 rounded-2xl bg-[#2A1F17] px-6 py-10 text-[#FBF7EF] sm:px-10">
      <p class="text-sm uppercase tracking-[0.2em] text-[#E2B659]">Pilihan kami</p>
      <h1 class="mt-2 max-w-xl font-serif text-3xl font-semibold leading-tight sm:text-4xl">Kopi dan perlengkapan seduh, diantar ke rumahmu.</h1>
      <p class="mt-3 max-w-lg text-sm text-[#D9CBB5]">Stok yang tampil adalah stok nyata. Bayar online, transfer, atau di tempat (COD).</p>
    </section>

    <div class="mb-6 flex flex-col gap-3 sm:flex-row">
      <input v-model="f.q" class="input sm:max-w-sm" placeholder="Cari kopi, alat seduh…" />
      <div class="flex flex-wrap gap-2">
        <button class="rounded-full border px-3 py-1.5 text-sm" :class="f.category_id === '' ? 'border-[#2A1F17] bg-[#2A1F17] text-[#FBF7EF]' : 'border-[#D9CBB5] hover:bg-[#F1E8D6]'" @click="f.category_id = ''">Semua</button>
        <button v-for="c in categories" :key="c.id" class="rounded-full border px-3 py-1.5 text-sm" :class="String(f.category_id) === String(c.id) ? 'border-[#2A1F17] bg-[#2A1F17] text-[#FBF7EF]' : 'border-[#D9CBB5] hover:bg-[#F1E8D6]'" @click="f.category_id = c.id">{{ c.name }}</button>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
      <article v-for="p in products.data" :key="p.id" class="flex flex-col overflow-hidden rounded-xl border border-[#E7DCC8] bg-white">
        <Link :href="route('shop.product', p.id)" class="flex aspect-[4/3] items-center justify-center bg-gradient-to-br from-[#E9D9B8] to-[#C9A26B] font-serif text-4xl font-semibold text-[#2A1F17]/70">{{ initials(p.name) }}</Link>
        <div class="flex flex-1 flex-col p-3">
          <p v-if="p.category" class="text-[11px] uppercase tracking-wider text-[#8A7A66]">{{ p.category }}</p>
          <Link :href="route('shop.product', p.id)" class="mt-0.5 line-clamp-2 text-sm font-medium hover:underline">{{ p.name }}</Link>
          <p class="num mt-2 text-base font-semibold">{{ rupiah(p.price) }}</p>
          <p class="mt-0.5 text-xs" :class="soldOut(p) ? 'text-red-600' : p.stock !== null && p.stock <= 5 ? 'text-amber-700' : 'text-[#7A6A58]'">
            {{ soldOut(p) ? 'Stok habis' : p.stock !== null && p.stock <= 5 ? `Sisa ${p.stock}` : 'Tersedia' }}
          </p>
          <button class="btn-primary mt-3 w-full" :disabled="soldOut(p)" @click="add(p)">Tambah</button>
        </div>
      </article>
    </div>
    <p v-if="!products.data.length" class="py-16 text-center text-[#7A6A58]">Produk tidak ditemukan.</p>
    <Pagination :links="products.links" />
  </ShopLayout>
</template>
