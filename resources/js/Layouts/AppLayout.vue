<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import SideNav from '@/Components/SideNav.vue'

const page = usePage()
const sidebarOpen = ref(false)
const isDark = ref(false)
const dismissedError = ref(false)

// Setiap pesan error baru harus muncul lagi walau pesan sebelumnya sudah ditutup.
watch(() => page.props.flash?.error, () => { dismissedError.value = false })

const user = computed(() => page.props.auth?.user ?? null)
const perms = computed(() => user.value?.permissions ?? [])
const has = (p) => perms.value.includes(p)

onMounted(() => {
  try { isDark.value = localStorage.getItem('cemwp-theme') === 'dark' } catch (e) { /* abaikan */ }
  document.documentElement.classList.toggle('dark', isDark.value)
})
function toggleTheme() {
  isDark.value = !isDark.value
  try { localStorage.setItem('cemwp-theme', isDark.value ? 'dark' : 'light') } catch (e) { /* abaikan */ }
  document.documentElement.classList.toggle('dark', isDark.value)
}

const I = {
  home: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
  cube: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
  tag: 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
  stock: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
  swap: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4',
  clipboard: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
  inbox: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4',
  truck: 'M8 17a2 2 0 11-4 0 2 2 0 014 0zm12 0a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10m10 0H8m5 0h2m4 0h1a1 1 0 001-1v-4l-3-4h-4v9',
  check: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
  plane: 'M12 19l9 2-9-18-9 18 9-2zm0 0v-8',
  building: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5m14 0h2M5 21H3m4-14h6m-6 4h6m-6 4h6',
  people: 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-2.13a4 4 0 100-8 4 4 0 000 8zm6 2a4 4 0 10-8 0',
  user: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
  log: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
  cart: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
  clock: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
  receipt: 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z',
  ticket: 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z',
  chart: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
  cog: 'M12 6V4m0 16v-2m6-6h2M4 12h2m10.243-4.243l1.414-1.414M6.343 17.657l1.414-1.414m0-8.486L6.343 6.343m11.314 11.314l-1.414-1.414M15 12a3 3 0 11-6 0 3 3 0 016 0z',
  store: 'M3 9l1-5h16l1 5M3 9v11h18V9M3 9h18M9 20v-6h6v6',
}

const groups = computed(() => {
  const g = [
    { title: '', items: [{ name: 'Dashboard', href: route('dashboard'), icon: I.home }] },
    {
      title: 'Penjualan',
      items: [
        has('pos.operate') && { name: 'Kasir (POS)', href: route('sales.pos.index'), icon: I.cart },
        (has('pos.operate') || has('orders.view')) && { name: 'Shift kasir', href: route('sales.shifts.index'), icon: I.clock },
        has('orders.view') && { name: 'Pesanan', href: route('sales.orders.index'), icon: I.receipt },
        has('coupons.manage') && { name: 'Kupon', href: route('sales.coupons.index'), icon: I.ticket },
        has('reports.view') && { name: 'Laporan', href: route('sales.reports.index'), icon: I.chart },
        has('salesconfig.manage') && { name: 'Pengaturan penjualan', href: route('sales.settings.index'), icon: I.cog },
        (has('pos.operate') || has('orders.view')) && { name: 'Lihat toko online', href: route('shop.home'), icon: I.store },
      ],
    },
    {
      title: 'Gudang',
      items: [
        has('inventory.view') && { name: 'Stok', href: route('warehouse.inventory.index'), icon: I.stock },
        has('inventory.view') && { name: 'Riwayat mutasi', href: route('warehouse.inventory.movements'), icon: I.swap },
        has('fulfillment.view') && { name: 'Pengiriman pesanan', href: route('warehouse.fulfillments.index'), icon: I.plane },
        has('transfers.view') && { name: 'Transfer lokasi', href: route('warehouse.transfers.index'), icon: I.truck },
        has('opname.view') && { name: 'Stok opname', href: route('warehouse.opnames.index'), icon: I.check },
      ],
    },
    {
      title: 'Pembelian',
      items: [
        has('purchasing.view') && { name: 'Purchase order', href: route('warehouse.purchase-orders.index'), icon: I.clipboard },
        has('purchasing.view') && { name: 'Penerimaan barang', href: route('warehouse.receipts.index'), icon: I.inbox },
        has('suppliers.view') && { name: 'Supplier', href: route('warehouse.suppliers.index'), icon: I.people },
      ],
    },
    {
      title: 'Data master',
      items: [
        has('products.view') && { name: 'Produk', href: route('warehouse.products.index'), icon: I.cube },
        has('products.view') && { name: 'Kategori', href: route('warehouse.categories.index'), icon: I.tag },
        has('warehouses.view') && { name: 'Lokasi stok', href: route('warehouse.locations.index'), icon: I.building },
      ],
    },
    {
      title: 'Sistem',
      items: [
        (has('users.view') || has('users.manage')) && { name: 'Pengguna', href: route('users.index'), icon: I.user },
        has('settings.manage') && { name: 'Log aktivitas', href: route('activity-log.index'), icon: I.log },
      ],
    },
  ]
  return g.map((x) => ({ ...x, items: x.items.filter(Boolean) })).filter((x) => x.items.length)
})
</script>

<template>
  <div class="min-h-screen bg-paper dark:bg-ink-950">
    <!-- Sidebar desktop -->
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col bg-ink-900 lg:flex print:hidden">
      <div class="flex h-16 shrink-0 items-center gap-2.5 border-b border-ink-800/70 px-6">
        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-seal-500 font-serif text-lg font-bold text-ink-950">C</div>
        <div class="text-white">
          <p class="font-serif text-sm font-semibold leading-tight">CEMWP</p>
          <p class="text-[11px] leading-tight tracking-wide text-ink-400">Gudang &amp; stok</p>
        </div>
      </div>
      <div class="mt-3 flex-1 overflow-y-auto px-3 pb-4">
        <SideNav :groups="groups" />
      </div>
      <div class="shrink-0 border-t border-ink-800/70 p-4">
        <div class="flex items-center gap-3">
          <Link :href="route('profile.edit')" class="-m-1 flex min-w-0 flex-1 items-center gap-3 rounded-md p-1 hover:bg-ink-800/70" title="Buka profil saya">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-ink-700 text-sm font-semibold text-white">{{ (user?.name ?? '?').charAt(0).toUpperCase() }}</span>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-white">{{ user?.name }}</p>
              <p class="truncate text-xs text-ink-400">{{ (user?.roles ?? [])[0] }}</p>
            </div>
          </Link>
          <Link :href="route('logout')" method="post" as="button" class="shrink-0 text-ink-400 hover:text-white" title="Keluar">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
          </Link>
        </div>
      </div>
    </aside>

    <!-- Sidebar mobile -->
    <div v-if="sidebarOpen" class="fixed inset-0 z-40 lg:hidden">
      <div class="absolute inset-0 bg-black/50" @click="sidebarOpen = false"></div>
      <aside class="absolute inset-y-0 left-0 flex w-64 flex-col overflow-y-auto bg-ink-900 p-4">
        <div class="flex-1"><SideNav :groups="groups" @navigate="sidebarOpen = false" /></div>
        <div class="shrink-0 border-t border-ink-800/70 pt-4">
          <Link :href="route('logout')" method="post" as="button" class="flex items-center gap-2 rounded-md px-3 py-2.5 text-sm font-medium text-ink-300 hover:bg-ink-800 hover:text-white">Keluar</Link>
        </div>
      </aside>
    </div>

    <!-- Konten -->
    <div class="lg:pl-64 print:pl-0">
      <header class="sticky top-0 z-20 flex h-16 print:hidden items-center justify-between border-b border-ink-200 bg-paper/90 px-4 backdrop-blur lg:px-8 dark:border-ink-800 dark:bg-ink-950/90">
        <div class="flex min-w-0 items-center gap-3">
          <button class="text-ink-500 lg:hidden" aria-label="Buka menu" @click="sidebarOpen = true">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
          </button>
          <h1 class="truncate font-serif text-lg font-semibold text-ink-900 dark:text-ink-100"><slot name="title">Dashboard</slot></h1>
        </div>
        <div class="flex shrink-0 items-center gap-2">
          <button class="rounded-md p-2 text-ink-500 hover:bg-ink-100 dark:text-ink-400 dark:hover:bg-ink-800" title="Ganti tema" @click="toggleTheme">
            <svg v-if="!isDark" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
            </svg>
            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
          </button>
          <slot name="actions" />
        </div>
      </header>

      <div v-if="$page.props.flash?.success" role="status" class="mx-4 mt-4 print:hidden rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 lg:mx-8 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error && !dismissedError" role="alert" class="mx-4 mt-4 print:hidden flex items-start justify-between gap-3 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 lg:mx-8 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
        <span>{{ $page.props.flash.error }}</span>
        <button type="button" class="shrink-0 font-bold opacity-60 hover:opacity-100" title="Tutup" @click="dismissedError = true">&times;</button>
      </div>

      <main class="p-4 lg:p-8 print:p-0"><slot /></main>
    </div>
  </div>
</template>
