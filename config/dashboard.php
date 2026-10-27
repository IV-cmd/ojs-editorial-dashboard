<?php

return [
    // A submission still in the workflow with no recorded activity for this many days counts as stuck.
    // 14 is a starting default, not an OJS rule: journals set their own handling norms, so it is configurable.
    'stuck_days' => (int) env('DASHBOARD_STUCK_DAYS', 14),

    'per_page' => (int) env('DASHBOARD_PER_PAGE', 25),
    'max_per_page' => 100,
];
