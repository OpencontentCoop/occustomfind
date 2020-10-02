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

<p>
    {if $attribute.has_content|not}
        <a href="#" id="import-definition-{$attribute.id}" class="btn btn-sm btn-info">
            {'Import fields definition from csv'|i18n('opendatadataset')}
        </a>
    {/if}

    <a href="#" id="edit-definition-{$attribute.id}" class="btn btn-sm btn-info">
        {if $attribute.has_content}
            {'Edit fields definition'|i18n('opendatadataset')}
        {else}
            {'Set fields definition'|i18n('opendatadataset')}
        {/if}
    </a>

    <a href="#" id="edit-view-{$attribute.id}" class="btn btn-sm btn-info{if $attribute.has_content|not} hide{/if}">
        {if $attribute.has_content}
            {'Edit views settings'|i18n('opendatadataset')}
        {else}
            {'Set views settings'|i18n('opendatadataset')}
        {/if}
    </a>
</p>

<div id="form-{$attribute.id}" class="clearfix p-2 bg-white hide"></div>

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

{literal}
<script>
    $(document).ready(function () {
        $("#import-definition-{/literal}{$attribute.id}{literal}").on('click', function (e) {
            var button = $(this).addClass('hide');
            var definitionButton = $("#edit-definition-{/literal}{$attribute.id}{literal}");
            definitionButton.addClass('hide');
            e.preventDefault();
            var form = $("#form-{/literal}{$attribute.id}{literal}");
            form.removeClass('hide').opendataForm({
                'id': {/literal}{$attribute.id}{literal},
                'version': {/literal}{$attribute.version}{literal},
                'language': "{/literal}{$attribute.language_code}{literal}",
            }, {
                'connector': 'opendatadatasetimportfielddefinition',
                'onSuccess': function (data) {
                    definitionButton.trigger('click')
                },
                "alpaca": {
                    "options": {
                        "form": {
                            "buttons": {
                                'submit': {
                                    'value': 'Import',
                                },
                                "reset": {
                                    "click": function () {
                                        form.addClass('hide');
                                        button.removeClass('hide');
                                        definitionButton.removeClass('hide');
                                    },
                                    "value": 'Cancel',
                                    "styles": "btn btn-lg btn-danger pull-left"
                                }
                            }
                        }
                    }
                }
            });
        });

        $("#edit-definition-{/literal}{$attribute.id}{literal}").on('click', function (e) {
            var button = $(this).addClass('hide');
            var viewButton = $("#edit-view-{/literal}{$attribute.id}{literal}");
            var importButton = $("#import-definition-{/literal}{$attribute.id}{literal}");
            var isViewButtonHidden = viewButton.hasClass('hide');
            viewButton.addClass('hide');
            e.preventDefault();
            var form = $("#form-{/literal}{$attribute.id}{literal}");
            form.removeClass('hide').opendataForm({
                'id': {/literal}{$attribute.id}{literal},
                'version': {/literal}{$attribute.version}{literal},
                'language': "{/literal}{$attribute.language_code}{literal}",
            }, {
                'connector': 'opendatadatasetfielddefinition',
                'onSuccess': function (data) {
                    form.addClass('hide');
                    button.removeClass('hide');
                    var table = $("#definition-{/literal}{$attribute.id}{literal}").removeClass('hide')
                    table.find('caption').text(data.itemName);
                    var tbody = table.find('tbody');
                    tbody.find('tr').remove();
                    if (data.fields.length > 0){
                        $.each(data.fields, function(){
                            tbody.append(
                                $('<tr></tr>')
                                    .append('<td>'+this.label+'</td>')
                                    .append('<td>'+this.identifier+'</td>')
                                    .append('<td>'+this.type+'</td>')
                            );
                        });
                        viewButton.removeClass('hide');
                        importButton.addClass('hide');
                    }else{
                        viewButton.addClass('hide');
                    }
                    $('html, body').animate({
                        scrollTop: $("#edit-{/literal}{$attribute.contentclass_attribute_identifier}{literal}").offset().top - 70
                    }, 100);
                },
                "alpaca": {
                    "options": {
                        "form": {
                            "buttons": {
                                "reset": {
                                    "click": function () {
                                        form.addClass('hide');
                                        button.removeClass('hide');
                                        if (!isViewButtonHidden) {
                                            viewButton.removeClass('hide');
                                        }
                                        $('html, body').animate({
                                            scrollTop: $("#edit-{/literal}{$attribute.contentclass_attribute_identifier}{literal}").offset().top - 70
                                        }, 100);
                                    },
                                    "value": 'Cancel',
                                    "styles": "btn btn-lg btn-danger pull-left"
                                }
                            }
                        }
                    }
                }
            });
        });

        $("#edit-view-{/literal}{$attribute.id}{literal}").on('click', function (e) {
            var button = $(this).addClass('hide');
            var definitionButton = $("#edit-definition-{/literal}{$attribute.id}{literal}").addClass('hide');
            e.preventDefault();
            var form = $("#form-{/literal}{$attribute.id}{literal}");
            form.removeClass('hide').opendataForm({
                'id': {/literal}{$attribute.id}{literal},
                'version': {/literal}{$attribute.version}{literal},
                'language': "{/literal}{$attribute.language_code}{literal}",
            }, {
                'connector': 'opendatadatasetviewdefinition',
                'onSuccess': function (data) {
                    form.addClass('hide');
                    button.removeClass('hide');
                    definitionButton.removeClass('hide');
                    $("#views-{/literal}{$attribute.id}{literal}").removeClass('hide').find('[data-view]')
                        .removeClass('chip-primary')
                        .addClass('chip-info');
                    $.each(data.views, function () {
                        $("#views-{/literal}{$attribute.id}{literal}").find('[data-view="'+this+'"]')
                            .addClass('chip-primary')
                            .removeClass('chip-info');
                    });
                    $('html, body').animate({
                        scrollTop: $("#edit-{/literal}{$attribute.contentclass_attribute_identifier}{literal}").offset().top - 70
                    }, 100);
                },
                "alpaca": {
                    "options": {
                        "form": {
                            "buttons": {
                                "reset": {
                                    "click": function () {
                                        form.addClass('hide');
                                        button.removeClass('hide');
                                        definitionButton.removeClass('hide');
                                        $('html, body').animate({
                                            scrollTop: $("#edit-{/literal}{$attribute.contentclass_attribute_identifier}{literal}").offset().top - 70
                                        }, 100);
                                    },
                                    "value": 'Cancel',
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