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

/**
 * Grid CRUD for NetBird instances.
 * @package OPNsense\Netbird
 */
class InstanceController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'settings';
    protected static $internalModelClass = '\OPNsense\Netbird\Settings';

    /**
     * List instances for the grid. "status" is a computed, non-model field
     * the grid renders as an icon: 0 = enabled and running (green), 2 =
     * enabled but not running (yellow), 5 = disabled (red).
     * @return array
     */
    public function searchInstanceAction()
    {
        $response = $this->searchBase('instance', ['enabled', 'name', 'wireguardInterface', 'wireguardPort']);

        $svc = new ServiceController();
        foreach ($response['rows'] as &$row) {
            if (empty($row['enabled'])) {
                $row['status'] = 5;
                continue;
            }
            $result = $svc->statusAction($row['uuid']);
            $row['status'] = ($result['status'] ?? '') == 'running' ? 0 : 2;
        }

        return $response;
    }

    public function getInstanceAction($uuid = null)
    {
        return $this->getBase('instance', 'instance', $uuid);
    }

    public function addInstanceAction()
    {
        return $this->addBase('instance', 'instance');
    }

    public function setInstanceAction($uuid)
    {
        return $this->setBase('instance', 'instance', $uuid);
    }

    public function delInstanceAction($uuid)
    {
        return $this->delBase('instance', $uuid);
    }

    public function toggleInstanceAction($uuid, $enabled = null)
    {
        return $this->toggleBase('instance', $uuid, $enabled);
    }
}
