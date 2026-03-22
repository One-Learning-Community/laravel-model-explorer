<template>
    <div>
        <div class="flex items-center justify-between mb-6 gap-4">
            <h1 class="text-xl font-semibold m-0">Relationship Graph</h1>
            <span v-if="!loading && !error" class="text-xs text-base-content/30">
                {{ nodes.length }} models · {{ edges.length }} relationships
            </span>
        </div>

        <div v-if="error" role="alert" class="alert alert-error text-sm mb-4">{{ error }}</div>
        <div v-else-if="loading" class="text-base-content/50 text-sm">Loading…</div>

        <template v-else>
            <div class="bg-base-200 rounded-xl border border-base-300 overflow-hidden" style="height: 72vh">
                <svg
                    ref="svgEl"
                    class="w-full h-full select-none"
                    :style="{ cursor: panning ? 'grabbing' : 'grab' }"
                    @mousedown="onPanStart"
                    @mousemove="onPanMove"
                    @mouseup="onPanEnd"
                    @mouseleave="onPanEnd"
                    @wheel.prevent="onWheel"
                >
                    <defs>
                        <marker id="arrowhead" markerWidth="8" markerHeight="6" refX="8" refY="3" orient="auto">
                            <polygon points="0 0, 8 3, 0 6" class="fill-base-content/30" />
                        </marker>
                    </defs>

                    <g :transform="`translate(${pan.x},${pan.y}) scale(${zoom})`">
                        <!-- Edges (rendered beneath nodes) -->
                        <g v-for="edge in edges" :key="`${edge.source}__${edge.rel}__${edge.target}`">
                            <path
                                :d="edgePath(edge)"
                                fill="none"
                                class="stroke-base-content/20"
                                stroke-width="1.5"
                                marker-end="url(#arrowhead)"
                            />
                            <text
                                :x="edgeMid(edge).x"
                                :y="edgeMid(edge).y - 5"
                                text-anchor="middle"
                                font-size="9"
                                class="fill-base-content/40 pointer-events-none"
                            >{{ edge.shortType }}</text>
                        </g>

                        <!-- Nodes -->
                        <g
                            v-for="node in nodes"
                            :key="node.id"
                            :transform="`translate(${node.x - NODE_W / 2},${node.y - NODE_H / 2})`"
                            class="cursor-pointer"
                            @click.stop="navigateTo(node)"
                            @mousedown.stop
                        >
                            <rect
                                :width="NODE_W"
                                :height="NODE_H"
                                rx="8"
                                class="fill-base-300 stroke-base-content/20 hover:stroke-primary transition-colors"
                                stroke-width="1.5"
                            />
                            <text
                                :x="NODE_W / 2"
                                :y="NODE_H / 2 - 7"
                                text-anchor="middle"
                                dominant-baseline="middle"
                                font-weight="600"
                                font-size="13"
                                class="fill-base-content pointer-events-none"
                            >{{ node.short_name }}</text>
                            <text
                                :x="NODE_W / 2"
                                :y="NODE_H / 2 + 10"
                                text-anchor="middle"
                                dominant-baseline="middle"
                                font-size="9"
                                font-family="monospace"
                                class="fill-base-content/40 pointer-events-none"
                            >{{ node.table }}</text>
                        </g>
                    </g>
                </svg>
            </div>
            <p class="text-xs text-base-content/30 mt-2 m-0">Scroll to zoom · drag to pan · click a node to open model details</p>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const svgEl = ref(null)

const NODE_W = 160
const NODE_H = 54

// Raw graph data with computed _x/_y positions
const graphData = ref([])
const loading = ref(true)
const error = ref(null)

// Pan / zoom
const pan = ref({ x: 0, y: 0 })
const zoom = ref(1)
let panning = false
let panStart = { x: 0, y: 0 }

// Derived node/edge lists read by the template
const nodes = computed(() =>
    graphData.value.map(m => ({
        id: m.class,
        short_name: m.short_name,
        table: m.table,
        x: m._x,
        y: m._y,
    }))
)

const edges = computed(() => {
    const knownClasses = new Set(graphData.value.map(m => m.class))
    const result = []
    for (const model of graphData.value) {
        for (const rel of model.relations ?? []) {
            if (knownClasses.has(rel.related)) {
                result.push({
                    source: model.class,
                    target: rel.related,
                    rel: rel.name,
                    type: rel.type,
                    shortType: rel.type.split('\\').pop(),
                })
            }
        }
    }
    return result
})

function getNode(id) {
    return graphData.value.find(m => m.class === id)
}

/**
 * Curved quadratic bezier path from source node edge to target node edge,
 * with a slight perpendicular offset to avoid straight overlapping lines.
 */
function edgePath(edge) {
    const s = getNode(edge.source)
    const t = getNode(edge.target)
    if (!s || !t) {
        return ''
    }

    const dx = t._x - s._x
    const dy = t._y - s._y
    const d = Math.sqrt(dx * dx + dy * dy) || 1

    // Control point perpendicular offset (20% of distance)
    const cx = (s._x + t._x) / 2 - (dy / d) * d * 0.15
    const cy = (s._y + t._y) / 2 + (dx / d) * d * 0.15

    // Clip start point to node border
    const sx = s._x + (dx / d) * (NODE_W / 2)
    const sy = s._y + (dy / d) * (NODE_H / 2)

    // Clip end point to node border + arrowhead gap
    const tx = t._x - (dx / d) * (NODE_W / 2 + 9)
    const ty = t._y - (dy / d) * (NODE_H / 2 + 9)

    return `M${sx},${sy} Q${cx},${cy} ${tx},${ty}`
}

function edgeMid(edge) {
    const s = getNode(edge.source)
    const t = getNode(edge.target)
    if (!s || !t) {
        return { x: 0, y: 0 }
    }
    return { x: (s._x + t._x) / 2, y: (s._y + t._y) / 2 }
}

function encodeModel(className) {
    return btoa(className).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '')
}

function navigateTo(node) {
    router.push(`/models/${encodeModel(node.id)}`)
}

// ─── Force-directed layout ────────────────────────────────────────────────────

function computeLayout(models, edgeList) {
    const n = models.length
    if (n === 0) {
        return
    }

    // Seed positions on a circle
    const radius = Math.max(200, n * 70)
    models.forEach((m, i) => {
        m._x = radius * Math.cos((2 * Math.PI * i) / n)
        m._y = radius * Math.sin((2 * Math.PI * i) / n)
        m._vx = 0
        m._vy = 0
    })

    const REPULSE = 9000
    const IDEAL_DIST = 220
    const SPRING = 0.012

    for (let iter = 0; iter < 250; iter++) {
        // Repulsion between every pair of nodes
        for (let i = 0; i < n; i++) {
            for (let j = i + 1; j < n; j++) {
                const dx = models[j]._x - models[i]._x
                const dy = models[j]._y - models[i]._y
                const d2 = dx * dx + dy * dy || 1
                const d = Math.sqrt(d2)
                const f = REPULSE / d2
                const fx = (f * dx) / d
                const fy = (f * dy) / d
                models[i]._vx -= fx
                models[i]._vy -= fy
                models[j]._vx += fx
                models[j]._vy += fy
            }
        }

        // Spring attraction along edges
        for (const edge of edgeList) {
            const s = models.find(m => m.class === edge.source)
            const t = models.find(m => m.class === edge.target)
            if (!s || !t) {
                continue
            }
            const dx = t._x - s._x
            const dy = t._y - s._y
            const d = Math.sqrt(dx * dx + dy * dy) || 1
            const f = (d - IDEAL_DIST) * SPRING
            const fx = (f * dx) / d
            const fy = (f * dy) / d
            s._vx += fx
            s._vy += fy
            t._vx -= fx
            t._vy -= fy
        }

        // Integrate with progressive damping
        const damping = 0.85 - (iter / 250) * 0.3
        for (const m of models) {
            m._x += m._vx * 0.4
            m._y += m._vy * 0.4
            m._vx *= damping
            m._vy *= damping
        }
    }

    // Center on origin so pan starts from the middle of the SVG viewport
    const xs = models.map(m => m._x)
    const ys = models.map(m => m._y)
    const cx = (Math.min(...xs) + Math.max(...xs)) / 2
    const cy = (Math.min(...ys) + Math.max(...ys)) / 2
    for (const m of models) {
        m._x -= cx
        m._y -= cy
    }
}

// ─── Pan / zoom ───────────────────────────────────────────────────────────────

function onPanStart(e) {
    panning = true
    panStart = { x: e.clientX - pan.value.x, y: e.clientY - pan.value.y }
}

function onPanMove(e) {
    if (!panning) {
        return
    }
    pan.value = { x: e.clientX - panStart.x, y: e.clientY - panStart.y }
}

function onPanEnd() {
    panning = false
}

function onWheel(e) {
    const factor = e.deltaY < 0 ? 1.1 : 0.9
    zoom.value = Math.max(0.1, Math.min(5, zoom.value * factor))
}

// ─── Mount ────────────────────────────────────────────────────────────────────

onMounted(async () => {
    try {
        const res = await fetch(`${window.modelExplorerBasePath}/api/graph`)
        if (!res.ok) {
            throw new Error(`HTTP ${res.status}`)
        }
        const models = await res.json()

        // Build a minimal edge list just for layout purposes
        const knownClasses = new Set(models.map(m => m.class))
        const edgeList = []
        for (const model of models) {
            for (const rel of model.relations ?? []) {
                if (knownClasses.has(rel.related)) {
                    edgeList.push({ source: model.class, target: rel.related })
                }
            }
        }

        computeLayout(models, edgeList)
        graphData.value = models

        // Start panned to the SVG centre
        const svg = svgEl.value
        if (svg) {
            pan.value = { x: svg.clientWidth / 2, y: svg.clientHeight / 2 }
        }
    } catch (e) {
        error.value = `Failed to load graph: ${e.message}`
    } finally {
        loading.value = false
    }
})
</script>
