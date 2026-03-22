<template>
    <div>
        <!-- Breadcrumb trail -->
        <nav v-if="trail.length" class="flex items-center gap-1 text-xs mb-3 flex-wrap">
            <template v-for="(entry, i) in trail" :key="i">
                <RouterLink :to="trailLink(i)" class="link link-hover text-base-content/50 font-mono">
                    {{ entry.display }}
                </RouterLink>
                <span class="text-base-content/30">›</span>
            </template>
            <span class="font-mono text-base-content/70">
                {{ record ? `${record.short_name}#${record.key_value}` : (modelStructure?.short_name ?? '…') }}
            </span>
        </nav>

        <RouterLink
            :to="`/models/${route.params.model}`"
            class="link link-hover text-sm text-base-content/50 mb-6 inline-block"
        >← {{ modelStructure?.short_name ?? 'Model' }} detail</RouterLink>

        <div v-if="modelStructureError" role="alert" class="alert alert-error text-sm mb-6">
            {{ modelStructureError }}
        </div>
        <div v-else-if="modelStructureLoading" class="text-base-content/50 text-sm">Loading…</div>

        <template v-else-if="modelStructure">
            <h1 class="text-2xl font-bold mb-6">Record Lookup — {{ modelStructure.short_name }}</h1>

            <!-- Lookup form -->
            <form @submit.prevent="submitLookup" class="flex gap-2 items-end mb-8 flex-wrap">
                <div class="form-control">
                    <label class="label pb-1"><span class="label-text text-xs">Field</span></label>
                    <select v-model="selectedField" class="select select-sm select-bordered font-mono">
                        <option v-for="f in availableLookupFields" :key="f" :value="f">{{ f }}</option>
                    </select>
                </div>
                <div class="form-control grow">
                    <label class="label pb-1"><span class="label-text text-xs">Value</span></label>
                    <input
                        v-model="lookupValue"
                        type="text"
                        placeholder="Enter value…"
                        class="input input-sm input-bordered font-mono"
                        required
                    />
                </div>
                <button type="submit" class="btn btn-sm btn-primary" :disabled="recordLoading">
                    <span v-if="recordLoading" class="loading loading-spinner loading-xs"></span>
                    Find
                </button>
            </form>

            <!-- Record error -->
            <div v-if="recordError" role="alert" class="alert alert-error text-sm mb-6">
                {{ recordError }}
            </div>

            <!-- No record found -->
            <div v-else-if="searched && !record && !recordLoading" role="alert" class="alert alert-warning text-sm mb-6">
                No record found for {{ selectedField }} = {{ lookupValue }}
            </div>

            <!-- Record panel -->
            <template v-if="record">
                <div class="flex items-center gap-3 mb-4">
                    <h2 class="text-lg font-semibold m-0">
                        {{ record.short_name }}
                        <span class="text-base-content/50 font-mono">#{{ record.key_value }}</span>
                    </h2>
                </div>

                <!-- Attribute filter -->
                <div class="mb-6">
                    <input
                        v-model="attributeFilter"
                        type="search"
                        placeholder="Filter attributes by name or value…"
                        class="input input-sm input-bordered font-mono w-72"
                    />
                </div>

                <!-- Raw column attributes -->
                <section class="mb-8">
                    <h3 class="text-xs font-semibold uppercase tracking-widest text-base-content/40 mb-3">Attributes</h3>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th class="w-40">Name</th>
                                    <th>Value (raw)</th>
                                    <th class="w-8"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!filteredAttributes.length">
                                    <td colspan="3" class="text-base-content/30 text-xs italic">No matching attributes.</td>
                                </tr>
                                <tr v-for="[name, value] in filteredAttributes" :key="name" class="align-top">
                                    <td class="font-mono text-sm font-medium">{{ name }}</td>
                                    <td class="font-mono text-sm text-base-content/70">
                                        <span v-if="value === null || value === undefined" class="text-base-content/30">—</span>
                                        <template v-else>
                                            <pre v-if="expandedCells[`attr:${name}`]" class="text-xs whitespace-pre-wrap break-all font-mono">{{ prettyValue(value) }}</pre>
                                            <span v-else class="block max-w-sm truncate">{{ formatValue(value) }}</span>
                                            <button
                                                v-if="isLong(value)"
                                                @click="toggleExpand(`attr:${name}`)"
                                                class="btn btn-xs btn-ghost opacity-60 hover:opacity-100 mt-1"
                                            >{{ expandedCells[`attr:${name}`] ? 'Less ↑' : 'More ↓' }}</button>
                                        </template>
                                    </td>
                                    <td class="align-top">
                                        <button
                                            v-if="value !== null && value !== undefined"
                                            @click="copyValue(`attr:${name}`, value)"
                                            class="btn btn-xs btn-ghost opacity-40 hover:opacity-100"
                                            :title="copiedCells[`attr:${name}`] ? 'Copied!' : 'Copy value'"
                                        >{{ copiedCells[`attr:${name}`] ? '✓' : '⎘' }}</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Accessor values (lazy) -->
                <section v-if="accessorAttributes.length && filteredAccessorAttributes.length" class="mb-8">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-base-content/40">
                            Accessor Values
                        </h3>
                        <button
                            @click="loadAllAccessors"
                            class="btn btn-xs btn-ghost"
                            :disabled="allAccessorsLoading"
                        >
                            <span v-if="allAccessorsLoading" class="loading loading-spinner loading-xs"></span>
                            Load all
                        </button>
                    </div>
                    <p class="text-xs text-base-content/40 mb-3">
                        These values are resolved on demand. Accessor methods may have non-database side effects that cannot be rolled back.
                    </p>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th class="w-40">Name</th>
                                    <th>Value</th>
                                    <th class="w-16"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="attr in filteredAccessorAttributes" :key="attr.name" class="align-top">
                                    <td class="font-mono text-sm font-medium">
                                        {{ attr.name }}
                                        <span v-if="attr.virtual" class="badge badge-ghost badge-xs ml-1">virtual</span>
                                    </td>
                                    <td class="font-mono text-sm text-base-content/70" :colspan="isModelResult(accessorData[attr.name]?.value) ? 2 : 1">
                                        <template v-if="accessorData[attr.name]">
                                            <span v-if="accessorData[attr.name].loading" class="loading loading-spinner loading-xs"></span>
                                            <span v-else-if="accessorData[attr.name].error" class="text-error text-xs">
                                                Error: {{ accessorData[attr.name].error }}
                                            </span>
                                            <template v-else-if="accessorData[attr.name].value?.type === 'one'">
                                                <p v-if="!accessorData[attr.name].value.record" class="text-sm text-base-content/50">—</p>
                                                <template v-else>
                                                    <div class="overflow-x-auto mt-1">
                                                        <table class="table table-xs">
                                                            <tbody>
                                                                <tr
                                                                    v-for="[col, val] in Object.entries(accessorData[attr.name].value.record.attributes)"
                                                                    :key="col"
                                                                >
                                                                    <td class="font-mono font-medium w-40">{{ col }}</td>
                                                                    <td class="font-mono text-base-content/70 max-w-xs truncate">
                                                                        <span v-if="val === null || val === undefined" class="text-base-content/30">—</span>
                                                                        <span v-else>{{ formatValue(val) }}</span>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <div class="mt-2">
                                                        <RouterLink
                                                            :to="recordLink(accessorData[attr.name].value.record)"
                                                            class="link link-primary text-xs"
                                                        >View {{ accessorData[attr.name].value.record.short_name }} #{{ accessorData[attr.name].value.record.key_value }} →</RouterLink>
                                                    </div>
                                                </template>
                                            </template>
                                            <template v-else-if="accessorData[attr.name].value?.type === 'many'">
                                                <p v-if="!accessorData[attr.name].value.records.length" class="text-sm text-base-content/50">No records.</p>
                                                <template v-else>
                                                    <div class="overflow-auto max-h-64 mt-1">
                                                        <table class="table table-xs">
                                                            <thead>
                                                                <tr>
                                                                    <th v-for="col in manyRelationColumns(accessorData[attr.name].value.records)" :key="col">{{ col }}</th>
                                                                    <th></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <tr v-for="r in accessorData[attr.name].value.records" :key="r.key_value">
                                                                    <td
                                                                        v-for="col in manyRelationColumns(accessorData[attr.name].value.records)"
                                                                        :key="col"
                                                                        class="font-mono text-xs text-base-content/70 max-w-32 truncate"
                                                                    >
                                                                        <span v-if="r.attributes[col] === null || r.attributes[col] === undefined" class="text-base-content/30">—</span>
                                                                        <span v-else>{{ formatValue(r.attributes[col]) }}</span>
                                                                    </td>
                                                                    <td>
                                                                        <RouterLink :to="recordLink(r)" class="link link-primary text-xs">View →</RouterLink>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <p v-if="accessorData[attr.name].value.total > accessorData[attr.name].value.records.length" class="text-xs text-base-content/40 mt-1">
                                                        Showing {{ accessorData[attr.name].value.records.length }} of {{ accessorData[attr.name].value.total }}
                                                    </p>
                                                </template>
                                            </template>
                                            <template v-else>
                                                <span v-if="accessorData[attr.name].value === null || accessorData[attr.name].value === undefined" class="text-base-content/30">—</span>
                                                <template v-else>
                                                    <pre v-if="expandedCells[`accessor:${attr.name}`]" class="text-xs whitespace-pre-wrap break-all font-mono">{{ prettyValue(accessorData[attr.name].value) }}</pre>
                                                    <span v-else class="block max-w-sm truncate">{{ formatValue(accessorData[attr.name].value) }}</span>
                                                    <button
                                                        v-if="isLong(accessorData[attr.name].value)"
                                                        @click="toggleExpand(`accessor:${attr.name}`)"
                                                        class="btn btn-xs btn-ghost opacity-60 hover:opacity-100 mt-1"
                                                    >{{ expandedCells[`accessor:${attr.name}`] ? 'Less ↑' : 'More ↓' }}</button>
                                                </template>
                                            </template>
                                        </template>
                                        <span v-else class="text-base-content/20 text-xs">not loaded</span>
                                    </td>
                                    <td v-if="!isModelResult(accessorData[attr.name]?.value)" class="align-top">
                                        <div class="flex gap-1">
                                            <button
                                                v-if="!accessorData[attr.name] || accessorData[attr.name].error"
                                                @click="loadAccessor(attr.name)"
                                                class="btn btn-xs btn-ghost"
                                                :disabled="accessorData[attr.name]?.loading"
                                            >Load</button>
                                            <button
                                                v-else-if="accessorData[attr.name] && !accessorData[attr.name].loading && accessorData[attr.name].value !== null && accessorData[attr.name].value !== undefined"
                                                @click="copyValue(`accessor:${attr.name}`, accessorData[attr.name].value)"
                                                class="btn btn-xs btn-ghost opacity-40 hover:opacity-100"
                                                :title="copiedCells[`accessor:${attr.name}`] ? 'Copied!' : 'Copy value'"
                                            >{{ copiedCells[`accessor:${attr.name}`] ? '✓' : '⎘' }}</button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Relations section -->
                <section v-if="modelStructure.relations?.length" class="mb-8">
                    <h3 class="text-xs font-semibold uppercase tracking-widest text-base-content/40 mb-3">Relations</h3>
                    <div class="flex flex-col gap-3">
                        <div
                            v-for="rel in modelStructure.relations"
                            :key="rel.name"
                            class="card card-border bg-base-200"
                        >
                            <div class="card-body p-4">
                                <div class="flex items-center justify-between gap-4 flex-wrap">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-medium text-sm">{{ rel.name }}</span>
                                        <span class="badge badge-xs gap-1" :class="relationColor(rel.type)" :title="rel.type">
                                            {{ shortName(rel.type) }}
                                        </span>
                                        <span class="text-xs text-base-content/50">→ {{ shortName(rel.related) }}</span>
                                    </div>
                                    <button
                                        v-if="!relationData[rel.name]"
                                        @click="loadRelation(rel.name)"
                                        class="btn btn-xs btn-ghost"
                                    >Load</button>
                                    <span v-else-if="relationData[rel.name].loading" class="loading loading-spinner loading-xs"></span>
                                    <button
                                        v-else
                                        @click="delete relationData[rel.name]"
                                        class="btn btn-xs btn-ghost"
                                    >Collapse</button>
                                </div>

                                <!-- Relation results -->
                                <template v-if="relationData[rel.name] && !relationData[rel.name].loading">
                                    <div v-if="relationData[rel.name].error" role="alert" class="alert alert-error text-xs mt-2">
                                        {{ relationData[rel.name].error }}
                                    </div>
                                    <template v-else-if="relationData[rel.name].data">
                                        <!-- To-one -->
                                        <template v-if="relationData[rel.name].data.type === 'one'">
                                            <p v-if="!relationData[rel.name].data.record" class="text-sm text-base-content/50 mt-2">
                                                No related record.
                                            </p>
                                            <template v-else>
                                                <div class="overflow-x-auto mt-3">
                                                    <table class="table table-xs">
                                                        <tbody>
                                                            <tr
                                                                v-for="[name, value] in Object.entries(relationData[rel.name].data.record.attributes)"
                                                                :key="name"
                                                            >
                                                                <td class="font-mono font-medium w-40">{{ name }}</td>
                                                                <td class="font-mono text-base-content/70 max-w-xs truncate">
                                                                    <span v-if="value === null || value === undefined" class="text-base-content/30">—</span>
                                                                    <span v-else>{{ formatValue(value) }}</span>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="mt-2">
                                                    <RouterLink
                                                        :to="recordLink(relationData[rel.name].data.record)"
                                                        class="link link-primary text-xs"
                                                    >View {{ relationData[rel.name].data.record.short_name }} #{{ relationData[rel.name].data.record.key_value }} →</RouterLink>
                                                </div>
                                            </template>
                                        </template>

                                        <!-- To-many -->
                                        <template v-else-if="relationData[rel.name].data.type === 'many'">
                                            <p v-if="!relationData[rel.name].data.records.length" class="text-sm text-base-content/50 mt-2">
                                                No related records.
                                            </p>
                                            <template v-else>
                                                <div class="overflow-x-auto mt-3">
                                                    <table class="table table-xs">
                                                        <thead>
                                                            <tr>
                                                                <th v-for="col in manyRelationColumns(relationData[rel.name].data.records)" :key="col">{{ col }}</th>
                                                                <th></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr v-for="r in relationData[rel.name].data.records" :key="r.key_value">
                                                                <td
                                                                    v-for="col in manyRelationColumns(relationData[rel.name].data.records)"
                                                                    :key="col"
                                                                    class="font-mono text-xs text-base-content/70 max-w-32 truncate"
                                                                >
                                                                    <span v-if="r.attributes[col] === null || r.attributes[col] === undefined" class="text-base-content/30">—</span>
                                                                    <span v-else>{{ formatValue(r.attributes[col]) }}</span>
                                                                </td>
                                                                <td>
                                                                    <RouterLink
                                                                        :to="recordLink(r)"
                                                                        class="link link-primary text-xs"
                                                                    >View →</RouterLink>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="flex items-center justify-between mt-2 text-xs text-base-content/50">
                                                    <span>{{ relationData[rel.name].data.total }} total records</span>
                                                    <div v-if="relationData[rel.name].data.last_page > 1" class="flex gap-1 items-center">
                                                        <button
                                                            class="btn btn-xs btn-ghost"
                                                            :disabled="relationData[rel.name].data.current_page <= 1"
                                                            @click="loadRelation(rel.name, relationData[rel.name].data.current_page - 1)"
                                                        >‹</button>
                                                        <span>{{ relationData[rel.name].data.current_page }} / {{ relationData[rel.name].data.last_page }}</span>
                                                        <button
                                                            class="btn btn-xs btn-ghost"
                                                            :disabled="relationData[rel.name].data.current_page >= relationData[rel.name].data.last_page"
                                                            @click="loadRelation(rel.name, relationData[rel.name].data.current_page + 1)"
                                                        >›</button>
                                                    </div>
                                                </div>
                                            </template>
                                        </template>
                                    </template>
                                </template>
                            </div>
                        </div>
                    </div>
                </section>
            </template>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

// Model structure (for field selector + relations list)
const modelStructure = ref(null)
const modelStructureLoading = ref(true)
const modelStructureError = ref(null)

// Lookup state
const selectedField = ref(route.query.field || '')
const lookupValue = ref(route.query.value || '')
const record = ref(null)
const recordLoading = ref(false)
const recordError = ref(null)
const searched = ref(false)

// Per-relation and per-accessor state
const relationData = reactive({})
const accessorData = reactive({})

// Attribute filter + cell expand/copy state
const attributeFilter = ref('')
const expandedCells = reactive({})
const copiedCells = reactive({})

// ── Helpers ──────────────────────────────────────────────────────────────────

function encodeModel(className) {
    return btoa(className).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '')
}

function shortName(fqcn) {
    return fqcn.split('\\').pop()
}

function isDateLike(value) {
    if (typeof value !== 'string' || value.length < 10 || value.length > 35) return false
    return /^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}:\d{2})?/.test(value)
}

function formatValue(value) {
    if (value === null || value === undefined) return '—'
    if (typeof value === 'boolean') return value ? 'true' : 'false'
    if (typeof value === 'object') return JSON.stringify(value)
    if (isDateLike(value)) {
        return String(value).replace('T', ' ').replace(/\.\d+/, '').replace(/Z$/, ' UTC')
    }
    return String(value)
}

function prettyValue(value) {
    if (value === null || value === undefined) return '—'
    if (typeof value === 'object') return JSON.stringify(value, null, 2)
    return String(value)
}

function isLong(value) {
    if (value === null || value === undefined) return false
    if (typeof value === 'object') return JSON.stringify(value).length > 80
    return String(value).length > 80
}

function toggleExpand(key) {
    if (expandedCells[key]) {
        delete expandedCells[key]
    } else {
        expandedCells[key] = true
    }
}

async function copyValue(key, value) {
    const text = typeof value === 'object' ? JSON.stringify(value, null, 2) : String(value)
    try {
        await navigator.clipboard.writeText(text)
    } catch {
        // Clipboard API unavailable (e.g. non-HTTPS) — silently ignore
    }
    copiedCells[key] = true
    setTimeout(() => { delete copiedCells[key] }, 1500)
}

function recordLink(r) {
    const newTrail = [...trail.value, {
        model_class: record.value.model_class,
        key_field: record.value.key_field,
        key_value: record.value.key_value,
        display: `${record.value.short_name}#${record.value.key_value}`,
    }]
    const params = new URLSearchParams({
        field: r.key_field,
        value: r.key_value,
        trail: JSON.stringify(newTrail),
    })
    return `/models/${encodeModel(r.model_class)}/record?${params}`
}

function trailLink(index) {
    const entry = trail.value[index]
    const truncatedTrail = trail.value.slice(0, index)
    const params = new URLSearchParams({ field: entry.key_field, value: entry.key_value })
    if (truncatedTrail.length) {
        params.set('trail', JSON.stringify(truncatedTrail))
    }
    return `/models/${encodeModel(entry.model_class)}/record?${params}`
}

function isModelResult(value) {
    return value && (value.type === 'one' || value.type === 'many')
}

function manyRelationColumns(records) {
    if (!records.length) return []
    return Object.keys(records[0].attributes)
}

const RELATION_COLORS = {
    HasOne:         'badge-info',
    HasMany:        'badge-primary',
    HasOneThrough:  'badge-info',
    HasManyThrough: 'badge-primary',
    BelongsTo:      'badge-secondary',
    BelongsToMany:  'badge-accent',
    MorphTo:        'badge-warning',
    MorphOne:       'badge-warning',
    MorphMany:      'badge-warning',
    MorphToMany:    'badge-error',
    MorphedByMany:  'badge-error',
}

function relationColor(fqcn) {
    return RELATION_COLORS[shortName(fqcn)] ?? 'badge-ghost'
}

// ── Trail (breadcrumb navigation history) ────────────────────────────────────

const trail = computed(() => {
    try {
        return JSON.parse(route.query.trail || '[]')
    } catch {
        return []
    }
})

// ── Available lookup fields ───────────────────────────────────────────────────

const availableLookupFields = computed(() => {
    const attrs = modelStructure.value?.attributes ?? []
    const fields = attrs
        .filter(a => !a.virtual && (a.primary === true || a.unique === true))
        .map(a => a.name)
    if (!fields.length && modelStructure.value) {
        const pk = attrs.find(a => a.primary === true)
        if (pk) fields.push(pk.name)
    }
    return fields
})

const accessorAttributes = computed(() => {
    return (modelStructure.value?.attributes ?? [])
        .filter(a => a.virtual || a.snippet !== null)
})

const allAccessorsLoading = computed(() =>
    accessorAttributes.value.some(a => accessorData[a.name]?.loading)
)

// ── Filtered attributes ───────────────────────────────────────────────────────

const filteredAttributes = computed(() => {
    const entries = Object.entries(record.value?.attributes ?? {})
    if (!attributeFilter.value) return entries
    const q = attributeFilter.value.toLowerCase()
    return entries.filter(([name, value]) =>
        name.toLowerCase().includes(q) ||
        formatValue(value).toLowerCase().includes(q)
    )
})

const filteredAccessorAttributes = computed(() => {
    if (!attributeFilter.value) return accessorAttributes.value
    const q = attributeFilter.value.toLowerCase()
    return accessorAttributes.value.filter(attr => {
        if (attr.name.toLowerCase().includes(q)) return true
        const loaded = accessorData[attr.name]
        if (loaded && !loaded.loading && !loaded.error) {
            return formatValue(loaded.value).toLowerCase().includes(q)
        }
        return false
    })
})

// ── API calls ─────────────────────────────────────────────────────────────────

async function loadModelStructure() {
    const slug = route.params.model
    try {
        const res = await fetch(`${window.modelExplorerBasePath}/api/models/${slug}`)
        if (!res.ok) throw new Error(`HTTP ${res.status}`)
        modelStructure.value = await res.json()
        if (!selectedField.value && availableLookupFields.value.length) {
            selectedField.value = availableLookupFields.value[0]
        }
    } catch (e) {
        modelStructureError.value = e.message
    } finally {
        modelStructureLoading.value = false
    }
}

async function submitLookup() {
    searched.value = false
    record.value = null
    recordError.value = null
    recordLoading.value = true
    attributeFilter.value = ''
    for (const key of Object.keys(relationData)) { delete relationData[key] }
    for (const key of Object.keys(accessorData)) { delete accessorData[key] }
    for (const key of Object.keys(expandedCells)) { delete expandedCells[key] }
    for (const key of Object.keys(copiedCells)) { delete copiedCells[key] }

    const slug = route.params.model
    const params = new URLSearchParams({ field: selectedField.value, value: lookupValue.value })
    try {
        const res = await fetch(`${window.modelExplorerBasePath}/api/models/${slug}/record?${params}`)
        if (res.status === 404) {
            searched.value = true
            return
        }
        if (!res.ok) throw new Error(`HTTP ${res.status}`)
        record.value = await res.json()
        const query = { field: selectedField.value, value: lookupValue.value }
        if (route.query.trail) query.trail = route.query.trail
        router.replace({ query })
        searched.value = true
    } catch (e) {
        recordError.value = e.message
    } finally {
        recordLoading.value = false
    }
}

async function loadAccessor(name) {
    accessorData[name] = { loading: true, value: null, error: null }
    const slug = route.params.model
    const params = new URLSearchParams({ record_key: record.value.key_value })
    try {
        const res = await fetch(
            `${window.modelExplorerBasePath}/api/models/${slug}/record/attributes/${name}?${params}`
        )
        if (!res.ok) throw new Error(`HTTP ${res.status}`)
        const data = await res.json()
        accessorData[name] = { loading: false, value: data.value, error: data.error ?? null }
    } catch (e) {
        accessorData[name] = { loading: false, value: null, error: e.message }
    }
}

async function loadAllAccessors() {
    const toLoad = accessorAttributes.value.filter(a => !accessorData[a.name] || accessorData[a.name].error)
    if (!toLoad.length) return

    for (const attr of toLoad) {
        accessorData[attr.name] = { loading: true, value: null, error: null }
    }

    const slug = route.params.model
    const params = new URLSearchParams({ record_key: record.value.key_value })
    for (const attr of toLoad) {
        params.append('names[]', attr.name)
    }

    try {
        const res = await fetch(`${window.modelExplorerBasePath}/api/models/${slug}/record/attributes?${params}`)
        if (!res.ok) throw new Error(`HTTP ${res.status}`)
        const data = await res.json()
        for (const [name, result] of Object.entries(data)) {
            accessorData[name] = { loading: false, value: result.value, error: result.error }
        }
    } catch (e) {
        for (const attr of toLoad) {
            if (accessorData[attr.name]?.loading) {
                accessorData[attr.name] = { loading: false, value: null, error: e.message }
            }
        }
    }
}

async function loadRelation(relationName, page = 1) {
    relationData[relationName] = { loading: true, error: null, data: null }

    const slug = route.params.model
    const params = new URLSearchParams({ record_key: record.value.key_value, page })
    try {
        const res = await fetch(
            `${window.modelExplorerBasePath}/api/models/${slug}/record/relations/${relationName}?${params}`
        )
        if (!res.ok) throw new Error(`HTTP ${res.status}`)
        relationData[relationName] = { loading: false, error: null, data: await res.json() }
    } catch (e) {
        relationData[relationName] = { loading: false, error: e.message, data: null }
    }
}

// ── Init + navigation ─────────────────────────────────────────────────────────

async function init() {
    modelStructure.value = null
    modelStructureLoading.value = true
    modelStructureError.value = null
    record.value = null
    searched.value = false
    recordError.value = null
    attributeFilter.value = ''
    for (const key of Object.keys(relationData)) { delete relationData[key] }
    for (const key of Object.keys(accessorData)) { delete accessorData[key] }
    for (const key of Object.keys(expandedCells)) { delete expandedCells[key] }
    for (const key of Object.keys(copiedCells)) { delete copiedCells[key] }

    selectedField.value = route.query.field || ''
    lookupValue.value = route.query.value || ''

    await loadModelStructure()
    if (route.query.field && route.query.value) {
        await submitLookup()
    }
}

onMounted(init)

// Re-init when navigating to a different model or when query params change externally
// (e.g. drilling into a related record or navigating via breadcrumb trail).
watch(
    () => ({ model: route.params.model, field: route.query.field, value: route.query.value }),
    async ({ model, field, value }, prev) => {
        if (model === prev.model && field === selectedField.value && value === lookupValue.value) return
        await init()
    }
)
</script>
