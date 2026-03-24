<template>
    <section v-if="groupedAttrs.length" id="virtual-attrs" class="mb-8 scroll-mt-16">
        <h2 class="font-semibold uppercase tracking-widest text-base-content/40 mb-3">Virtual Attributes</h2>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Kind</th>
                        <th>Attributes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="group in groupedAttrs" :key="group.source ?? '__model__'">
                        <tr v-if="group.label" class="bg-base-200/40">
                            <td colspan="4" class="py-2">
                                <span class="text-xs font-semibold text-base-content/40 uppercase tracking-wider flex items-center gap-2">
                                    via <span class="badge badge-neutral badge-sm font-mono normal-case tracking-normal" :title="group.source">{{ group.label }}</span>
                                </span>
                            </td>
                        </tr>
                        <tr v-for="attr in group.items" :key="attr.name">
                            <td class="font-medium">
                                {{ attr.name }}
                                <div v-if="attr.snippet?.doc_summary" class="text-xs text-base-content/50 font-mono font-normal mt-0.5">{{ attr.snippet.doc_summary }}</div>
                            </td>
                            <td class="font-mono text-xs text-base-content/50">{{ attr.cast }}</td>
                            <td>
                                <div class="flex gap-1 flex-wrap">
                                    <span v-if="attr.appended" class="badge badge-primary badge-xs">appended</span>
                                    <span v-if="attr.fillable" class="badge badge-success badge-xs">fillable</span>
                                    <span v-if="attr.hidden" class="badge badge-warning badge-xs">hidden</span>
                                </div>
                            </td>
                            <td>
                                <button
                                    v-if="attr.snippet"
                                    class="btn btn-xs btn-ghost font-mono"
                                    @click="emit('view-snippet', attr)"
                                >{ }</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </section>
</template>

<script setup>
defineProps({
    groupedAttrs: { type: Array, required: true },
})

const emit = defineEmits(['view-snippet'])
</script>
