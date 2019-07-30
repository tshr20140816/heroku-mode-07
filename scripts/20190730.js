const ChartjsNode = require('chartjs-node');

var chartNode = new ChartjsNode(process.argv[2], process.argv[3]);

// var util = require('util');

// var chartJsOptions = {"type":"line","data":{"datasets":[{"data":[1,2,3,3,2,1]}]},"options":{}};
// var chartJsOptions = {"type":"line","data":{"datasets":[{"data":[1,2]}]},"options":{"legend":{"display":false}}};

// var buffer = Buffer.from(process.argv[4], 'base64');
// var chartJsOptions = JSON.stringify(JSON.parse(buffer.toString()));
var chartJsOptions = JSON.parse(buffer.toString());

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
    return chartNode.writeImageToFile('image/png', '/tmp/testimage.png');
})
.then(() => {
    // ./testimage.png
});

chartNode.destroy();
