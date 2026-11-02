<script setup>
import { computed, inject, onMounted, ref } from 'vue';
import { syncStatusLine } from '../lib/editorial';

const api = inject('api');
const loading = ref(true);
const failed = ref(false);
const run = ref(null);
const now = ref(new Date().toISOString());

onMounted(async () => {
    try {
        run.value = (await api.latestSyncRun()).data;
        now.value = new Date().toISOString();
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
});

const line = computed(() => syncStatusLine(run.value, now.value));
const tone = computed(() => (run.value?.status === 'failure' ? 'bg-red-50 text-red-800' : 'bg-gray-50 text-gray-600'));
</script>

<template>
    <div class="border-b border-gray-200 text-xs" :class="tone" data-test="sync-status">
        <div class="mx-auto max-w-5xl px-4 py-1.5">
            <span v-if="loading">Checking last sync…</span>
            <span v-else-if="failed">Last sync status is unavailable.</span>
            <template v-else>
                <span data-test="sync-line">{{ line }}</span>
                <span v-if="run?.error_excerpt" class="ml-2 text-red-700" data-test="sync-error">{{ run.error_excerpt }}</span>
            </template>
        </div>
    </div>
</template>
