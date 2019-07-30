const ChartjsNode = require('chartjs-node');

var chartNode = new ChartjsNode(process.argv[2], process.argv[3]);

var chartJsOptions = {"type":"line","data":{"datasets":[{"data":[1,2,3,3,2,1]}]},"options":{}};
/*
var buffer = Buffer.from(process.argv[4], 'base64');
var chartJsOptions = buffer.toString('ascii');
console.log(process.argv[4]);
console.error(process.argv[4]);
*/
console.log(chartJsOptions);
console.error(chartJsOptions);

return chartNode.drawChart(chartJsOptions)
.then(() => {
    // console.log(process.argv[4]);
    // console.error(process.argv[4]);
    console.log(chartJsOptions);
    console.error(chartJsOptions);
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
