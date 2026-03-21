<template>
    <div>
        <RouterLink to="/" class="back">← All models</RouterLink>

        <p v-if="error" class="error">{{ error }}</p>
        <div v-else-if="loading" class="muted">Loading…</div>

        <template v-else-if="model">
            <div class="model-header">
                <div>
                    <h1 class="model-name">{{ model.short_name }}</h1>
                    <span class="model-class">{{ model.class }}</span>
                </div>
                <div class="badges">
                    <span class="badge badge-table">{{ model.table }}</span>
                    <span class="badge badge-neutral" :class="model.uses_timestamps ? 'badge-on' : 'badge-off'">
                        {{ model.uses_timestamps ? 'timestamps' : 'no timestamps' }}
                    </span>
                </div>
            </div>

            <!-- Columns -->
            <section class="section">
                <h2 class="section-title">Columns</h2>
                <table class="data-table" v-if="dbColumns.length">
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
                            <td class="col-name">
                                {{ attr.name }}
                                <span v-if="attr.primary" class="tag tag-blue">PK</span>
                                <span v-if="attr.increments" class="tag tag-gray">auto</span>
                            </td>
                            <td class="mono muted">{{ attr.type }}</td>
                            <td>
                                <span v-if="attr.nullable" class="tag tag-gray">null</span>
                                <span v-else class="tag tag-dim">not null</span>
                            </td>
                            <td>
                                <span v-if="attr.fillable" class="tag tag-green">fillable</span>
                                <span v-if="attr.hidden" class="tag tag-amber">hidden</span>
                                <span v-if="attr.unique" class="tag tag-gray">unique</span>
                            </td>
                            <td class="mono muted">{{ attr.cast ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="muted">No columns found.</p>
            </section>

            <!-- Virtual attributes -->
            <section class="section" v-if="virtualAttrs.length">
                <h2 class="section-title">Virtual Attributes</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Kind</th>
                            <th>Attributes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="attr in virtualAttrs" :key="attr.name">
                            <td class="col-name">{{ attr.name }}</td>
                            <td class="mono muted">{{ attr.cast }}</td>
                            <td>
                                <span v-if="attr.appended" class="tag tag-blue">appended</span>
                                <span v-if="attr.fillable" class="tag tag-green">fillable</span>
                                <span v-if="attr.hidden" class="tag tag-amber">hidden</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- Relations -->
            <section class="section" v-if="model.relations.length">
                <h2 class="section-title">Relations</h2>
                <table class="data-table">
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
                        <tr v-for="rel in model.relations" :key="rel.name">
                            <td class="col-name mono">{{ rel.name }}</td>
                            <td><span class="tag tag-blue">{{ rel.type }}</span></td>
                            <td>
                                <RouterLink
                                    :to="`/models/${encodeModel(rel.related)}`"
                                    class="related-link"
                                >{{ shortName(rel.related) }}</RouterLink>
                                <span class="muted mono small"> {{ rel.related }}</span>
                            </td>
                            <td class="mono muted">{{ rel.foreign_key ?? '—' }}</td>
                            <td class="mono muted">{{ rel.local_key ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>
            <section class="section" v-else>
                <h2 class="section-title">Relations</h2>
                <p class="muted">No relationships detected.</p>
            </section>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const model = ref(null)
const loading = ref(true)
const error = ref(null)

const dbColumns = computed(() => model.value?.attributes.filter(a => !a.virtual) ?? [])
const virtualAttrs = computed(() => model.value?.attributes.filter(a => a.virtual) ?? [])

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

<style scoped>
.back {
    display: inline-block;
    color: #64748b;
    text-decoration: none;
    font-size: 0.875rem;
    margin-bottom: 1.5rem;
}

.back:hover { color: #94a3b8; }

.model-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 2rem;
}

.model-name {
    margin: 0 0 0.25rem;
    font-size: 1.5rem;
    font-weight: 700;
    color: #f1f5f9;
}

.model-class {
    font-size: 0.8125rem;
    color: #64748b;
    font-family: ui-monospace, monospace;
}

.badges {
    display: flex;
    gap: 0.5rem;
    flex-shrink: 0;
    align-items: center;
    padding-top: 0.375rem;
}

.badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.625rem;
    border-radius: 9999px;
    font-weight: 500;
}

.badge-table { background: #1e293b; color: #94a3b8; font-family: ui-monospace, monospace; }
.badge-on { background: #14532d; color: #86efac; }
.badge-off { background: #292524; color: #a8a29e; }

.section {
    margin-bottom: 2rem;
}

.section-title {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #64748b;
    margin: 0 0 0.75rem;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.data-table th {
    text-align: left;
    font-size: 0.6875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #475569;
    padding: 0 0.75rem 0.5rem;
    border-bottom: 1px solid #1e293b;
}

.data-table td {
    padding: 0.5rem 0.75rem;
    border-bottom: 1px solid #1e293b;
    color: #cbd5e1;
    vertical-align: middle;
}

.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: #1e293b40; }

.col-name {
    color: #e2e8f0;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.375rem;
    flex-wrap: wrap;
}

.mono { font-family: ui-monospace, monospace; font-size: 0.8125rem; }
.muted { color: #64748b; }
.small { font-size: 0.75rem; }

.tag {
    display: inline-block;
    font-size: 0.6875rem;
    font-weight: 500;
    padding: 0.125rem 0.4rem;
    border-radius: 4px;
    white-space: nowrap;
}

.tag-blue { background: #1e3a5f; color: #60a5fa; }
.tag-green { background: #14532d; color: #86efac; }
.tag-amber { background: #451a03; color: #fbbf24; }
.tag-gray { background: #1e293b; color: #94a3b8; }
.tag-dim { background: transparent; color: #475569; }

.related-link {
    color: #60a5fa;
    text-decoration: none;
    font-weight: 500;
}

.related-link:hover { text-decoration: underline; }

.error { color: #f87171; font-size: 0.875rem; }
</style>
