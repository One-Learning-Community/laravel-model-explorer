<template>
    <section id="columns" class="mb-8 scroll-mt-16">
        <h2 class="font-semibold uppercase tracking-widest text-base-content/40 mb-3">Columns</h2>
        <div class="overflow-x-auto" v-if="columns.length">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Nullable</th>
                        <th>Attributes</th>
                        <th>Cast</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="attr in columns" :key="attr.name">
                        <td>
                            <div class="flex items-center gap-1 flex-wrap">
                                <span class="font-medium">{{ attr.name }}</span>
                                <span v-if="attr.primary" class="badge badge-primary badge-xs">PK</span>
                                <span v-if="attr.increments" class="badge badge-ghost badge-xs">auto</span>
                                <span
                                    v-if="foreignKeyMap[attr.name]"
                                    class="badge badge-secondary badge-xs"
                                    :title="`FK → ${foreignKeyMap[attr.name].related}`"
                                >FK</span>
                            </div>
                        </td>
                        <td class="font-mono text-xs text-base-content/50">{{ attr.type }}</td>
                        <td>
                            <span v-if="attr.nullable" class="badge badge-ghost badge-xs">null</span>
                            <span v-else class="text-xs text-base-content/30">not null</span>
                        </td>
                        <td>
                            <div class="flex gap-1 flex-wrap">
                                <span v-if="attr.fillable" class="badge badge-success badge-xs">fillable</span>
                                <span v-if="attr.hidden" class="badge badge-warning badge-xs">hidden</span>
                                <span v-if="attr.unique" class="badge badge-ghost badge-xs">unique</span>
                            </div>
                        </td>
                        <td class="font-mono text-xs text-base-content/50">{{ attr.cast ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-else class="text-sm text-base-content/50">No columns found.</p>
    </section>
</template>

<script setup>
defineProps({
    columns: { type: Array, required: true },
    foreignKeyMap: { type: Object, default: () => ({}) },
})
</script>
