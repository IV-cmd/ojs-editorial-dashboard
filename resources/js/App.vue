<script setup>
import { inject, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import SyncStatusBar from './components/SyncStatusBar.vue';

const api = inject('api');
const route = useRoute();
const router = useRouter();
const user = ref(null);
let userToken = null;

watch(
    () => route.name,
    async (name) => {
        if (name === 'login' || !name) {
            user.value = null;
            userToken = null;
            return;
        }
        const token = api.getToken();
        if (token !== userToken) {
            userToken = token;
            try {
                user.value = await api.user();
            } catch {
                user.value = null;
            }
        }
    },
    { immediate: true },
);

function signOut() {
    api.clearToken();
    router.push({ name: 'login' });
}
</script>

<template>
    <div class="min-h-screen bg-gray-100 text-gray-900">
        <header v-if="route.name && route.name !== 'login'" class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-5xl items-center gap-6 px-4 py-3 text-sm">
                <span class="font-semibold">OJS Editorial Dashboard</span>
                <nav class="flex gap-4">
                    <RouterLink to="/" active-class="font-semibold underline" exact>Summary</RouterLink>
                    <RouterLink to="/stuck" active-class="font-semibold underline">Stuck submissions</RouterLink>
                    <RouterLink to="/overdue" active-class="font-semibold underline">Overdue reviews</RouterLink>
                </nav>
                <span class="ml-auto text-gray-500" data-test="current-user">
                    <template v-if="user">{{ user.name }} ({{ user.role }})</template>
                </span>
                <button type="button" class="text-gray-600 hover:underline" @click="signOut">Sign out</button>
            </div>
        </header>
        <SyncStatusBar v-if="route.name && route.name !== 'login'" :key="route.fullPath" />
        <main class="mx-auto max-w-5xl px-4 py-6">
            <RouterView />
        </main>
    </div>
</template>
