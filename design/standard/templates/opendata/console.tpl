<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>OCQL Console</title>

    <link href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
    <script src="//code.jquery.com/jquery-1.12.0.min.js" type="application/javascript"></script>
    <link rel="stylesheet" href="/extension/ocopendata/design/standard/stylesheets/leaflet.css" />
    <link rel="stylesheet" href="/extension/ocopendata/design/standard/stylesheets/MarkerCluster.css">
    <link rel="stylesheet" href="/extension/ocopendata/design/standard/stylesheets/MarkerCluster.Default.css">
    <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css">

    <script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
    <script src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <script src="/extension/ocopendata/design/standard/javascript/leaflet.js"></script>
    <script src="/extension/ocopendata/design/standard/javascript/leaflet.markercluster.js"></script>
    <script src="/extension/ocopendata/design/standard/javascript/leaflet.makimarkers.js"></script>

    <style>
        {literal}
        .leaflet-div-icon {
            background: transparent;
            border: none;
        }

        .leaflet-marker-icon .number{
            background: #fff none repeat scroll 0 0;
            border-radius: 20px;
            color: #000;
            font-size: 1em;
            font-weight: bold;
            height: 21px;
            line-height: 21px;
            margin-left: 2px;
            position: relative;
            text-align: center;
            top: -37px;
            width: 21px;
        }
        {/literal}
    </style>

</head>

<body>

<div class="container">

    <h2 class="console">OCQL Console
        <small>beta version</small>
        <small class="pull-right"><i class="fa fa-map-marker"></i> <small id="geolocation"></small></small>
    </h2>

    <form id="search" action="{'opendata/console'|ezurl(no)}" method="GET">
        <div class="row">
            <div class="col-xs-11">
                <input id="query" class="form-control input-lg" type="text"
                       placeholder="q = 'Lorem ipsum' and keywords in [foo, bar] and published range [last month, today]"
                       name="query"
                       value="{$query|wash()}"/>
            </div>
            <div class="col-xs-1">
                <button class="btn btn-success btn-lg" type="submit">
                    <i class="fa fa-search" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </form>


    <div id='errors'role="alert">{if $error}{$error|wash()}{/if}</div>
    <div id="query-analysis" style="margin: 20px 0"></div>
    <div id="query-string" style="margin: 20px 0"></div>

    <div class="row">
        <div class="col-md-6">
            <div id="search-results" style="margin: 20px 0"></div>
        </div>
        <div class="col-md-6">
            <div id="geo-results" style="margin: 20px 0"></div>
            <div id="map" style="width: 100%; height: 400px"></div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-6">
            <h3>eZFind Query</h3>
            <pre id="ezfind-query"></pre>
        </div>
        <div class="col-sm-6">
            <h3>Extra results</h3>
            <pre id="facets-results"></pre>
        </div>
    </div>

    <hr />

    <h2>Classi</h2>
    <form id="class" action="{'opendata/console'|ezurl(no)}" method="GET">
        <div class="row">
            <div class="col-xs-11">
                <select class="form-control input-lg" name="class">
                    {foreach $classes as $class}
                        <option value="{$class|wash()}">{$class|wash()}</option>
                    {/foreach}
                </select>

            </div>
            <div class="col-xs-1">
                <button class="btn btn-success btn-lg" type="submit">
                    <i class="fa fa-search" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </form>

    <div id="class-result" style="margin: 20px 0"></div>


    {def $extendedAttributeFilters = ezini('ExtendedAttributeFilters', 'FiltersList', 'ezfind.ini')}

    <script>
    $(function() {ldelim}
        var analyzerEndpoint = "{'opendata/analyzer'|ezurl(no)}/";
        {if $use_current_user}
            var internal = true;
            var contentEndpoint = "{'opendata/api/content/read'|ezurl(no)}/";
            var searchEndpoint = "{'opendata/api/content/search'|ezurl(no)}/";
            var geoEndpoint = "{'opendata/api/geo/search'|ezurl(no)}/";
			var classEndpoint = "{'opendata/api/classes'|ezurl(no)}/";
        {else}
            var internal = false;
            var contentEndpoint = "/api/opendata/v2/content/read/";
            var searchEndpoint = "/api/opendata/v2/content/search/";
            var geoEndpoint = "/api/opendata/v2/geo/search/";
			var classEndpoint = "/api/opendata/v2/classes/";
        {/if}        
        var availableTokens = [{foreach $tokens as $token}'{$token}'{delimiter},{/delimiter}{/foreach}];
        {literal}

        // https://gist.github.com/comp615/2288108
        L.NumberedDivIcon = L.Icon.extend({
            options: {
                iconUrl: '/extension/ocopendata/design/standard/images/marker-icon.png',
                number: '',
                shadowUrl: null,
                iconSize: new L.Point(25, 41),
                iconAnchor: new L.Point(13, 41),
                popupAnchor: new L.Point(0, -33),
                /*
                 iconAnchor: (Point)
                 popupAnchor: (Point)
                 */
                className: 'leaflet-div-icon'
            },

            createIcon: function () {
                var div = document.createElement('div');
                var img = this._createImg(this.options['iconUrl']);
                var numdiv = document.createElement('div');
                numdiv.setAttribute ( "class", "number" );
                numdiv.innerHTML = this.options['number'] || '';
                div.appendChild ( img );
                div.appendChild ( numdiv );
                this._setIconStyles(div, 'icon');
                return div;
            },

            //you could change this to add a shadow like in the normal marker if you really wanted
            createShadow: function () {
                return null;
            }
        });

        var $searchContainers = {
            'queryString': $('#query-string'),
            'queryAnalysis': $('#query-analysis'),
            'results': $('#search-results'),
            'geoResults': $('#geo-results'),
            'ezfindQuery': $('#ezfind-query'),
            'facetsResults': $('#facets-results')
        };

        var $errors = $('#errors');

        var $forms = {
            'search': $('form#search'),
            'class': $('form#class')
        };
        var icon = 'button > i';

        var $classContainers ={
            'results': $('#class-result')
        };

        var loadError = function(data){
            $(icon).removeClass('fa-cog fa-spin');
            $('.fa-spinner').remove();
            $errors.append($('<p>'+data+'</p>')).addClass('alert alert-warning');
        };

        var clearError = function(){
            $errors.html('').removeClass('alert alert-warning');
        };

        var clearContainers = function(){
            $.each( $searchContainers, function(){
                this.html('')
            });
        };

        // search form
        var search = function( url ){
            clearContainers();
            clearError();
            var searchQuery = url.replace(searchEndpoint,'');
            var geoUrl = geoEndpoint+searchQuery;

            // parte lo spinner
            $(icon,$forms.search).addClass('fa-cog fa-spin');
            $searchContainers.queryAnalysis.html('<i class="fa fa-spinner fa-spin fa-3x"></i>');
            $.ajax({
                type: "GET",
                url: analyzerEndpoint,
                data: {query:searchQuery},
                contentType: "application/json; charset=utf-8",
                dataType: "json",
                success: function(data) {
                    if( 'error_message' in data )
                        loadError(data.error_message);
                    else if( data.length == 0 )
                        loadError("ANALISI: L'analisi della query non ha portato risultati");
                    else {
                        markers.clearLayers();
                        $searchContainers.results.html('<i class="fa fa-spinner fa-spin fa-3x"></i>');
                        $searchContainers.geoResults.html('<i class="fa fa-spinner fa-spin fa-3x"></i>');
                        loadAnalysisResults(data);
                        contentSearch(url);
                        geoSearch(geoUrl);
                    }
                },
                error: function(data){
                    var error = data.responseJSON;
                    loadError("ANALISI: "+error.error_message);
                }
            });
        };

        var contentSearch = function(url){
            $.ajax({
                type: "GET",
                url: url,
                contentType: "application/json; charset=utf-8",
                dataType: "json",
                success: function(response) {
                    if( 'error_message' in response )
                        loadError("CONTENT: "+response.error_message);
                    else {
                        loadSearchResults(url,response);
                    }
                },
                error: function(data){
                    var error = data.responseJSON;
                    loadError("CONTENT: "+error.error_message);
                }
            });
        };

        var geoSearch = function(geoUrl){
            $.ajax({
                type: "GET",
                url: geoUrl,
                contentType: "application/json; charset=utf-8",
                dataType: "json",
                success: function(response) {
                    if( 'error_message' in response )
                        loadError('GEO: '+response.error_message);
                    else {

                        $.ajax({
                            url: 'http://geojsonlint.com/validate',
                            type: 'POST',
                            data: JSON.stringify(response),
                            dataType: 'json',
                            success: function(validatorData) {
                                if (validatorData.status === 'error') {
                                    loadError('GEO: There was a problem with your GeoJSON: ' + validatorData.message);
                                }
                            },
                            error: function(){loadError('GEO: Problema con validatore geojson');}
                        });

                        var searchQuery = geoUrl.replace(geoEndpoint,'');
                        var content = '<strong>API /geo</strong>:<br/> <a href="'+geoUrl+'"><code>'+decodeURIComponent(searchQuery)+'</code></a>';
                        if ( response.features.length > 0 ) {
                            total = response.features.length + markers.getLayers().length;
                            content += '<h3>Visualizzati ' + total + ' su ' +response.totalCount+ ' marker ';
                            if ( response.nextPageQuery !== null )
                                content += '<a href="#" data-query="'+response.nextPageQuery+'" class="geosearch">(ancora)</a></h3>';
                            $searchContainers.geoResults.html(content);
                            var geoJsonLayer = L.geoJson(response,{
                                pointToLayer: function(feature, latlng) {
                                    if( 'index' in feature.properties )
                                        return L.marker(latlng, {icon: new L.NumberedDivIcon({number: feature.properties.index})});
                                    else
                                        return L.marker(latlng);
                                },
                                onEachFeature: function(feature, layer) {
                                    var popup = '<a href="/content/view/full/'+feature.properties.mainNodeId+'" target="_blank"><strong>';
                                    popup += feature.properties.name;
                                    popup += '</strong></a><br />';
                                    popup += $.map(feature.geometry.coordinates.reverse(), function(val,index) {return val;}).join(",");
                                    layer.bindPopup(popup);
                                }
                            });
                            markers.addLayer(geoJsonLayer);
                            if (typeof userMarker == 'object') {
                                var group = new L.FeatureGroup([markers, userMarker]);
                                map.fitBounds(group.getBounds());
                            }else {
                                map.fitBounds(markers.getBounds());
                            }
                        }else{
                            content += '<h3>Nessun risultato</h3>';
                            $searchContainers.geoResults.html(content);
                        }
                    }
                },
                error: function(data){
                    var error = data.responseJSON;
                    loadError('GEO: '+error.error_message);
                }
            });
        };

        var loadAnalysisResults = function(data){
            var content = '<h4>Analisi della query</h4>';
            var writeFilter = function(item){
                content += '<span class="field label label-success" data-toggle="tooltip" data-placement="top" title="Campo">'+item.field+'</span> ';
                content += '<span class="operator label label-default" data-toggle="tooltip" data-placement="top" title="Operatore">'+item.operator+'</span> ';
                content += '<span class="value label label-info" data-toggle="tooltip" data-placement="top" title="Valore ('+item.format+')">'+item.value+'</span> ';
            };
            var writeClause = function(item){
                content += '<span class="clause label label-danger" data-toggle="tooltip" data-placement="top" title="Clausola logica">'+item.value+'</span> ';
            };
            var writeParenthesis = function(item){
                content += '<span class="parenthesis label label-default" data-toggle="tooltip" data-placement="top" title="Parentesi">'+item.value+'</span> ';
            };
            var writeParameter = function(item){
                content += '<span class="key label label-warning" data-toggle="tooltip" data-placement="top" title="Parametro">'+item.key+'</span> ';
                content += '<span class="value label label-info" data-toggle="tooltip" data-placement="top" title="Valore ('+item.format+')">'+item.value+'</span> ';
                if ( item.key == 'geosort' ){
                    setUserMarker(new L.latLng(eval(item.value)));
                }
            };
            $.each(data.analysis,function(){
                if( this.type == 'filter' ) writeFilter(this);
                else if( this.type == 'clause' ) writeClause(this);
                else if( this.type == 'parenthesis' ) writeParenthesis(this);
                else if( this.type == 'parameter' ) writeParameter(this);
            });
            $searchContainers.queryAnalysis.html( content );
            console.log(data.ezfind);
            $searchContainers.ezfindQuery.html(JSON.stringify(data.ezfind, null, 4));
        };

        var loadSearchResults = function(url,data){
            $(icon).removeClass('fa-cog fa-spin');
            $searchContainers.facetsResults.html('Facets: ' + JSON.stringify(data.facets, null, 4));
            if (data.ranges)
                $searchContainers.facetsResults.append("\nRanges: " + JSON.stringify(data.ranges, null, 4));
            {/literal}
            {foreach $extendedAttributeFilters as $filter => $class}
            if (data.{$filter})
                $searchContainers.facetsResults.append("\n{$filter}: " + JSON.stringify(data.{$filter}, null, 4));
            {/foreach}
            {literal}
            var results = data.searchHits;
            var searchQuery = url.replace(searchEndpoint,'');
            var content = '<strong>API /content</strong>:<br/> <a href="'+url+'"><code>'+decodeURIComponent(searchQuery)+'</code></a>';
            if ( results.length > 0 ) {
                content += '<h3>Visualizzati ' + results.length + ' su ' +data.totalCount+ ' risultati ';
                if ( data.nextPageQuery !== null ) {
                    var prefix = internal ? searchEndpoint : '';
                    content += '<a href="#" data-query="' + prefix + data.nextPageQuery + '" class="search">(pagina successiva)</a></h3>';
                }else
                    content += '</h3>';
                content += '<div style="max-height: 400px; overflow: scroll;"><ul class="list-group">';
                $.each(results, function(){
                    if( this.metadata.classIdentifier != null ) {
                        content += '<li class="list-group-item">';
                        content += '<a href="/content/view/full/'+this.metadata.mainNodeId+'" target="_blank"><strong>';
                        content += this.metadata.name['ita-IT'];
                        content += '</strong></a>';
                        content += ' ['+this.metadata.classIdentifier+']';
                        content += '<br /><small>';
                        var published = new Date(Date.parse(this.metadata.published));
                        var modified = new Date(Date.parse(this.metadata.modified));
                        content += 'ID: <a href="'+contentEndpoint+this.metadata.id+'">'+this.metadata.id+'</a> - Pubblicato il: '+ published.toDateString();
                        content += ' - Ultima modifica di: '+ modified.toDateString();
                        content += '</small>';
                    }
                });
                content += '</ul></div>';
            }else{
                content += '<h3>Nessun risultato</h3>';
            }
            $searchContainers.results.html(content);
            $('[data-toggle="tooltip"]').tooltip();
        };
        $(document).on( 'click', 'a.search', function(e){
            $searchContainers.results.html('<i class="fa fa-spinner fa-spin fa-3x"></i>');
            contentSearch($(e.currentTarget).data('query'));
            e.preventDefault();
        });
        $(document).on( 'click', 'a.geosearch', function(e){
            $searchContainers.geoResults.html('<i class="fa fa-spinner fa-spin fa-3x"></i>');
            geoSearch($(e.currentTarget).data('query'));
            e.preventDefault();
        });
        $forms.search.submit( function(e){
            var query = $forms.search.find('input').val();
            search(searchEndpoint+encodeURIComponent(query));
            e.preventDefault();
        });

        // class form
        var searchClass = function( url ) {
            $(icon, $forms.class).addClass('fa-cog fa-spin');
            $classContainers.results.html('');
            clearError();
            $.ajax({
                type: "GET",
                url: url,
                contentType: "application/json; charset=utf-8",
                dataType: "json",
                success: function (data) {
                    if ('error_message' in data)
                        loadError('CLASS: '+data.error_message);
                    else
                        loadClass(data);
                },
                error: function (data) {
                    var error = data.responseJSON;
                    loadError('CLASS: '+error.error_message);
                }
            });
        };
        var loadClass = function(data){
            var content = '<table class="table table-striped">';
            content += '<tr>';
            content += '<th>Identificatore</th>';
            content += '<th>Nome</th>';
            content += '<th>Descrizione</th>';
            content += '<th>Datatype</th>';
            content += '</tr>';
            $.each(data.fields, function(){
                if ( this.isSearchable ) {
                    content += '<tr>';
                    content += '<td>' + this.identifier + '</td>';
                    content += '<td>' + $.map(this.name, function(val,index) {return val;}).join(", ") + '</td>';
                    content += '<td>' + $.map(this.description, function(val,index) {return val;}).join(", ") + '</td>';
                    content += '<td>' + this.dataType + '</td>';
                    content += '</tr>';
                }
            });
            content += '</table>';
            $classContainers.results.html(content);
            $(icon, $forms.class).removeClass('fa-cog fa-spin');
        };
        $forms.class.submit( function(e){
            var identifier = $forms.class.find('select option:selected').val();
            searchClass(classEndpoint+identifier);
            e.preventDefault();
        });


        // autocomplete
        function split( val ) {
            return val.split( ' ' );
        }
        function extractLast( term ) {
            return split( term ).pop();
        }
        $forms.search.find('input.austosuggest')
            .bind( "keydown", function( event ) {
                if ( event.keyCode === $.ui.keyCode.TAB &&
                        $( this ).autocomplete( "instance" ).menu.active ) {
                    event.preventDefault();
                }
            })
            .autocomplete({
                minLength: 0,
                source: function( request, response ) {
                    response( $.ui.autocomplete.filter(
                            availableTokens, extractLast( request.term ) ) );
                },
                focus: function() {
                    return false;
                },
                select: function( event, ui ) {
                    var terms = split( this.value );
                    terms.pop();
                    terms.push( ui.item.value );
                    terms.push( "" );
                    this.value = terms.join( " " );
                    return false;
                }
            });

        var map = L.map('map').setView([0, 0], 1);
        L.tileLayer('//{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        var markers = L.markerClusterGroup();
        map.addLayer(markers);
        var userMarker;
        map.on('click', function (e) {
            setUserMarker(e.latlng);
        });
        var setUserMarker = function(latlng){
            var customIcon = L.MakiMarkers.icon({icon: "star", color: "#f00", size: "l"});
            if (typeof userMarker != 'object')
                userMarker = new L.marker(latlng,{icon: customIcon});
            userMarker.setLatLng(latlng);
            userMarker.addTo(map);
            map.addLayer(userMarker);
            userMarker.bindPopup(latlng.lat+','+latlng.lng);
            $('#geolocation').html( latlng.lat+','+latlng.lng);
        };

        $('.fa-map-marker').bind( 'click', function(){
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position){
                    setUserMarker(new L.latLng(position.coords.latitude,position.coords.longitude) );
                });
            }
        });

        {/literal}
    });
    </script>

</div>


{*fetch( 'ezfind', 'search', hash(
       'limit', 1,
       'extended_attribute_filter', array(
           hash(
            'id', 'stats',
            'params', hash(
                'field', 'meta_section_id_si',
                'facet', array('meta_owner_id_si', 'extra_project_name_s')
            )
           )
       )
)).SearchExtras|attribute(show,4)*}

</body>
</html>
