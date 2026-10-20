<script setup>
import { onMounted, onBeforeUnmount, computed } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'

const page = usePage()
const shop = computed(() => page.props.shop ?? { name: 'Toko', customer: null, cartCount: 0 })

// Etalase selalu bertema terang (pilihan tema gelap hanya untuk back-office).
onMounted(() => document.documentElement.classList.remove('dark'))
onBeforeUnmount(() => {
  try { document.documentElement.classList.toggle('dark', localStorage.getItem('cemwp-theme') === 'dark') } catch (e) { /* abaikan */ }
})

function logout() { router.post(route('shop.logout')) }
</script>

<template>
  <div class="flex min-h-screen flex-col bg-[#FBF7EF] text-[#2A1F17]">
    <header class="sticky top-0 z-20 border-b border-[#E7DCC8] bg-[#FBF7EF]/95 backdrop-blur">
      <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4">
        <Link :href="route('shop.home')" class="flex items-center gap-2.5">
          <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#2A1F17] font-serif text-base font-bold text-[#E2B659]">{{ (shop.name ?? 'T').charAt(0) }}</span>
          <span class="font-serif text-lg font-semibold tracking-tight">{{ shop.name }}</span>
        </Link>
        <nav class="flex items-center gap-1 text-sm font-medium">
          <Link :href="route('shop.home')" class="rounded-md px-3 py-2 hover:bg-[#F1E8D6]">Katalog</Link>
          <Link :href="route('shop.track')" class="hidden rounded-md px-3 py-2 hover:bg-[#F1E8D6] sm:block">Lacak pesanan</Link>
          <Link v-if="shop.customer" :href="route('shop.account')" class="rounded-md px-3 py-2 hover:bg-[#F1E8D6]">{{ shop.customer.name.split(' ')[0] }}</Link>
          <Link v-else :href="route('shop.auth')" class="rounded-md px-3 py-2 hover:bg-[#F1E8D6]">Masuk</Link>
          <Link :href="route('shop.cart')" class="relative ml-1 flex items-center gap-2 rounded-full bg-[#2A1F17] px-4 py-2 text-[#FBF7EF] hover:bg-[#3C2D22]">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3c-.6.6-.2 1.7.7 1.7H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
            Keranjang
            <span v-if="shop.cartCount" class="rounded-full bg-[#E2B659] px-2 text-xs font-bold text-[#2A1F17]">{{ shop.cartCount }}</span>
          </Link>
        </nav>
      </div>
    </header>

    <div v-if="$page.props.flash?.success" role="status" class="mx-auto mt-4 w-full max-w-6xl px-4">
      <p class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ $page.props.flash.success }}</p>
    </div>
    <div v-if="$page.props.flash?.error" role="alert" class="mx-auto mt-4 w-full max-w-6xl px-4">
      <p class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $page.props.flash.error }}</p>
    </div>
    <div v-if="$page.props.flash?.note" class="mx-auto mt-4 w-full max-w-6xl px-4">
      <p class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $page.props.flash.note }}</p>
    </div>

    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8"><slot /></main>

    <footer class="border-t border-[#E7DCC8] py-6 text-center text-xs text-[#7A6A58]">
      <p>© {{ new Date().getFullYear() }} {{ shop.name }}</p>
      <p v-if="shop.customer" class="mt-1"><button class="underline" @click="logout">Keluar dari akun</button></p>
    </footer>
  </div>
</template>
