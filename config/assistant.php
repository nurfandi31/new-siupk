<?php

declare(strict_types=1);

return [
    /**
     * Optional default persona slug when widget does not pass ?persona=.
     */
    'default_persona_slug' => (string) env('ASSISTANT_PERSONA_SLUG', ''),

    /**
     * System actor fallback — User row_id used when a tool is invoked without
     * an authenticated user (e.g. cron jobs). 0 → use first superadmin.
     */
    'system_actor_user_id' => (int) env('ASSISTANT_SYSTEM_ACTOR_USER_ID', 0),

    /**
     * Toggle the floating chat widget globally.
     */
    'widget_enabled' => filter_var(env('ASSISTANT_WIDGET_ENABLED', true), FILTER_VALIDATE_BOOL),
];
