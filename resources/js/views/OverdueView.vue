<script setup>
import { computed, inject, onMounted, ref } from 'vue';
import OverdueTable from '../components/OverdueTable.vue';
import StateBlock from '../components/StateBlock.vue';
import { hasReviewerIdentity } from '../lib/editorial';

const api = inject('api');
const loading = ref(true);
const error = ref('');
const rows = ref([]);
const meta = ref(null);

const reviewerHidden = computed(() => rows.value.length > 0 && !hasReviewerIdentity(rows.value));

onMounted(async () => {
    try {
        const response = await api.overdueReviews();
        rows.value = response.data;
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
        <h2 class="mb-1 text-lg font-semibold">Overdue reviews</h2>
        <p v-if="meta" class="mb-4 text-sm text-gray-500">Due before today, not completed, and not declined or cancelled, as of {{ meta.as_of }}.</p>
        <StateBlock :loading="loading" :error="error" :empty="rows.length === 0" empty-text="No overdue reviews.">
            <p v-if="reviewerHidden" class="mb-3 text-xs text-gray-500" data-test="reviewer-hidden">Reviewer identities are not included for your role.</p>
            <OverdueTable :rows="rows" :as-of="meta.as_of" />
            <p v-if="meta.total > rows.length" class="mt-3 text-xs text-gray-500">Showing the first {{ rows.length }} of {{ meta.total }}.</p>
        </StateBlock>
    </section>
</template>
