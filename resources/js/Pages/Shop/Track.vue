<script setup>
import ShopLayout from '@/Layouts/ShopLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'

const form = useForm({ number: '', phone: '' })
</script>

<template>
  <ShopLayout>
    <Head title="Lacak pesanan" />
    <div class="mx-auto max-w-md">
      <h1 class="font-serif text-2xl font-semibold">Lacak pesanan</h1>
      <p class="mt-2 text-sm text-[#7A6A58]">Masukkan nomor pesanan dan nomor HP yang dipakai saat memesan.</p>
      <form class="mt-6 space-y-4 rounded-xl border border-[#E7DCC8] bg-white p-5" @submit.prevent="form.post(route('shop.track.lookup'))">
        <div><label class="label">Nomor pesanan</label><input v-model="form.number" class="input bg-white font-mono uppercase" placeholder="ORD-202610-0001" required /><p v-if="form.errors.number" class="error-text">{{ form.errors.number }}</p></div>
        <div><label class="label">Nomor HP</label><input v-model="form.phone" class="input bg-white" inputmode="tel" required /><p v-if="form.errors.phone" class="error-text">{{ form.errors.phone }}</p></div>
        <button class="btn-primary w-full" :disabled="form.processing">Lacak</button>
      </form>
    </div>
  </ShopLayout>
</template>
