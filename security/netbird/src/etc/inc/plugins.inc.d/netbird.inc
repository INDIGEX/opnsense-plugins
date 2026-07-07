<?php

/*
 * Copyright (C) 2025 Ralph Moser, PJ Monitoring GmbH
 * Copyright (C) 2025 squared GmbH
 * Copyright (C) 2025 Christopher Linn, BackendMedia IT-Services GmbH
 * Copyright (C) 2025 NetBird GmbH
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

/**
 * Master enable switch (global, independent of any individual instance).
 * @return bool
 */
function netbird_enabled()
{
    return !(new \OPNsense\Netbird\Settings())->general->enable->isEmpty();
}

/**
 * Enumerate configured NetBird instances.
 * @param bool $enabled_only only return instances with enabled == 1
 * @return array list of instance descriptors: uuid, uuid_safe (dashes
 *     replaced with underscores, safe for use in shell variable names and
 *     rc.conf.d keys), name, iface, port, carp, managementUrl, setupKey,
 *     socket, pidfile, config (path to this instance's config.json)
 */
function netbird_instances($enabled_only = true)
{
    $instances = [];
    if (!netbird_enabled()) {
        return $instances;
    }

    foreach ((new \OPNsense\Netbird\Settings())->instance->iterateItems() as $uuid => $node) {
        if ($enabled_only && (string)$node->enabled != '1') {
            continue;
        }
        $uuid_safe = str_replace('-', '_', $uuid);
        $instances[] = [
            'uuid' => $uuid,
            'uuid_safe' => $uuid_safe,
            'name' => (string)$node->name,
            'iface' => (string)$node->wireguardInterface,
            'port' => (string)$node->wireguardPort,
            'carp' => (string)$node->enablecarp == '1',
            'managementUrl' => (string)$node->managementUrl,
            'setupKey' => (string)$node->setupKey,
            'socket' => "/var/run/netbird_{$uuid_safe}.sock",
            'pidfile' => "/var/run/netbird_{$uuid_safe}.pid",
            'config' => \OPNsense\Netbird\Settings::configPath($uuid),
        ];
    }

    return $instances;
}

/**
 * Check whether CARP failover support is relevant right now: NetBird is
 * enabled and at least one instance (or, when $instance is given, that
 * specific instance) has CARP support enabled.
 * @param array|null $instance a single instance descriptor from
 *     netbird_instances(), or null to check across all instances
 * @return bool
 */
function netbird_carp_enabled($instance = null)
{
    if (!netbird_enabled()) {
        return false;
    }

    if ($instance !== null) {
        return !empty($instance['carp']);
    }

    foreach (netbird_instances() as $inst) {
        if ($inst['carp']) {
            return true;
        }
    }

    return false;
}

/**
 * Check if the current host is CARP master for any monitored VIP. This is
 * a host-wide probe, independent of any individual NetBird instance; used
 * to guard instances so they cannot start on a BACKUP node.
 * Returns true if we are currently MASTER for any VHID.
 * @return bool
 */
function netbird_carp_check_master()
{
    // Check ifconfig output for CARP MASTER state
    $ifconfig = shell_exec('/sbin/ifconfig');
    if (preg_match('/carp:.*MASTER/', $ifconfig)) {
        return true;
    }

    return false;
}

/**
 * Check whether a network interface currently exists.
 * @param string $iface interface name
 * @return bool
 */
function netbird_iface_exists($iface)
{
    exec('/sbin/ifconfig ' . escapeshellarg($iface) . ' > /dev/null 2>&1', $output, $ret);
    return $ret === 0;
}

/**
 * Check whether a NetBird instance is connected to its management service.
 * @param string $socket path to the instance's daemon socket
 * @return bool
 */
function netbird_connected($socket)
{
    $cmd = '/usr/local/bin/netbird status --json --daemon-addr ' . escapeshellarg('unix://' . $socket) . ' 2>/dev/null';
    $status_json = shell_exec($cmd);
    if (!empty($status_json)) {
        $status = json_decode($status_json, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return ($status['management']['connected'] ?? false) === true;
        }
    }
    return false;
}

/**
 * Destroy a leftover tunnel interface from a previous NetBird run so a
 * fresh one can be created in its place.
 * @param string $iface interface name
 */
function netbird_destroy_orphan_iface($iface)
{
    if (netbird_iface_exists($iface)) {
        log_msg("NetBird: Found orphaned {$iface} interface, destroying");
        mwexecf('/sbin/ifconfig %s destroy', [$iface]);
    }
}

/**
 * Wait for the tunnel interface to appear and reload the packet filter so
 * its rules attach to the freshly created device.  NetBird creates a tun
 * device and renames it, which takes a few seconds; without the reload all
 * traffic on the new interface hits the default block rule.  This is the
 * only place interface polling and the filter reload live — it must never
 * be called from a syshook foreground, only from backgrounded wrappers.
 * @param string $iface interface name
 * @param int $timeout maximum seconds to wait for the interface
 * @return bool true when the interface appeared and the filter was reloaded
 */
function netbird_sync_filter($iface, $timeout = 15)
{
    for ($i = 0; $i < $timeout; $i++) {
        if (netbird_iface_exists($iface)) {
            log_msg("NetBird: {$iface} interface detected, reloading packet filter");
            mwexecfm('/usr/local/sbin/configctl filter reload');
            return true;
        }
        sleep(1);
    }

    log_msg("NetBird: Timeout ({$timeout}s) waiting for {$iface} interface. Packet filter reload skipped.");
    return false;
}

function netbird_devices()
{
    $devices = [];
    foreach (netbird_instances() as $inst) {
        /*
         * Interfaces > Assignments only allows saving an assignment to a
         * device that appears in this "names" list (see core's
         * list_assign_options.php); it does not consult "volatile" at all.
         * Without this, a tunnel device is only listed while its NetBird
         * instance is actually connected, so the assignment page can't be
         * saved on a CARP BACKUP node where it's intentionally kept down.
         * Populate it from our own configuration instead of live interface
         * presence, mirroring how core's own WireGuard plugin
         * (wireguard_devices()) handles the same volatile-interface gap.
         */
        $devices[] = [
            'pattern' => '^' . preg_quote($inst['iface'], '/') . '$',
            'configurable' => false,
            'spoofmac' => false,
            'volatile' => true,
            'type' => 'netbird',
            'names' => [
                $inst['iface'] => ['descr' => sprintf('%s (NetBird - %s)', $inst['iface'], $inst['name'])],
            ],
        ];
    }

    return $devices;
}

function netbird_services()
{
    $services = [];
    foreach (netbird_instances() as $inst) {
        $services[] = [
            'description' => sprintf('%s (%s)', gettext('NetBird'), $inst['name']),
            'configd' => [
                'restart' => ["netbird restart {$inst['uuid']}"],
                'start' => ["netbird start {$inst['uuid']}"],
                'stop' => ["netbird stop {$inst['uuid']}"],
            ],
            'name' => 'netbird',
            'id' => $inst['uuid'],
            'pidfile' => $inst['pidfile'],
        ];
    }

    return $services;
}

function netbird_configure()
{
    return [
        'netbird_sync_config' => ['netbird_configure_do']
    ];
}

function netbird_configure_do($verbose = false)
{
    service_log('Sync NetBird config...', $verbose);
    (new \OPNsense\Netbird\Settings())->syncConfig();
    service_log("done.\n", $verbose);
}
