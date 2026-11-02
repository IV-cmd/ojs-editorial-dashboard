<script setup>
import { inject, onMounted, ref } from 'vue';
import StateBlock from '../components/StateBlock.vue';
import { statusLabel } from '../lib/editorial';

const api = inject('api');
const loading = ref(true);
const error = ref('');
const summary = ref(null);
const meta = ref(null);

onMounted(async () => {
    try {
        const response = await api.summary();
        summary.value = response.data;
        meta.value = response.meta;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <section>
        <h2 class="mb-4 text-lg font-semibold">Summary</h2>
        <StateBlock :loading="loading" :error="error" :empty="summary?.submissions_total === 0" empty-text="No submissions have been ingested yet. Run php artisan ojs:sync.">
            <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded border border-gray-200 bg-white p-4" data-test="card-total">
                    <div class="text-sm text-gray-500">Submissions</div>
                    <div class="text-2xl font-semibold">{{ summary.submissions_total }}</div>
                </div>
                <RouterLink to="/stuck" class="rounded border border-gray-200 bg-white p-4 hover:border-gray-400" data-test="card-stuck">
                    <div class="text-sm text-gray-500">Stuck ({{ meta.stuck_threshold_days }}+ days idle)</div>
                    <div class="text-2xl font-semibold">{{ summary.stuck_count }}</div>
                </RouterLink>
                <RouterLink to="/overdue" class="rounded border border-gray-200 bg-white p-4 hover:border-gray-400" data-test="card-overdue">
                    <div class="text-sm text-gray-500">Overdue reviews</div>
                    <div class="text-2xl font-semibold">{{ summary.overdue_reviews_count }}</div>
                </RouterLink>
            </div>
            <table class="min-w-full divide-y divide-gray-200 bg-white text-sm" data-test="status-table">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium">Status</th>
                        <th class="px-3 py-2 text-left font-medium">Submissions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="row in summary.submissions_by_status" :key="row.status">
                        <td class="px-3 py-2">{{ statusLabel(row.status) }}</td>
                        <td class="px-3 py-2">{{ row.count }}</td>
                    </tr>
                </tbody>
            </table>
            <p class="mt-3 text-xs text-gray-500">As of {{ meta.as_of }} (server time).</p>
        </StateBlock>
    </section>
</template>
