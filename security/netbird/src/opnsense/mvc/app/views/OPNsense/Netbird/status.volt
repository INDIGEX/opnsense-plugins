{#
 # Copyright (C) 2025 Ralph Moser, PJ Monitoring GmbH
 # Copyright (C) 2025 squared GmbH
 # Copyright (C) 2025 Christopher Linn, BackendMedia IT-Services GmbH
 # Copyright (C) 2025 NetBird GmbH
 # Copyright (C) 2026 Myah Mitchell, Innovative Networks, Inc. d.b.a INDIGEX
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1.  Redistributions of source code must retain the above copyright notice,
 #     this list of conditions and the following disclaimer.
 #
 # 2.  Redistributions in binary form must reproduce the above copyright notice,
 #     this list of conditions and the following disclaimer in the documentation
 #     and/or other materials provided with the distribution.
 #
 # THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
 # INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 # AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 # AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 # OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 # SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 # INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 # CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 # ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 # POSSIBILITY OF SUCH DAMAGE.
 #}

<script>
    $(document).ready(() => {
        function getElapsedTime(date) {
            if (!(date instanceof Date) || isNaN(date) || date.getMonth() === 0) return "-";

            const now = new Date();
            const diff = now - date;
            if (diff < 1000) return "Now";

            const units = [{
                    label: "day",
                    ms: 86400000
                },
                {
                    label: "hour",
                    ms: 3600000
                },
                {
                    label: "minute",
                    ms: 60000
                },
                {
                    label: "second",
                    ms: 1000
                },
            ];

            const parts = [];
            let remaining = diff;

            for (const {
                    label,
                    ms
                }
                of units) {
                const val = Math.floor(remaining / ms);
                if (val > 0) {
                    parts.push(`${val} ${label}${val > 1 ? "s" : ""}`);
                    remaining %= ms;
                }
                if (parts.length === 2) break;
            }

            return parts.join(", ") + " ago";
        }

        function formatBytes(bytes) {
            const unit = 1024;

            if (bytes < unit) {
                return `${bytes} B`;
            }

            const units = ['Ki', 'Mi', 'Gi', 'Ti', 'Pi', 'Ei'];
            const exp = Math.floor(Math.log(bytes) / Math.log(unit));
            const prefix = units[exp - 1];
            const value = bytes / Math.pow(unit, exp);

            return `${value.toFixed(1)} ${prefix}B`;
        }

        function getPeerConnectionStatus(status) {
            if (!status) return 'No status available.';

            const fmtConn = ({
                    connected,
                    url,
                    error
                }) =>
                connected ? `Connected${url ? ` to ${url}` : ''}` : `Disconnected${error ? `, reason: ${error}` : ''}`;

            const fmtList = (items, fmtFn, fallback) =>
                items?.length ? `\n${items.map(fmtFn).join("\n")}` : fallback;

            const managementStr = fmtConn(status.management || {});
            const signalStr = fmtConn(status.signal || {});

            const interfaceType = status.kernelInterface ? "Kernel" : status.netbirdIp ? "Userspace" : "N/A";
            const interfaceIp = status.netbirdIp || "N/A";

            const relaysStr = fmtList(
                status.relays?.details,
                r => `  [${r.uri}] is ${r.available ? "Available" : "Unavailable"}${r.error ? `, reason: ${r.error}` : ""}`,
                `${status.relays?.available || 0}/${status.relays?.total || 0} Available`
            );

            const dnsStr = fmtList(
                status.dnsServers,
                g => `  [${g.servers?.join(", ") || "N/A"}] for [${g.domains?.join(", ") || "."}] is ${g.enabled ? "Available" : "Unavailable"}${g.error ? `, reason: ${g.error}` : ""}`,
                `${(status.dnsServers || []).filter(g => g.enabled).length}/${(status.dnsServers || []).length} Available`
            );

            const info = {
                "Daemon version": status.daemonVersion,
                "CLI version": status.cliVersion,
                "Management": managementStr,
                "Signal": signalStr,
                "Relays": relaysStr,
                "Nameservers": dnsStr,
                "FQDN": status.fqdn,
                "NetBird IP": interfaceIp,
                "Interface type": interfaceType,
                "Quantum resistance": status.quantumResistance ? `true${status.quantumResistancePermissive ? " (permissive)" : ""}` : "false",
                "Lazy connection": status.lazyConnectionEnabled ? "true" : "false",
                "Networks": status.networks?.join(", ") || "-",
                "Forwarding rules": status.forwardingRules,
                "Peers count": `${status.peers?.connected || 0}/${status.peers?.total || 0} Connected`
            };
            return Object.entries(info).map(([k, v]) => `${k}: ${v}`).join("\n");
        }

        function getPeersDetail(status) {
            const details = status?.peers?.details || [];

            return details.map(peer => {
                const getOrDefault = (val, def = '-') => val ?? def;
                const localIce = getOrDefault(peer.iceCandidateType?.local);
                const remoteIce = getOrDefault(peer.iceCandidateType?.remote);

                const quantumStatus = peer.quantumResistance ?
                    (status.quantumResistance ? 'true' : 'false (connection might not work without a remote permissive mode)') :
                    status.quantumResistance ?
                    (status.quantumResistancePermissive ?
                        "false (remote didn't enable quantum resistance)" :
                        "false (connection won't work without a permissive mode)") :
                    'false';

                const networks = Array.isArray(peer.networks) && peer.networks.length ?
                    peer.networks.sort().join(', ') :
                    '-';

                const lastUpdate = new Date(peer.lastStatusUpdate || 0);
                const handshake = new Date(peer.lastWireguardHandshake || 0);

                const latency = typeof peer.latency === 'number' ?
                    `${(peer.latency / 1_000_000).toFixed(2)} ms` :
                    '-';

                const indent = (line) => `  ${line}`; // 2-space indent

                return [
                    `${peer.fqdn}:`,
                    indent(`NetBird IP: ${peer.netbirdIp}`),
                    indent(`Public key: ${peer.publicKey}`),
                    indent(`Status: ${peer.status}`),
                    indent(`-- detail --`),
                    indent(`Connection type: ${getOrDefault(peer.connectionType)}`),
                    indent(`ICE candidate (Local/Remote): ${localIce}/${remoteIce}`),
                    indent(`ICE candidate endpoints (Local/Remote): ${localIce}/${remoteIce}`),
                    indent(`Relay server address: ${getOrDefault(peer.relayAddress)}`),
                    indent(`Last connection update: ${getElapsedTime(lastUpdate)}`),
                    indent(`Last WireGuard handshake: ${getElapsedTime(handshake)}`),
                    indent(`Transfer status (received/sent): ${formatBytes(peer.transferReceived || 0)}/${formatBytes(peer.transferSent || 0)}`),
                    indent(`Quantum resistance: ${quantumStatus}`),
                    indent(`Networks: ${networks}`),
                    indent(`Latency: ${latency}`)
                ].join('\n');
            }).join('\n\n');
        }

        const renderPreTable = (content, maxHeight = null) => {
            const style = `padding: 10px;${maxHeight ? ` max-height: ${maxHeight}; overflow-y: auto;` : ''}`;
            return `
              <table class="table table-hover table-striped table-condensed">
                <tbody>
                  <tr>
                    <td><pre style="${style}">${content}</pre></td>
                  </tr>
                </tbody>
              </table>
            `;
        };

        function loadInstanceStatus(uuid) {
            ajaxGet(`/api/netbird/status/status/${uuid}`, {}, (data) => {
                const isConnected = data.management?.connected === true;

                $(`#nb-badge-${uuid}`)
                    .removeClass('label-default label-success label-warning')
                    .addClass(isConnected ? 'label-success' : 'label-warning')
                    .text(isConnected ? '{{ lang._("Connected") }}' : '{{ lang._("Disconnected") }}');

                const peerCount = `${data.peers?.connected || 0}/${data.peers?.total || 0}`;
                const netbirdIp = data.netbirdIp || '-';
                const fqdn = data.fqdn || '-';
                $(`#nb-summary-${uuid}`).html(
                    `{{ lang._('FQDN') }}: ${fqdn} &nbsp;|&nbsp; ` +
                    `{{ lang._('NetBird IP') }}: ${netbirdIp} &nbsp;|&nbsp; ` +
                    `{{ lang._('Peers') }}: ${peerCount}`
                );

                $(`#nb-connect-${uuid}`).toggleClass('hidden', isConnected);
                $(`#nb-disconnect-${uuid}`).toggleClass('hidden', !isConnected);

                $(`#nb-connstatus-${uuid}`).html(renderPreTable(getPeerConnectionStatus(data)));

                const details = getPeersDetail(data);
                $(`#nb-peers-container-${uuid}`).toggleClass('hidden', !isConnected);
                $(`#nb-peers-${uuid}`).html(renderPreTable(details, '500px'));
            });
        }

        function loadInstances() {
            ajaxGet('/api/netbird/status/instances', {}, (data) => {
                const instances = data.instances || [];
                const $panels = $('#instanceAccordion');
                $panels.empty();

                if (!instances.length) {
                    $panels.html('<div class="content-box"><div class="col-md-12"><p>{{ lang._("No NetBird instances configured.") }}</p></div></div>');
                    return;
                }

                instances.forEach((inst) => {
                    const collapseId = `nb-collapse-${inst.uuid}`;
                    const panel = `
                        <div class="panel panel-default">
                            <div class="panel-heading" role="tab">
                                <h4 class="panel-title">
                                    <a role="button" data-toggle="collapse" data-parent="#instanceAccordion" href="#${collapseId}">
                                        ${inst.name} &nbsp;
                                        <span id="nb-badge-${inst.uuid}" class="label label-default">{{ lang._('Unknown') }}</span>
                                        ${inst.enabled ? '' : ' <span class="label label-default">{{ lang._("Disabled") }}</span>'}
                                        &nbsp;
                                        <span id="nb-summary-${inst.uuid}" class="text-muted" style="font-size: 0.85em;"></span>
                                    </a>
                                </h4>
                            </div>
                            <div id="${collapseId}" class="panel-collapse collapse" role="tabpanel">
                                <div class="panel-body">
                                    <button class="btn btn-primary btn-xs hidden" id="nb-connect-${inst.uuid}">{{ lang._('Connect') }}</button>
                                    <button class="btn btn-default btn-xs hidden" id="nb-disconnect-${inst.uuid}">{{ lang._('Disconnect') }}</button>
                                    <br><br>
                                    <h2>{{ lang._('Connection Status') }}</h2>
                                    <div class="table-responsive" id="nb-connstatus-${inst.uuid}"></div>
                                    <div class="hidden" id="nb-peers-container-${inst.uuid}">
                                        <h2>{{ lang._('Peers Detail') }}</h2>
                                        <div class="table-responsive" id="nb-peers-${inst.uuid}"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    $panels.append(panel);

                    $(`#nb-connect-${inst.uuid}`).SimpleActionButton({
                        onAction: () => {
                            loadInstanceStatus(inst.uuid);
                        }
                    }).attr('data-endpoint', `/api/netbird/service/connect/${inst.uuid}`);

                    $(`#nb-disconnect-${inst.uuid}`).SimpleActionButton({
                        onAction: () => {
                            loadInstanceStatus(inst.uuid);
                        }
                    }).attr('data-endpoint', `/api/netbird/service/disconnect/${inst.uuid}`);

                    loadInstanceStatus(inst.uuid);
                });
            });
        }

        function loadVersionData() {
            const $packages = $("#packages");

            ajaxGet('/api/core/firmware/info', {}, (data) => {
                const pkgs = data.package?.filter(pkg =>
                    pkg.name?.toLowerCase().includes("netbird")
                ) || [];

                const rows = pkgs.map(pkg => `
                <tr>
                    <td>${pkg.name}</td>
                    <td>${pkg.version}</td>
                    <td>${pkg.comment}</td>
                </tr>
            `).join("");

                const table = `
                <table class="table table-hover table-striped table-condensed">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Version</th>
                            <th>Comment</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows}
                    </tbody>
                </table>
            `;
                $packages.html(table);
            });
        }

        loadInstances();
        loadVersionData();
        updateServiceControlUI('netbird');
    });
</script>
<section class="page-content-main">
    <div class="content-box">
        <div class="panel-group" id="instanceAccordion" role="tablist" aria-multiselectable="true"></div>
    </div>
    <br>
    <div class="content-box">
        <div class="col-md-12">
            <h2>{{ lang._('Package Versions') }}</h2>
            <div class="table-responsive" id="packages"></div>
        </div>
    </div>
</section>
