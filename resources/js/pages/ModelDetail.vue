<template>
    <div>
        <RouterLink to="/" class="link link-hover text-sm text-base-content/50 mb-6 inline-block">
            ← All models
        </RouterLink>

        <div v-if="error" role="alert" class="alert alert-error text-sm">{{ error }}</div>
        <div v-else-if="loading" class="text-base-content/50 text-sm">Loading…</div>

        <template v-else-if="model">
            <div class="flex items-start justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold m-0 mb-1">{{ model.short_name }}</h1>
                    <span class="font-mono text-xs text-base-content/50">{{ model.class }}</span>
                </div>
                <div class="flex gap-2 items-center pt-1 shrink-0 flex-wrap justify-end">
                    <span class="badge badge-ghost font-mono">{{ model.table }}</span>
                    <span class="badge" :class="model.uses_timestamps ? 'badge-success' : 'badge-ghost'">
                        {{ model.uses_timestamps ? 'timestamps' : 'no timestamps' }}
                    </span>
                    <span
                        v-if="model.policy"
                        class="badge badge-info font-mono"
                        :title="model.policy"
                    >policy: {{ shortName(model.policy) }}</span>
                    <RouterLink :to="`/models/${route.params.model}/record`" class="btn btn-xs btn-ghost">
                        Look up record
                    </RouterLink>
                </div>
            </div>

            <SectionNav
                :sections="navSections"
                :active-section="activeSection"
                @navigate="scrollToSection"
            />

            <ColumnsTable :columns="dbColumns" :foreign-key-map="foreignKeyMap" />
            <VirtualAttributesTable :grouped-attrs="groupedVirtualAttrs" @view-snippet="snippetModal.open" />
            <RelationsTable :grouped-relations="groupedRelations" @view-snippet="snippetModal.open" />
            <ScopesTable :grouped-scopes="groupedScopes" @view-snippet="snippetModal.open" />
            <TraitsList :traits="model.traits" />
        </template>
    </div>

    <SourceCodeModal ref="snippetModal" />
</template>

<script setup>
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { shortName } from '../utils/model.js'
import SourceCodeModal from '../components/SourceCodeModal.vue'
import SectionNav from '../components/detail/SectionNav.vue'
import ColumnsTable from '../components/detail/ColumnsTable.vue'
import VirtualAttributesTable from '../components/detail/VirtualAttributesTable.vue'
import RelationsTable from '../components/detail/RelationsTable.vue'
import ScopesTable from '../components/detail/ScopesTable.vue'
import TraitsList from '../components/detail/TraitsList.vue'

const route = useRoute()
const model = ref(null)
const loading = ref(true)
const error = ref(null)
const snippetModal = ref(null)

// ── Computed data ─────────────────────────────────────────────────────────────

const dbColumns = computed(() => model.value?.attributes.filter(a => !a.virtual) ?? [])
const virtualAttrs = computed(() => model.value?.attributes.filter(a => a.virtual) ?? [])

function groupBySource(items) {
    const groups = {}
    for (const item of items) {
        const key = item.defined_in ?? ''
        if (!groups[key]) groups[key] = []
        groups[key].push(item)
    }
    return Object.entries(groups).map(([key, members]) => ({
        source: key || null,
        label: key ? shortName(key) : null,
        items: members,
    }))
}

const groupedVirtualAttrs = computed(() => {
    const groups = groupBySource(virtualAttrs.value)
    for (const group of groups) {
        group.items.sort((a, b) => a.name.localeCompare(b.name))
    }
    return groups.sort((a, b) => {
        if (a.source === null) return -1
        if (b.source === null) return 1
        return (a.label ?? '').localeCompare(b.label ?? '')
    })
})

const groupedRelations = computed(() => {
    const groups = groupBySource(model.value?.relations ?? [])
    for (const group of groups) {
        group.items.sort((a, b) => a.name.localeCompare(b.name))
    }
    return groups.sort((a, b) => {
        if (a.source === null) return -1
        if (b.source === null) return 1
        return (a.label ?? '').localeCompare(b.label ?? '')
    })
})

const groupedScopes = computed(() => groupBySource(model.value?.scopes ?? []))

// Map of column name → relation for FK columns that live on this model's table.
// Only BelongsTo and MorphTo have the FK on the local table.
const foreignKeyMap = computed(() => {
    const map = {}
    for (const rel of model.value?.relations ?? []) {
        if ((rel.type === 'BelongsTo' || rel.type === 'MorphTo') && rel.foreign_key) {
            map[rel.foreign_key] = rel
        }
    }
    return map
})

// ── Section nav + scroll spy ──────────────────────────────────────────────────

const activeSection = ref('columns')

const navSections = computed(() => {
    if (!model.value) return []
    const s = [{ id: 'columns', label: 'Columns' }]
    if (virtualAttrs.value.length)       s.push({ id: 'virtual-attrs', label: 'Virtual Attributes' })
    if (model.value.relations.length)    s.push({ id: 'relations',     label: 'Relations' })
    if (model.value.scopes.length)       s.push({ id: 'scopes',        label: 'Scopes' })
    if (model.value.traits.length)       s.push({ id: 'traits',        label: 'Traits' })
    return s
})

const SCROLL_THRESHOLD = 64

function updateActiveSection() {
    const ids = navSections.value.map(s => s.id)
    let active = ids[0]
    for (const id of ids) {
        const el = document.getElementById(id)
        if (el && el.getBoundingClientRect().top <= SCROLL_THRESHOLD) {
            active = id
        }
    }
    activeSection.value = active
}

function initScrollSpy() {
    window.removeEventListener('scroll', updateActiveSection)
    window.addEventListener('scroll', updateActiveSection, { passive: true })
    updateActiveSection()
}

function scrollToSection(id) {
    activeSection.value = id
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' })
}

onUnmounted(() => window.removeEventListener('scroll', updateActiveSection))

// ── Load ──────────────────────────────────────────────────────────────────────

async function load(slug) {
    loading.value = true
    error.value = null
    model.value = null
    try {
        const res = await fetch(`${window.modelExplorerBasePath}/api/models/${slug}`)
        if (res.status === 404) throw new Error('Model not found.')
        if (!res.ok) throw new Error(`HTTP ${res.status}`)
        model.value = await res.json()
        activeSection.value = 'columns'
        nextTick(initScrollSpy)
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

watch(() => route.params.model, slug => { if (slug) load(slug) }, { immediate: true })
</script>
