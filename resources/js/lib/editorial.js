const DAY_MS = 24 * 60 * 60 * 1000;

// OJS 3.6 submission status codes, read from pkp-lib source and not verified against a live server.
const STATUS_LABELS = { 1: 'Queued', 3: 'Published', 4: 'Declined', 5: 'Scheduled' };

export function statusLabel(status) {
    return STATUS_LABELS[status] ? `${STATUS_LABELS[status]} (${status})` : `Status ${status}`;
}

function utcDay(iso) {
    const date = new Date(iso);
    return Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate());
}

// Whole days from `fromIso` to `asOfIso`, or null when the start date is missing.
export function daysSince(fromIso, asOfIso) {
    if (!fromIso || !asOfIso) {
        return null;
    }
    return Math.floor((new Date(asOfIso) - new Date(fromIso)) / DAY_MS);
}

// Calendar days past the due date, matching the API rule that a review due today is not yet overdue.
export function daysOverdue(dueIso, asOfIso) {
    if (!dueIso || !asOfIso) {
        return null;
    }
    return Math.max(0, Math.round((utcDay(asOfIso) - utcDay(dueIso)) / DAY_MS));
}

// The API omits reviewer_id for roles that may not see it, so the UI asks the payload instead of guessing the role.
export function hasReviewerIdentity(rows) {
    return rows.some((row) => Object.prototype.hasOwnProperty.call(row, 'reviewer_id'));
}

export function filterRows(rows, filters) {
    return rows.filter((row) => Object.entries(filters).every(([key, value]) => value === '' || value === null || row[key] === Number(value)));
}

export function sortRows(rows, key, direction = 'asc') {
    const factor = direction === 'desc' ? -1 : 1;
    return [...rows].sort((a, b) => {
        const left = a[key];
        const right = b[key];
        if (left === right) return 0;
        if (left === null || left === undefined) return 1;
        if (right === null || right === undefined) return -1;
        return (left < right ? -1 : 1) * factor;
    });
}

export function relativeAge(fromIso, nowIso) {
    if (!fromIso || !nowIso) {
        return null;
    }
    const minutes = Math.floor(Math.max(0, new Date(nowIso) - new Date(fromIso)) / 60000);
    if (minutes < 1) return 'just now';
    if (minutes < 60) return `${minutes} min ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 48) return `${hours} h ago`;
    return `${Math.floor(hours / 24)} days ago`;
}

export function syncStatusLine(run, nowIso) {
    if (!run) {
        return 'No sync has run yet.';
    }
    if (run.status === 'running') {
        return `Sync running, started ${relativeAge(run.started_at, nowIso)}.`;
    }

    const failures = Object.values(run.failures ?? {}).reduce((sum, n) => sum + n, 0);
    const outcome =
        run.status === 'success' ? 'OK' : failures > 0 ? `FAILED (${failures} ${failures === 1 ? 'failure' : 'failures'})` : 'FAILED';

    return `Last sync: ${relativeAge(run.finished_at, nowIso)}, ${run.submissions_upserted} submissions, ${run.review_assignments_upserted} review assignments, ${outcome}`;
}
