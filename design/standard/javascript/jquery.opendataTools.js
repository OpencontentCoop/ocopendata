;(function ($) {

    var OpendataTools = function (options) {

        var defaults = {
            language: 'ita-IT',
            fallbackLanguage: 'ita-IT',
            accessPath: '/',
            endpoint: {
                geo: '/opendata/api/geo/search/',
                search: '/opendata/api/content/search/',
                class: '/opendata/api/classes/',
                tags_tree: '/opendata/api/tags_tree/'
            },
            onError: function(errorCode,errorMessage,jqXHR){
                console.log(errorMessage + ' (error: '+errorCode+')');
            }
        };

        var settings = $.extend({}, defaults, options);

        var map, markers, userMarker, markerBuilder, lastMapQuery;

        var detectError = function(response,jqXHR){
            if(response.error_message || response.error_code){
                if ($.isFunction(settings.onError)) {
                    settings.onError(response.error_code, response.error_message,jqXHR);
                }
                return true;
            }
            return false;
        };

        var setUserMarker = function(latlng, cb, context){
            var customIcon = L.MakiMarkers.icon({icon: "star", color: "#f00", size: "l"});
            if (typeof userMarker != 'object')
                userMarker = new L.marker(latlng,{icon: customIcon});
            userMarker.setLatLng(latlng);
            userMarker.addTo(map);
            map.addLayer(userMarker);
            if ($.isFunction(cb)) {
                cb.call(context, userMarker);
            }
            //$('#geolocation').html( latlng.lat+','+latlng.lng);
            //if ($.isFunction(markerBuilder) && lastMapQuery){
            //    geoJsonFind(lastMapQuery + ' geosort ['+latlng.lat+','+latlng.lng+'] limit 10', function (response) {
            //        if (response.features.length > 0) {
            //            markers.clearLayers();
            //            var geoJsonLayer = markerBuilder(response);
            //            markers.addLayer(geoJsonLayer);
            //            if (typeof userMarker == 'object') {
            //                var group = new L.FeatureGroup([markers, userMarker]);
            //                map.fitBounds(group.getBounds());
            //            } else {
            //                map.fitBounds(markers.getBounds());
            //            }
            //        }
            //    });
            //}
        };

        var loadMarkersInMap =  function(query, onLoad, geoJsonBuilder, context){
            if (map) {
                markers.clearLayers();
                lastMapQuery = query;
                geoJsonFindAll(query, function (response) {
                    if (response.features.length > 0) {
                        var geoJsonLayer = $.isFunction(geoJsonBuilder) ? geoJsonBuilder(response) : markerBuilder(response);
                        markers.addLayer(geoJsonLayer);
                        if (typeof userMarker == 'object') {
                            var group = new L.FeatureGroup([markers, userMarker]);
                            map.fitBounds(group.getBounds());
                        } else {
                            map.fitBounds(markers.getBounds());
                        }
                        if ($.isFunction(onLoad)) {
                            onLoad.call(context, response);
                        }
                    }
                });
            }
        };

        var loadAndCacheMarkersInMap =  function(query, onLoad, geoJsonBuilder, context){
            if (map) {
                markers.clearLayers();
                lastMapQuery = query;
                geoJsonCacheAll(query, function (response) {
                    if (response.features.length > 0) {
                        var geoJsonLayer = $.isFunction(geoJsonBuilder) ? geoJsonBuilder(response) : markerBuilder(response);
                        markers.addLayer(geoJsonLayer);
                        if (typeof userMarker == 'object') {
                            var group = new L.FeatureGroup([markers, userMarker]);
                            map.fitBounds(group.getBounds());
                        } else {
                            map.fitBounds(markers.getBounds());
                        }
                        if ($.isFunction(onLoad)) {
                            onLoad.call(context, response);
                        }
                    }
                });
            }
        };

        var find = function (query, cb, context) {
            $.ajax({
                type: "GET",
                url: settings.endpoint.search,
                data: {q: query},
                contentType: "application/json; charset=utf-8",
                dataType: "json",
                success: function (data,textStatus,jqXHR) {
                    if (!detectError(data,jqXHR)){
                        cb.call(context, data);
                    }
                },
                error: function (jqXHR) {
                    var error = {
                        error_code: jqXHR.status,
                        error_message: jqXHR.statusText
                    };
                    detectError(error,jqXHR);
                }
            });
        };

        var findOne = function (query, cb, context) {

            $.ajaxPrefilter(function (options, originalOptions, jqXHR) {
                if (options.cache && window.sessionStorage !== undefined) {
                    var success = originalOptions.success || $.noop,
                        url = JSON.stringify(originalOptions.data);

                    options.cache = false; //remove jQuery cache as we have our own sessionStorage
                    options.beforeSend = function () {
                        if (sessionStorage.getItem(url)) {
                            success(JSON.parse(sessionStorage.getItem(url)));
                            return false;
                        }
                        return true;
                    };
                    options.success = function (data, textStatus) {
                        var responseData = JSON.stringify(data);
                        sessionStorage.setItem(url, responseData);
                        if ($.isFunction(success)) success(data); //call back to original ajax call
                    };
                }
            });

            $.ajax({
                type: "GET",
                url: settings.endpoint.search,
                data: {q: query},
                contentType: "application/json; charset=utf-8",
                dataType: "json",
                cache: true,
                success: function (data,textStatus,jqXHR) {
                    if (!detectError(data,jqXHR)){
                        cb.call(context, data.searchHits[0]);
                    }
                },
                error: function (jqXHR) {
                    var error = {
                        error_code: jqXHR.status,
                        error_message: jqXHR.statusText
                    };
                    detectError(error,jqXHR);
                }
            });
        };

        var findAll = function (query, cb, context) {
            var collectData = [];
            var getSubRequest = function (query) {
                find(query, function (data) {
                    parseSubResponse(data);
                })
            };
            var parseSubResponse = function (response) {
                $.each(response.searchHits, function () {
                    collectData.push(this);
                });
                if (response.nextPageQuery) {
                    getSubRequest(response.nextPageQuery);
                } else {
                    cb.call(context, collectData);
                }
            };
            getSubRequest(query);
        };

        var cacheAllItems = {
            items: [],
            instance: function (id, generator, cb, context) {
                for (var i = 0, len = this.items.length; i < len; i++) {
                    if (this.items[i].id === id) {
                        if ($.isFunction(cb)) {
                            cb.call(context, this.items[i].data);
                            return true;
                        }
                    }
                }
                var newItem = generator();
                this.items.push(newItem);
                newItem.load(cb, context);
            }
        };

        var contentClass = function (query, cb, context) {

            $.ajaxPrefilter(function (options, originalOptions, jqXHR) {
                if (options.cache && window.sessionStorage !== undefined) {
                    var success = originalOptions.success || $.noop,
                        url = originalOptions.url;

                    options.cache = false; //remove jQuery cache as we have our own sessionStorage
                    options.beforeSend = function () {
                        if (sessionStorage.getItem(url)) {
                            success(JSON.parse(sessionStorage.getItem(url)));
                            return false;
                        }
                        return true;
                    };
                    options.success = function (data, textStatus) {
                        var responseData = JSON.stringify(data);
                        sessionStorage.setItem(url, responseData);
                        if ($.isFunction(success)) success(data); //call back to original ajax call
                    };
                }
            });

            $.ajax({
                type: "GET",
                url: settings.endpoint.class + encodeURIComponent(query),
                contentType: "application/json; charset=utf-8",
                dataType: "json",
                cache: true,
                success: function (data,textStatus,jqXHR) {
                    if (!detectError(data,jqXHR)){
                        cb.call(context, data);
                    }
                },
                error: function (jqXHR) {
                    var error = {
                        error_code: jqXHR.status,
                        error_message: jqXHR.statusText
                    };
                    detectError(error,jqXHR);
                }
            });
        };

        var tagsTree = function (query, cb, context) {

            $.ajax({
                type: "GET",
                url: settings.endpoint.tags_tree + encodeURIComponent(query),
                contentType: "application/json; charset=utf-8",
                dataType: "json",
                cache: true,
                success: function (data,textStatus,jqXHR) {
                    if (!detectError(data,jqXHR)){
                        cb.call(context, data);
                    }
                },
                error: function (jqXHR) {
                    var error = {
                        error_code: jqXHR.status,
                        error_message: jqXHR.statusText
                    };
                    detectError(error,jqXHR);
                }
            });
        };

        var geoJsonFind = function (query, cb, context) {
            $.ajax({
                type: "GET",
                url: settings.endpoint.geo,
                data: {q: query},
                contentType: "application/json; charset=utf-8",
                dataType: "json",
                success: function (response,textStatus,jqXHR) {
                    if (!detectError(response,jqXHR)){
                        if ($.isFunction(cb)) {
                            cb.call(context, response);
                        }
                    }
                },
                error: function (jqXHR) {
                    var error = {
                        error_code: jqXHR.status,
                        error_message: jqXHR.statusText
                    };
                    detectError(error,jqXHR);
                }
            });
        };

        var geoJsonFindAll = function (query, cb, context) {
            var features = [];
            var getSubRequest = function (query) {
                geoJsonFind(query, function (data) {
                    parseSubResponse(data);
                })
            };
            var parseSubResponse = function (response) {
                if (response.features.length > 0) {
                    $.each(response.features, function () {
                        features.push(this);
                    });
                }
                if (response.nextPageQuery) {
                    getSubRequest(response.nextPageQuery);
                } else {
                    var featureCollection = {
                        'type': 'FeatureCollection',
                        'features': features
                    };
                    cb.call(context, featureCollection);
                }
            };
            getSubRequest(query);
        };

        var cacheAll = function(query, cb, context){
            cacheAllItems.instance(
                query,
                function(){
                    return {
                        id: query,
                        query: query,
                        data: null,
                        load: function (cb, context) {
                            if (sessionStorage.getItem(this.query)) {
                                this.data = JSON.parse(sessionStorage.getItem(this.query));
                                if ($.isFunction(cb)) {
                                    cb.call(context, this.data);
                                }
                            } else {
                                var that = this;
                                findAll(this.query, function (response) {
                                    that.data = response;
                                    sessionStorage.setItem(that.query, JSON.stringify(response));
                                    if ($.isFunction(cb)) {
                                        cb.call(context, that.data);
                                    }
                                });
                            }
                        }
                    };
                },
                cb, context
            );
        };

        var geoJsonCacheAll = function(query, cb, context){
            cacheAllItems.instance(
                query,
                function(){
                    return {
                        id: query,
                        query: query,
                        data: null,
                        load: function (cb, context) {
                            if (sessionStorage.getItem(this.query)) {
                                this.data = JSON.parse(sessionStorage.getItem(this.query));
                                if ($.isFunction(cb)) {
                                    cb.call(context, this.data);
                                }
                            } else {
                                var that = this;
                                geoJsonFindAll(this.query, function (response) {
                                    that.data = response;
                                    sessionStorage.setItem(that.query, JSON.stringify(response));
                                    if ($.isFunction(cb)) {
                                        cb.call(context, that.data);
                                    }
                                });
                            }
                        }
                    };
                },
                cb, context
            );
        };

        var formatDate = function (date, format) {
            return moment(new Date(date)).format(format);
        };

        var filterUrl = function (fullUrl) {
            if ($.isFunction(getSetSettings('filterUrl'))) {
                fullUrl = getSetSettings('filterUrl')(fullUrl);
            }
            return fullUrl;
        };

        var mainImageUrl = function (data) {
            var images = i18n(data, 'images');
            if (images.length > 0) {
                return getSetSettings('accessPath') + '/agenda/image/' + images[0].id;
            }
            var image = i18n(data, 'image');
            if (image) {
                return filterUrl(image.url);
            }
            return null;
        };

        var getSetSettings = function (key, value) {
            if (value)
                settings[key] = value;
            if (key)
                return settings[key];
            return settings;
        };

        var language = function(){
            return settings('language')
        };

        var i18n = function (data, key, fallbackLanguage) {
            var currentLanguage = getSetSettings('language');
            fallbackLanguage = fallbackLanguage || getSetSettings('fallbackLanguage');
            var languages = data ? Object.keys(data) : [];
            var veryFallbackLanguage = languages.shift();
            var returnData = false;
            if (data && key) {
                if (typeof data[currentLanguage] != 'undefined' && data[currentLanguage][key]) {
                    returnData = data[currentLanguage][key];
                }
                else if (fallbackLanguage && typeof data[fallbackLanguage] != 'undefined' && data[fallbackLanguage][key]) {
                    returnData = data[fallbackLanguage][key];
                }
                else if (veryFallbackLanguage && typeof data[veryFallbackLanguage] != 'undefined' && data[veryFallbackLanguage][key]) {
                    returnData = data[veryFallbackLanguage][key];
                }
            } else if (data) {
                if (typeof data[currentLanguage] != 'undefined' && data[currentLanguage]) {
                    returnData = data[currentLanguage];
                }
                else if (fallbackLanguage && typeof data[fallbackLanguage] != 'undefined' && data[fallbackLanguage]) {
                    returnData = data[fallbackLanguage];
                }
                else if (veryFallbackLanguage && typeof data[veryFallbackLanguage] != 'undefined' && data[veryFallbackLanguage]) {
                    returnData = data[veryFallbackLanguage];
                }
            }
            return returnData != 0 ? returnData : false;
        };

        var editLink = function(metadata){
          var keys = $.map(metadata.name, function (value, key) {
              return key;
          });
          var string = '';
          var languages = getSetSettings('languages');
          var length = languages.length;
          for (var i = 0; i < length; i++) {
              if ($.inArray(languages[i], keys) >= 0) {                  
                  string += '<a href="' + getSetSettings('accessPath') + '/content/edit/' + metadata.id + '/f/'+languages[i]+'"><img src="/share/icons/flags/' + languages[i] + '.gif" /></a> ';
              } else {
                  string += '<a href="' + getSetSettings('accessPath') + '/content/edit/' + metadata.id + '/a"><img style="opacity:0.2" src="/share/icons/flags/' + languages[i] + '.gif" /></a> ';
              }
          }
          return string;
        };
        
        var removeLink = function(metadata, redirect){
          return ' <form method="post" action="' + getSetSettings('accessPath') + '/content/action" style="display: inline;"><button class="btn btn-link btn-xs" type="submit" name="ActionRemove"><i class="fa fa-trash" style="font-size: 12px;"></i></button><input name="ContentObjectID" value="' + metadata.id + '" type="hidden"><input name="NodeID" value="' + metadata.mainNodeId + '" type="hidden"><input name="ContentNodeID" value="' + metadata.mainNodeId + '" type="hidden"><input name="RedirectIfCancel" value="'+redirect+'" type="hidden"><input name="RedirectURIAfterRemove" value="'+redirect+'" type="hidden"></form> ';
        }

        return {

            settings: function (key, value) {
                return getSetSettings(key, value);
            },

            geoJsonFind: function (query, cb, context) {
                return geoJsonFind(query, cb, context);
            },

            geoJsonFindAll: function (query, cb, context) {
                return geoJsonFindAll(query, cb, context);
            },

            find: function (query, cb, context) {
                return find(query, cb, context);
            },

            findOne: function (query, cb, context) {
                return findOne(query, cb, context);
            },

            findAll: function (query, cb, context) {
                return findAll(query, cb, context);
            },

            cacheAll: function (query, cb, context) {
                cacheAll(query, cb, context);
            },

            geoJsonCacheAll: function (query, cb, context) {
                geoJsonCacheAll(query, cb, context);
            },

            contentClass: function (query, cb, context) {
                return contentClass(query, cb, context);
            },

            tagsTree: function (query, cb, context) {
                return tagsTree(query, cb, context);
            },

            clearCache: function (startsWith) {
                startsWith = startsWith || settings.endpoint.class;
                var myLength = startsWith.length;

                Object.keys(sessionStorage)
                    .forEach(function (key) {
                        if (key.substring(0, myLength) == startsWith) {
                            sessionStorage.removeItem(key);
                        }
                    });
            },

            buildFacetsString: function (facets) {
                var facetStringList = [];
                $.each(facets, function () {
                    facetStringList.push(this.field + '|' + this.sort + '|' + this.limit);
                });
                return facetStringList.join(',');
            },

            buildFilterInput: function (facets, facet, datatable, cb, context) {
                datatable.buildFilterInput(facets, facet, cb, context);
            },

            refreshFilterInput: function (facet, cb, context) {
                var select = $('[data-field="' + facet.name + '"]');
                var data = facet.data;
                select.find('option').attr('disabled', 'disabled');
                select.find('option[value=""]').removeAttr('disabled');
                $.each(data, function (value, count) {
                    if (value.length > 0) {
                        var quotedValue = facet.name.search("extra_") > -1 ? encodeURIComponent('"' + value + '"') : value;
                        var xpath = 'option[value="' + quotedValue + '"]';
                        var newText = value + ' (' + count + ')';
                        $(xpath, select).text(newText).removeAttr('disabled').show();
                    }
                });
                if ($.isFunction(cb)) {
                    cb.call(context, select);
                }
            },

            initMap: function(id, cb){
                markerBuilder = cb;
                map = L.map('map').setView([0, 0], 1);
                L.tileLayer('//{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'}).addTo(map);
                markers = L.markerClusterGroup();
                map.addLayer(markers);
                return map;
            },

            initUserMarker: function(cb, context){
                if (map) {
                    map.on('click', function (e) {
                        setUserMarker(e.latlng, cb, context);
                    });
                    $('.fa-map-marker').bind( 'click', function(){
                        if (navigator.geolocation) {
                            navigator.geolocation.getCurrentPosition(function(position){
                                setUserMarker(new L.latLng(position.coords.latitude,position.coords.longitude), cb, context);
                            });
                        }
                    });
                }
            },

            setUserMarker: function(latlng, cb, context){
                setUserMarker(latlng, cb, context);
            },

            getMap: function(){
                return map;
            },

            loadMarkersInMap:  function(query, onLoad, geoJsonBuilder, context){
                loadMarkersInMap(query, onLoad, geoJsonBuilder, context);
            },

            loadAndCacheMarkersInMap:  function(query, onLoad, geoJsonBuilder, context){
                loadAndCacheMarkersInMap(query, onLoad, geoJsonBuilder, context);
            },

            refreshMap: function(){
                if (map) {
                    map.invalidateSize(false);
                    if (typeof userMarker == 'object') {
                        var group = new L.FeatureGroup([markers, userMarker]);
                        map.fitBounds(group.getBounds());
                    } else if( markers.getLayers().length > 0){
                        map.fitBounds(markers.getBounds());
                    }
                }
            },

            helpers: {
                'formatDate': function (date, format) {
                    return formatDate(date, format);
                },
                'filterUrl': function (fullUrl) {
                    return filterUrl(fullUrl);
                },
                'mainImageUrl': function (data) {
                    return mainImageUrl(data);
                },
                'settings': function (setting) {
                    return getSetSettings(setting);
                },
                'language': function (setting) {
                    return language(setting);
                },
                'i18n': function (data, key, fallbackLanguage) {
                    return i18n(data, key, fallbackLanguage);
                },
                'editLink': function (metadata) {
                    return editLink(metadata);
                },
                'removeLink': function (metadata, redirect) {
                    return removeLink(metadata, redirect);
                }
            }
        }
    };

    var OpendataToolsSingleton = (function () {
        var instance;

        function createInstance() {
            return OpendataTools();
        }

        return {
            getInstance: function () {
                if (!instance) {
                    instance = createInstance();
                }
                return instance;
            }
        };
    })();

    $.opendataTools = OpendataToolsSingleton.getInstance();

}(jQuery));
