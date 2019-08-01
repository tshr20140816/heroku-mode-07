const ChartjsNode = require('chartjs-node');
const ChartjsAnnotation = require('chartjs-plugin-annotation');

var chartNode = new ChartjsNode(process.argv[2], process.argv[3]);

chartNode.on('beforeDraw', function (Chartjs) {
    Chartjs.defaults.global.defaultFontFamily = 'IPAexGothic';
    Chartjs.pluginService.register(ChartjsAnnotation);
    // console.error(Chartjs.plugins)
});

function reviver(k, v) {
    if (typeof v === "string" && v.match(/^function/)){
        return Function.call(this, "return " + v)();
    }
    return v;
}

var util = require('util');

var chartJsOptions = JSON.parse(Buffer.from(process.argv[4], 'base64').toString('utf-8'), reviver);

// console.error(process.argv[4]);
// console.error(chartJsOptions);
// console.error(util.inspect(chartJsOptions, false, null));

return chartNode.drawChart(chartJsOptions)
.then(() => {
    // console.error(chartJsOptions);
    return chartNode.writeImageToFile('image/png', process.argv[5]);
});

// chartNode.destroy();
