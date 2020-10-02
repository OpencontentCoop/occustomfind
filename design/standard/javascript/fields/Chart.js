(function ($) {

    var Alpaca = $.alpaca;

    Alpaca.Fields.Chart = Alpaca.Fields.HiddenField.extend({
        getFieldType: function () {
            return "chart";
        },

        afterRenderControl: function(model, callback) {
            var self = this;
            this.base(model, function() {
                var container = self.getFieldEl();
                var chart = $('<div class="my-3"></div>').appendTo(container);
                var easyChart = new ec($.extend({}, {
                    debuggerTab: false,
                    chartTab: true,
                    dataTab: true,
                    showLogo: false,
                    element: chart[0]
                }, self.options.chart));
                if (self.data) {
                    easyChart.setConfigStringified(self.data);
                }
                easyChart.on('configUpdate', function(e){
                    self.setValue(easyChart.getConfigStringified);
                });
                callback();
            });
        }
    });

    Alpaca.registerFieldClass("chart", Alpaca.Fields.Chart);

})(jQuery);
