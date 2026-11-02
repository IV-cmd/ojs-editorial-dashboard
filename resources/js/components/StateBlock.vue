<script setup>
defineProps({
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    empty: { type: Boolean, default: false },
    emptyText: { type: String, default: 'Nothing to show.' },
});
</script>

<template>
    <p v-if="loading" class="py-6 text-sm text-gray-500" data-test="loading">Loading…</p>
    <div v-else-if="error" class="rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800" role="alert" data-test="error">
        {{ error }}
    </div>
    <p v-else-if="empty" class="py-6 text-sm text-gray-500" data-test="empty">{{ emptyText }}</p>
    <slot v-else />
</template>
