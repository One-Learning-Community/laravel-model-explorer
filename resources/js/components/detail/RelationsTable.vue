<template>
    <section id="relations" class="mb-8 scroll-mt-16">
        <h2 class="font-semibold uppercase tracking-widest text-base-content/40 mb-3">Relations</h2>
        <div class="overflow-x-auto" v-if="groupedRelations.length">
            <table class="table">
                <thead>
                    <tr>
                        <th>Method</th>
                        <th>Type</th>
                        <th>Related model</th>
                        <th>Foreign key</th>
                        <th>Local key</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="group in groupedRelations" :key="group.source ?? '__model__'">
                        <tr v-if="group.label" class="bg-base-200/40">
                            <td colspan="6" class="py-2">
                                <span class="text-xs font-semibold text-base-content/40 uppercase tracking-wider flex items-center gap-2">
                                    via <span class="badge badge-neutral badge-sm font-mono normal-case tracking-normal" :title="group.source">{{ group.label }}</span>
                                </span>
                            </td>
                        </tr>
                        <tr v-for="rel in group.items" :key="rel.name">
                            <td class="font-mono text-sm">
                                {{ rel.name }}
                                <div v-if="rel.description" class="text-xs text-base-content/50 font-sans font-normal mt-0.5">{{ rel.description }}</div>
                            </td>
                            <td>
                                <RelationBadge :type="rel.type" />
                            </td>
                            <td>
                                <RouterLink
                                    :to="`/models/${encodeModel(rel.related)}`"
                                    class="link link-primary font-medium"
                                    :title="rel.related"
                                >{{ shortName(rel.related) }}</RouterLink>
                            </td>
                            <td class="font-mono text-xs text-base-content/50">{{ rel.foreign_key ?? '—' }}</td>
                            <td class="font-mono text-xs text-base-content/50">{{ rel.local_key ?? '—' }}</td>
                            <td>
                                <button
                                    v-if="rel.snippet"
                                    class="btn btn-xs btn-ghost font-mono"
                                    @click="emit('view-snippet', rel)"
                                >{ }</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <p v-else class="text-sm text-base-content/50">No relationships detected.</p>
    </section>
</template>

<script setup>
import { encodeModel, shortName } from '../../utils/model.js'
import RelationBadge from '../RelationBadge.vue'

defineProps({
    groupedRelations: { type: Array, required: true },
})

const emit = defineEmits(['view-snippet'])
</script>
