<script setup>
import { computed, ref } from 'vue';
import { daysOverdue, hasReviewerIdentity, sortRows } from '../lib/editorial';
import SortHeader from './SortHeader.vue';

const props = defineProps({
    rows: { type: Array, required: true },
    asOf: { type: String, required: true },
});

const sortKey = ref('days_overdue');
const sortDir = ref('desc');

const showReviewer = computed(() => hasReviewerIdentity(props.rows));
const decorated = computed(() => props.rows.map((row) => ({ ...row, days_overdue: daysOverdue(row.date_due, props.asOf) })));
const sorted = computed(() => sortRows(decorated.value, sortKey.value, sortDir.value));

function sortBy(field) {
    sortDir.value = sortKey.value === field && sortDir.value === 'asc' ? 'desc' : 'asc';
    sortKey.value = field;
}
</script>

<template>
    <table class="min-w-full divide-y divide-gray-200 text-sm" data-test="overdue-table">
        <thead class="bg-gray-50">
            <tr>
                <SortHeader label="Submission (OJS ID)" field="submission_ojs_id" :sort-key="sortKey" :sort-dir="sortDir" @sort="sortBy" />
                <SortHeader label="Review (OJS ID)" field="ojs_id" :sort-key="sortKey" :sort-dir="sortDir" @sort="sortBy" />
                <SortHeader v-if="showReviewer" label="Reviewer ID" field="reviewer_id" :sort-key="sortKey" :sort-dir="sortDir" @sort="sortBy" />
                <SortHeader label="Round" field="round" :sort-key="sortKey" :sort-dir="sortDir" @sort="sortBy" />
                <SortHeader label="Due" field="date_due" :sort-key="sortKey" :sort-dir="sortDir" @sort="sortBy" />
                <SortHeader label="Days overdue" field="days_overdue" :sort-key="sortKey" :sort-dir="sortDir" @sort="sortBy" />
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <tr v-for="row in sorted" :key="row.id" data-test="overdue-row">
                <td class="px-3 py-2">{{ row.submission_ojs_id }}</td>
                <td class="px-3 py-2">{{ row.ojs_id }}</td>
                <td v-if="showReviewer" class="px-3 py-2" data-test="reviewer-cell">{{ row.reviewer_id ?? '—' }}</td>
                <td class="px-3 py-2">{{ row.round }}</td>
                <td class="px-3 py-2">{{ row.date_due?.slice(0, 10) }}</td>
                <td class="px-3 py-2">{{ row.days_overdue }}</td>
            </tr>
        </tbody>
    </table>
</template>
