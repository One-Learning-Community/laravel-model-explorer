<template>
    <div>
        <RouterLink to="/" class="link link-hover text-sm text-base-content/50 mb-6 inline-block">
            ← All models
        </RouterLink>

        <div v-if="error" role="alert" class="alert alert-error text-sm">{{ error }}</div>
        <div v-else-if="loading" class="text-base-content/50 text-sm">Loading…</div>

        <template v-else-if="model">
            <div class="flex items-start justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-2xl font-bold m-0 mb-1">{{ model.short_name }}</h1>
                    <span class="font-mono text-xs text-base-content/50">{{ model.class }}</span>
                </div>
                <div class="flex gap-2 items-center pt-1 shrink-0">
                    <span class="badge badge-ghost font-mono">{{ model.table }}</span>
                    <span class="badge" :class="model.uses_timestamps ? 'badge-success' : 'badge-ghost'">
                        {{ model.uses_timestamps ? 'timestamps' : 'no timestamps' }}
                    </span>
                </div>
            </div>

            <!-- Columns -->
            <section class="mb-8">
                <h2 class="text-xs font-semibold uppercase tracking-widest text-base-content/40 mb-3">Columns</h2>
                <div class="overflow-x-auto" v-if="dbColumns.length">
                    <table class="table table-sm">
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
                            <tr v-for="attr in dbColumns" :key="attr.name">
                                <td>
                                    <div class="flex items-center gap-1 flex-wrap">
                                        <span class="font-medium">{{ attr.name }}</span>
                                        <span v-if="attr.primary" class="badge badge-primary badge-xs">PK</span>
                                        <span v-if="attr.increments" class="badge badge-ghost badge-xs">auto</span>
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

            <!-- Virtual attributes / accessors -->
            <section class="mb-8" v-if="virtualAttrs.length">
                <h2 class="text-xs font-semibold uppercase tracking-widest text-base-content/40 mb-3">Virtual Attributes</h2>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Kind</th>
                                <th>Attributes</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="attr in virtualAttrs" :key="attr.name">
                                <td class="font-medium">{{ attr.name }}</td>
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
                                        @click="openSnippet(attr)"
                                    >{ } source</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Traits -->
            <section class="mb-8" v-if="model.traits.length">
                <h2 class="text-xs font-semibold uppercase tracking-widest text-base-content/40 mb-3">Traits</h2>
                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="trait in model.traits"
                        :key="trait"
                        class="badge badge-ghost font-mono"
                        :title="trait"
                    >{{ shortName(trait) }}</span>
                </div>
            </section>

            <!-- Scopes -->
            <section class="mb-8" v-if="model.scopes.length">
                <h2 class="text-xs font-semibold uppercase tracking-widest text-base-content/40 mb-3">Scopes</h2>
                <template v-for="group in groupedScopes" :key="group.source ?? '__model__'">
                    <p v-if="group.label" class="text-xs text-base-content/40 mb-1 flex items-center gap-1">
                        via <span class="badge badge-ghost badge-xs font-mono" :title="group.source">{{ group.label }}</span>
                    </p>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <span v-for="scope in group.items" :key="scope.name" class="badge badge-ghost font-mono">
                            {{ scope.name }}
                        </span>
                    </div>
                </template>
            </section>

            <!-- Relations -->
            <section class="mb-8">
                <h2 class="text-xs font-semibold uppercase tracking-widest text-base-content/40 mb-3">Relations</h2>
                <div class="overflow-x-auto" v-if="groupedRelations.length">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Method</th>
                                <th>Type</th>
                                <th>Related model</th>
                                <th>Foreign key</th>
                                <th>Local key</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="group in groupedRelations" :key="group.source ?? '__model__'">
                                <tr v-if="group.label">
                                    <td colspan="5" class="pt-4 pb-1">
                                        <span class="text-xs text-base-content/40 flex items-center gap-1">
                                            via <span class="badge badge-ghost badge-xs font-mono" :title="group.source">{{ group.label }}</span>
                                        </span>
                                    </td>
                                </tr>
                                <tr v-for="rel in group.items" :key="rel.name">
                                    <td class="font-mono text-sm">{{ rel.name }}</td>
                                    <td><span class="badge badge-primary badge-xs">{{ rel.type }}</span></td>
                                    <td>
                                        <RouterLink
                                            :to="`/models/${encodeModel(rel.related)}`"
                                            class="link link-primary font-medium"
                                            :title="rel.related"
                                        >{{ shortName(rel.related) }}</RouterLink>
                                    </td>
                                    <td class="font-mono text-xs text-base-content/50">{{ rel.foreign_key ?? '—' }}</td>
                                    <td class="font-mono text-xs text-base-content/50">{{ rel.local_key ?? '—' }}</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-sm text-base-content/50">No relationships detected.</p>
            </section>
        </template>
    </div>

    <!-- Accessor source modal -->
    <dialog ref="snippetModal" class="modal">
        <div class="modal-box max-w-3xl w-full p-0 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3 border-b border-base-300">
                <div class="flex items-center gap-3">
                    <span class="font-semibold font-mono text-sm">{{ snippetAttr?.name }}</span>
                    <span v-if="snippetAttr?.snippet" class="text-xs text-base-content/40 font-mono">
                        {{ snippetFileLabel(snippetAttr.snippet) }}
                    </span>
                </div>
                <form method="dialog">
                    <button class="btn btn-sm btn-ghost btn-circle">✕</button>
                </form>
            </div>
            <pre
                class="language-php line-numbers m-0 rounded-none text-sm overflow-x-auto max-h-[70vh]"
                :data-start="snippetAttr?.snippet?.start_line ?? 1"
            ><code ref="codeEl" class="language-php">{{ snippetAttr?.snippet?.code ?? '' }}</code></pre>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>
</template>

<script setup>
import { ref, computed, nextTick, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import Prism from 'virtual:prismjs'

const route = useRoute()
const model = ref(null)
const loading = ref(true)
const error = ref(null)

const snippetModal = ref(null)
const snippetAttr = ref(null)
const codeEl = ref(null)

function openSnippet(attr) {
    snippetAttr.value = attr
    snippetModal.value?.showModal()
    nextTick(() => {
        if (codeEl.value) {
            Prism.highlightElement(codeEl.value)
        }
    })
}

function snippetFileLabel(snippet) {
    if (!snippet?.file) return ''
    return snippet.file.split('/').pop() + ':' + snippet.start_line
}

const dbColumns = computed(() => model.value?.attributes.filter(a => !a.virtual) ?? [])
const virtualAttrs = computed(() => model.value?.attributes.filter(a => a.virtual) ?? [])

function groupBySource(items) {
    const groups = {}
    for (const item of items) {
        const key = item.defined_in ?? ''
        if (!groups[key]) groups[key] = []
        groups[key].push(item)
    }
    return Object.entries(groups).map(([key, members]) => ({
        source: key || null,
        label: key ? shortName(key) : null,
        items: members,
    }))
}

const groupedRelations = computed(() => {
    const groups = groupBySource(model.value?.relations ?? [])
    for (const group of groups) {
        group.items.sort((a, b) => a.name.localeCompare(b.name))
    }
    return groups.sort((a, b) => {
        if (a.source === null) return -1
        if (b.source === null) return 1
        return (a.label ?? '').localeCompare(b.label ?? '')
    })
})
const groupedScopes = computed(() => groupBySource(model.value?.scopes ?? []))

function encodeModel(className) {
    return btoa(className).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '')
}

function shortName(fqcn) {
    return fqcn.split('\\').pop()
}

async function load(slug) {
    loading.value = true
    error.value = null
    model.value = null
    try {
        const res = await fetch(`${window.modelExplorerBasePath}/api/models/${slug}`)
        if (res.status === 404) throw new Error('Model not found.')
        if (!res.ok) throw new Error(`HTTP ${res.status}`)
        model.value = await res.json()
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

onMounted(() => load(route.params.model))
watch(() => route.params.model, slug => { if (slug) load(slug) })
</script>
