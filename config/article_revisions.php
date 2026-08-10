<?php

return [

    /*
     * Retention window for the scheduled `article-revisions:purge` command.
     * Each revision is removed once it is older than this many months, so the
     * history always holds a rolling window of the most recent months —
     * matching the activity log policy.
     */
    'retention_months' => 12,

];
