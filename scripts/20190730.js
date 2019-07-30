const ChartjsNode = require('chartjs-node');

var chartNode = new ChartjsNode(process.argv[2], process.argv[3]);

/*
var chartJsOptions = {type: 'line',
                      data: {datasets: [{data: [1, 2]}]},
                      options: {}
                     };
*/
var chartJsOptions = new Buffer(process.argv[4], 'base64');

return chartNode.drawChart(chartJsOptions)
.then(() => {
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
