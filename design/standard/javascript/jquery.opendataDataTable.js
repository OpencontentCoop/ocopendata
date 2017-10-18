(function ($, window, document, undefined) {
    "use strict";
    var pluginName = "opendataDataTable",
        defaults = {
            "builder": {
                "query": null,
                "filters": {}
            },
            "table": {
                "id": 'exemple',
                "template": '<table class="table table-striped table-bordered" cellspacing="0" width="100%"></table>'
            },
            "datatable": {
                "deferRender": true,
                "responsive": true,
                "processing": true,
                "serverSide": true,
                "ajax": {
                    serverSide: true
                },
                "columnDefs": [
                    {
                        "render": function (data, type, row, meta) {
                            var validDate = moment(data, moment.ISO_8601);
                            if (validDate.isValid()) {
                                return '<span style="white-space:nowrap">' + validDate.format("D MMMM YYYY, hh:mm") + '</span>';
                            } else {
                                return data;
                            }
                        },
                        "targets": '_all'
                    }
                ],
                "columns": []
            },
            "loadDatatableCallback": null
        };

    // The actual plugin constructor
    function OpendataDataTable(element, options) {
        this.element = element;
        this.settings = $.extend(true, {}, defaults, options);
        this._defaults = defaults;
        this._name = pluginName;
        this._ajaxUrl = this.settings.datatable.ajax.url;
        this.datatable = null;
        this.init();
    }

    $.extend(OpendataDataTable.prototype, {
        init: function () {
            //this.loadDataTable();
        },
        loadDataTable: function () {
            this.settings.datatable.prevQuery = this.settings.datatable.ajax.query;
            var buildedQuery = this.buildQuery();
            this.settings.datatable.ajax.url = this._ajaxUrl + '?q=' + buildedQuery;
            this.settings.datatable.ajax.query = buildedQuery;
            var id = this.settings.table.id;
            var table = $(this.settings.table.template).attr('id', id);
            $(this.element).append(table);
            if (this.datatable != null) {
                this.datatable.destroy(true);
            }
            this.datatable = table.DataTable(this.settings.datatable);
            if($.isFunction(this.settings.loadDatatableCallback)){
                this.settings.loadDatatableCallback(this);
            }
        },
        buildQuery: function (notEncoded) {
            var query = '';
            $.each(this.settings.builder.filters, function () {
                if (this != null) {
                    if ($.isArray(this.value)) {
                        query += this.field + " " + this.operator + " ['" + this.value.join("','") + "']";
                        query += ' and ';
                    }
                }
            });
            query += this.settings.builder.query;
            //console.log( ' -- Query: ' + query);
            return !notEncoded ? encodeURIComponent(query) : query;
        },
        buildFilterInput: function (facets, facet, cb, context) {
            var self = this;
            for (var i = 0, len = facets.length; i < len; i++) {
                var currentFilters = this.settings.builder.filters;
                var facetDefinition = facets[i];

                if (facetDefinition.field === facet.name && !facetDefinition.hidden) {

                    var select = $('<select id="' + facetDefinition.field + '" data-field="' + facetDefinition.field + '" data-placeholder="Seleziona" name="' + facetDefinition.field + '">');

                    if (facetDefinition.multiple) {
                        select.attr('multiple', 'multiple');
                    } else {
                        select.append($('<option value=""></option>'));
                    }

                    facetDefinition.data = facet.data;

                    $.each(facetDefinition.data, function (value, count) {
                        if (value.length > 0) {
                            var quotedValue = facetDefinition.field.search("extra_") > -1 ? encodeURIComponent('"' + value + '"') : value;
                            var option = $('<option value="' + quotedValue + '">' + value + ' (' + count + ')</option>');
                            if (currentFilters[facetDefinition.field]
                                && currentFilters[facetDefinition.field].value
                                && $.inArray(quotedValue, currentFilters[facetDefinition.field].value) > -1) {
                                option.attr('selected', 'selected');
                            }
                            select.append(option);
                        }
                    });

                    var selectContainer = $('<div class="form-group" style="margin-bottom: 10px"></div>');
                    var label = $('<label for="' + facetDefinition.field + '">' + facetDefinition.name + '</label>');

                    selectContainer.append(label);
                    selectContainer.append(select);

                    self.attachFilterInput(select);

                    if ($.isFunction(cb)) {
                        cb.call(context, selectContainer);
                    }
                }
            }

            return self;
        },

        attachFilterInput: function($select, cb, context){
            var self = this;
            $select.chosen({width: '100%', allow_single_deselect: true}).on('change', function (e) {
                var that = $(e.currentTarget);
                var values = $(e.currentTarget).val();
                if (typeof $(e.currentTarget).attr('multiple') == 'undefined' && values) {
                    values = [values]
                }
                if (values != null && values.length > 0) {
                    self.settings.builder.filters[that.data('field')] = {
                        'field': that.data('field'),
                        'operator': 'contains',
                        'value': values
                    };
                } else {
                    self.settings.builder.filters[that.data('field')] = null;
                }
                self.loadDataTable();
            });

            if ($.isFunction(cb)) {
                cb.call(context, self);
            }

            return self;
        }
    });

    $.fn[pluginName] = function (options) {
        return this.each(function () {
            if (!$.data(this, pluginName)) {
                $.data(this, pluginName, new OpendataDataTable(this, options));
            }
        });
    };

})(jQuery, window, document);

var opendataDataTableRenderField = function opendataDataTableRenderField(dataType,
                                                                         templateType,
                                                                         currentLanguage,
                                                                         data,
                                                                         type,
                                                                         row,
                                                                         meta,
                                                                         link) {
    switch (templateType) {
        case 'array of id or remoteId or file or image':
            var names = [];
            if (data.length > 0) {
                $.each(data, function () {
                    names.push(this.name[currentLanguage]);
                });
            }
            data = names.join(', ');
            break;

        case 'file':
            if (data.url) {
                return '<a href="' + data.url + '">' + data.filename + '</a>';
            }
            break;

        case 'multifile':
            var files = [];
            if (data.length > 0) {
                $.each(data, function () {
                    files.push('<a href="' + this.url + '">' + this.filename + '</a>');
                });
            }
            return files.join(', ');
            break;

        case 'array of objects':
            if (dataType == 'ezmatrix') {
                if (data.length > 0) {
                    var $container = $('<div>');
                    var $table = $('<table class="table table-condensed">');
                    $.each(data, function () {
                        var row = this;
                        var $row = $('<tr>');
                        $.each(row, function (index, value) {
                            var $cell = $('<td>' + value + '</td>');
                            $row.append($cell);
                        });
                        $table.append($row);
                    });
                    $container.append($table);
                    return $container.html();
                }
            } else if (dataType == 'ezauthor') {
                var authors = [];
                if (data.length > 0) {
                    $.each(data, function () {
                        authors.push('<a href="mailto:' + this.email + '">' + this.name + '</a>');
                    });
                }
                return authors.join(', ');
            }
            break;

        case 'user':
            var value = [];
            if (data.length > 0) {
                $.each(data, function () {
                    value.push('<a href="mailto:' + this.email + '">' + this.login + '</a>');
                });
            }
            return value.join(', ');
            break;

        case 'ISO 8601 date':
            var validDate = moment(data);
            if (validDate.isValid()) {
                if (dataType == 'ezdate') {
                    data = '<span style="white-space:nowrap">' + validDate.format("D MMMM YYYY") + '</span>';
                } else {
                    data = '<span style="white-space:nowrap">' + validDate.format("D MMMM YYYY, hh:mm") + '</span>';
                }

            }
            break;
    }

    switch (dataType) {
        case 'ezprice':
            if ($.type(data) == 'string') {
                var number = data.split('|')[0];
                data = parseFloat(number).toFixed(2);
                break;
            } else {
                data = parseFloat(data.value).toFixed(2);
            }
            break;
    }

    if (link) {
        data = '<a href="' + link + '">' + data + '</a>'
    }

    return data;

};
