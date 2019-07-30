const ChartjsNode = require('chartjs-node');

var chartNode = new ChartjsNode(process.argv[2], process.argv[3]);

chartNode.on('beforeDraw', function (Chartjs) {
    Chartjs.defaults.global.defaultFontFamily = 'IPAexGothic';
});

// var util = require('util');

/*
var buffer = Buffer.from(process.argv[4], 'base64');
var chartJsOptions = JSON.parse(buffer.toString('utf-8'));
*/
var chartJsOptions = JSON.parse(Buffer.from(process.argv[4], 'base64').toString('utf-8'));

// console.error(process.argv[4]);

console.error(chartJsOptions);
// console.error(util.inspect(chartJsOptions, false, null));

return chartNode.drawChart(chartJsOptions)
.then(() => {
    // console.error(util.inspect(chartJsOptions, false, null));
    return chartNode.getImageBuffer('image/png');
})
.then(buffer => {
    Array.isArray(buffer)
    return chartNode.getImageStream('image/png');
})
.then(streamResult => {
    streamResult.stream
    streamResult.length
    return chartNode.writeImageToFile('image/png', process.argv[5]);
});

chartNode.destroy();
