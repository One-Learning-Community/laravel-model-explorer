<template>
    <section v-if="relations.length" class="mb-8">
        <h3 class="text-xs font-semibold uppercase tracking-widest text-base-content/40 mb-3">Relations</h3>
        <div class="flex flex-col gap-3">
            <div
                v-for="rel in relations"
                :key="rel.name"
                class="card card-border bg-base-200"
            >
                <div class="card-body p-4">
                    <div class="flex items-center justify-between gap-4 flex-wrap">
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-medium text-sm">{{ rel.name }}</span>
                            <RelationBadge :type="rel.type" />
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

                    <template v-if="relationData[rel.name] && !relationData[rel.name].loading">
                        <div v-if="relationData[rel.name].error" role="alert" class="alert alert-error text-xs mt-2">
                            {{ relationData[rel.name].error }}
                        </div>
                        <RecordResultView
                            v-else-if="relationData[rel.name].data"
                            :data="relationData[rel.name].data"
                            :record-link="recordLink"
                            :paginate="true"
                            class="mt-3"
                            @paginate="loadRelation(rel.name, $event)"
                        />
                    </template>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { reactive, watch } from 'vue'
import { shortName } from '../../utils/model.js'
import RelationBadge from '../RelationBadge.vue'
import RecordResultView from '../RecordResultView.vue'

const props = defineProps({
    relations: { type: Array, required: true },
    modelSlug: { type: String, required: true },
    recordKey: { type: [String, Number], default: null },
    recordLink: { type: Function, required: true },
})

const relationData = reactive({})

watch(() => props.recordKey, () => {
    for (const key of Object.keys(relationData)) { delete relationData[key] }
})

async function loadRelation(relationName, page = 1) {
    relationData[relationName] = { loading: true, error: null, data: null }

    const params = new URLSearchParams({ record_key: props.recordKey, page })
    try {
        const res = await fetch(
            `${window.modelExplorerBasePath}/api/models/${props.modelSlug}/record/relations/${relationName}?${params}`
        )
        if (!res.ok) throw new Error(`HTTP ${res.status}`)
        relationData[relationName] = { loading: false, error: null, data: await res.json() }
    } catch (e) {
        relationData[relationName] = { loading: false, error: e.message, data: null }
    }
}
</script>
