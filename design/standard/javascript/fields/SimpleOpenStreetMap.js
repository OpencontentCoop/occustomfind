(function ($) {

    var Alpaca = $.alpaca;

    Alpaca.Fields.OpenStreetMap = Alpaca.Fields.ObjectField.extend(
        /**
         * @lends Alpaca.Fields.OpenStreetMap.prototype
         */
        {
            /**
             * @see Alpaca.Fields.ObjectField#getFieldType
             */
            getFieldType: function () {
                return "openstreetmap";
            },

            /**
             * @private
             * @see Alpaca.Fields.ObjectField#setup
             */
            setup: function () {
                this.base();

                if(!this.isDisplayOnly()) {
                    this.schema = {
                        "type": "object",
                        "properties": {
                            "latitude": {
                                "title": this.options.i18n.latitude,
                                "minimum": -180,
                                "maximum": 180
                            },
                            "longitude": {
                                "title": this.options.i18n.longitude,
                                "minimum": -180,
                                "maximum": 180
                            }
                        }
                    };
                }

                Alpaca.merge(this.options, {
                    "fields": {
                        "latitude": {
                            "type": "number"
                        },
                        "longitude": {
                            "type": "number"
                        }
                    },
                    "i18n":{
                        'latitude': 'Latitude',
                        'longitude': 'Longitude',
                        'noResultsFinding': 'No results finding',
                        'tryToRefineYourSearch': 'try to refine your search keywords',
                    }
                });
            },

            /**
             * @see Alpaca.Field#afterRenderContainer
             */
            afterRenderContainer: function(model, callback) {

                var self = this;

                this.base(model, function() {
                    var container = self.getContainerEl();
                    var mapContainer = $('<div id="osm-' + self.getId() + '" style="width: 100%; min-width:200px; max-width:100%; height: 280px; margin-top: 2px;"></div>').prependTo(container);
                    // init map
                    var map = new L.Map(mapContainer[0], {loadingControl: true});
                    L.tileLayer('//{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
                    }).addTo(map);
                    map.setView(new L.latLng(0, 0), 1);

                    var userMarker = self.getUserMarker().init(map);
                    if (self.data && self.data.latitude) {
                        userMarker.set(self.data.latitude, self.data.longitude);
                    }

                    if(!self.isDisplayOnly()) {

                        var inputGroup = $('<div class="input-group"></div>');
                        var searchInput = $('<input class="form-control osm-search-map" type="text" placeholder="" value=""/>').appendTo(inputGroup);
                        var inputGroupButtonContainer = $('<div class="input-group-btn"></div>');
                        var searchButton = $('<button type="button" class="btn btn-default"><span class="glyphicon glyphicon-search"></span></button>').appendTo(inputGroupButtonContainer);
                        var locationButton = $('<button type="button" class="btn btn-default"><span class="glyphicon glyphicon-map-marker"></span></button>').appendTo(inputGroupButtonContainer);
                        var mapButton = $('<button type="button" class="btn btn-default"><i class="fa fa-map"></i></button>').appendTo(inputGroupButtonContainer);
                        inputGroupButtonContainer.appendTo(inputGroup);
                        inputGroup.prependTo(container);

                        mapContainer.hide();

                        mapButton.bind('click', function (e) {
                            e.preventDefault();
                            mapContainer.toggle();
                            map.invalidateSize(false);
                        });

                        searchButton.bind('click', function (e) {
                            //mapContainer.hide();
                            e.preventDefault();
                            var query = searchInput.val();
                            userMarker.search(query, function (results) {
                                userMarker.resetMakers();
                                if (results.length > 0) {
                                    mapContainer.show();
                                    map.invalidateSize(false);
                                    $.each(results, function (index, result) {
                                        var number = index + 1;
                                        var latLng = new L.latLng(result.center.lat, result.center.lng);
                                        var marker = new L.marker(latLng, {
                                            icon: new L.MakiMarkers.icon({
                                                icon: "star",
                                                color: "#000"
                                            })
                                        });
                                        marker.on('click', function (e) {
                                            if (e.latlng !== undefined) {
                                                userMarker.moveIn(e.latlng.lat, e.latlng.lng);
                                            }
                                        });
                                        userMarker.addMarker(marker, false);
                                        if (results.length === 1){
                                            userMarker.moveIn(result.center.lat, result.center.lng);
                                        }
                                    });
                                    userMarker.fitBounds();
                                } else {
                                    mapContainer.hide();
                                    self.displayMessage(self.options.i18n.noResultsFinding+' "' + query + '", '+self.options.i18n.tryToRefineYourSearch);
                                }
                            });
                        });

                        var locationerror = false;
                        locationButton.bind('click', function (e) {
                            map.loadingControl.addLoader('lc');
                            map.locate({setView: true, watch: false})
                                .on('locationfound', function (e) {
                                    map.loadingControl.removeLoader('lc');
                                    mapContainer.show();
                                    map.invalidateSize(false);
                                    userMarker.moveIn(e.latitude, e.longitude);
                                })
                                .on('locationerror', function (e) {
                                    map.loadingControl.removeLoader('lc');
                                    if (!locationerror) {
                                        locationerror = true;
                                        self.displayMessage(e.message);
                                    }
                                });
                            e.preventDefault();
                        });

                        map.on('click', function (e) {
                            userMarker.moveIn(e.latlng.lat, e.latlng.lng);
                        });
                    }else{
                        map.on('click', function (e) {
                            map.invalidateSize(false);
                        });
                        window.setTimeout(function () {
                            map.invalidateSize(false);
                        }, 500);
                    }

                    callback();

                });
            },

            getUserMarker : function(){

                var self = this;

                return {
                    "lat": 0,
                    "lng": 0,
                    "map": null,
                    "marker": null,
                    "markers": null,
                    "geocoder": function () {
                        return new L.Control.Geocoder.Nominatim();
                    },
                    "search": function (query, cb, context) {
                        this.map.loadingControl.addLoader('sc');
                        var that = this;
                        this.geocoder().geocode(query, function (results) {
                            cb.call(context, results);
                            that.map.loadingControl.removeLoader('sc');
                        });
                    },
                    "init": function (map, lat, lng) {
                        this.lat = lat || 0;
                        this.lng = lng || 0;
                        this.map = map;
                        this.marker = new L.marker(
                            new L.latLng(this.lat, this.lng), {
                                icon: new L.MakiMarkers.icon({
                                    icon: "star",
                                    color: "#f00",
                                    size: "l"
                                }),
                                draggable: true
                            }
                        );
                        var that = this;
                        this.marker.on('dragend', function (event) {
                            var position = event.target.getLatLng();
                            that.moveIn(position.lat, position.lng);
                        });
                        this.markers = new L.markerClusterGroup();
                        return this;
                    },
                    "resetMakers": function () {
                        this.markers.clearLayers();
                        return this;
                    },
                    "addMarker": function (marker, fit) {
                        this.markers.addLayer(marker).addTo(this.map);
                        if (fit) this.map.fitBounds(this.markers.getBounds());
                        return this;
                    },
                    "fitBounds": function () {
                        this.map.fitBounds(this.markers.getBounds());
                        return this;
                    },
                    "set": function (lat, lng) {
                        this.lat = lat || 0;
                        this.lng = lng || 0;
                        var latLng = new L.latLng(this.lat, this.lng);
                        this.marker.setLatLng(latLng);
                        this.resetMakers().addMarker(this.marker,true);
                    },
                    "moveIn": function (lat, lng) {
                        this.set(lat, lng);
                        var latLng = new L.latLng(this.lat, this.lng);
                        this.map.loadingControl.addLoader('sc');
                        var that = this;
                        this.geocoder().reverse(latLng, 1, function (result) {
                            that.map.loadingControl.removeLoader('sc');
                            if (result.length > 0) {
                                self.setValue({
                                    latitude: result[0].properties.lat,
                                    longitude: result[0].properties.lon
                                });
                            }
                        });
                        return this;
                    }
                }
            }

        });

    Alpaca.registerFieldClass("openstreetmap", Alpaca.Fields.OpenStreetMap);

})(jQuery);
