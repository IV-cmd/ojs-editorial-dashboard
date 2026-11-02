import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import { ApiError } from '../api';
import OverdueTable from '../components/OverdueTable.vue';
import StuckTable from '../components/StuckTable.vue';
import OverdueView from '../views/OverdueView.vue';
import StuckView from '../views/StuckView.vue';
import SummaryView from '../views/SummaryView.vue';
import TokenEntry from '../views/TokenEntry.vue';
import editorOverdue from './fixtures/editor-overdue-reviews.json';
import editorStuck from './fixtures/editor-stuck.json';
import editorSummary from './fixtures/editor-summary.json';
import filters from './fixtures/filters.json';
import viewerOverdue from './fixtures/viewer-overdue-reviews.json';

const empty = (meta) => ({ data: [], meta: { ...meta, total: 0 } });

async function mountView(component, api, path = '/') {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/', name: 'summary', component: { template: '<div />' } },
            { path: '/stuck', component: { template: '<div />' } },
            { path: '/overdue', component: { template: '<div />' } },
            { path: '/login', name: 'login', component: { template: '<div />' } },
        ],
    });
    router.push(path);
    await router.isReady();
    const wrapper = mount(component, { global: { provide: { api }, plugins: [router] } });
    await flushPromises();
    return { wrapper, router };
}

describe('tables', () => {
    it('renders one stuck row per API item', () => {
        const wrapper = mount(StuckTable, { props: { rows: editorStuck.data, asOf: editorStuck.meta.as_of } });

        expect(wrapper.findAll('[data-test="stuck-row"]')).toHaveLength(editorStuck.data.length);
        expect(wrapper.text()).toContain(String(editorStuck.data[0].ojs_id));
    });

    it('shows a reviewer column for the editor payload', () => {
        const wrapper = mount(OverdueTable, { props: { rows: editorOverdue.data, asOf: editorOverdue.meta.as_of } });

        expect(wrapper.findAll('[data-test="overdue-row"]')).toHaveLength(editorOverdue.data.length);
        expect(wrapper.text()).toContain('Reviewer ID');
        expect(wrapper.findAll('[data-test="reviewer-cell"]')).toHaveLength(editorOverdue.data.length);
    });

    it('never renders a reviewer column when the payload has no reviewer_id', () => {
        expect(viewerOverdue.data.some((row) => 'reviewer_id' in row)).toBe(false);

        const wrapper = mount(OverdueTable, { props: { rows: viewerOverdue.data, asOf: viewerOverdue.meta.as_of } });

        expect(wrapper.findAll('[data-test="overdue-row"]')).toHaveLength(viewerOverdue.data.length);
        expect(wrapper.text()).not.toContain('Reviewer');
        expect(wrapper.find('[data-test="reviewer-cell"]').exists()).toBe(false);
    });
});

describe('views', () => {
    it('renders summary counts from the API response', async () => {
        const { wrapper } = await mountView(SummaryView, { summary: vi.fn().mockResolvedValue(editorSummary) });

        expect(wrapper.get('[data-test="card-total"]').text()).toContain(String(editorSummary.data.submissions_total));
        expect(wrapper.get('[data-test="card-stuck"]').text()).toContain(String(editorSummary.data.stuck_count));
        expect(wrapper.get('[data-test="card-overdue"]').text()).toContain(String(editorSummary.data.overdue_reviews_count));
    });

    it('builds stuck filters from the filters endpoint and applies them', async () => {
        const { wrapper } = await mountView(StuckView, { stuck: vi.fn().mockResolvedValue(editorStuck), filters: vi.fn().mockResolvedValue(filters) });

        const contextOptions = wrapper.findAll('[data-test="filter-context"] option').map((o) => o.text());
        expect(contextOptions).toEqual(['All', ...filters.data.context_ids.map(String)]);

        await wrapper.get('[data-test="filter-context"]').setValue(String(filters.data.context_ids[0]));
        expect(wrapper.findAll('[data-test="stuck-row"]')).toHaveLength(editorStuck.data.filter((r) => r.context_id === filters.data.context_ids[0]).length);
    });

    it('shows the empty state when there are no overdue reviews', async () => {
        const { wrapper } = await mountView(OverdueView, { overdueReviews: vi.fn().mockResolvedValue(empty(viewerOverdue.meta)) });

        expect(wrapper.get('[data-test="empty"]').text()).toBe('No overdue reviews.');
        expect(wrapper.find('[data-test="overdue-table"]').exists()).toBe(false);
    });

    it('notes hidden reviewer identities for the viewer payload', async () => {
        const { wrapper } = await mountView(OverdueView, { overdueReviews: vi.fn().mockResolvedValue(viewerOverdue) });

        expect(wrapper.find('[data-test="reviewer-hidden"]').exists()).toBe(true);
    });

    it('shows an error banner instead of crashing on a server error', async () => {
        const { wrapper } = await mountView(OverdueView, { overdueReviews: vi.fn().mockRejectedValue(new ApiError(500, 'Request failed with status 500. Server Error')) });

        expect(wrapper.get('[data-test="error"]').text()).toBe('Request failed with status 500. Server Error');
    });

    it('shows a loading state before the response arrives', async () => {
        const { wrapper } = await mountView(SummaryView, { summary: () => new Promise(() => {}) });

        expect(wrapper.find('[data-test="loading"]').exists()).toBe(true);
    });

    it('explains a 401 on the token screen and stores a new token', async () => {
        const api = { setToken: vi.fn() };
        const { wrapper, router } = await mountView(TokenEntry, api, '/login?reason=unauthorized');

        expect(wrapper.find('[data-test="unauthorized"]').exists()).toBe(true);
        await wrapper.get('input').setValue('2|token');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(api.setToken).toHaveBeenCalledWith('2|token');
        expect(router.currentRoute.value.name).toBe('summary');
    });
});
