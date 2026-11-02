import { createApp } from 'vue';
import { createApiClient } from './api';
import App from './App.vue';
import { createAppRouter } from './router';

let router;
const api = createApiClient({ onUnauthorized: () => router.push({ name: 'login', query: { reason: 'unauthorized' } }) });
router = createAppRouter(api);

createApp(App).provide('api', api).use(router).mount('#app');
