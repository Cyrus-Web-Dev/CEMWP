import { reactive, watch, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'

// Filter halaman daftar: berubah -> muat ulang dengan query (ditunda 300 ms agar tidak membanjiri server saat mengetik).
export function useFilters(routeName, initial) {
  const filters = reactive({ ...initial })
  let timer = null

  watch(filters, () => {
    clearTimeout(timer)
    timer = setTimeout(() => {
      const params = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '' && v !== null && v !== undefined))
      router.get(route(routeName), params, { preserveState: true, preserveScroll: true, replace: true })
    }, 300)
  })
  onBeforeUnmount(() => clearTimeout(timer))

  return filters
}
