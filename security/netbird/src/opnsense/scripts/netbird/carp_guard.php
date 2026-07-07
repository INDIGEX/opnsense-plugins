#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2026 Myah Mitchell, Innovative Networks, Inc. d.b.a INDIGEX
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

/*
 * CARP start guard for a single NetBird instance's os-netbird rc.d entry.
 *
 * Usage: carp_guard.php <instance-uuid_safe>
 *
 * Runs as start_postcmd for that instance. After the daemon starts — at
 * boot, after an HA config-sync service restart, or a manual service
 * start — this makes sure a CARP BACKUP node does not keep this instance's
 * tunnel active. No-op if the instance doesn't have CARP support enabled.
 *
 * The MASTER check is a quick ifconfig scan; if the tunnel must be torn
 * down, "netbird down" is spawned in the background so the rc start path
 * (and the boot sequence) is never delayed. Always exits 0: a failing
 * start_postcmd would make run_rc_command report a start failure even
 * though the daemon is running.
 */

require_once('config.inc');
require_once('util.inc');
require_once('plugins.inc.d/netbird.inc');

$uuid_safe = $argv[1] ?? '';
$instance = null;
foreach (netbird_instances(false) as $inst) {
    if ($inst['uuid_safe'] === $uuid_safe) {
        $instance = $inst;
        break;
    }
}

if ($instance === null || !netbird_carp_enabled($instance)) {
    exit(0);
}

if (!netbird_carp_check_master()) {
    log_msg("NetBird: CARP BACKUP node detected after service start, disconnecting instance {$instance['name']}");
    mwexecfb('/usr/local/bin/netbird down --daemon-addr ' . escapeshellarg('unix://' . $instance['socket']));
}

exit(0);
