<template>
    <!-- To-one result -->
    <template v-if="data.type === 'one'">
        <p v-if="!data.record" class="text-sm text-base-content/50 mt-2">No related record.</p>
        <template v-else>
            <div class="overflow-x-auto mt-1">
                <table class="table table-xs">
                    <tbody>
                        <tr
                            v-for="[col, val] in Object.entries(data.record.attributes)"
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
                <RouterLink :to="recordLink(data.record)" class="link link-primary text-xs">
                    View {{ data.record.short_name }} #{{ data.record.key_value }} →
                </RouterLink>
            </div>
        </template>
    </template>

    <!-- To-many result -->
    <template v-else-if="data.type === 'many'">
        <p v-if="!data.records.length" class="text-sm text-base-content/50 mt-2">No records.</p>
        <template v-else>
            <div class="overflow-auto max-h-64 mt-1">
                <table class="table table-xs">
                    <thead>
                        <tr>
                            <th v-for="col in columns" :key="col">{{ col }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in data.records" :key="r.key_value">
                            <td
                                v-for="col in columns"
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
            <div class="flex items-center justify-between mt-2 text-xs text-base-content/50">
                <span v-if="paginate">{{ data.total }} total records</span>
                <span v-else-if="data.total > data.records.length">
                    Showing {{ data.records.length }} of {{ data.total }}
                </span>
                <PaginationControls
                    v-if="paginate && data.last_page"
                    :current-page="data.current_page"
                    :last-page="data.last_page"
                    @paginate="emit('paginate', $event)"
                />
            </div>
        </template>
    </template>
</template>

<script setup>
import { computed } from 'vue'
import { formatValue } from '../utils/format.js'
import PaginationControls from './PaginationControls.vue'

const props = defineProps({
    data: { type: Object, required: true },
    recordLink: { type: Function, required: true },
    paginate: { type: Boolean, default: false },
})

const emit = defineEmits(['paginate'])

const columns = computed(() => {
    if (!props.data.records?.length) return []
    return Object.keys(props.data.records[0].attributes)
})
</script>
