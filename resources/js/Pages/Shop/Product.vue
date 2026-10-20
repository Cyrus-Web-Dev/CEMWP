<script setup>
import { ref } from 'vue'
import ShopLayout from '@/Layouts/ShopLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { num, rupiah } from '@/Composables/format'

const props = defineProps({ product: Object })
const qty = ref(1)
const p = props.product
const soldOut = p.stock !== null && p.stock <= 0

function add() {
  const q = Math.max(1, Math.floor(Number(qty.value) || 1))
  router.post(route('shop.cart.add'), { product_id: p.id, quantity: q }, { preserveScroll: true })
}
</script>

<template>
  <ShopLayout>
    <Head :title="p.name" />
    <Link :href="route('shop.home')" class="mb-4 inline-block text-sm text-[#7A6A58] hover:underline">← Kembali ke katalog</Link>

    <div class="grid gap-8 md:grid-cols-2">
      <div class="flex aspect-square items-center justify-center rounded-2xl bg-gradient-to-br from-[#E9D9B8] to-[#C9A26B] font-serif text-7xl font-semibold text-[#2A1F17]/70">
        {{ p.name.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase() }}
      </div>
      <div>
        <p v-if="p.category" class="text-xs uppercase tracking-wider text-[#8A7A66]">{{ p.category }}</p>
        <h1 class="mt-1 font-serif text-3xl font-semibold">{{ p.name }}</h1>
        <p class="num mt-3 text-2xl font-semibold">{{ rupiah(p.price) }} <span class="text-sm font-normal text-[#7A6A58]">/ {{ p.unit }}</span></p>
        <p class="mt-2 text-sm" :class="soldOut ? 'text-red-600' : 'text-emerald-700'">{{ soldOut ? 'Stok habis' : p.stock !== null && p.stock <= 5 ? `Tersisa ${p.stock}` : 'Tersedia' }}</p>
        <p v-if="p.description" class="mt-5 whitespace-pre-line text-sm leading-relaxed text-[#4A3B2E]">{{ p.description }}</p>
        <p class="mt-4 text-xs text-[#7A6A58]">Berat {{ num(p.weight_grams) }} g</p>

        <div class="mt-6 flex items-center gap-3">
          <input v-model="qty" type="number" min="1" :max="p.stock ?? 999" class="input w-24 text-center" :disabled="soldOut" />
          <button class="btn-primary px-6 py-2.5" :disabled="soldOut" @click="add">Masukkan keranjang</button>
        </div>
      </div>
    </div>
  </ShopLayout>
</template>
