;(function ($, window, document, undefined) {
    'use strict';

    let pluginName = 'datasetView',
        defaults = {
            id: 0,
            version: 0,
            language: 'ita-IT',
            facets: [],
            canEdit: false,
            mainQuery: '',
            endpoints: {
                geo: '/',
                search: '/',
                datatable: '/',
                datatableLanguage: '/',
                calendar: '/',
                csv: '/'
            },
            calendar: {
                defaultView: 'dayGridWeek',
                includeWeekends: true,
                startDateField: false,
                startDateFormat: 'DD/MM/YYYY',
                endDateField: false,
                endDateFormat: 'DD/MM/YYYY',
                textFields: [],
                textLabels: [],
                eventLimit: false
            },
            chart: {
                settings: ''
            },
            datatable: {
                columns: []
            },
            i18n: {
                filter_by: 'Filter by',
                delete: 'Delete',
                cancel: 'Cancel operation',
                delete_dataset: 'I understand the consequences, delete this dataset',
                import: 'Import',
                select: 'Select'
            },
            itemName: 'Item'
        };

    function Plugin(element, options) {
        let settings = $.extend({}, defaults, options);
        let datasetContainer = $(element);
        let tools = $.opendataTools;
        tools.settings('endpoint', settings.endpoints);
        let form = $('<div class="row my-3 opendatadataset_view_form">');
        let datatable;
        let calendar;
        let map;
        let markers;
        let mapFilters = {};
        let counterFilters = {};
        let counterElement;
        if (settings.facets.length > 0) {
            settings.mainQuery += ' facets [' + tools.buildFacetsString(settings.facets) + ']';
        }

        function isEmail(email) {
            return String(email)
                .toLowerCase()
                .match(
                    /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
                );
        }

        function textellipsis(text, n) {
            return (text.length > n) ? text.substr(0, n - 1) + '...' : text;
        }

        function autoLink(text) {
            if (text) {
                if (isEmail(text)) {
                    return '<a href="mailto:' + text + '">' + text + '</a>';
                }
                return text.replace(/(https?:\/\/[^\s]+)/g, function (url) {

                    return '<a href="' + url + '" title="' + textellipsis(url, 20) + '">' + url + '</a>';
                });
            }

            return text;
        }

        function checkPending() {
            $.get('/opendatadataset/has_pending_action/' + settings.id, function (response) {
                if (response.pending > 0) {
                    datasetContainer.find('.has_error_action_alert').hide();
                    datasetContainer.find('.has_pending_action_alert').show();
                    datasetContainer.find('.data_actions').hide();
                    datasetContainer.trigger('dataset:add');
                    setTimeout(checkPending, 10000);
                } else if (response.pending === 0) {
                    datasetContainer.find('.has_pending_action_alert').hide();
                    datasetContainer.find('.data_actions').show();
                }
                if (response.failed > 0) {
                    datasetContainer.find('.has_error_action_alert').text(response.message).show();
                }
            });
        }

        function checkScheduled() {
            $.get('/opendatadataset/has_scheduled_action/' + settings.id, function (response) {
                if (response.scheduled > 0) {
                    datasetContainer.find('.has_scheduled_action_alert').show();
                } else {
                    datasetContainer.find('.has_scheduled_action_alert').hide();
                }
            });
        }

        datasetContainer.find('[data-action="add"]').on('click', function (e) {
            e.preventDefault();
            datasetContainer.find('.dataset-form').opendataForm({
                'id': settings.id,
                'version': settings.version,
                'language': settings.language,
            }, {
                'connector': 'opendatadataset',
                'onBeforeCreate': function () {
                    datasetContainer.find('.dataset-modal').modal('show');
                },
                'onSuccess': function () {
                    datasetContainer.find('.dataset-modal').modal('hide');
                    datasetContainer.trigger('dataset:add');
                }
            });
        });

        let viewItem = function (guid) {
            datasetContainer.find('.dataset-form').opendataForm({
                'id': settings.id,
                'version': settings.version,
                'language': settings.language,
                'guid': guid,
                'viewmode': 'display'
            }, {
                'connector': 'opendatadataset',
                'onBeforeCreate': function () {
                    datasetContainer.find('.dataset-modal').modal('show');
                },
                'onSuccess': function () {
                    datasetContainer.find('.dataset-modal').modal('hide');
                },
                'alpaca': {
                    'options': {
                        'form': {
                            'buttons': {
                                'submit': {
                                    'click': null,
                                    'id': '',
                                    'value': '',
                                    'styles': 'hide'
                                }
                            }
                        }
                    }
                }
            });
        };

        let editItem = function (guid) {
            datasetContainer.find('.dataset-form').opendataForm({
                'id': settings.id,
                'version': settings.version,
                'language': settings.language,
                'guid': guid,
            }, {
                'connector': 'opendatadataset',
                'onBeforeCreate': function () {
                    datasetContainer.find('.dataset-modal').modal('show');
                },
                'onSuccess': function () {
                    datasetContainer.find('.dataset-modal').modal('hide');
                    datasetContainer.trigger('dataset:add');
                }
            });
        };

        let deleteItem = function (guid) {
            datasetContainer.find('.dataset-form').opendataForm({
                'id': settings.id,
                'version': settings.version,
                'language': settings.language,
                'guid': guid,
            }, {
                'connector': 'opendatadatasetdeleteitem',
                'onBeforeCreate': function () {
                    datasetContainer.find('.dataset-modal').modal('show');
                },
                'onSuccess': function () {
                    datasetContainer.find('.dataset-modal').modal('hide');
                    datasetContainer.trigger('dataset:add');
                },
                'alpaca': {
                    'options': {
                        'form': {
                            'buttons': {
                                'submit': {
                                    'value': settings.i18n.delete,
                                },
                                'reset': {
                                    'click': function () {
                                        datasetContainer.find('.dataset-modal').modal('hide');
                                    },
                                    'value': settings.i18n.cancel,
                                    'styles': 'btn btn-lg btn-danger pull-left'
                                }
                            }
                        }
                    }
                }
            });
        };

        datasetContainer.find('[data-action="delete-all"]').on('click', function (e) {
            e.preventDefault();
            datasetContainer.find('.dataset-form').opendataForm({
                'id': settings.id,
                'version': settings.version,
                'language': settings.language,
            }, {
                'connector': 'opendatadatasetdelete',
                'onBeforeCreate': function () {
                    datasetContainer.find('.dataset-modal').modal('show');
                },
                'onSuccess': function () {
                    datasetContainer.find('.dataset-modal').modal('hide');
                    datasetContainer.trigger('dataset:add');
                    datasetContainer.find('.has_error_action_alert').hide();
                },
                'alpaca': {
                    'options': {
                        'form': {
                            'buttons': {
                                'submit': {
                                    'value': settings.i18n.delete_dataset,
                                },
                                'reset': {
                                    'click': function () {
                                        datasetContainer.find('.dataset-modal').modal('hide');
                                    },
                                    'value': settings.i18n.cancel,
                                    'styles': 'btn btn-lg btn-danger pull-left'
                                }
                            }
                        }
                    }
                }
            });
        });

        datasetContainer.find('[data-action="import"]').on('click', function (e) {
            e.preventDefault();
            datasetContainer.find('.dataset-form').opendataForm({
                'id': settings.id,
                'version': settings.version,
                'language': settings.language,
            }, {
                'connector': 'opendatadatasetimport',
                'onBeforeCreate': function () {
                    datasetContainer.find('.dataset-modal').modal('show');
                },
                'onSuccess': function () {
                    datasetContainer.find('.dataset-modal').modal('hide');
                    datasetContainer.trigger('dataset:add');
                    checkPending();
                },
                'onError': function (data) {
                    datasetContainer.find('.dataset-modal').modal('hide');
                    alert(data.error);
                },
                'alpaca': {
                    'options': {
                        'form': {
                            'buttons': {
                                'submit': {
                                    'value': settings.i18n.import,
                                },
                                'reset': {
                                    'click': function () {
                                        datasetContainer.find('.dataset-modal').modal('hide');
                                    },
                                    'value': settings.i18n.cancel,
                                    'styles': 'btn btn-lg btn-danger pull-left'
                                }
                            }
                        }
                    }
                }
            });
        });

        let selectSheet = function (sheet) {
            datasetContainer.find('.dataset-form').opendataForm({
                'id': settings.id,
                'version': settings.version,
                'language': settings.language,
                'sheet': sheet,
            }, {
                'connector': 'opendatadatasetgoogleimport',
                'onSuccess': function () {
                    datasetContainer.find('.dataset-modal').modal('hide');
                    datasetContainer.trigger('dataset:add');
                    checkPending();
                    checkScheduled();
                },
                'onError': function (data) {
                    datasetContainer.find('.dataset-modal').modal('hide');
                    alert(data.error);
                },
                'alpaca': {
                    'options': {
                        'form': {
                            'buttons': {
                                'submit': {
                                    'value': settings.i18n.import,
                                },
                                'reset': {
                                    'click': function () {
                                        datasetContainer.find('.dataset-modal').modal('hide');
                                    },
                                    'value': settings.i18n.cancel,
                                    'styles': 'btn btn-lg btn-danger pull-left'
                                }
                            }
                        }
                    }
                }
            });
        };

        datasetContainer.find('[data-action="google-import"]').on('click', function (e) {
            e.preventDefault();
            datasetContainer.find('.dataset-form').opendataForm({
                'id': settings.id,
                'version': settings.version,
                'language': settings.language,
            }, {
                'connector': 'opendatadatasetselectspreadsheet',
                'onBeforeCreate': function () {
                    datasetContainer.find('.dataset-modal').modal('show');
                },
                'onSuccess': function (data) {
                    selectSheet(data);
                },
                'onError': function (data) {
                    datasetContainer.find('.dataset-modal').modal('hide');
                    alert(data.error);
                },
                'alpaca': {
                    'options': {
                        'form': {
                            'buttons': {
                                'submit': {
                                    'value': settings.i18n.select
                                },
                                'reset': {
                                    'click': function () {
                                        datasetContainer.find('.dataset-modal').modal('hide');
                                    },
                                    'value': settings.i18n.cancel,
                                    'styles': 'btn btn-lg btn-danger pull-left'
                                }
                            }
                        }
                    }
                }
            });
        });

        datasetContainer.find('[data-view="table"]').each(function () {
            $.fn.dataTable.ext.errMode = 'none';
            let table = $(this);
            let order = [[0, 'asc']];
            if (settings.canEdit) {
                settings.datatable.columns.unshift({
                    data: '_guid',
                    name: '_guid',
                    title: '',
                    searchable: false,
                    orderable: false
                });
                order = [[1, 'asc']];
            }
            let renderAll = function (data, type, row) {
                if (row._guid === data && settings.canEdit) {
                    if (row._canEdit) {
                        return '<span class="text-nowrap"><a data-action="edit" class="btn btn-xs btn-primary px-2 py-1 mx-1" href="#" data-guid="' + data + '">'
                            + '<i class="fa fa-pencil"></i>'
                            + '</a>'
                            + '<a data-action="delete" class="btn btn-xs btn-danger px-2 py-1" href="#" data-guid="' + data + '">'
                            + '<i class="fa fa-trash"></i>'
                            + '</a></span>';
                    } else {
                        return '';
                    }
                }
                if ($.isArray(data)) {
                    return data.join(', ');
                }
                if ($.isPlainObject(data)) {
                    let str = '';
                    for (let p in data) {
                        if (data.hasOwnProperty(p)) {
                            str += p + ': ' + data[p] + '<br />';
                        }
                    }
                    return str;
                }
                return autoLink(data);
            };
            let headers;
            let tokenNode = document.getElementById('ezxform_token_js');
            if (tokenNode) {
                headers = {'X-CSRF-TOKEN': tokenNode.getAttribute('title')};
            }
            datatable = table.opendataDataTable({
                'table': {
                    'template': '<table class="table table-striped table-sm display responsive no-wrap w-100"></table>'
                },
                'builder': {
                    'query': settings.mainQuery
                },
                'datatable': {
                    'responsive': true,
                    'dom': 'itpr',
                    'order': order,
                    'language': {'url': settings.endpoints.datatableLanguage},
                    'ajax': {
                        url: settings.endpoints.datatable,
                        type: settings.datatable.columns.length > 15 ? 'POST' : 'GET',
                        headers: headers
                    },
                    'lengthMenu': [30, 60, 90, 120],
                    'columns': settings.datatable.columns,
                    'columnDefs': [{
                        'className': 'dtr-control',
                        'render': function (data, type, row) {
                            return renderAll(data, type, row);
                        },
                        'targets': 0
                    }, {
                        'render': function (data, type, row) {
                            return renderAll(data, type, row);
                        },
                        'targets': '_all'
                    }]
                }
            }).on('draw.dt', function (e, settings) {
                $('a[data-action="edit"]').on('click', function (e) {
                    editItem($(this).data('guid'));
                    e.preventDefault();
                });
                $('a[data-action="delete"]').on('click', function (e) {
                    deleteItem($(this).data('guid'));
                    e.preventDefault();
                });
            }).data('opendataDataTable');
            datasetContainer.on('dataset:add', function () {
                datatable.loadDataTable();
            });
            datasetContainer.on('dataset:changeFilter', function (e, filter) {
                if (filter.value) {
                    datatable.settings.builder.filters[filter.name] = {
                        'field': filter.name,
                        'operator': 'in',
                        'value': [filter.value]
                    };
                } else {
                    datatable.settings.builder.filters[filter.name] = null;
                }
                datatable.loadDataTable();
            });
            datatable.loadDataTable();
        });

        datasetContainer.find('[data-view="calendar"] .block-calendar-default').each(function () {
            let filters = {};
            calendar = $(this).data('fullcalendar', new FullCalendar.Calendar(
                this,
                {
                    plugins: ['dayGrid', 'list'],
                    header: {
                        left: 'prev,next',
                        center: 'title',
                        right: 'today,dayGridDay,dayGridWeek,dayGridMonth'
                    },
                    locale: 'it',
                    height: 'parent',
                    aspectRatio: 3,
                    eventLimit: settings.calendar.eventLimit,
                    columnHeaderFormat: {
                        weekday: 'short',
                        omitCommas: true
                    },
                    displayEventTime: false,
                    defaultView: settings.calendar.defaultView,
                    weekends: settings.calendar.includeWeekends,
                    windowResize: function () {
                        if ($(window).width() < 800) {
                            this.changeView('listWeek');
                            calendar.setOption('header', {
                                left: 'prev,next',
                                center: 'title',
                                right: 'today'
                            });
                        } else {
                            this.changeView(settings.calendar.defaultView);
                            calendar.setOption('header', {
                                left: 'prev,next',
                                center: 'title',
                                right: 'today,dayGridDay,dayGridWeek,dayGridMonth'
                            });
                        }
                    },
                    events: {
                        url: settings.endpoints.calendar,
                        extraParams: function () {
                            let params = {};
                            if (settings.calendar.startDateField) {
                                params.startDateField = settings.calendar.startDateField;
                            }
                            if (settings.calendar.startDateFormat) {
                                params.startDateFormat = settings.calendar.startDateFormat;
                            }
                            if (settings.calendar.endDateField) {
                                params.endDateField = settings.calendar.endDateField;
                            }
                            if (settings.calendar.endDateFormat) {
                                params.endDateFormat = settings.calendar.endDateFormat;
                            }
                            $.each(filters, function (name, value) {
                                if (value) {
                                    params['filters[' + name + ']'] = value;
                                }
                            });
                            return params;
                        }
                    },
                    eventRender: function (info) {
                        if (settings.calendar.textFields.length > 0) {
                            let html = '';
                            html += '<p class="m-0">';
                            $.each(settings.calendar.textFields, function () {
                                if (settings.calendar.textLabels[this]) {
                                    html += '<strong class="d-block">' + settings.calendar.textLabels[this] + '</strong>';
                                }
                                html += autoLink(info.event.extendedProps.content[this].replace(/\n/g, "<br>")) + ' ';
                            });
                            html += '</p>';
                            $(info.el)
                                .find('.fc-content, .fc-list-item-title')
                                .html(html);
                        }
                    },
                    eventClick: function (info) {
                        viewItem(info.event.id);
                    }
                }
            )).data('fullcalendar');
            calendar.render();
            window.dispatchEvent(new Event('resize'));
            datasetContainer.on('dataset:add', function () {
                calendar.refetchEvents();
            });
            datasetContainer.on('dataset:changeFilter', function (e, filter) {
                filters[filter.name] = filter.value;
                calendar.refetchEvents();
            });
        });

        datasetContainer.find('[data-view="chart"]').each(function () {
            let chartContainer = $(this);
            let drawChart = function (chartContainer) {
                let easyChart = new ec({
                    dataUrl: settings.endpoints.csv
                });
                easyChart.setConfigStringified(settings.chart.settings);
                easyChart.on('dataUpdate', function () {
                    let options = easyChart.getConfigAndData();
                    options.chart.renderTo = chartContainer;
                    options.chart.width = null;
                    //options.legend = {enabled: false};
                    options.title = {text: null};
                    options.subtitle = {text: null};
                    options.exporting = {
                        enabled: true,
                        buttons: {
                            contextButton: {
                                menuItems: ['downloadPNG', 'downloadJPEG', 'downloadPDF', 'downloadSVG']
                            }
                        }
                    };
                    new Highcharts.Chart(options);
                });
            };
            drawChart(chartContainer[0]);
            datasetContainer.on('dataset:add', function () {
                chartContainer.html('');
                drawChart(chartContainer[0]);
            });
        });

        function loadMap() {
            map.invalidateSize(false);
            markers.clearLayers();
            let markerBuilder = function (response) {
                return L.geoJson(response, {
                    pointToLayer: function (feature, latlng) {
                        let customIcon = L.MakiMarkers.icon({icon: 'circle', size: 'l'});
                        return L.marker(latlng, {icon: customIcon});
                    },
                    onEachFeature: function (feature, layer) {
                        layer.on('click', function (e) {
                            viewItem(feature.id);
                        });
                    }
                });
            };
            $.ajax({
                type: 'GET',
                url: settings.endpoints.geo,
                data: {'filters': mapFilters},
                contentType: 'application/json; charset=utf-8',
                dataType: 'json',
                success: function (response) {
                    if (response && response.features.length > 0) {
                        let geoJsonLayer = markerBuilder(response);
                        markers.addLayer(geoJsonLayer);
                        map.fitBounds(markers.getBounds());
                    } else {
                        map.setView([0, 0], 1);
                    }
                }
            });
        }

        datasetContainer.find('[data-view="map"]').each(function () {
            let mapContainer = $(this).css({height: '600px'});
            markers = L.markerClusterGroup();
            map = L.map(mapContainer[0]).setView([0, 0], 1);
            map.scrollWheelZoom.disable();
            L.tileLayer('//{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'}).addTo(map);
            map.addLayer(markers);

            datasetContainer.on('dataset:add', function () {
                loadMap();
            });
            datasetContainer.on('dataset:changeFilter', function (e, filter) {
                if (filter.value) {
                    mapFilters[filter.name] = filter.value;
                } else if (mapFilters[filter.name]) {
                    delete mapFilters[filter.name];
                }
                loadMap();
            });
        });

        function loadCounter() {
            $.ajax({
                type: 'GET',
                url: settings.endpoints.search,
                data: {
                    'filters': counterFilters,
                    'limit': 1
                },
                contentType: 'application/json; charset=utf-8',
                dataType: 'json',
                success: function (response) {
                    counterElement.html('<p class="h1 m-0" style="line-height:1;font-size:3em">' + response.totalCount + '</p><p>' + settings.itemName + '</p>');
                },
                error: function () {
                    counterElement.text('Error');
                }
            });
        }

        datasetContainer.find('[data-view="counter"]').each(function () {
            let counterContainer = $(this);
            counterElement = $('<div class="lead font-weight-bolder text-center"></div>').appendTo(counterContainer);

            datasetContainer.on('dataset:add', function () {
                loadCounter();
            });
            datasetContainer.on('dataset:changeFilter', function (e, filter) {
                if (filter.value) {
                    counterFilters[filter.name] = filter.value;
                } else if (counterFilters[filter.name]) {
                    delete counterFilters[filter.name];
                }
                loadCounter();
            });
        });

        let loadFilters = function () {
            tools.find(settings.mainQuery + ' limit 1', function (response) {
                form.html('');
                $.each(response.facets, function (field, data) {
                    let labelText = field;
                    $.each(settings.facets, function () {
                        if (this.field === field) {
                            labelText = this.name;
                        }
                    });
                    let select = $('<select data-placeholder="' + settings.i18n.filter_by + ' ' + labelText + '" id="' + field + '" data-field="' + field + '"">');
                    select.append($('<option value=""></option>'));
                    $.each(data, function (value, count) {
                        let quotedValue = value.toString()
                            .replace(/"/g, '&quot;')
                            .replace(/'/g, "\\'")
                            .replace(/\(/g, "\\(")
                            .replace(/\)/g, "\\)")
                            .replace(/\[/g, "\\[")
                            .replace(/\]/g, "\\]");
                        let option = $('<option value="' + quotedValue + '">' + value + '</option>');
                        if (count === 0) {
                            option.attr('disabled', 'disabled');
                        }
                        select.append(option);
                    });
                    let label = $('<label class="sr-only" for="' + field + '">' + labelText + '</label>');
                    let selectContainer = $('<div class="col my-1"></div>');
                    selectContainer.append(label).append(select);
                    form.append(selectContainer);
                    select.chosen({width: '100%', allow_single_deselect: true}).on('change', function (e) {
                        let that = $(e.currentTarget);
                        let values = $(e.currentTarget).val();
                        let filter = {'name': that.data('field'), 'value': null};
                        if (values !== null && values.length > 0) {
                            filter.value = values;
                        }
                        datasetContainer.trigger('dataset:changeFilter', filter);
                    }).val('');
                });
                datasetContainer.find('.tab-content').before(form);
            });
        };

        let setActiveView = function (view) {
            if (view === 'chart') {
                form.hide();
            } else {
                form.show();
                if (view === 'table') {
                    datatable.datatable.draw();
                    datatable.datatable.responsive.recalc();
                }
                if (view === 'calendar') {
                    calendar.updateSize();
                }
                if (view === 'map') {
                    loadMap();
                }
                if (view === 'counter') {
                    loadCounter();
                }
            }
        };

        if (settings.facets.length > 0) {
            loadFilters();
            datasetContainer.on('dataset:add', function () {
                loadFilters();
            });
        }

        datasetContainer.find('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            setActiveView($(this).data('active_view'));
        });
        let activeTab = datasetContainer.find('a[data-toggle="tab"].active');
        if (activeTab.length > 0) {
            setActiveView(activeTab.data('active_view'));
        }else{
            let activeTabPane = datasetContainer.find('.tab-pane.active');
            if (activeTabPane.length > 0) {
                setActiveView(activeTabPane.data('view'));
            }
        }

        let filters = {};
        datasetContainer.on('dataset:changeFilter', function (e, filter) {
            filters[filter.name] = filter;
            let exportButton = datasetContainer.find('[data-action="export"]');
            let filtersStrings = [];
            $.each(filters, function () {
                if (this.value) {
                    filtersStrings.push('filters[' + this.name + ']=' + this.value.replace(/"/g, '\\"'));
                }
            });
            exportButton.attr('href', exportButton.data('href') + '?' + filtersStrings.join('&'));
        });

        checkPending();
        if (datasetContainer.find('.has_scheduled_action_alert').length > 0){
            checkScheduled();
        }
    }

    $.fn[pluginName] = function (options) {
        return this.each(function () {
            if (!$.data(this, 'plugin_' + pluginName)) {
                $.data(this, 'plugin_' +
                    pluginName, new Plugin(this, options));
            }
        });
    };

})(jQuery, window, document);
