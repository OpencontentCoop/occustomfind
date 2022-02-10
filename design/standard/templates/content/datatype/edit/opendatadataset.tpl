{ezscript_require(array(
    'ec.min.js',
    'highcharts/highcharts.js',
    'highcharts/highcharts-3d.js',
    'highcharts/highcharts-more.js',
    'highcharts/modules/funnel.js',
    'highcharts/modules/heatmap.js',
    'highcharts/modules/solid-gauge.js',
    'highcharts/modules/treemap.js',
    'highcharts/modules/boost.js',
    'highcharts/modules/exporting.js',
    'highcharts/modules/no-data-to-display.js',
    'handlebars.min.js',
    'jquery.opendatabrowse.js',
    'alpaca.js',
    'fields/Chart.js',
    'fields/RelationBrowse.js',
    'jquery.opendataform.js'
))}
{ezcss_require(array(
    'ec.css',
    'highcharts/highcharts.css'
))}

<div class="mb-4">
    <a href="#" id="edit-definition-{$attribute.id}"
       class="btn btn-sm btn-info mt-1"
       data-create_label="{'Set fields definition'|i18n('opendatadataset')}"
       data-edit_label="{'Edit fields definition'|i18n('opendatadataset')}">
        {if $attribute.has_content}
            {'Edit fields definition'|i18n('opendatadataset')}
        {else}
            {'Set fields definition'|i18n('opendatadataset')}
        {/if}
    </a>
    <a href="#" id="edit-view-{$attribute.id}"
       class="btn btn-sm btn-info mt-1{if $attribute.has_content|not} hide{/if}"
       data-create_label="{'Set views settings'|i18n('opendatadataset')}"
       data-edit_label="{'Edit views settings'|i18n('opendatadataset')}">
        {if $attribute.has_content}
            {'Edit views settings'|i18n('opendatadataset')}
        {else}
            {'Set views settings'|i18n('opendatadataset')}
        {/if}
    </a>
    <a href="#" id="delete-definition-{$attribute.id}" class="btn btn-sm btn-danger mt-1{if $attribute.has_content|not} hide{/if}">
        {'Remove all data and settings'|i18n('opendatadataset')}
    </a>
</div>

<div id="import-container-{$attribute.id}" class="{if $attribute.has_content}hide{/if}">
    {*<small class="text-uppercase text-100 d-block">{'You can import field definitions from existing data'|i18n('opendatadataset')}</small>*}
    <a href="#" id="import-definition-{$attribute.id}" class="btn btn-sm btn-info mt-1">
        {'Import fields definition from csv'|i18n('opendatadataset')}
    </a>
    <a href="#" id="import-definition-{$attribute.id}-from-spreadsheet" class="btn btn-sm btn-info mt-1">
        {'Import fields definition from Google Spreadsheet'|i18n('opendatadataset')}
    </a>
</div>

<table id="definition-{$attribute.id}" class="table table-sm{if $attribute.has_content|not} hide{/if}">
    <caption>{if $attribute.has_content}{$attribute.content.item_name|wash}{/if}</caption>
    <thead>
    <tr>
        <th>{'Label'|i18n('opendatadataset')}</th>
        <th>{'Identifier'|i18n('opendatadataset')}</th>
        <th>{'Type'|i18n('opendatadataset')}</th>
    </tr>
    </thead>
    <tbody>
    {if $attribute.has_content}
    {foreach $attribute.content.fields as $field}
        <tr>
            <td>{$field.label|wash()}</td>
            <td>{$field.identifier|wash()}</td>
            <td>{$field.type|wash()}</td>
        </tr>
    {/foreach}
    {/if}
    </tbody>
</table>

<div id="views-{$attribute.id}" class="mb-5{if $attribute.has_content|not} hide{/if}">
    {foreach $attribute.class_content.views as $view => $name}
    <div data-view="{$view}" class="chip chip-{if and($attribute.has_content, $attribute.content.views|contains($view))}primary{else}info{/if}">
        <span class="chip-label">{$name|wash()}</span>
    </div>
    {/foreach}
</div>

<div id="modal-{$attribute.id}" class="modal modal-fullscreen fade" data-backdrop="static" style="z-index:10000">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-body p-4">
                <div id="form-{$attribute.id}" class="clearfix p-2 bg-white"></div>
            </div>
        </div>
    </div>
</div>

{literal}
<script>
    $(document).ready(function () {
        var modalDataset = $("#modal-{/literal}{$attribute.id}{literal}");
        var definitionButton = $("#edit-definition-{/literal}{$attribute.id}{literal}");
        var viewButton = $("#edit-view-{/literal}{$attribute.id}{literal}");
        var importContainer = $("#import-container-{/literal}{$attribute.id}{literal}");
        var importButton = $("#import-definition-{/literal}{$attribute.id}{literal}");
        var importFromSpreadsheetButton = $("#import-definition-{/literal}{$attribute.id}{literal}-from-spreadsheet");
        var deleteButton = $("#delete-definition-{/literal}{$attribute.id}{literal}");
        var form = $("#form-{/literal}{$attribute.id}{literal}");
        var table = $("#definition-{/literal}{$attribute.id}{literal}");
        var views = $("#views-{/literal}{$attribute.id}{literal}");
        var datasetIdentifier = {
            'id': {/literal}{$attribute.id}{literal},
            'version': {/literal}{$attribute.version}{literal},
            'language': "{/literal}{$attribute.language_code}{literal}",
        };
        var renderTable = function (data){
            if (!data){
                var prefix = $.isFunction($.ez) ? $.ez.root_url : '/';
                $.get(prefix+"forms/connector/opendatadatasetfielddefinition/?" + $.param(datasetIdentifier), function (response){
                    renderTable(response.data);
                })
            }else {
                if (data.fields && data.fields.length > 0) {
                    table.removeClass('hide').find('caption').text(data.itemName);
                    var tbody = table.find('tbody');
                    tbody.find('tr').remove();
                    definitionButton.text(definitionButton.data('edit_label'));
                    viewButton.removeClass('hide');
                    importContainer.addClass('hide');
                    deleteButton.removeClass('hide');
                    $.each(data.fields, function () {
                        tbody.append(
                            $('<tr></tr>')
                                .append('<td>' + this.label + '</td>')
                                .append('<td>' + this.identifier + '</td>')
                                .append('<td>' + this.type + '</td>')
                        );
                    });
                    views.removeClass('hide').find('[data-view]').removeClass('chip-primary').addClass('chip-info');
                    if (data.views && data.views.length > 0) {
                        $.each(data.views, function () {
                            views.find('[data-view="' + this + '"]').addClass('chip-primary').removeClass('chip-info');
                        });
                        viewButton.text(viewButton.data('edit_label'));
                    } else {
                        viewButton.text(viewButton.data('create_label'));
                    }
                } else {
                    definitionButton.text(definitionButton.data('create_label'));
                    views.addClass('hide');
                    viewButton.addClass('hide');
                    importContainer.removeClass('hide');
                    table.addClass('hide').find('caption').text('');
                    deleteButton.addClass('hide');
                }
            }
        };

        definitionButton.on('click', function (e) {
            e.preventDefault();
            form.opendataForm(datasetIdentifier, {
                'connector': 'opendatadatasetfielddefinition',
                'onBeforeCreate': function () {
                    modalDataset.modal('show');
                },
                'onSuccess': function (data) {
                    modalDataset.modal('hide');
                    renderTable(data);
                },
                "alpaca": {
                    "options": {
                        "form": {
                            "buttons": {
                                "reset": {
                                    "click": function () {
                                        modalDataset.modal('hide');
                                    },
                                    "value": '{/literal}{'Cancel'|i18n('opendatadataset')}{literal}',
                                    "styles": "btn btn-lg btn-danger pull-left"
                                }
                            }
                        }
                    }
                }
            });
        });

        let selectSheet = function (sheet){
            datasetIdentifier.sheet = sheet;
            form.opendataForm(datasetIdentifier, {
                'connector': 'opendatadatasetimportgooglefields',
                'onSuccess': function () {
                    renderTable(false);
                    modalDataset.modal('hide');
                },
                'alpaca': {
                    'options': {
                        'form': {
                            'buttons': {
                                'submit': {
                                    "value": '{/literal}{'Import'|i18n('opendatadataset')}{literal}'
                                },
                                'reset': {
                                    'click': function () {
                                        modalDataset.modal('hide');
                                    },
                                    "value": '{/literal}{'Cancel'|i18n('opendatadataset')}{literal}',
                                    'styles': 'btn btn-lg btn-danger pull-left'
                                }
                            }
                        }
                    }
                }
            });
        };

        importFromSpreadsheetButton.on('click', function (e) {
            e.preventDefault();
            form.opendataForm(datasetIdentifier, {
                'connector': 'opendatadatasetselectspreadsheet',
                'onBeforeCreate': function () {
                    modalDataset.modal('show');
                },
                'onSuccess': function (data) {
                    selectSheet(data);
                },
                "alpaca": {
                    "options": {
                        "form": {
                            "buttons": {
                                'submit': {
                                    "value": '{/literal}{'Select'|i18n('opendatadataset')}{literal}'
                                },
                                "reset": {
                                    "click": function () {
                                        modalDataset.modal('hide');
                                    },
                                    "value": '{/literal}{'Cancel'|i18n('opendatadataset')}{literal}',
                                    "styles": "btn btn-lg btn-danger pull-left"
                                }
                            }
                        }
                    }
                }
            });
        });

        importButton.on('click', function (e) {
            e.preventDefault();
            form.opendataForm(datasetIdentifier, {
                'connector': 'opendatadatasetimportfielddefinition',
                'onBeforeCreate': function () {
                    modalDataset.modal('show');
                },
                'onSuccess': function (data) {
                    modalDataset.modal('hide');
                    renderTable(false);
                },
                "alpaca": {
                    "options": {
                        "form": {
                            "buttons": {
                                'submit': {
                                    "value": '{/literal}{'Import'|i18n('opendatadataset')}{literal}'
                                },
                                "reset": {
                                    "click": function () {
                                        modalDataset.modal('hide');
                                    },
                                    "value": '{/literal}{'Cancel'|i18n('opendatadataset')}{literal}',
                                    "styles": "btn btn-lg btn-danger pull-left"
                                }
                            }
                        }
                    }
                }
            });
        });

        viewButton.on('click', function (e) {
            e.preventDefault();
            form.opendataForm(datasetIdentifier, {
                'connector': 'opendatadatasetviewdefinition',
                'onBeforeCreate': function () {
                    modalDataset.modal('show');
                },
                'onSuccess': function (data) {
                    modalDataset.modal('hide');
                    renderTable(data);
                },
                "alpaca": {
                    "options": {
                        "form": {
                            "buttons": {
                                "reset": {
                                    "click": function () {
                                        modalDataset.modal('hide');
                                    },
                                    "value": '{/literal}{'Cancel'|i18n('opendatadataset')}{literal}',
                                    "styles": "btn btn-lg btn-danger pull-left"
                                }
                            }
                        }
                    },
                    "postRender": function(control) {
                        var refreshTabs = function(values){
                            $('.dataset-definition-group').hide();
                            $.each(values, function (){
                                $('#dataset-definition-group-'+this).show();
                            })
                        }
                        refreshTabs(control.childrenByPropertyId["views"].getValue());
                        control.childrenByPropertyId["views"].on("change", function() {
                            refreshTabs(this.getValue());
                        });
                    }
                }
            });
        });

        deleteButton.on('click', function (e) {
            e.preventDefault();
            form.opendataForm(datasetIdentifier, {
                'connector': 'opendatadatareset',
                'onBeforeCreate': function () {
                    modalDataset.modal('show');
                },
                'onSuccess': function (data) {
                    modalDataset.modal('hide');
                    renderTable(false);
                },
                "alpaca": {
                    "options": {
                        "form": {
                            "buttons": {
                                "reset": {
                                    "click": function () {
                                        modalDataset.modal('hide');
                                    },
                                    "value": '{/literal}{'Cancel'|i18n('opendatadataset')}{literal}',
                                    "styles": "btn btn-lg btn-danger pull-left"
                                }
                            }
                        }
                    }
                }
            });
        });
    });
</script>
{/literal}
