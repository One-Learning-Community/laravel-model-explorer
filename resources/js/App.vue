<template>
    <div class="min-h-screen flex flex-col">
        <div class="navbar bg-base-200 border-b border-base-300 px-6 min-h-12 gap-6">
            <RouterLink to="/" class="text-sm font-semibold tracking-tight no-underline text-base-content">
                Model Explorer
            </RouterLink>
            <div class="flex gap-4">
                <RouterLink to="/" class="text-sm no-underline text-base-content/60 hover:text-base-content transition-colors" :class="{ 'text-base-content! font-medium': $route.path === '/' }">
                    Models
                </RouterLink>
                <RouterLink to="/graph" class="text-sm no-underline text-base-content/60 hover:text-base-content transition-colors" :class="{ 'text-base-content! font-medium': $route.path === '/graph' }">
                    Graph
                </RouterLink>
            </div>

            <!-- Theme toggle -->
            <button
                @click="toggleTheme"
                class="btn btn-ghost btn-sm btn-square ml-auto"
                :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
            >
                <Sun v-if="isDark" :size="16" />
                <Moon v-else :size="16" />
            </button>

            <!-- Model search -->
            <div class="relative" ref="searchContainer">
                <input
                    v-model="searchQuery"
                    @focus="searchOpen = true"
                    @keydown.escape="closeSearch"
                    @keydown.down.prevent="moveSelection(1)"
                    @keydown.up.prevent="moveSelection(-1)"
                    @keydown.enter.prevent="selectCurrent"
                    type="text"
                    placeholder="Jump to model…"
                    class="input input-sm w-52 font-mono text-xs"
                />
                <ul
                    v-if="searchOpen && filteredModels.length"
                    class="absolute right-0 top-full mt-1 w-72 bg-base-100 border border-base-300 rounded-lg shadow-lg z-50 max-h-72 overflow-y-auto p-1"
                >
                    <li
                        v-for="(model, i) in filteredModels"
                        :key="model.class"
                        @mousedown.prevent="navigateTo(model)"
                        class="px-3 py-2 rounded cursor-pointer flex items-baseline gap-2"
                        :class="i === selectedIndex ? 'bg-base-200' : 'hover:bg-base-200'"
                    >
                        <span class="text-sm font-medium">{{ model.short_name }}</span>
                        <span class="text-xs text-base-content/40 font-mono truncate">{{ model.table }}</span>
                    </li>
                </ul>
            </div>
        </div>
        <main class="flex-1 p-8 max-w-6xl w-full mx-auto">
            <RouterView />
        </main>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { Moon, Sun } from 'lucide-vue-next'
import { encodeModel } from './utils/model.js'

const router = useRouter()

// ── Theme ─────────────────────────────────────────────────────────────────────

const STORAGE_KEY = 'model-explorer-theme'
const isDark = ref(document.documentElement.getAttribute('data-theme') === 'night')

function toggleTheme() {
    isDark.value = !isDark.value
    const theme = isDark.value ? 'night' : 'light'
    document.documentElement.setAttribute('data-theme', theme)
    localStorage.setItem(STORAGE_KEY, theme)
}

// ── Model search ──────────────────────────────────────────────────────────────

const models = ref([])
const searchQuery = ref('')
const searchOpen = ref(false)
const selectedIndex = ref(0)
const searchContainer = ref(null)

const filteredModels = computed(() => {
    const q = searchQuery.value.trim().toLowerCase()
    const list = q
        ? models.value.filter(m =>
            m.short_name.toLowerCase().includes(q) ||
            m.class.toLowerCase().includes(q)
          )
        : models.value
    return list.slice(0, 10)
})

watch(filteredModels, () => { selectedIndex.value = 0 })

function navigateTo(model) {
    router.push(`/models/${encodeModel(model.class)}`)
    closeSearch()
}

function closeSearch() {
    searchOpen.value = false
    searchQuery.value = ''
    selectedIndex.value = 0
}

function moveSelection(dir) {
    const max = filteredModels.value.length - 1
    selectedIndex.value = Math.max(0, Math.min(max, selectedIndex.value + dir))
}

function selectCurrent() {
    const model = filteredModels.value[selectedIndex.value]
    if (model) navigateTo(model)
}

function handleClickOutside(e) {
    if (searchContainer.value && !searchContainer.value.contains(e.target)) {
        searchOpen.value = false
    }
}

onMounted(async () => {
    document.addEventListener('mousedown', handleClickOutside)
    try {
        const res = await fetch(`${window.modelExplorerBasePath}/api/models`)
        if (res.ok) models.value = await res.json()
    } catch {}
})

onUnmounted(() => {
    document.removeEventListener('mousedown', handleClickOutside)
})
</script>
