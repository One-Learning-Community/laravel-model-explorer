<template>
    <section v-if="groupedScopes.length" id="scopes" class="mb-8 scroll-mt-16">
        <h2 class="font-semibold uppercase tracking-widest text-base-content/40 mb-3">Scopes</h2>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Parameters</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="group in groupedScopes" :key="group.source ?? '__model__'">
                        <tr v-if="group.label" class="bg-base-200/40">
                            <td colspan="3" class="py-2">
                                <span class="text-xs font-semibold text-base-content/40 uppercase tracking-wider flex items-center gap-2">
                                    via <span class="badge badge-neutral badge-sm font-mono normal-case tracking-normal" :title="group.source">{{ group.label }}</span>
                                </span>
                            </td>
                        </tr>
                        <tr v-for="scope in group.items" :key="scope.name">
                            <td class="font-mono">
                                {{ scope.name }}
                                <div v-if="scope.description" class="text-xs text-base-content/50 font-sans font-normal mt-0.5">{{ scope.description }}</div>
                            </td>
                            <td class="font-mono text-xs text-base-content/60">
                                <span v-if="!scope.parameters?.length" class="text-base-content/30">—</span>
                                <span v-else>{{ formatScopeParams(scope.parameters) }}</span>
                            </td>
                            <td>
                                <button
                                    v-if="scope.snippet"
                                    class="btn btn-xs btn-ghost font-mono"
                                    @click="emit('view-snippet', scope)"
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
import { shortName } from '../../utils/model.js'

defineProps({
    groupedScopes: { type: Array, required: true },
})

const emit = defineEmits(['view-snippet'])

function formatScopeParams(params) {
    return params.map(p => {
        let s = `$${p.name}`
        if (p.type) s = `${shortName(p.type)} ${s}`
        if (p.has_default) s += ` = ${p.default}`
        return s
    }).join(', ')
}
</script>
