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

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;

/**
 * Class StatusController
 * @package OPNsense\Netbird
 */
class StatusController extends ApiMutableModelControllerBase
{
    protected static $internalModelClass = '\OPNsense\Netbird\Status';
    protected static $internalModelName = 'Netbird';

    /**
     * List every configured instance (uuid, name, enabled) for the Status
     * page's instance selector.
     * @return array
     */
    public function instancesAction(): array
    {
        $instances = [];
        foreach ((new \OPNsense\Netbird\Settings())->instance->iterateItems() as $uuid => $node) {
            $instances[] = [
                'uuid' => $uuid,
                'name' => (string)$node->name,
                'enabled' => (string)$node->enabled == '1',
            ];
        }
        return ['instances' => $instances];
    }

    /**
     * @param string $uuid instance uuid
     * @return array
     */
    public function statusAction($uuid): array
    {
        $backend = new Backend();
        $status = json_decode($backend->configdpRun("netbird status-json", [$uuid]), true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($status)) {
            return $status;
        }
        return [];
    }

    /**
     * One row per configured instance for the Status page's Sessions grid.
     * "running" reflects whether the instance's daemon answered at all
     * (distinct from "connected", which is whether its tunnel is up) --
     * a disabled instance is never queried since its daemon isn't running.
     * @return array
     */
    public function searchSessionsAction()
    {
        $backend = new Backend();
        $records = [];

        foreach ((new \OPNsense\Netbird\Settings())->instance->iterateItems() as $uuid => $node) {
            $uuid = (string)$uuid;
            $enabled = (string)$node->enabled === '1';
            $record = [
                'uuid' => $uuid,
                'name' => (string)$node->name,
                'fqdn' => null,
                'netbirdIp' => null,
                'peersTotal' => null,
                'peersConnected' => null,
                'connectedPeerNames' => [],
                'networks' => [],
                'enabled' => $enabled,
                'running' => false,
                'connected' => false,
            ];

            if ($enabled) {
                $status = json_decode($backend->configdpRun('netbird status-json', [$uuid]), true);
                if (is_array($status) && !empty($status)) {
                    $record['running'] = true;
                    $record['connected'] = ($status['management']['connected'] ?? false) === true;
                    $record['fqdn'] = $status['fqdn'] ?? null;
                    $record['netbirdIp'] = $status['netbirdIp'] ?? null;
                    $record['peersTotal'] = $status['peers']['total'] ?? 0;
                    $record['peersConnected'] = $status['peers']['connected'] ?? 0;

                    // "networks" on the top-level status is only what this
                    // instance itself advertises as a routing peer. Networks
                    // reached *through* other peers only appear per-peer, so
                    // fold in every connected peer's networks as well.
                    $networks = $status['networks'] ?? [];
                    foreach ($status['peers']['details'] ?? [] as $peer) {
                        if (strcasecmp((string)($peer['status'] ?? ''), 'connected') === 0) {
                            $record['connectedPeerNames'][] = (string)($peer['fqdn'] ?? $peer['publicKey'] ?? '');
                            foreach ($peer['networks'] ?? [] as $network) {
                                $networks[] = $network;
                            }
                        }
                    }
                    $record['networks'] = array_values(array_unique($networks));
                }
            }

            $records[] = $record;
        }

        return $this->searchRecordsetBase($records);
    }
}
