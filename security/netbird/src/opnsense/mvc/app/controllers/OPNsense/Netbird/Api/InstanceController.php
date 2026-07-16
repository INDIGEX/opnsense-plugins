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

namespace OPNsense\Netbird\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;

/**
 * Grid CRUD for NetBird instances.
 * @package OPNsense\Netbird
 */
class InstanceController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'settings';
    protected static $internalModelClass = '\OPNsense\Netbird\Settings';

    public function searchInstanceAction()
    {
        return $this->searchBase('instance', ['enabled', 'name', 'wireguardInterface', 'wireguardPort']);
    }

    public function getInstanceAction($uuid = null)
    {
        return $this->getBase('instance', 'instance', $uuid);
    }

    public function addInstanceAction()
    {
        $result = $this->addBase('instance', 'instance');
        $this->reloadTemplate();
        return $result;
    }

    public function setInstanceAction($uuid)
    {
        $result = $this->setBase('instance', 'instance', $uuid);
        $this->reloadTemplate();
        return $result;
    }

    public function delInstanceAction($uuid)
    {
        $result = $this->delBase('instance', $uuid);
        $this->reloadTemplate();
        return $result;
    }

    public function toggleInstanceAction($uuid, $enabled = null)
    {
        $result = $this->toggleBase('instance', $uuid, $enabled);
        $this->reloadTemplate();
        return $result;
    }

    /**
     * Regenerate /etc/rc.conf.d/osnetbird from the current model so the
     * "start every configured instance" rc.d dispatch (used by the generic,
     * no-instance-id service actions) reflects each instance's current
     * enabled state immediately, rather than whatever was enabled the last
     * time this template happened to be regenerated.
     */
    private function reloadTemplate()
    {
        (new Backend())->configdRun('template reload OPNsense/Netbird');
    }
}
