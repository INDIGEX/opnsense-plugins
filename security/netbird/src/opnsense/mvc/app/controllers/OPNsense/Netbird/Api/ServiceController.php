<?php

/*
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

namespace OPNsense\Netbird\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Netbird\Settings;

/**
 * Class ServiceController
 * @package OPNsense\Netbird
 */
class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\Netbird\Settings';
    protected static $internalServiceEnabled = 'general.enable';
    protected static $internalServiceTemplate = 'OPNsense/Netbird';
    protected static $internalServiceName = 'netbird';

    /**
     * Sync settings into every instance's own config.json before the base
     * class stops/reloads/starts.  Settings changes are normally picked up
     * by a debounced, asynchronous config-changed event; that is too late
     * for a value like the WireGuard interface name, which must already be
     * correct in config.json by the time the service restarts as part of
     * this same request, or the daemon would come back up on the previous
     * interface name.
     * @return array response message
     * @throws \Exception when configd action fails
     * @throws \ReflectionException when model can't be instantiated
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            (new Backend())->configdRun('netbird sync-config');
        }

        return parent::reconfigureAction();
    }

    /**
     * @param string|null $uuid instance uuid, or null to start every
     *     configured instance (falls through to the base implementation,
     *     which os-netbird's rc script itself expands into "every instance")
     * @return array
     */
    public function startAction($uuid = null)
    {
        if (empty($uuid)) {
            return parent::startAction();
        }
        if ($this->request->isPost()) {
            $response = trim((new Backend())->configdpRun('netbird start', [$uuid]));
            return ['response' => $response];
        }
        return ['response' => []];
    }

    /**
     * @param string|null $uuid instance uuid, or null for every instance
     * @return array
     */
    public function stopAction($uuid = null)
    {
        if (empty($uuid)) {
            return parent::stopAction();
        }
        if ($this->request->isPost()) {
            $response = trim((new Backend())->configdpRun('netbird stop', [$uuid]));
            return ['response' => $response];
        }
        return ['response' => []];
    }

    /**
     * @param string|null $uuid instance uuid, or null for every instance
     * @return array
     */
    public function restartAction($uuid = null)
    {
        if (empty($uuid)) {
            return parent::restartAction();
        }
        if ($this->request->isPost()) {
            $response = trim((new Backend())->configdpRun('netbird restart', [$uuid]));
            return ['response' => $response];
        }
        return ['response' => []];
    }

    /**
     * @param string|null $uuid instance uuid, or null for the global
     *     (any instance running) status used by the services widget
     * @return array
     * @throws \Exception when configd action fails
     */
    public function statusAction($uuid = null)
    {
        if (empty($uuid)) {
            return parent::statusAction();
        }

        $response = (new Backend())->configdpRun('netbird status', [$uuid]);
        if (strpos($response, 'not running') !== false) {
            $status = 'stopped';
        } elseif (strpos($response, 'is running') !== false) {
            $status = 'running';
        } else {
            $status = 'unknown';
        }

        return [
            'status' => $status,
            'widget' => [
                'caption_restart' => gettext('Restart'),
                'caption_start' => gettext('Start'),
                'caption_stop' => gettext('Stop'),
            ],
        ];
    }

    /**
     * Bring a single instance's tunnel up (netbird up), passing its
     * configured management URL / setup key for first-time connection.
     * @param string $uuid instance uuid
     * @return array
     * @throws \ReflectionException when model can't be instantiated
     */
    public function connectAction($uuid)
    {
        if ($this->request->isPost()) {
            $mdl = new Settings();
            $node = $mdl->getNodeByReference('instance.' . $uuid);
            if ($node === null) {
                return ['result' => 'not found'];
            }
            $response = (new Backend())->configdpRun('netbird up-setup-key', [
                $uuid,
                $node->managementUrl->getValue(),
                $node->setupKey->getValue(),
            ]);
            return ['result' => trim($response)];
        }
        return ['result' => 'failed'];
    }

    /**
     * Disconnect a single instance's tunnel (netbird down).
     * @param string $uuid instance uuid
     * @return array
     */
    public function disconnectAction($uuid)
    {
        if ($this->request->isPost()) {
            $response = (new Backend())->configdpRun('netbird down', [$uuid]);
            return ['result' => trim($response)];
        }
        return ['result' => 'failed'];
    }
}
