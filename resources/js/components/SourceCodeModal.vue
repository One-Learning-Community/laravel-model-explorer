<template>
    <dialog ref="modal" class="modal">
        <div class="modal-box max-w-3xl w-full p-0 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3 border-b border-base-300">
                <div class="flex items-center gap-3">
                    <span class="font-semibold font-mono text-sm">{{ attr?.name }}</span>
                    <span v-if="attr?.snippet" class="text-xs text-base-content/40 font-mono">
                        {{ fileLabel }}
                    </span>
                </div>
                <form method="dialog">
                    <button class="btn btn-sm btn-ghost btn-circle">✕</button>
                </form>
            </div>
            <pre
                class="language-php line-numbers m-0 rounded-none text-sm overflow-x-auto max-h-[70vh]"
                :data-start="attr?.snippet?.start_line ?? 1"
            ><code ref="codeEl" class="language-php">{{ attr?.snippet?.code ?? '' }}</code></pre>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>
</template>

<script setup>
import { computed, nextTick, ref } from 'vue'
import Prism from 'virtual:prismjs'

const modal = ref(null)
const codeEl = ref(null)
const attr = ref(null)

const fileLabel = computed(() => {
    if (!attr.value?.snippet?.file) return ''
    return attr.value.snippet.file.split('/').pop() + ':' + attr.value.snippet.start_line
})

function open(item) {
    attr.value = item
    modal.value?.showModal()
    nextTick(() => {
        if (codeEl.value) {
            Prism.highlightElement(codeEl.value)
        }
    })
}

defineExpose({ open })
</script>
