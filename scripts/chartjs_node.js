const ChartjsNode = require('chartjs-node');
const ChartjsAnnotation = require('chartjs-plugin-annotation');

var chartNode = new ChartjsNode(process.argv[2], process.argv[3]);

chartNode.on('beforeDraw', function (Chartjs) {
    Chartjs.defaults.global.defaultFontFamily = 'IPAexGothic';
    Chartjs.pluginService.register(ChartjsAnnotation);
    // console.error(Chartjs.plugins)
});

var util = require('util');

var config = {
    type: 'line',
    data: {
        labels: ["0", "1", "2", "3", "4", "5"],
        datasets: [{
            label: "sample",
            data: [
                Math.random(),
                Math.random(),
                Math.random(),
                Math.random(),
                Math.random(),
                Math.random()
            ]
        }]
    },
    options: {
        scales: {
            yAxes: [{
                ticks: {
                    beginAtZero: true,
                    suggestedMin: 0.0,
                    suggestedMax: 1.0,
                    stepSize: 0.1,
                    callback: function(value, index, values) {
                        if (index % 2 === 1) {
                            return "";
                        }
                        return value;
                    }
                }
            }]
        }
    }
};
console.error(util.inspect(config, false, null));

var chartJsOptions = JSON.parse(Buffer.from(process.argv[4], 'base64').toString('utf-8'));

// console.error(process.argv[4]);
// console.error(chartJsOptions);
console.error(util.inspect(chartJsOptions, false, null));

return chartNode.drawChart(chartJsOptions)
.then(() => {
    // console.error(chartJsOptions);
    return chartNode.writeImageToFile('image/png', process.argv[5]);
});

// chartNode.destroy();
