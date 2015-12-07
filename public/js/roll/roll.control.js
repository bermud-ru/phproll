/**
 * @module roll.control.js
 * Элементы UI SPA
 * @author Андрей Новиков <bermud@nm.ru>
 * @data 20/10/2015
 */

var RollControl = function(params){
    var settings = {
        parent: $("[role='Application']"),

    };
    this.settings = $.extend(settings, params);
    return this.instance =  this;
};

RollControl.prototype = {
    set model(params) {
        this.settings.model = params;
    },
    get model() {
        return this.settings.model;
    },
    set view(params) {
        this.settings.view = params;
    },
    get view() {
        return this.settings.view;
    },
    draw:function(dataURL, viewURL, formatter){
        var self = this;
        var data = new Rest(this);
        var view = new Rest(this);
        return $.when(data.get(dataURL), view.get(viewURL)).then(function(data, view) {
            var template = Handlebars.compile(view[0]);
            var params = (typeof formatter === 'undefined') ?  {data:data[0]} : formatter(data[0]);
            self.settings.parent.html(template(params));
        });
    },
    create:function(url, params, callback){
        var data = new Rest(this);
        return data.post(url,params).done(function(data,jqXHR,textStatus) {
                if (typeof callback !== 'undefined' && typeof callback === 'function'){
                    callback(data, jqXHR, textStatus);
                }
            }
        );
    },
    delete:function(url, callback){
        var data = new Rest(this);
        return data.delete(url).done(function(data,jqXHR,textStatus) {
                if (typeof callback !== 'undefined' && typeof callback === 'function'){
                    callback(data, jqXHR, textStatus);
                }
            }
        );
    },
    save:function(url, params, callback){
        var data = new Rest(this);
        return data.put(url,params).done(function(data,jqXHR,textStatus) {
                if (typeof callback !== 'undefined' && typeof callback === 'function'){
                    callback(data, jqXHR, textStatus);
                }
            }
        );
    },
};