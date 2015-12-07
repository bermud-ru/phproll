/**
 * @module handlebars.helpers.js
 * Дополнения для библиотеки Handlebars (https://cdn.jsdelivr.net/handlebarsjs/4.0.3/handlebars.min.js)
 * @author Андрей Новиков <bemud@nm.ru>
 * @data 08/10/2015
 */

Handlebars.registerHelper('json', function() {
    return JSON.stringify(arguments[0]);
});

Handlebars.registerHelper('timeStamp', function() {
    var dt = new Date(parseInt(arguments[0])*1000);
    //return dt.toUTCString();
    return dt.toLocaleDateString(); // + ' ' +dt.toTimeString();
});

Handlebars.registerHelper('foreach',function() {
    var arr = arguments[0];
    var options = arguments[1];
    if(options.inverse && !arr.length)
        return options.inverse(this);

    return arr.map(function(item,index) {
        item.$index = index;
        item.$first = index === 0;
        item.$last  = index === arr.length-1;
        return options.fn(item);
    }).join('');
});

Handlebars.registerHelper('exists', function() {
    var variable = arguments[0];
    var options = arguments[1];
    if (typeof variable !== 'undefined') {
        return options.fn(this);
    } else {
        return options.inverse(this);
    }
});
