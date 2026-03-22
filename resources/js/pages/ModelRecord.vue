<template>
    <div>
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
                <div class="flex items-center gap-3 mb-6">
                    <h2 class="text-lg font-semibold m-0">
                        {{ record.short_name }}
                        <span class="text-base-content/50 font-mono">#{{ record.key_value }}</span>
                    </h2>
                </div>

                <!-- Raw column attributes -->
                <section class="mb-8">
                    <h3 class="text-xs font-semibold uppercase tracking-widest text-base-content/40 mb-3">Attributes</h3>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Value (raw)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="[name, value] in Object.entries(record.attributes)" :key="name">
                                    <td class="font-mono text-sm font-medium">{{ name }}</td>
                                    <td class="font-mono text-sm text-base-content/70 max-w-md truncate">
                                        <span v-if="formatValue(value) === '—'" class="text-base-content/30">—</span>
                                        <span v-else>{{ formatValue(value) }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Accessor values (lazy) -->
                <section v-if="accessorAttributes.length" class="mb-8">
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
                                    <th>Name</th>
                                    <th>Value</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="attr in accessorAttributes" :key="attr.name">
                                    <td class="font-mono text-sm font-medium">
                                        {{ attr.name }}
                                        <span v-if="attr.virtual" class="badge badge-ghost badge-xs ml-1">virtual</span>
                                    </td>
                                    <td class="font-mono text-sm text-base-content/70 max-w-md">
                                        <template v-if="accessorData[attr.name]">
                                            <span v-if="accessorData[attr.name].loading" class="loading loading-spinner loading-xs"></span>
                                            <span v-else-if="accessorData[attr.name].error" class="text-error text-xs">
                                                Error: {{ accessorData[attr.name].error }}
                                            </span>
                                            <span v-else class="truncate block max-w-md">
                                                <span v-if="formatValue(accessorData[attr.name].value) === '—'" class="text-base-content/30">—</span>
                                                <span v-else>{{ formatValue(accessorData[attr.name].value) }}</span>
                                            </span>
                                        </template>
                                        <span v-else class="text-base-content/20 text-xs">not loaded</span>
                                    </td>
                                    <td>
                                        <button
                                            v-if="!accessorData[attr.name] || accessorData[attr.name].error"
                                            @click="loadAccessor(attr.name)"
                                            class="btn btn-xs btn-ghost"
                                            :disabled="accessorData[attr.name]?.loading"
                                        >Load</button>
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
                                                                <td class="font-mono text-base-content/70">
                                                                    <span v-if="formatValue(value) === '—'" class="text-base-content/30">—</span>
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
                                                                    class="font-mono text-xs text-base-content/70"
                                                                >
                                                                    <span v-if="formatValue(r.attributes[col]) === '—'" class="text-base-content/30">—</span>
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

// ── Helpers ──────────────────────────────────────────────────────────────────

function encodeModel(className) {
    return btoa(className).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '')
}

function shortName(fqcn) {
    return fqcn.split('\\').pop()
}

function formatValue(value) {
    if (value === null || value === undefined) return '—'
    if (typeof value === 'boolean') return value ? 'true' : 'false'
    if (typeof value === 'object') return JSON.stringify(value)
    return String(value)
}

function recordLink(r) {
    return `/models/${encodeModel(r.model_class)}/record?field=${r.key_field}&value=${r.key_value}`
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

// Attributes that have accessor methods: virtual (appended) attrs, or DB columns
// with a known accessor override (snippet present). These are loaded lazily to
// avoid triggering potential side effects automatically.
const accessorAttributes = computed(() => {
    return (modelStructure.value?.attributes ?? [])
        .filter(a => a.virtual || a.snippet !== null)
})

const allAccessorsLoading = computed(() =>
    accessorAttributes.value.some(a => accessorData[a.name]?.loading)
)

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
    for (const key of Object.keys(relationData)) { delete relationData[key] }
    for (const key of Object.keys(accessorData)) { delete accessorData[key] }

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
        router.replace({ query: { field: selectedField.value, value: lookupValue.value } })
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
    for (const key of Object.keys(relationData)) { delete relationData[key] }
    for (const key of Object.keys(accessorData)) { delete accessorData[key] }

    selectedField.value = route.query.field || ''
    lookupValue.value = route.query.value || ''

    await loadModelStructure()
    if (route.query.field && route.query.value) {
        await submitLookup()
    }
}

onMounted(init)

// Re-init when navigating to a different model. Also handles same-model navigation
// (e.g. drilling into a related record of the same type) by detecting when the
// query params changed externally rather than from our own router.replace call.
watch(
    () => ({ model: route.params.model, field: route.query.field, value: route.query.value }),
    async ({ model, field, value }, prev) => {
        if (model === prev.model && field === selectedField.value && value === lookupValue.value) return
        await init()
    }
)
</script>
