<template>
    <div>
        <BreadcrumbTrail
            :trail="trail"
            :current-display="record ? `${record.short_name}#${record.key_value}` : (modelStructure?.short_name ?? '…')"
        />

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

            <div v-if="!availableLookupFields.length" role="alert" class="alert alert-info text-sm mb-8">
                This model has no primary key or unique columns — records cannot be looked up by field.
            </div>
            <RecordLookupForm
                v-else
                :available-fields="availableLookupFields"
                v-model:selected-field="selectedField"
                v-model:lookup-value="lookupValue"
                :loading="recordLoading"
                @submit="submitLookup"
            />

            <div v-if="recordError" role="alert" class="alert alert-error text-sm mb-6">
                {{ recordError }}
            </div>
            <div v-else-if="searched && !record && !recordLoading" role="alert" class="alert alert-warning text-sm mb-6">
                No record found for {{ selectedField }} = {{ lookupValue }}
            </div>

            <template v-if="record">
                <div class="flex items-center gap-3 mb-4">
                    <h2 class="text-lg font-semibold m-0">
                        {{ record.short_name }}
                        <span class="text-base-content/50 font-mono">#{{ record.key_value }}</span>
                    </h2>
                </div>

                <div class="mb-6">
                    <input
                        v-model="attributeFilter"
                        type="search"
                        placeholder="Filter attributes by name or value…"
                        class="input input-sm input-bordered font-mono w-72"
                    />
                </div>

                <AttributesTable :rows="filteredAttributes" :record-key="record.key_value" />

                <AccessorValuesSection
                    :accessor-attributes="accessorAttributes"
                    :model-slug="route.params.model"
                    :record-key="record.key_value"
                    :attribute-filter="attributeFilter"
                    :record-link="recordLink"
                />

                <RelationsSection
                    :relations="modelStructure.relations ?? []"
                    :model-slug="route.params.model"
                    :record-key="record.key_value"
                    :record-link="recordLink"
                />
            </template>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { encodeModel } from '../utils/model.js'
import { formatValue } from '../utils/format.js'
import BreadcrumbTrail from '../components/record/BreadcrumbTrail.vue'
import RecordLookupForm from '../components/record/RecordLookupForm.vue'
import AttributesTable from '../components/record/AttributesTable.vue'
import AccessorValuesSection from '../components/record/AccessorValuesSection.vue'
import RelationsSection from '../components/record/RelationsSection.vue'

const route = useRoute()
const router = useRouter()

const modelStructure = ref(null)
const modelStructureLoading = ref(true)
const modelStructureError = ref(null)

const selectedField = ref(route.query.field || '')
const lookupValue = ref(route.query.value || '')
const record = ref(null)
const recordLoading = ref(false)
const recordError = ref(null)
const searched = ref(false)

const attributeFilter = ref('')

// ── Trail ─────────────────────────────────────────────────────────────────────

const trail = computed(() => {
    try {
        return JSON.parse(route.query.trail || '[]')
    } catch {
        return []
    }
})

// ── Computed ──────────────────────────────────────────────────────────────────

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

const filteredAttributes = computed(() => {
    const entries = Object.entries(record.value?.attributes ?? {})
    if (!attributeFilter.value) return entries
    const q = attributeFilter.value.toLowerCase()
    return entries.filter(([name, value]) =>
        name.toLowerCase().includes(q) ||
        formatValue(value).toLowerCase().includes(q)
    )
})

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

// ── API ───────────────────────────────────────────────────────────────────────

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

// ── Init + navigation ─────────────────────────────────────────────────────────

async function init() {
    modelStructure.value = null
    modelStructureLoading.value = true
    modelStructureError.value = null
    record.value = null
    searched.value = false
    recordError.value = null
    attributeFilter.value = ''

    selectedField.value = route.query.field || ''
    lookupValue.value = route.query.value || ''

    await loadModelStructure()
    if (route.query.field && route.query.value) {
        await submitLookup()
    }
}

onMounted(init)

watch(
    () => ({ model: route.params.model, field: route.query.field, value: route.query.value }),
    async ({ model, field, value }, prev) => {
        if (model === prev.model && field === selectedField.value && value === lookupValue.value) return
        await init()
    }
)
</script>
