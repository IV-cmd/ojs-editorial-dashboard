import { describe, expect, it } from 'vitest';
import { daysOverdue, daysSince, filterRows, hasReviewerIdentity, sortRows, statusLabel } from '../lib/editorial';

describe('editorial helpers', () => {
    it('counts whole days since a date', () => {
        expect(daysSince('2026-10-13T12:00:00+00:00', '2026-10-27T12:00:00+00:00')).toBe(14);
        expect(daysSince('2026-10-13T12:00:01+00:00', '2026-10-27T12:00:00+00:00')).toBe(13);
        expect(daysSince(null, '2026-10-27T12:00:00+00:00')).toBeNull();
    });

    it('counts calendar days overdue, with a review due today at zero', () => {
        expect(daysOverdue('2026-10-26T23:00:00+00:00', '2026-10-27T01:00:00+00:00')).toBe(1);
        expect(daysOverdue('2026-10-27T00:00:00+00:00', '2026-10-27T23:00:00+00:00')).toBe(0);
        expect(daysOverdue('2026-10-17T09:00:00+00:00', '2026-10-27T05:00:00+00:00')).toBe(10);
        expect(daysOverdue(null, '2026-10-27T05:00:00+00:00')).toBeNull();
    });

    it('detects reviewer identity from the payload, including a present null', () => {
        expect(hasReviewerIdentity([{ id: 1, reviewer_id: null }])).toBe(true);
        expect(hasReviewerIdentity([{ id: 1 }])).toBe(false);
        expect(hasReviewerIdentity([])).toBe(false);
    });

    it('filters on numeric fields and ignores empty filters', () => {
        const rows = [{ context_id: 1, status: 1 }, { context_id: 2, status: 1 }];

        expect(filterRows(rows, { context_id: '2', status: '' })).toEqual([{ context_id: 2, status: 1 }]);
        expect(filterRows(rows, { context_id: '', status: '' })).toHaveLength(2);
    });

    it('sorts both ways and keeps missing values last', () => {
        const rows = [{ v: 2 }, { v: null }, { v: 1 }];

        expect(sortRows(rows, 'v').map((r) => r.v)).toEqual([1, 2, null]);
        expect(sortRows(rows, 'v', 'desc').map((r) => r.v)).toEqual([2, 1, null]);
    });

    it('labels known status codes and falls back to the number', () => {
        expect(statusLabel(1)).toBe('Queued (1)');
        expect(statusLabel(9)).toBe('Status 9');
    });
});
