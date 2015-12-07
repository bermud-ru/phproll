/**
 * @module roll.model.js
 * Модуль работы с блоком данных SPA
 * @author Андрей Новиков <bermud@nm.ru>
 * @data 15/10/2015
 */

/**
 * @class Model
 * Класс управления эелементами GUI SPA
 *
 * @property { Application } parent владелец объкта класса Application
 * @property { Model } instance указатель на экземпляр класса
 * @property { Array } _data cтек данных используемый в UI шаблонах модуля View
 * @property { String } routes маршрут по которому запрашиваються данные
 * @property { Boolean } pending индикатор асинхронного запроса
 * @property { Promise } _ajax
 *
 * @event { model.fail | model.done }
 */

var RollModel = function(parent){
    this.parent = parent;
    this._data = [];
    this.route = null;
    this.pending = false,
    this._rpomise = null;
    return this.instance =  this;
};

RollModel.prototype = {
    set data(obj){
        if (this._data[obj.id] !== 'undefined'){
            this.destroy(obj.id);
        }
        this._data[obj.id] = obj.data;
    },
    get data(){
        var self = this;
        return function(id){
            if (typeof id !== 'undefined') {
                return self._data[id];
            } else {
                return self._data;
            }
        }
    },
    set ajax(object) {
        if (!this.pending) {
            this.pending = true;
            this._rpomise = $.ajax(object);
            return this._rpomise.fail(this.onFail.bind(this)).done(this.onDone.bind(this));
        }
    },
    get ajax() {
        return this._rpomise;
    },
    getData: function(params){
        var options = (typeof params !== 'undefined') ? params : this.parent;
        this.route = options.route.substring(1);

        if (typeof this.data(this.route) !== 'undefined'){
            document.application.event('model.done', {data: this.data(this.route)});
        } else {
            this.ajax = {
                url: (new Route(this.route)).path,
                //crossDomain: true,
                async: true,
                type: 'GET',
                contentType:"Application/json; charset=utf-8",
                dataType: 'json',
                headers: {'X-Model': '1.0','X-Route': JSON.stringify(options.route.substring(1)), 'X-Identifier': options.user.hash}
            };
        }
        return this.instance;
    },
    destroy: function(route){
        var map = [], self = this;
        switch (typeof route) {
            case 'undefined': map = this.data(); break;
            case 'string': map = [route];
        }
        $.each(map, function(a) {
                delete self._data[a];
        });
        return this.instance;
    },
    onFail: function( jqXHR, textStatus ) {
        document.application.event('model.fail', {jqXHR:jqXHR, textStatus:textStatus});
        this.pending = false;
    },
    onDone: function( data, textStatus, jqXHR) {
        if (data.result === 'ok') {
            this.data = {id:this.route, data:data.dataset};
            document.application.event('model.done', {data: this.data(this.route), jqXHR:jqXHR, textStatus:textStatus});
        } else {
            this.data = {id:this.route, data:null};
            document.application.event('model.done', {error: data, jqXHR:jqXHR, textStatus:textStatus});
        }

        this.pending = false;
    },
}