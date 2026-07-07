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

class Settings extends BaseModel
{
    /**
     * Path to the config.json NetBird reads for a given instance uuid.
     * @param string $uuid instance uuid
     * @return string
     */
    public static function configPath($uuid)
    {
        return "/var/db/netbird/{$uuid}/config.json";
    }

    /**
     * Sync every configured instance's settings into its own NetBird
     * config.json. An instance whose config.json does not exist yet (e.g.
     * it has never been started, so NetBird has not created its default
     * configuration) is skipped rather than having one fabricated here.
     */
    public function syncConfig()
    {
        foreach ($this->instance->iterateItems() as $uuid => $node) {
            $this->syncInstanceConfig($uuid, $node);
        }
    }

    /**
     * @param string $uuid instance uuid
     * @param \OPNsense\Base\FieldTypes\ArrayField $node instance model node
     */
    private function syncInstanceConfig($uuid, $node)
    {
        $target = self::configPath($uuid);
        if (!is_file($target)) {
            syslog(LOG_NOTICE, "netbird: no config.json yet for instance {$uuid}, skipping sync");
            return;
        }

        $config = json_decode(file_get_contents($target), true);
        if (!is_array($config)) {
            $jsonError = json_last_error_msg();
            syslog(LOG_ERR, "netbird: failed to decode configuration for instance {$uuid}: $jsonError");
            return;
        }

        $config["WgIface"] = $node->wireguardInterface->__toString();
        $config["WgPort"] = (int)$node->wireguardPort->__toString();
        $config["ServerSSHAllowed"] = $node->sshEnable->__toString() == 1;
        $config["IpMapping"] = $node->ipmapping->__toString();
        $config["EnableSSHRoot"] = $node->sshEnableRoot->__toString() == 1;
        $config["EnableSSHSFTP"] = $node->sshEnableSFTP->__toString() == 1;
        $config["EnableSSHLocalPortForwarding"] = $node->sshEnableLocalPortForwarding->__toString() == 1;
        $config["EnableSSHRemotePortForwarding"] = $node->sshEnableRemotePortForwarding->__toString() == 1;
        $config["DisableSSHAuth"] = $node->sshEnableAuth->__toString() != 1;
        $config["DisableFirewall"] = $node->firewallAllowConfig->__toString() != 1;
        $config["BlockInbound"] = $node->firewallBlockInboundConnection->__toString() == 1;
        $config["DisableDNS"] = $node->dnsEnable->__toString() != 1;
        $config["BlockLANAccess"] = $node->routingAccessLan->__toString() != 1;
        $config["DisableClientRoutes"] = $node->routingAcceptClientRoutes->__toString() != 1;
        $config["DisableServerRoutes"] = $node->routingAcceptServerRoutes->__toString() != 1;
        $config["RosenpassEnabled"] = $node->enableRosenpass->__toString() == 1;
        $config["RosenpassPermissive"] = $node->rosenpassPermissive->__toString() == 1;

        $result = file_put_contents($target, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if ($result === false) {
            syslog(LOG_ERR, "netbird: failed to write updated configuration for instance {$uuid} to $target");
        }
    }
}
