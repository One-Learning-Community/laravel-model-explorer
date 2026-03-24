<template>
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
                    <tr v-if="!rows.length">
                        <td colspan="3" class="text-base-content/30 text-xs italic">No matching attributes.</td>
                    </tr>
                    <tr v-for="[name, value] in rows" :key="name" class="align-top">
                        <td class="font-mono text-sm font-medium">{{ name }}</td>
                        <td class="font-mono text-sm text-base-content/70">
                            <span v-if="value === null || value === undefined" class="text-base-content/30">—</span>
                            <template v-else>
                                <pre v-if="expandedCells[name]" class="text-xs whitespace-pre-wrap break-all font-mono">{{ prettyValue(value) }}</pre>
                                <span v-else class="block max-w-sm truncate">{{ formatValue(value) }}</span>
                                <button
                                    v-if="isLong(value)"
                                    @click="toggleExpand(name)"
                                    class="btn btn-xs btn-ghost opacity-60 hover:opacity-100 mt-1"
                                >{{ expandedCells[name] ? 'Less ↑' : 'More ↓' }}</button>
                            </template>
                        </td>
                        <td class="align-top">
                            <button
                                v-if="value !== null && value !== undefined"
                                @click="copyValue(name, value)"
                                class="btn btn-xs btn-ghost opacity-40 hover:opacity-100"
                                :title="copiedCells[name] ? 'Copied!' : 'Copy value'"
                            >{{ copiedCells[name] ? '✓' : '⎘' }}</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>

<script setup>
import { reactive, watch } from 'vue'
import { formatValue, prettyValue, isLong } from '../../utils/format.js'

const props = defineProps({
    rows: { type: Array, required: true },
    recordKey: { type: [String, Number], default: null },
})

const expandedCells = reactive({})
const copiedCells = reactive({})

watch(() => props.recordKey, () => {
    for (const key of Object.keys(expandedCells)) { delete expandedCells[key] }
    for (const key of Object.keys(copiedCells)) { delete copiedCells[key] }
})

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
</script>
