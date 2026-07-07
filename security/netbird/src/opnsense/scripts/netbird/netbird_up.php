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
 * Wrapper script for "netbird up" that automatically reloads the packet
 * filter after the tunnel interface is created.
 *
 * Usage: netbird_up.php <instance-uuid_safe> [extra netbird-up args...]
 *
 * This script:
 *   1. Resolves the instance's socket/interface from netbird_instances().
 *   2. Checks whether NetBird is already connected (skip reload if so).
 *   3. Destroys an orphaned tunnel interface left over from a previous run.
 *   4. Runs `/usr/local/bin/netbird up` against this instance's daemon
 *      socket, with any arguments passed through by configd
 *      (e.g. -m <url> -k <key>).
 *   5. If NetBird was not already connected, waits for the tunnel interface
 *      and reloads the packet filter via netbird_sync_filter().
 *
 * All configd actions that invoke "netbird up" should point here so that
 * filter reloads happen regardless of the caller (API, CARP, CLI).
 */

require_once("config.inc");
require_once("util.inc");
require_once("plugins.inc.d/netbird.inc");

$instance = netbird_resolve_instance($argv[1] ?? '');
if ($instance === null) {
    log_msg("NetBird: netbird_up.php called with unknown instance '" . ($argv[1] ?? '') . "'");
    exit(1);
}

$iface = $instance['iface'];
$daemon_addr = '--daemon-addr ' . escapeshellarg('unix://' . $instance['socket']);

// Determine the current connection state before bringing the tunnel up.
$was_connected = netbird_connected($instance['socket']);

// If NetBird is not connected but its interface is still present, it is
// orphaned from a previous run — destroy it so NetBird starts from a clean
// slate.  If NetBird is connected, the interface is legitimately in use.
if (!$was_connected) {
    netbird_destroy_orphan_iface($iface);
}

// --- Build and execute the real "netbird up" command ------------------------
// $argv[0] is this script, $argv[1] is the instance id; everything after is
// passed through by configd (e.g. "-m https://mgmt.example.com -k KEY").
$extra_args = array_slice($argv, 2);
$cmd = '/usr/local/bin/netbird up ' . $daemon_addr;
if (!empty($extra_args)) {
    $cmd .= ' ' . implode(' ', array_map('escapeshellarg', $extra_args));
}

// Run netbird up in the background.  It can block indefinitely (e.g.
// waiting for authentication or an unreachable management server), so
// we fork it and proceed to wait for the tunnel interface.
mwexecfm($cmd);

// Only reload when transitioning from disconnected -> connected.  If NetBird
// was already up, the interface already exists and the filter already knows
// about it.
if (!$was_connected) {
    netbird_sync_filter($iface);
}

exit(0);
