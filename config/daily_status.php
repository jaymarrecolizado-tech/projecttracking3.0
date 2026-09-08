<?php

// Daily status vocabulary — Plan_revision §Phase 1. One source of truth, so the
// validators, the importer, the heartbeat and the reporting maths cannot drift
// apart again. That drift is what hid 19.3% of all status rows (NO_NMS +
// DOWN_SERVER) from every uptime figure and trend chart in the app.

return [
    // Every code the system may persist.
    'codes' => ['UP', 'DOWN', 'NO_NMS', 'DOWN_SERVER', 'NO_DATA'],

    // Codes that represent an actual observation for a day.
    //
    // NO_DATA is deliberately excluded: it is the *absence* of a report (written
    // by statuses:snapshot at 23:00), so counting it would let a non-report look
    // like a report and would pollute the uptime ratio.
    //
    //   uptime = UP / (UP + DOWN + NO_NMS + DOWN_SERVER)
    //
    // If DICT later decides NO_NMS means "not measurable, exclude from SLA",
    // remove it here — every caller follows.
    'observed' => ['UP', 'DOWN', 'NO_NMS', 'DOWN_SERVER'],
];
