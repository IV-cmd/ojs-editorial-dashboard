<script setup>
import { computed, inject, onMounted, ref } from 'vue';
import StateBlock from '../components/StateBlock.vue';
import StuckTable from '../components/StuckTable.vue';
import { filterRows, statusLabel } from '../lib/editorial';

const api = inject('api');
const loading = ref(true);
const error = ref('');
const rows = ref([]);
const meta = ref(null);
const options = ref({ statuses: [], context_ids: [] });
const contextId = ref('');
const status = ref('');

const visible = computed(() => filterRows(rows.value, { context_id: contextId.value, status: status.value }));

onMounted(async () => {
    try {
        const [stuck, filters] = await Promise.all([api.stuck(), api.filters()]);
        rows.value = stuck.data;
        meta.value = stuck.meta;
        options.value = filters.data;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <section>
        <h2 class="mb-1 text-lg font-semibold">Stuck submissions</h2>
        <p v-if="meta" class="mb-4 text-sm text-gray-500">Queued with no activity for {{ meta.stuck_threshold_days }} or more days, as of {{ meta.as_of }}.</p>
        <StateBlock :loading="loading" :error="error" :empty="rows.length === 0" empty-text="No stuck submissions.">
            <div class="mb-3 flex gap-4 text-sm">
                <label>Context
                    <select v-model="contextId" class="ml-1 rounded border border-gray-300 px-2 py-1" data-test="filter-context">
                        <option value="">All</option>
                        <option v-for="id in options.context_ids" :key="id" :value="String(id)">{{ id }}</option>
                    </select>
                </label>
                <label>Status
                    <select v-model="status" class="ml-1 rounded border border-gray-300 px-2 py-1" data-test="filter-status">
                        <option value="">All</option>
                        <option v-for="code in options.statuses" :key="code" :value="String(code)">{{ statusLabel(code) }}</option>
                    </select>
                </label>
            </div>
            <StateBlock :empty="visible.length === 0" empty-text="No stuck submissions match these filters.">
                <StuckTable :rows="visible" :as-of="meta.as_of" />
            </StateBlock>
            <p v-if="meta.total > rows.length" class="mt-3 text-xs text-gray-500">Showing the first {{ rows.length }} of {{ meta.total }}.</p>
        </StateBlock>
    </section>
</template>
