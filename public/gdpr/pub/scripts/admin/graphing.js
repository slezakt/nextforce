//Konektor vytahne data z tabulky a preda je jqPlotu pro zobrazeni grafu
//pouzivane atributy elementu
//data-angle uhel popisku
//data-title titulek grafu
//data-table id zdrojove tabulky
//data-col poradove cislo sloupce dat pro graf
//data-relative - poradove cislo sloupce ze ktereho bude spolecne s data-col vypocitana procenta
//data-unit - pripojena jednotka
//data-max - maximální hodnota na ose

var graphingAdmin = {
    forOnload: function() {
        var thisClass = this;
        
            $.jqplot.LabelFormatter = function(format, val) {
                if (val) {
                  return $.jqplot.sprintf(format, val); 
                } else {
                    return '';
                }
            };

        if ($('.graph-bar').length !== 0) {
            $('.graph-bar').each(function() {
                thisClass.makeGraph($(this), 'bar');
                $(this).css('visibility', 'visible');
            });
        }

        if ($('.graph-horizontal-bar').length !== 0) {
            $('.graph-horizontal-bar').each(function() {
                thisClass.makeGraph($(this), 'horizontal-bar');
                $(this).css('visibility', 'visible');
            });
        }

        if ($('.graph-line').length !== 0) {
            $('.graph-line').each(function() {
                thisClass.makeGraph($(this), 'line');
                $(this).css('visibility', 'visible');
            });
        }
    },
    //ziska data z tabulky
    // source - id zdrojove tabulky
    //col - poradove cislo sloupce tabulky pro graf
    //relative_col - poradove cislo sloupce tabulky pro vypocet procent
    //col_label = poradove cislo sloupce tabulky pro sestaveni popisku graf
    getTableData: function(source, col, relative_col, col_label) {

        var ticks = [];
        var data = [[]];

        if ($('#' + source).length === 0) {
            return {data: data};
        }

        table = $('#' + source);

        table.children('tbody').children('tr').each(function() {

            ticks.push($(this).find('td:eq(' + col_label + ')').text());
            var v = graphingAdmin.getTableColumnValue($(this).find('td:eq(' + col + ')'));

            if (relative_col > 0) {               
                var t = graphingAdmin.getTableColumnValue($(this).find('td:eq(' + relative_col + ')'));
                v = (v / t) * 100;
            }
            
            data[0].push([$(this).find('td:eq(' + col_label + ')').text(), v]);
        });
        //pro graf přehodíme pořadí položek
        data[0].reverse();
        
        return {data: data};
    },
    //ziska hodnotu z bunky primarne z data atributu, jinak z textu
    getTableColumnValue: function(col) {
        if (col.attr('data-value')) {
            return parseInt(col.attr('data-value'));
        } else {
            return parseInt(col.text());
        }
        
    },
    //ziska data z jsonu uvnitr elementu
    //source je id elementu grafu
    getJsonData: function(source) {

        var data = [[]];
        var labels = [];

        if ($('#' + source).length === 0) {
            return {data: data};
        }

        var gdata = $.parseJSON($('#' + source).text());
        $('#' + source).text("");

        var i = 0;
        $.each(gdata.data, function(k, v) {
            data[0].push([k, v]);
            i++;
        });

        labels = gdata.labels;

        return {data: data, labels: labels};

    },
    //vytvori sloupcovy graf - jqPlot
    //el - div grafu
    makeGraph: function(el, type) {

        var max = null;
        var total = null;
        var unit = "";

        if (el.attr('data-unit')) {
            unit = el.attr('data-unit');

        }
        var col_label = 0;

        if (el.attr('data-relative')) {
            total = parseInt(el.attr('data-relative'));
            max = 100;
            unit = " %";
        }

        if (el.attr('data-col-label')) {
            col_label = el.attr('data-col-label');
        }

        if (el.attr('data-max')) {
            max = el.attr('data-max');
        }

        if (el.attr('data-table')) {
            var gdata = this.getTableData(el.attr('data-table'), el.attr('data-col'), total, col_label);
        } else {
            var gdata = this.getJsonData(el.attr('id'));
        }

        var labels = [];

        if (gdata.labels) {
            labels = gdata.labels;
        }

        var barWidth = this.getBarWidth(el.width(), gdata.data[0].length);

        $.jqplot(el.attr('id'), gdata.data, this.getGraphFormat(type, labels, el.attr('data-title'), el.attr('data-angle'), max, unit, barWidth));
    },
    //optimalizace sirky sloupcu grafu vuci celkove sirce oblasti (divu) grafu
    getBarWidth: function(el_width, bar_count) {
        var max_bar_width = 90;
        var min_bar_width = 8;

        var av_bar_width = el_width / (bar_count * 1.3);

        if (av_bar_width >= max_bar_width) {
            return max_bar_width;
        } else if (av_bar_width <= min_bar_width) {
            return min_bar_width;
        } else {
            return av_bar_width;
        }
    },
    getGraphFormat: function(type, labels, title, angle, max, unit, barWidth) {

        switch (type) {
            case 'bar':
                return {
                    seriesDefaults: {
                        renderer: $.jqplot.BarRenderer,
                        rendererOptions: {
                            fillToZero: true,
                            barWidth: barWidth
                        },
                        pointLabels: {
                            show: true,
                            labels: labels,
                            formatString: '%d' + unit,
                            formatter: $.jqplot.LabelFormatter
                        }
                    },
                    title: {text: title, textAlign: 'left'},
                    axes: {
                        xaxis: {
                            renderer: $.jqplot.CategoryAxisRenderer,
                            tickOptions: {
                                angle: angle,
                                fontSize: '8pt'
                            },
                            tickRenderer: $.jqplot.CanvasAxisTickRenderer
                        },
                        yaxis: {
                            min: 0,
                            max: max,
                            tickOptions: {
                                formatString: '%d' + unit,
                                fontSize: '8pt'
                            }
                        }
                    }
                };
            case 'horizontal-bar':
                return  {
                    seriesDefaults: {
                        renderer: $.jqplot.BarRenderer,
                        rendererOptions: {
                            barDirection: 'horizontal'
                        },
                        pointLabels: {
                            show: true,
                            labels: labels,
                            location: 'e',
                            edgeTolerance: -18
                        }
                    },
                    title: {text: title, textAlign: 'left'},
                    axes: {
                        xaxis: {
                            max: max,
                            min: 0,
                            tickOptions: {
                                formatString: '%d' + unit
                            }

                        },
                        yaxis: {
                            tickOptions: {
                                show: false
                            }
                        }
                    }
                };
            case 'line':
                return {
                    seriesDefaults: {
                        pointLabels: {
                            show: true,
                            labels: labels,
                            location: 'ne',
                            escapeHTML: true,
                        }
                    },
                    title: {text: title, textAlign: 'left'},
                    axes: {
                        xaxis: {
                            renderer: $.jqplot.DateAxisRenderer,
                             tickRenderer: $.jqplot.CanvasAxisTickRenderer,
                            tickOptions: {
                                angle: angle,
                                fontSize: '8pt',
                                formatString: '%Y-%m-%d'
                            }
                        },
                        yaxis: {
                            min: 0,
                             tickOptions: { 
                             formatString: '%d' 
                            } 
                        }
                    },
                    highlighter: {
                        show: true,
                        tooltipLocation: 'n',
                        sizeAdjust: 7
                    }
                };
        }

    }

};


addLoadEvent('graphingAdmin.forOnload');		