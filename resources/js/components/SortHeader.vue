<script setup>
defineProps({
    label: { type: String, required: true },
    field: { type: String, required: true },
    sortKey: { type: String, required: true },
    sortDir: { type: String, required: true },
});
defineEmits(['sort']);
</script>

<template>
    <th class="px-3 py-2 text-left font-medium">
        <button type="button" class="hover:underline" @click="$emit('sort', field)">
            {{ label }}<span v-if="sortKey === field">{{ sortDir === 'asc' ? ' ▲' : ' ▼' }}</span>
        </button>
    </th>
</template>
