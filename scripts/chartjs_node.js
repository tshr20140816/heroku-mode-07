const ChartjsNode = require('chartjs-node');

var chartNode = new ChartjsNode(process.argv[2], process.argv[3]);

chartNode.on('beforeDraw', function (Chartjs) {
    Chartjs.defaults.global.defaultFontFamily = 'IPAexGothic';
    // Chartjs.pluginService.register(annotation);
    console.error(Chartjs.plugins)
});

var util = require('util');

var chartJsOptions = JSON.parse(Buffer.from(process.argv[4], 'base64').toString('utf-8'));

// console.error(process.argv[4]);

console.error(chartJsOptions);
console.error(util.inspect(chartJsOptions, false, null));

return chartNode.drawChart(chartJsOptions)
.then(() => {
    console.error(chartJsOptions);
    return chartNode.getImageBuffer('image/png');
})
.then(buffer => {
    Array.isArray(buffer)
    return chartNode.getImageStream('image/png');
})
.then(streamResult => {
    return chartNode.writeImageToFile('image/png', process.argv[5]);
});

chartNode.destroy();
