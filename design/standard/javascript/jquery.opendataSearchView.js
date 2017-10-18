;(function ($, window, document, undefined) {

    var defaults = {
        query: 'classes [example]',
        filters: [],
        onInit: null,
        onBeforeSearch: null,
        onAfterSearch: null,
        onBuildQuery: null,
        onLoadResults: null,
        onClearResults: null,
        onLoadErrors: null,
        onClearErrors: null,
        debug: false
    };

    var defaultsFilter = {
        name: null,
        container: $(),
        init: function (container, view) {
        },
        setCurrent: function () {
        },
        getCurrent: function () {
        },
        buildQuery: function () {
        },
        buildQueryFacet: function () {
        },
        refresh: function () {
        },
        reset: function () {
        }
    };

    function OpendataSearchView(element, options) {
        this.query = '';
        this.element = element;
        this.container = $(element);
        this.options = $.extend({}, defaults, options);

        var filters = [];
        $.each(this.options.filters, function () {
            filters.push($.extend({}, defaultsFilter, this));
        });
        this.filters = filters;

        this._defaults = defaults;
        this._defaultsFilter = defaultsFilter;
        //this.init();
    }

    OpendataSearchView.prototype = {

        log: function (text) {
            if (window.console && this.options.debug == true) {
                window.console.log('OpendataSearchView: ' + text);
            }
        },

        init: function () {
            var self = this;
            self.log('start init');
            if ($.isFunction(self.options.onLoadErrors)) {
                $.opendataTools.settings('onError', function (errorCode, errorMessage, jqXHR) {
                    self.loadErrors(errorCode, errorMessage, jqXHR);
                });
            }

            $.when($.each(self.filters, function (i) {
                self.log(JSON.stringify(this));
                self.log(this.name + ' filter init');
                this.init(self, this);
            })).then(function () {
                if ($.isFunction(self.options.onInit)) {
                    self.log('global init');
                    self.options.onInit(self);
                }
                self.log('end init');
            });
            return self;
        },

        buildQueryParts: function (data) {
            var filters = $.map(data.filters, function(val,index) {
                return typeof val == 'string' ? val : null;
            }).join(' and ');

            var facets = '';
            var facetsParts = $.map(data.facets, function(val,index) {
                return typeof val == 'string' ? val : null;
            });
            if (facetsParts.length > 0){
                facets = 'facets [' + facetsParts.join(',') + ']';
            }
            return filters + ' ' + data.base + ' ' + facets;
        },

        buildQuery: function () {
            var self = this;
            self.log('start buildQuery');
            var queryParts = {};
            var facetsParts = {};
            var addQuery = null;
            var addFacet = null;

            $.when($.each(self.filters, function (i) {
                addQuery = this.buildQuery(queryParts);
                addFacet = this.buildQueryFacet(facetsParts);
                self.log(this.name + ' filter buildQuery: ' + addQuery);
                self.log(this.name + ' filter buildQueryFacet: ' + addFacet);
                if (typeof addQuery == 'string') {
                    queryParts[this.name] = addQuery;
                }
                if (typeof addFacet == 'string') {
                    facetsParts[this.name] = addFacet;
                }
            })).then(function(){
                if ($.isFunction(self.options.onBuildQuery)) {
                    self.log('global buildQuery');
                    addQuery = self.options.onBuildQuery(queryParts);
                    if (typeof addQuery == 'string') {
                        queryParts['global'] = addQuery;
                    }
                }
            });

            var data = {
                filters: queryParts,
                facets: facetsParts,
                base: self.options.query
            };

            self.query = self.buildQueryParts(data);

            self.log('end buildQuery');
            return data;
        },

        getQuery: function () {
            return this.query;
        },

        appendSearch: function (query) {
            return this.doSearch(query, true);
        },

        doSearch: function (query, appendResults) {
            var self = this;
            self.log('start doSearch');
            $.each(this.filters, function () {
                self.log(this.name + ' filter reset');
                this.reset(self);
            });

            appendResults = appendResults || false;
            if (typeof query === 'undefined') {
                self.buildQuery();
                query = self.query;
                if ($.isFunction(self.options.onBeforeSearch)) {
                    self.log('onBeforeSearch');
                    self.options.onBeforeSearch(query, self);
                }
            }
            self.clearErrors();
            self.clearResults();

            self.log(query);

            $.opendataTools.find(query, function (response) {
                $.when($.each(self.filters, function (i) {
                    self.log(this.name + ' filter refresh');
                    this.refresh(response, self);
                })).then(function(){
                    self.loadResults(response, query, appendResults);
                    if ($.isFunction(self.options.onAfterSearch)) {
                        self.options.onAfterSearch(query, self);
                    }
                    self.log('end doSearch');
                });
            });

            return self;
        },

        loadResults: function (response, query, appendResults) {
            var self = this;
            self.log('start loadResults');
            if ($.isFunction(self.options.onLoadResults)) {
                self.options.onLoadResults(response, query, appendResults, self);
            }
            self.log('end loadResults');
            return self;
        },

        clearResults: function () {
            if ($.isFunction(this.options.onClearResults)) {
                this.options.onClearResults(this);
            }
            return this;
        },

        loadErrors: function (errorCode, errorMessage, jqXHR) {
            if ($.isFunction(this.options.onLoadErrors)) {
                this.options.onLoadErrors(errorCode, errorMessage, jqXHR, this);
            }
            return this;
        },

        clearErrors: function () {
            if ($.isFunction(this.options.onClearErrors)) {
                this.options.onClearErrors(this);
            }
            return this;
        },

        addFilter: function (filter) {
            var self = this;
            var newFilter = $.extend({}, self._defaultsFilter, filter);
            self.filters.push(newFilter);
            return self;
        },

        setFilterValue: function (filter, value) {
            var self = this;
            $.each(self.filters, function (i) {
                if (filter == this.name) {
                    self.log(this.name + ' filter set value ' + value);
                    this.setCurrent(value);
                }
            })
        }

    };

    $.fn['opendataSearchView'] = function (options) {
        return this.each(function () {
            if (!$.data(this, 'opendataSearchView')) {
                $.data(this, 'opendataSearchView', new OpendataSearchView(this, options));
            }
        });
    };

})(jQuery, window, document);
