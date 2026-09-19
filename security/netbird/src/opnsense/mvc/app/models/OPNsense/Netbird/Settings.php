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

namespace OPNsense\Netbird;

use OPNsense\Base\BaseModel;
use OPNsense\Core\Config;

class Settings extends BaseModel
{
    /**
     * Copy of the interface name prefixes NetBird never gathers ICE
     * candidates from (DefaultInterfaceBlacklist in the client's
     * profilemanager); keep it in step with the client.  NetBird only
     * supplies these itself when IFaceBlackList is empty, so a list written
     * here has to carry them to keep the tunnel device and other VPN
     * devices excluded even when they do not exist yet at sync time.
     */
    private const DEFAULT_INTERFACE_BLACKLIST = [
        'wt0', 'wt', 'utun', 'tun0', 'zt', 'ZeroTier', 'wg', 'ts',
        'Tailscale', 'tailscale', 'docker', 'veth', 'br-', 'lo',
    ];

    /**
     * Build NetBird's IFaceBlackList so that only the allowed devices are
     * used for ICE candidate gathering.  NetBird matches every entry as a
     * name prefix, which has two consequences handled here: a device
     * already covered by a built-in exclusion needs no entry of its own,
     * and a device whose name is a prefix of an allowed device (igb0 versus
     * an allowed igb0_vlan10) cannot be listed without also excluding the
     * allowed one, so it is left out and reported instead.
     * @param array $allowedDevices devices candidates may be gathered from; empty yields the built-in exclusions only
     * @param array $systemDevices all network devices present on the system
     * @return array 'blacklist' => list of prefixes, 'skipped' => [device => allowed device it would shadow]
     */
    public static function buildInterfaceBlacklist(array $allowedDevices, array $systemDevices): array
    {
        $blacklist = self::DEFAULT_INTERFACE_BLACKLIST;
        $skipped = [];

        if (empty($allowedDevices)) {
            return ['blacklist' => $blacklist, 'skipped' => $skipped];
        }

        foreach ($systemDevices as $device) {
            if (in_array($device, $allowedDevices, true)) {
                continue;
            }
            if (self::defaultBlacklistPrefix($device) !== null) {
                continue;
            }
            foreach ($allowedDevices as $allowedDevice) {
                if (str_starts_with($allowedDevice, $device)) {
                    $skipped[$device] = $allowedDevice;
                    continue 2;
                }
            }
            $blacklist[] = $device;
        }

        return ['blacklist' => $blacklist, 'skipped' => $skipped];
    }

    /**
     * Prefix from NetBird's built-in exclusions that covers the device.
     * @param string $device device name
     * @return string|null matching prefix, null when NetBird can use the device
     */
    private static function defaultBlacklistPrefix(string $device): ?string
    {
        foreach (self::DEFAULT_INTERFACE_BLACKLIST as $prefix) {
            if (str_starts_with($device, $prefix)) {
                return $prefix;
            }
        }

        return null;
    }

    /**
     * Devices behind the interfaces selected for peer connections.  The
     * setting stores logical interface names (wan, opt3) so that it stays
     * valid on both nodes of an HA pair.  Selections that do not map to a
     * device are dropped, as are devices NetBird excludes regardless of
     * this setting (the tunnel itself, WireGuard and tun devices): counting
     * those as allowed would exclude every other device while NetBird
     * still refuses the selected one, leaving no direct connectivity.
     * @return array device names, empty when candidates are not restricted
     */
    private function allowedPeerConnectionDevices(): array
    {
        $devices = [];
        $interfaces = Config::getInstance()->object()->interfaces;
        foreach ($this->general->peerConnectionInterfaces->getValues() as $interface) {
            $device = isset($interfaces->$interface) ? (string)$interfaces->$interface->if : '';
            if ($device === '') {
                continue;
            }
            $prefix = self::defaultBlacklistPrefix($device);
            if ($prefix !== null) {
                syslog(LOG_WARNING, "netbird: {$device} cannot be used for peer connections, NetBird always " .
                    "excludes devices named {$prefix}*");
                continue;
            }
            $devices[] = $device;
        }

        return array_values(array_unique($devices));
    }

    /**
     * @return array names of all network devices currently present
     */
    private function systemDevices(): array
    {
        return preg_split('/\s+/', (string)shell_exec('/sbin/ifconfig -l'), -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * IFaceBlackList for the current settings and the devices present right
     * now.  Without a usable selection, or without a device list to build
     * from, candidates are not restricted rather than guessing.
     * @return array|null list of interface name prefixes, null for no restriction
     */
    private function interfaceBlacklist(): ?array
    {
        $allowedDevices = $this->allowedPeerConnectionDevices();
        if (empty($allowedDevices)) {
            if (!$this->general->peerConnectionInterfaces->isEmpty()) {
                syslog(LOG_WARNING, 'netbird: none of the selected peer connection interfaces is usable, ' .
                    'offering all interfaces');
            }
            return null;
        }

        $systemDevices = $this->systemDevices();
        if (empty($systemDevices)) {
            syslog(LOG_WARNING, 'netbird: unable to list network devices, offering all interfaces');
            return null;
        }

        $result = self::buildInterfaceBlacklist($allowedDevices, $systemDevices);
        foreach ($result['skipped'] as $device => $allowedDevice) {
            syslog(LOG_WARNING, "netbird: cannot exclude {$device} from peer connections, NetBird would " .
                "exclude {$allowedDevice} along with it");
        }

        return $result['blacklist'];
    }

    public function syncConfig($target = '/var/db/netbird/config.json')
    {
        $config = json_decode(file_get_contents($target), true);
        if (!is_array($config)) {
            $jsonError = json_last_error_msg();
            syslog(LOG_ERR, "netbird: failed to decode configuration: $jsonError");
            return;
        }

        $config["WgPort"] = (int)$this->general->wireguardPort->__toString();
        $config["ServerSSHAllowed"] = $this->ssh->enable->__toString() == 1;
        $config["IpMapping"] = $this->general->ipmapping->__toString();
        $config["EnableSSHRoot"] = $this->ssh->enableRoot->__toString() == 1;
        $config["EnableSSHSFTP"] = $this->ssh->enableSFTP->__toString() == 1;
        $config["EnableSSHLocalPortForwarding"] = $this->ssh->enableLocalPortForwarding->__toString() == 1;
        $config["EnableSSHRemotePortForwarding"] = $this->ssh->enableRemotePortForwarding->__toString() == 1;
        $config["DisableSSHAuth"] = $this->ssh->enableAuth->__toString() != 1;
        $config["DisableFirewall"] = $this->firewall->allowConfig->__toString() != 1;
        $config["BlockInbound"] = $this->firewall->blockInboundConnection->__toString() == 1;
        $config["DisableDNS"] = $this->dns->enable->__toString() != 1;
        $config["BlockLANAccess"] = $this->routing->accessLan->__toString() != 1;
        $config["DisableClientRoutes"] = $this->routing->acceptClientRoutes->__toString() != 1;
        $config["DisableServerRoutes"] = $this->routing->acceptServerRoutes->__toString() != 1;
        $config["RosenpassEnabled"] = $this->postquantum->enableRosenpass->__toString() == 1;
        $config["RosenpassPermissive"] = $this->postquantum->rosenpassPermissive->__toString() == 1;

        /* NetBird fills in its own defaults when the list is absent */
        $interfaceBlacklist = $this->interfaceBlacklist();
        if ($interfaceBlacklist === null) {
            unset($config["IFaceBlackList"]);
        } else {
            $config["IFaceBlackList"] = $interfaceBlacklist;
        }

        $result = file_put_contents($target, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if ($result === false) {
            syslog(LOG_ERR, "netbird: failed to write updated configuration to $target");
        }
    }
}
