import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import SyncStatusBar from '../components/SyncStatusBar.vue';
import { relativeAge, syncStatusLine } from '../lib/editorial';

const NOW = '2026-03-15T12:00:00+00:00';
const okRun = {
    status: 'success',
    started_at: '2026-03-15T11:47:58+00:00',
    finished_at: '2026-03-15T11:48:00+00:00',
    submissions_upserted: 5,
    review_assignments_upserted: 3,
    failures: { api_error: 0, validation: 0, db: 0, other: 0 },
};

describe('relativeAge', () => {
    it('rounds down to minutes, hours, then days', () => {
        expect(relativeAge('2026-03-15T11:59:30+00:00', NOW)).toBe('just now');
        expect(relativeAge('2026-03-15T11:48:00+00:00', NOW)).toBe('12 min ago');
        expect(relativeAge('2026-03-14T09:00:00+00:00', NOW)).toBe('27 h ago');
        expect(relativeAge('2026-03-10T12:00:00+00:00', NOW)).toBe('5 days ago');
        expect(relativeAge(null, NOW)).toBeNull();
    });
});

describe('syncStatusLine', () => {
    it('summarises a successful run from its own counts', () => {
        expect(syncStatusLine(okRun, NOW)).toBe('Last sync: 12 min ago, 5 submissions, 3 review assignments, OK');
    });

    it('reports failures with their total', () => {
        const run = { ...okRun, status: 'failure', submissions_upserted: 4, failures: { ...okRun.failures, api_error: 1 } };
        expect(syncStatusLine(run, NOW)).toBe('Last sync: 12 min ago, 4 submissions, 3 review assignments, FAILED (1 failure)');
    });

    it('handles a running run and no run at all', () => {
        expect(syncStatusLine({ ...okRun, status: 'running', finished_at: null }, NOW)).toBe('Sync running, started 12 min ago.');
        expect(syncStatusLine(null, NOW)).toBe('No sync has run yet.');
    });
});

describe('SyncStatusBar', () => {
    const mountBar = async (latestSyncRun) => {
        const wrapper = mount(SyncStatusBar, { global: { provide: { api: { latestSyncRun } } } });
        await flushPromises();
        return wrapper;
    };

    it('renders the line and, when the API includes it, the error excerpt', async () => {
        const run = { ...okRun, status: 'failure', failures: { ...okRun.failures, db: 2 }, error_excerpt: 'SQLSTATE[23000]' };
        const wrapper = await mountBar(vi.fn().mockResolvedValue({ data: run }));

        expect(wrapper.get('[data-test="sync-line"]').text()).toContain('FAILED (2 failures)');
        expect(wrapper.get('[data-test="sync-error"]').text()).toBe('SQLSTATE[23000]');
    });

    it('has no error line when the API omits the excerpt', async () => {
        const wrapper = await mountBar(vi.fn().mockResolvedValue({ data: okRun }));

        expect(wrapper.get('[data-test="sync-line"]').text()).toContain('OK');
        expect(wrapper.find('[data-test="sync-error"]').exists()).toBe(false);
    });

    it('shows the empty and unavailable states', async () => {
        expect((await mountBar(vi.fn().mockResolvedValue({ data: null }))).text()).toContain('No sync has run yet.');
        expect((await mountBar(vi.fn().mockRejectedValue(new Error('500')))).text()).toContain('unavailable');
    });
});
