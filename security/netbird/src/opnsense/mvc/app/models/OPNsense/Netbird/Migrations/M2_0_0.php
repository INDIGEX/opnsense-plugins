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

namespace OPNsense\Netbird\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;

class M2_0_0 extends BaseModelMigration
{
    /**
     * Pre-2.0.0 NetBird supported a single, implicit instance under
     * general/firewall/ssh/dns/routing/postquantum, plus a separate
     * Authentication model. Convert both into the first entry of the new
     * "instance" array so existing installs keep exactly one instance
     * ("default") with all of their previous settings intact.
     * @param $model
     */
    public function run($model)
    {
        $config = Config::getInstance()->object();

        if (empty($config->OPNsense->netbird->settings)) {
            return;
        }

        $old = $config->OPNsense->netbird->settings;
        $auth = $config->OPNsense->netbird->authentication;

        $node = $model->instance->Add();
        $node->setNodes([
            'enabled' => (string)$old->general->enable == '1' ? '1' : '0',
            'name' => 'default',
            'enablecarp' => (string)$old->general->enablecarp,
            'wireguardInterface' => !empty($old->general->wireguardInterface)
                ? (string)$old->general->wireguardInterface : 'wt0',
            'wireguardPort' => (string)$old->general->wireguardPort,
            'ipmapping' => (string)$old->general->ipmapping,
            'managementUrl' => !empty($auth->managementUrl)
                ? (string)$auth->managementUrl : 'https://api.netbird.io',
            'setupKey' => (string)$auth->setupKey,
            'firewallAllowConfig' => (string)$old->firewall->allowConfig,
            'firewallBlockInboundConnection' => (string)$old->firewall->blockInboundConnection,
            'sshEnable' => (string)$old->ssh->enable,
            'sshEnableRoot' => (string)$old->ssh->enableRoot,
            'sshEnableSFTP' => (string)$old->ssh->enableSFTP,
            'sshEnableLocalPortForwarding' => (string)$old->ssh->enableLocalPortForwarding,
            'sshEnableRemotePortForwarding' => (string)$old->ssh->enableRemotePortForwarding,
            'sshEnableAuth' => (string)$old->ssh->enableAuth,
            'dnsEnable' => (string)$old->dns->enable,
            'routingAccessLan' => (string)$old->routing->accessLan,
            'routingAcceptClientRoutes' => (string)$old->routing->acceptClientRoutes,
            'routingAcceptServerRoutes' => (string)$old->routing->acceptServerRoutes,
            'enableRosenpass' => (string)$old->postquantum->enableRosenpass,
            'rosenpassPermissive' => (string)$old->postquantum->rosenpassPermissive,
        ]);

        // The old //OPNsense/netbird/authentication tree is left in place
        // (harmless, unused stale data) since the Authentication model
        // that owned it no longer exists to clean it up itself.
    }
}
