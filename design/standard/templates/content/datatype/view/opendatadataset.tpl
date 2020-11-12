{if $attribute.has_content}
{if module_params().function_name|eq('edit')}
    <table class="table table-sm">
        <caption>{if $attribute.has_content}{$attribute.content.item_name|wash}{/if}</caption>
        <thead>
        <tr>
            <th>{'Label'|i18n('opendatadataset')}</th>
            <th>{'Identifier'|i18n('opendatadataset')}</th>
            <th>{'Type'|i18n('opendatadataset')}</th>
        </tr>
        </thead>
        <tbody>
            {foreach $attribute.content.fields as $field}
                <tr>
                    <td>{$field.label|wash()}</td>
                    <td>{$field.identifier|wash()}</td>
                    <td>{$field.type|wash()}</td>
                </tr>
            {/foreach}
        </tbody>
    </table>

    <div class="mb-5">
        {foreach $attribute.class_content.views as $view => $name}
            <div class="chip chip-{if and($attribute.has_content, $attribute.content.views|contains($view))}primary{else}info{/if}">
                <span class="chip-label">{$name|wash()}</span>
            </div>
        {/foreach}
    </div>
{else}
{ezscript_require(array(
    'dataTables.responsive.min.js',
    'handlebars.min.js',
    'moment-with-locales.min.js',
    'bootstrap-datetimepicker.min.js',
    'leaflet/Control.Geocoder.js',
    'leaflet/Control.Loading.js',
    'leaflet/Leaflet.MakiMarkers.js',
    'leaflet/leaflet.activearea.js',
    'leaflet/leaflet.markercluster.js',
    'jquery.fileupload.js',
    'jquery.fileupload-process.js',
    'jquery.fileupload-ui.js',
    'alpaca.js',
    'fields/OpenStreetMap.js',
    'jquery.opendataform.js',
    'fullcalendar/core/main.js',
    'fullcalendar/core/locales/it.js',
    'fullcalendar/daygrid/main.js',
    'fullcalendar/list/main.js',
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
    'jquery.opendatadatasetview.js'
))}
{ezcss_require(array(
    'datatable.responsive.bootstrap4.min.css',
    'leaflet/leaflet.0.7.2.css',
    'leaflet/Control.Loading.css',
    'leaflet/MarkerCluster.css',
    'leaflet/MarkerCluster.Default.css',
    'bootstrap-datetimepicker.min.css',
    'fullcalendar/core/main.css',
    'fullcalendar/daygrid/main.css',
    'fullcalendar/list/main.css',
    'jquery.fileupload.css',
    'alpaca-custom.css'
))}
{def $custom_repository = concat('dataset-', $attribute.contentclass_attribute_identifier, '-',$attribute.contentobject_id)}

<div id="dataset-{$attribute.id}" class="my-5 w-100">
    <a href="{concat('/customexport/',$custom_repository)|ezurl(no)}" data-action="export" class="btn btn-primary btn-xs mb-1 mr-1"><i class="fa fa-download"></i> {'Download CSV'|i18n('opendatadataset')}</a>
    {if and($attribute.content.can_edit, $attribute.content.is_api_enabled)}
        <a href="#" data-action="add" class="btn btn-outline-primary btn-xs mb-1"><i class="fa fa-plus"></i> {'Create new %name'|i18n('opendatadataset',,hash('%name', $attribute.content.item_name|wash()))}</a>
        {*<a href="#" data-action="apidoc" class="btn btn-outline-primary btn-xs mb-1"><i class="fa fa-external-link"></i> {'API Doc'|i18n('opendatadataset')}</a>*}
    {/if}
    {if $attribute.content.can_edit}
        <a href="#" data-action="import" class="btn btn-outline-primary btn-xs mb-1"><i class="fa fa-arrow-up"></i> {'Import from CSV'|i18n('opendatadataset')}</a>
    {/if}
    {if $attribute.content.can_truncate}
        <a href="#" data-action="delete-all" class="btn btn-outline-primary btn-xs mb-1"><i class="fa fa-times"></i> {'Delete data'|i18n('opendatadataset')}</a>
    {/if}

    {if $attribute.content.views|count()|gt(1)}
    <ul class="nav nav-tabs nav-fill overflow-hidden mt-3">
        {def $index = 0}
        {foreach $attribute.class_content.views as $view => $name}
        {if $attribute.content.views|contains($view)}
        <li role="presentation" class="nav-item">
            <a class="text-decoration-none nav-link{if $index|eq(0)} active{/if} text-sans-serif" data-active_view="{$view}" data-toggle="tab" href="#{$view}-{$attribute.id}">
                {$name|wash()|upfirst}
            </a>
        </li>
        {set $index = $index|inc()}
        {/if}
        {/foreach}
        {undef $index}
    </ul>
    {/if}
    <div class="tab-content mt-3">

    {def $index = 0}
    {foreach $attribute.class_content.views as $view => $name}
        {if $attribute.content.views|contains($view)}
            {if $view|eq('calendar')}
                <div role="tabpanel" data-view="{$view}" class="tab-pane{if $index|eq(0)} active{/if}" id="{$view}-{$attribute.id}">
                    <div class="block-calendar-default shadow block-calendar block-calendar-big"></div>
                </div>
            {else}
                <div role="tabpanel" data-view="{$view}" class="tab-pane{if $index|eq(0)} active{/if}" id="{$view}-{$attribute.id}"></div>
            {/if}
        {set $index = $index|inc()}
    {/if}
    {/foreach}
    {undef $index}
    </div>

    <div class="dataset-modal modal fade">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-body pb-3">
                    <div class="clearfix">
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    </div>
                    <div class="dataset-form clearfix"></div>
                </div>
            </div>
        </div>
    </div>
</div>

{def $current_language = ezini('RegionalSettings', 'Locale')}
{def $current_locale = fetch( 'content', 'locale' , hash( 'locale_code', $current_language ))}
{def $moment_language = $current_locale.http_locale_code|explode('-')[0]|downcase()|extract_left( 2 )}
{def $startDateFormat = ''
     $endDateFormat = ''
     $textLabels = hash()
     $facets = array()
     $columns = array()
}
{foreach $attribute.content.fields as $field}
    {if and(is_set($attribute.content.settings.calendar.start_date_field), $field.identifier|eq($attribute.content.settings.calendar.start_date_field))}
        {if is_set($field.date_format)}{set $startDateFormat = $field.date_format}{else}{set $dateFormat = $field.datetime_format}{/if}
    {/if}
    {if and(is_set($attribute.content.settings.calendar.end_date_field), $field.identifier|eq($attribute.content.settings.calendar.end_date_field))}
        {if is_set($field.date_format)}{set $endDateFormat = $field.date_format}{else}{set $dateFormat = $field.datetime_format}{/if}
    {/if}
    {if and(is_set($attribute.content.settings.calendar.text_labels), $attribute.content.settings.calendar.text_labels|contains($field.identifier))}
        {set $textLabels = $textLabels|merge(hash($field.identifier, $field.label|explode("'")|implode('&apos;')))}
    {/if}
    {if $attribute.content.settings.facets|contains($field.identifier)}
        {set $facets = $facets|append(hash(field, $field.identifier, limit, 10000, sort, 'alpha', name, $field.label|explode("'")|implode('&apos;')))}
    {/if}
    {if $attribute.content.settings.table.show_fields|contains($field.identifier)}
        {set $columns = $columns|append(hash(data, $field.identifier, name, $field.identifier, title, $field.label|explode("'")|implode('&apos;'), searchable, true(), orderable, cond(array('checkbox', 'geo')|contains($field.type), false(), true())))}
    {/if}
{/foreach}

<script>
    moment.locale('{$moment_language}');
    $(document).ready(function () {ldelim}
        $("#dataset-{$attribute.id}").datasetView({ldelim}
            id: {$attribute.id},
            version: {$attribute.version},
            language: "{$attribute.language_code}",
            facets: JSON.parse('{$facets|json_encode()}'),
            canEdit: {cond(and($attribute.content.can_edit, $attribute.content.is_api_enabled), 'true', 'false')},
            endpoints: {ldelim}
                geo: "{concat('/customgeo/',$custom_repository)|ezurl(no)}/",
                search: "{concat('/customfind/',$custom_repository)|ezurl(no)}/",
                datatable: "{concat('/customdatatable/',$custom_repository)|ezurl(no)}/",
                datatableLanguage: "{concat('javascript/datatable/',$current_language,'.json')|ezdesign(no)}",
                calendar: "{concat('/customcalendar/',$custom_repository)|ezurl(no)}/",
                csv: "{concat('/customexport/',$custom_repository)|ezurl(no)}/"
            {rdelim},
            calendar: {ldelim}
                defaultView: "{if is_set($attribute.content.settings.calendar.default_view)}{$attribute.content.settings.calendar.default_view}{else}dayGridWeek{/if}",
                includeWeekends: {cond(and(is_set($attribute.content.settings.calendar.include_weekends), $attribute.content.settings.calendar.include_weekends|eq('true')), 'true', 'false')},
                startDateField: {if is_set($attribute.content.settings.calendar.start_date_field)}"{$attribute.content.settings.calendar.start_date_field}"{else}false{/if},
                startDateFormat: '{$startDateFormat}',
                endDateField: {if is_set($attribute.content.settings.calendar.end_date_field)}"{$attribute.content.settings.calendar.end_date_field}"{else}false{/if},
                endDateFormat: '{$endDateFormat}',
                textFields: [{if is_set($attribute.content.settings.calendar.text_fields)}"{$attribute.content.settings.calendar.text_fields|implode('","')}"{/if}],
                textLabels: JSON.parse('{$textLabels|json_encode()}')
            {rdelim},
            chart: {ldelim}
                settings: '{if is_set($attribute.content.settings.chart)}{$attribute.content.settings.chart}{/if}'
            {rdelim},
            datatable: {ldelim}
                columns: JSON.parse('{$columns|json_encode()}')
            {rdelim}
        {rdelim});
    {rdelim})
</script>
{undef $current_language $current_locale $moment_language $startDateFormat $endDateFormat $textLabels $facets $columns}
{/if}
{/if}