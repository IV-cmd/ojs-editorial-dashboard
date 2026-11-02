<script setup>
import { inject, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const api = inject('api');
const route = useRoute();
const router = useRouter();
const token = ref('');

function save() {
    const value = token.value.trim();
    if (!value) {
        return;
    }
    api.setToken(value);
    router.push({ name: 'summary' });
}
</script>

<template>
    <section class="mx-auto mt-16 max-w-md rounded border border-gray-200 bg-white p-6">
        <h1 class="mb-2 text-lg font-semibold">OJS Editorial Dashboard</h1>
        <p v-if="route.query.reason === 'unauthorized'" class="mb-4 rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900" role="alert" data-test="unauthorized">
            The server rejected your token (401). It may be invalid or revoked. Enter a new one.
        </p>
        <p class="mb-4 text-sm text-gray-600">Paste a Sanctum API token. See the README for how to create one for an editor or a viewer account.</p>
        <form @submit.prevent="save">
            <label for="token" class="mb-1 block text-sm font-medium">API token</label>
            <input id="token" v-model="token" type="password" autocomplete="off" class="mb-4 w-full rounded border border-gray-300 px-3 py-2 text-sm" />
            <button type="submit" class="rounded bg-gray-900 px-4 py-2 text-sm text-white">Open dashboard</button>
        </form>
    </section>
</template>
