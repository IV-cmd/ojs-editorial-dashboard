import { createRouter, createWebHashHistory } from 'vue-router';
import OverdueView from './views/OverdueView.vue';
import StuckView from './views/StuckView.vue';
import SummaryView from './views/SummaryView.vue';
import TokenEntry from './views/TokenEntry.vue';

export function createAppRouter(api, history = createWebHashHistory()) {
    const router = createRouter({
        history,
        routes: [
            { path: '/', name: 'summary', component: SummaryView },
            { path: '/stuck', name: 'stuck', component: StuckView },
            { path: '/overdue', name: 'overdue', component: OverdueView },
            { path: '/login', name: 'login', component: TokenEntry, meta: { public: true } },
        ],
    });

    router.beforeEach((to) => (to.meta.public || api.getToken() ? true : { name: 'login' }));

    return router;
}
