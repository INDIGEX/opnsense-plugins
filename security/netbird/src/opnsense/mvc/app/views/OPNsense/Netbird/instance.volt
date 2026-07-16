{#
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
 # THIS SOFTWARE IS PROVIDED "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
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
        $("#grid-instance").UIBootgrid({
            search: '/api/netbird/instance/search_instance/',
            get: '/api/netbird/instance/get_instance/',
            set: '/api/netbird/instance/set_instance/',
            add: '/api/netbird/instance/add_instance/',
            del: '/api/netbird/instance/del_instance/',
            toggle: '/api/netbird/instance/toggle_instance/',
        });

        updateServiceControlUI('netbird');
    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#instances" id="tab_instances">{{ lang._('Instances') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="instances" class="tab-pane fade in active">
        <table id="grid-instance" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogInstance">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                    <th data-column-id="wireguardInterface" data-type="string">{{ lang._('WireGuard Interface') }}</th>
                    <th data-column-id="wireguardPort" data-type="string">{{ lang._('Port') }}</th>
                    <th data-column-id="uuid" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody></tbody>
            <tfoot>
                <tr>
                    <td/>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-primary">
                            <span class="fa fa-plus fa-fw"></span>
                        </button>
                        <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default">
                            <span class="fa fa-trash-o fa-fw"></span>
                        </button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{ partial('layout_partials/base_dialog', ['fields': formDialogInstance, 'id': 'DialogInstance', 'label': lang._('Edit Instance')]) }}
