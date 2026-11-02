<script setup>
import { computed, ref } from 'vue';
import { daysSince, sortRows, statusLabel } from '../lib/editorial';
import SortHeader from './SortHeader.vue';

const props = defineProps({
    rows: { type: Array, required: true },
    asOf: { type: String, required: true },
});

const sortKey = ref('days_idle');
const sortDir = ref('desc');

const decorated = computed(() => props.rows.map((row) => ({ ...row, days_idle: daysSince(row.date_last_activity, props.asOf) })));
const sorted = computed(() => sortRows(decorated.value, sortKey.value, sortDir.value));

function sortBy(field) {
    sortDir.value = sortKey.value === field && sortDir.value === 'asc' ? 'desc' : 'asc';
    sortKey.value = field;
}
</script>

<template>
    <table class="min-w-full divide-y divide-gray-200 text-sm" data-test="stuck-table">
        <thead class="bg-gray-50">
            <tr>
                <SortHeader label="OJS ID" field="ojs_id" :sort-key="sortKey" :sort-dir="sortDir" @sort="sortBy" />
                <SortHeader label="Context" field="context_id" :sort-key="sortKey" :sort-dir="sortDir" @sort="sortBy" />
                <SortHeader label="Status" field="status" :sort-key="sortKey" :sort-dir="sortDir" @sort="sortBy" />
                <SortHeader label="Days since last activity" field="days_idle" :sort-key="sortKey" :sort-dir="sortDir" @sort="sortBy" />
                <SortHeader label="Reviews" field="review_assignments_count" :sort-key="sortKey" :sort-dir="sortDir" @sort="sortBy" />
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <tr v-for="row in sorted" :key="row.id" data-test="stuck-row">
                <td class="px-3 py-2">{{ row.ojs_id }}</td>
                <td class="px-3 py-2">{{ row.context_id }}</td>
                <td class="px-3 py-2">{{ statusLabel(row.status) }}</td>
                <td class="px-3 py-2">{{ row.days_idle }}</td>
                <td class="px-3 py-2">{{ row.review_assignments_count }}</td>
            </tr>
        </tbody>
    </table>
</template>
