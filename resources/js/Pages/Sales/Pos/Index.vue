<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { num, rupiah } from '@/Composables/format'

const props = defineProps({ shift: Object, paymentMethods: Array, storeName: String, userId: Number })

// ---------- Katalog ----------
const products = ref([])
const loading = ref(true)
const loadError = ref('')
const search = ref('')
const searchEl = ref(null)

async function loadCatalog() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await window.axios.get(route('sales.pos.catalog'))
    products.value = data.products
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat katalog. Periksa koneksi lalu muat ulang.'
  } finally {
    loading.value = false
  }
}

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  const list = q
    ? products.value.filter((p) => `${p.name} ${p.sku} ${p.barcode ?? ''} ${p.category ?? ''}`.toLowerCase().includes(q))
    : products.value
  return list.slice(0, 60)
})

// ---------- Keranjang ----------
const cart = ref([])
const toast = ref('')
let toastTimer = null
function say(msg) {
  toast.value = msg
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => { toast.value = '' }, 2500)
}

function addProduct(p) {
  const line = cart.value.find((l) => l.product_id === p.id)
  const wanted = (line?.quantity ?? 0) + 1
  if (p.track_stock && p.available !== null && wanted > p.available) {
    say(`Stok ${p.name} hanya ${p.available}.`)
    return
  }
  if (line) line.quantity = wanted
  else cart.value.push({ product_id: p.id, name: p.name, sku: p.sku, price: p.price, quantity: 1, available: p.available, track_stock: p.track_stock })
}
function setQty(line, v) {
  const q = Math.max(0, Math.floor(Number(v) || 0))
  if (q === 0) { cart.value = cart.value.filter((l) => l !== line); return }
  if (line.track_stock && line.available !== null && q > line.available) { say(`Stok ${line.name} hanya ${line.available}.`); line.quantity = line.available; return }
  line.quantity = q
}
const removeLine = (line) => { cart.value = cart.value.filter((l) => l !== line) }

function onSearchEnter() {
  const q = search.value.trim().toLowerCase()
  if (!q) return
  const exact = products.value.find((p) => (p.barcode && p.barcode.toLowerCase() === q) || p.sku.toLowerCase() === q)
  const target = exact ?? (filtered.value.length === 1 ? filtered.value[0] : null)
  if (target) { addProduct(target); search.value = '' } else say('Produk tidak ditemukan / lebih dari satu hasil.')
}

// ---------- Pelanggan & kupon ----------
const customerPhone = ref('')
const customerName = ref('')
const customerInfo = ref(null) // null = belum dicek, { found, name, orders_count }
const couponCode = ref('')

async function lookupCustomer() {
  if (!customerPhone.value.trim()) { customerInfo.value = null; return }
  try {
    const { data } = await window.axios.post(route('sales.pos.customers.lookup'), { phone: customerPhone.value })
    customerInfo.value = data.customer ? { found: true, ...data.customer } : { found: false }
    if (data.customer) customerName.value = data.customer.name
    requestQuote()
  } catch (e) {
    customerInfo.value = null
    say(e.response?.data?.errors?.phone?.[0] ?? 'Nomor HP tidak valid.')
  }
}

// ---------- Harga dari server ----------
const quote = ref(null)
const quoting = ref(false)
let quoteTimer = null
let quoteSeq = 0

const localSubtotal = computed(() => cart.value.reduce((s, l) => s + l.price * l.quantity, 0))
const total = computed(() => quote.value?.grand_total ?? localSubtotal.value)

function requestQuote() {
  clearTimeout(quoteTimer)
  if (!cart.value.length) { quote.value = null; return }
  quoteTimer = setTimeout(async () => {
    const mine = ++quoteSeq
    quoting.value = true
    try {
      const { data } = await window.axios.post(route('sales.pos.quote'), {
        items: cart.value.map((l) => ({ product_id: l.product_id, quantity: l.quantity })),
        coupon_code: couponCode.value || null, customer_phone: customerPhone.value || null,
      })
      if (mine === quoteSeq) quote.value = data
    } catch (e) {
      if (mine === quoteSeq) { quote.value = null; say(e.response?.data?.message ?? 'Gagal menghitung total.') }
    } finally {
      if (mine === quoteSeq) quoting.value = false
    }
  }, 350)
}
watch(cart, requestQuote, { deep: true })
watch(couponCode, requestQuote)

// ---------- Tahan / lanjutkan ----------
const HELD_KEY = `cemwp-pos-held-${props.userId}`
const held = ref([])
function loadHeld() { try { held.value = JSON.parse(localStorage.getItem(HELD_KEY) ?? '[]') } catch (e) { held.value = [] } }
function saveHeld() { try { localStorage.setItem(HELD_KEY, JSON.stringify(held.value)) } catch (e) { /* abaikan */ } }
function hold() {
  if (!cart.value.length) return
  held.value.push({ at: new Date().toISOString(), cart: cart.value, customerPhone: customerPhone.value, customerName: customerName.value, couponCode: couponCode.value })
  saveHeld()
  resetSale(false)
  say('Transaksi ditahan.')
}
function resume(i) {
  if (cart.value.length && !confirm('Keranjang saat ini akan diganti. Lanjutkan?')) return
  const h = held.value[i]
  cart.value = h.cart; customerPhone.value = h.customerPhone; customerName.value = h.customerName; couponCode.value = h.couponCode
  held.value.splice(i, 1)
  saveHeld()
  lookupCustomer()
}

// ---------- Pembayaran ----------
const payOpen = ref(false)
const payments = ref([])
const processing = ref(false)
const payError = ref('')
const idemKey = ref(newKey())

function newKey() {
  return (window.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(16).slice(2)}`)
}
const kindOf = (code) => props.paymentMethods.find((m) => m.code === code)?.kind
const methodOf = (code) => props.paymentMethods.find((m) => m.code === code)

function openPay() {
  if (!cart.value.length || quoting.value) return
  payError.value = ''
  const cash = props.paymentMethods.find((m) => m.kind === 'cash') ?? props.paymentMethods[0]
  payments.value = [{ method: cash?.code, amount: total.value, reference: '' }]
  payOpen.value = true
}
function addPaymentLine() {
  const used = payments.value.map((p) => p.method)
  const next = props.paymentMethods.find((m) => !used.includes(m.code)) ?? props.paymentMethods[0]
  const remaining = Math.max(0, total.value - payments.value.reduce((s, p) => s + Number(p.amount || 0), 0))
  payments.value.push({ method: next.code, amount: remaining, reference: '' })
}

const calc = computed(() => {
  let noncash = 0
  let cashTendered = 0
  payments.value.forEach((p) => {
    const a = Number(p.amount || 0)
    if (kindOf(p.method) === 'cash') cashTendered += a
    else noncash += a
  })
  const remaining = total.value - noncash
  const cashAllocated = Math.min(cashTendered, Math.max(0, remaining))
  const shortage = Math.max(0, total.value - noncash - cashAllocated)
  return { noncash, cashTendered, change: Math.max(0, cashTendered - cashAllocated), shortage, over: noncash > total.value }
})
const needsRef = computed(() => payments.value.some((p) => methodOf(p.method)?.requires_reference && !String(p.reference).trim()))
const canSubmit = computed(() => !processing.value && (total.value <= 0 || (calc.value.shortage === 0 && !calc.value.over && !needsRef.value)))

async function submitSale() {
  processing.value = true
  payError.value = ''
  try {
    const { data } = await window.axios.post(route('sales.pos.store'), {
      idempotency_key: idemKey.value,
      items: cart.value.map((l) => ({ product_id: l.product_id, quantity: l.quantity })),
      payments: total.value > 0 ? payments.value.map((p) => ({ method: p.method, amount: Number(p.amount), reference: p.reference || null })) : [],
      coupon_code: couponCode.value || null, customer_phone: customerPhone.value || null, customer_name: customerName.value || null,
    })
    idemKey.value = newKey()
    resetSale(false)
    router.visit(data.receipt_url)
  } catch (e) {
    if (e.response?.status === 422 && e.response.data?.message) payError.value = e.response.data.message
    else if (e.response?.data?.errors) payError.value = Object.values(e.response.data.errors).flat().join(' ')
    else payError.value = 'Koneksi bermasalah. Tekan Bayar sekali lagi — aman, transaksi tidak akan tercatat dobel.'
  } finally {
    processing.value = false
  }
}

function resetSale(confirmFirst = true) {
  if (confirmFirst && cart.value.length && !confirm('Batalkan transaksi ini?')) return
  cart.value = []; customerPhone.value = ''; customerName.value = ''; customerInfo.value = null; couponCode.value = ''
  quote.value = null; payOpen.value = false; payError.value = ''
  searchEl.value?.focus()
}

// ---------- Pintasan keyboard ----------
function onKey(e) {
  if (e.key === 'F2') { e.preventDefault(); searchEl.value?.focus() }
  if (e.key === 'F9') { e.preventDefault(); payOpen.value ? (canSubmit.value && submitSale()) : openPay() }
  if (e.key === 'Escape' && payOpen.value) payOpen.value = false
}
onMounted(() => { loadCatalog(); loadHeld(); window.addEventListener('keydown', onKey); searchEl.value?.focus() })
onBeforeUnmount(() => { window.removeEventListener('keydown', onKey); clearTimeout(quoteTimer); clearTimeout(toastTimer) })
</script>

<template>
  <AppLayout>
    <Head title="Kasir" />
    <template #title>Kasir · {{ shift.outlet }}</template>
    <template #actions>
      <Link :href="route('sales.shifts.index')" class="btn-secondary btn-sm">Shift &amp; tutup kasir</Link>
    </template>

    <div v-if="toast" class="fixed left-1/2 top-20 z-50 -translate-x-1/2 rounded-md bg-ink-900 px-4 py-2 text-sm text-white shadow-lg">{{ toast }}</div>

    <div class="grid gap-6 xl:grid-cols-5">
      <!-- Katalog -->
      <section class="xl:col-span-3">
        <div class="mb-3 flex gap-2">
          <input ref="searchEl" v-model="search" class="input" placeholder="Cari produk, atau scan barcode lalu Enter  (F2)" @keydown.enter.prevent="onSearchEnter" />
          <button class="btn-secondary" title="Muat ulang stok" @click="loadCatalog">↻</button>
        </div>
        <p v-if="loadError" class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ loadError }}</p>
        <p v-if="loading" class="text-sm text-ink-500">Memuat katalog…</p>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 2xl:grid-cols-4">
          <button v-for="p in filtered" :key="p.id" type="button" :disabled="p.track_stock && p.available <= 0"
            class="card flex flex-col items-start p-3 text-left transition hover:border-seal-500 disabled:cursor-not-allowed disabled:opacity-50"
            @click="addProduct(p)">
            <span class="line-clamp-2 text-sm font-medium">{{ p.name }}</span>
            <span class="font-mono text-[11px] text-ink-500">{{ p.sku }}</span>
            <span class="num mt-2 text-base font-semibold">{{ rupiah(p.price) }}</span>
            <span class="mt-1 text-xs" :class="p.track_stock && p.available <= 0 ? 'text-red-600' : 'text-ink-500'">
              {{ p.track_stock ? (p.available <= 0 ? 'Stok habis' : `Stok ${num(p.available)} ${p.unit}`) : 'Tanpa stok' }}
            </span>
          </button>
        </div>
        <p v-if="!loading && !filtered.length" class="py-10 text-center text-sm text-ink-500">Tidak ada produk yang cocok.</p>
        <p v-if="!search && products.length > 60" class="mt-3 text-center text-xs text-ink-500">Menampilkan 60 dari {{ products.length }} produk. Ketik untuk mencari.</p>
      </section>

      <!-- Keranjang -->
      <aside class="xl:col-span-2">
        <div class="card sticky top-20">
          <div class="card-header">
            <h2 class="text-sm font-semibold">Keranjang</h2>
            <div class="flex gap-1">
              <button v-if="held.length" class="btn-ghost btn-sm" @click="$refs.heldList.open = !$refs.heldList.open">Tahanan ({{ held.length }})</button>
              <button class="btn-ghost btn-sm" :disabled="!cart.length" @click="hold">Tahan</button>
              <button class="btn-ghost btn-sm text-red-600" :disabled="!cart.length" @click="resetSale()">Batal</button>
            </div>
          </div>

          <details v-if="held.length" ref="heldList" class="border-b border-ink-100 px-5 py-2 text-sm dark:border-ink-800">
            <summary class="cursor-pointer text-ink-500">Transaksi ditahan</summary>
            <ul class="mt-2 space-y-1">
              <li v-for="(h, i) in held" :key="h.at" class="flex items-center justify-between">
                <span>{{ new Date(h.at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }} · {{ h.cart.length }} item</span>
                <button class="text-seal-600 hover:underline" @click="resume(i)">Lanjutkan</button>
              </li>
            </ul>
          </details>

          <div class="max-h-[40vh] overflow-y-auto">
            <p v-if="!cart.length" class="px-5 py-10 text-center text-sm text-ink-500">Pilih produk di kiri atau scan barcode.</p>
            <ul class="divide-y divide-ink-100 dark:divide-ink-800">
              <li v-for="l in cart" :key="l.product_id" class="flex items-center gap-3 px-5 py-2.5">
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-medium">{{ l.name }}</p>
                  <p class="num text-xs text-ink-500">{{ rupiah(l.price) }}</p>
                </div>
                <div class="flex items-center gap-1">
                  <button class="btn-secondary btn-sm px-2" @click="setQty(l, l.quantity - 1)">−</button>
                  <input :value="l.quantity" type="number" min="0" class="input w-14 px-1 text-center" @change="setQty(l, $event.target.value)" />
                  <button class="btn-secondary btn-sm px-2" @click="setQty(l, l.quantity + 1)">+</button>
                </div>
                <p class="num w-24 text-right text-sm font-semibold">{{ rupiah(l.price * l.quantity) }}</p>
                <button class="text-ink-400 hover:text-red-600" title="Hapus" @click="removeLine(l)">&times;</button>
              </li>
            </ul>
          </div>

          <div class="space-y-3 border-t border-ink-100 px-5 py-4 dark:border-ink-800">
            <div class="grid grid-cols-2 gap-2">
              <input v-model="customerPhone" class="input" placeholder="HP pelanggan (opsional)" inputmode="tel" @blur="lookupCustomer" @keydown.enter.prevent="lookupCustomer" />
              <input v-model="customerName" class="input" placeholder="Nama pelanggan" :disabled="customerInfo?.found" />
            </div>
            <p v-if="customerInfo?.found" class="text-xs text-emerald-600">Pelanggan lama · {{ customerInfo.orders_count }} pesanan</p>
            <p v-else-if="customerInfo && !customerInfo.found" class="text-xs text-ink-500">Pelanggan baru, akan dicatat otomatis.</p>

            <div>
              <input v-model="couponCode" class="input uppercase" placeholder="Kode kupon (opsional)" />
              <p v-if="quote?.coupon" class="mt-1 text-xs text-emerald-600">Kupon {{ quote.coupon.code }} dipakai.</p>
              <p v-else-if="quote?.coupon_error" class="mt-1 text-xs text-red-600">{{ quote.coupon_error }}</p>
            </div>

            <dl class="space-y-1 text-sm">
              <div class="flex justify-between"><dt class="text-ink-500">Subtotal</dt><dd class="num">{{ rupiah(quote?.subtotal ?? localSubtotal) }}</dd></div>
              <div v-if="quote?.discount_total > 0" class="flex justify-between text-emerald-600"><dt>Diskon</dt><dd class="num">−{{ rupiah(quote.discount_total) }}</dd></div>
              <div v-if="quote?.tax_total > 0" class="flex justify-between"><dt class="text-ink-500">PPN</dt><dd class="num">{{ rupiah(quote.tax_total) }}</dd></div>
              <div class="flex justify-between border-t border-ink-100 pt-2 text-lg font-semibold dark:border-ink-800"><dt>Total</dt><dd class="num">{{ rupiah(total) }}</dd></div>
            </dl>
            <p v-if="quote?.shortages?.length" class="text-xs text-red-600">Stok kurang: {{ quote.shortages.map((s) => `${s.name} (tersedia ${s.available})`).join(', ') }}</p>

            <button class="btn-primary w-full py-3 text-base" :disabled="!cart.length || quoting || !!quote?.shortages?.length" @click="openPay">Bayar  (F9)</button>
          </div>
        </div>
      </aside>
    </div>

    <!-- Dialog pembayaran -->
    <div v-if="payOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4" @click.self="payOpen = false">
      <div class="card w-full max-w-lg">
        <div class="card-header"><h2 class="text-sm font-semibold">Pembayaran</h2><button class="btn-ghost btn-sm" @click="payOpen = false">Tutup (Esc)</button></div>
        <div class="card-body space-y-4">
          <p class="num text-center text-3xl font-semibold">{{ rupiah(total) }}</p>

          <template v-if="total > 0">
            <div v-for="(p, i) in payments" :key="i" class="space-y-2 rounded-md border border-ink-200 p-3 dark:border-ink-700">
              <div class="flex gap-2">
                <select v-model="p.method" class="input"><option v-for="m in paymentMethods" :key="m.code" :value="m.code">{{ m.name }}</option></select>
                <input v-model.number="p.amount" type="number" min="0" step="any" class="input text-right" />
                <button v-if="payments.length > 1" class="text-ink-400 hover:text-red-600" @click="payments.splice(i, 1)">&times;</button>
              </div>
              <div v-if="kindOf(p.method) === 'cash'" class="flex flex-wrap gap-2">
                <button class="btn-secondary btn-sm" @click="p.amount = total - calc.noncash">Uang pas</button>
                <button v-for="n in [50000, 100000, 200000]" :key="n" class="btn-secondary btn-sm" @click="p.amount = n">{{ num(n) }}</button>
              </div>
              <input v-if="methodOf(p.method)?.requires_reference" v-model="p.reference" class="input" placeholder="Nomor referensi / approval (wajib)" />
            </div>
            <button class="btn-ghost btn-sm" @click="addPaymentLine">+ Pecah pembayaran</button>

            <dl class="space-y-1 text-sm">
              <div v-if="calc.change > 0" class="flex justify-between text-lg font-semibold text-emerald-600"><dt>Kembalian</dt><dd class="num">{{ rupiah(calc.change) }}</dd></div>
              <div v-if="calc.shortage > 0" class="flex justify-between font-semibold text-red-600"><dt>Kurang</dt><dd class="num">{{ rupiah(calc.shortage) }}</dd></div>
              <p v-if="calc.over" class="text-red-600">Pembayaran non-tunai melebihi total.</p>
            </dl>
          </template>
          <p v-else class="text-center text-sm text-ink-500">Total Rp 0, tidak ada yang perlu dibayar.</p>

          <p v-if="payError" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ payError }}</p>
          <button class="btn-accent w-full py-3 text-base" :disabled="!canSubmit" @click="submitSale">{{ processing ? 'Memproses…' : 'Selesaikan transaksi' }}</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
