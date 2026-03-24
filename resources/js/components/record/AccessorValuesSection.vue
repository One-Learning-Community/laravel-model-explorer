<template>
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
                        <td
                            class="font-mono text-sm text-base-content/70"
                            :colspan="isModelResult(accessorData[attr.name]?.value) ? 2 : 1"
                        >
                            <template v-if="accessorData[attr.name]">
                                <span v-if="accessorData[attr.name].loading" class="loading loading-spinner loading-xs"></span>
                                <span v-else-if="accessorData[attr.name].error" class="text-error text-xs">
                                    Error: {{ accessorData[attr.name].error }}
                                </span>
                                <RecordResultView
                                    v-else-if="isModelResult(accessorData[attr.name].value)"
                                    :data="accessorData[attr.name].value"
                                    :record-link="recordLink"
                                />
                                <template v-else>
                                    <span v-if="accessorData[attr.name].value === null || accessorData[attr.name].value === undefined" class="text-base-content/30">—</span>
                                    <template v-else>
                                        <pre v-if="expandedCells[attr.name]" class="text-xs whitespace-pre-wrap break-all font-mono">{{ prettyValue(accessorData[attr.name].value) }}</pre>
                                        <span v-else class="block max-w-sm truncate">{{ formatValue(accessorData[attr.name].value) }}</span>
                                        <button
                                            v-if="isLong(accessorData[attr.name].value)"
                                            @click="toggleExpand(attr.name)"
                                            class="btn btn-xs btn-ghost opacity-60 hover:opacity-100 mt-1"
                                        >{{ expandedCells[attr.name] ? 'Less ↑' : 'More ↓' }}</button>
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
                                    @click="copyValue(attr.name, accessorData[attr.name].value)"
                                    class="btn btn-xs btn-ghost opacity-40 hover:opacity-100"
                                    :title="copiedCells[attr.name] ? 'Copied!' : 'Copy value'"
                                >{{ copiedCells[attr.name] ? '✓' : '⎘' }}</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>

<script setup>
import { computed, reactive, watch } from 'vue'
import { formatValue, prettyValue, isLong } from '../../utils/format.js'
import RecordResultView from '../RecordResultView.vue'

const props = defineProps({
    accessorAttributes: { type: Array, required: true },
    modelSlug: { type: String, required: true },
    recordKey: { type: [String, Number], default: null },
    attributeFilter: { type: String, default: '' },
    recordLink: { type: Function, required: true },
})

const accessorData = reactive({})
const expandedCells = reactive({})
const copiedCells = reactive({})

watch(() => props.recordKey, () => {
    for (const key of Object.keys(accessorData)) { delete accessorData[key] }
    for (const key of Object.keys(expandedCells)) { delete expandedCells[key] }
    for (const key of Object.keys(copiedCells)) { delete copiedCells[key] }
})

const allAccessorsLoading = computed(() =>
    props.accessorAttributes.some(a => accessorData[a.name]?.loading)
)

const filteredAccessorAttributes = computed(() => {
    if (!props.attributeFilter) return props.accessorAttributes
    const q = props.attributeFilter.toLowerCase()
    return props.accessorAttributes.filter(attr => {
        if (attr.name.toLowerCase().includes(q)) return true
        const loaded = accessorData[attr.name]
        if (loaded && !loaded.loading && !loaded.error) {
            return formatValue(loaded.value).toLowerCase().includes(q)
        }
        return false
    })
})

function isModelResult(value) {
    return value && (value.type === 'one' || value.type === 'many')
}

function toggleExpand(name) {
    if (expandedCells[name]) {
        delete expandedCells[name]
    } else {
        expandedCells[name] = true
    }
}

async function copyValue(name, value) {
    const text = typeof value === 'object' ? JSON.stringify(value, null, 2) : String(value)
    try {
        await navigator.clipboard.writeText(text)
    } catch {
        // Clipboard API unavailable (e.g. non-HTTPS) — silently ignore
    }
    copiedCells[name] = true
    setTimeout(() => { delete copiedCells[name] }, 1500)
}

async function loadAccessor(name) {
    accessorData[name] = { loading: true, value: null, error: null }
    const params = new URLSearchParams({ record_key: props.recordKey })
    try {
        const res = await fetch(
            `${window.modelExplorerBasePath}/api/models/${props.modelSlug}/record/attributes/${name}?${params}`
        )
        if (!res.ok) throw new Error(`HTTP ${res.status}`)
        const data = await res.json()
        accessorData[name] = { loading: false, value: data.value, error: data.error ?? null }
    } catch (e) {
        accessorData[name] = { loading: false, value: null, error: e.message }
    }
}

async function loadAllAccessors() {
    const toLoad = props.accessorAttributes.filter(a => !accessorData[a.name] || accessorData[a.name].error)
    if (!toLoad.length) return

    for (const attr of toLoad) {
        accessorData[attr.name] = { loading: true, value: null, error: null }
    }

    const params = new URLSearchParams({ record_key: props.recordKey })
    for (const attr of toLoad) {
        params.append('names[]', attr.name)
    }

    try {
        const res = await fetch(`${window.modelExplorerBasePath}/api/models/${props.modelSlug}/record/attributes?${params}`)
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
</script>
