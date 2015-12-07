/**
 * @module roll.rest.js
 * RESTfull SPA
 * @author Андрей Новиков <bermud@nm.ru>
 * @data 20/10/2015
 */

/**
 * @class RollRest
 * Класс работы по RESTfull данными SPA
 * @property { Object } parent
 * @property { Boolean } pending
 * @property { Promise } _rpomise jQuery ajax
 * @property { Rest } instance объект хранилище
 * @event { Defered chance }
 */

var RollRest = function(parent){
    this.parent = parent;
    this.pending = false,
    this._rpomise = null;
    return this.instance = this;
};
/**
 *
 * @type {{get: Function, post: Function, delete: Function, put: Function}}
 */
RollRest.prototype = {
    get: function(url, type){
        if (!this.pending) {
            this.pending = true;
            this._rpomise = $.ajax({
                url: url,
                //crossDomain: true,
                async: true,
                type: 'GET',
                contentType:"Application/json; charset=utf-8",
                dataType: (typeof type !== 'undefinde' ? type:'json')
                //dataType:"text|html|json|jsonp|script|xml"
            });
            return this._rpomise;
        }
    },
    post:function(url, data){
        if (!this.pending) {
            this.pending = true;
            this._rpomise = $.ajax({
                url: url,
                //crossDomain: true,
                async: true,
                type: 'POST',
                //dataType:"text|html|json|jsonp|script|xml"
                contentType:"Application/json; charset=utf-8",
                data: data
            });
            return this._rpomise;
        }
    },
    delete: function(url){
        if (!this.pending) {
            this.pending = true;
            this._rpomise = $.ajax({
                url: url,
                //crossDomain: true,
                async: true,
                type: 'DELETE'
            });
            return this._rpomise;
        }
    },
    put: function(url, data, type){
        if (!this.pending) {
            this.pending = true;
            this._rpomise = $.ajax({
                url: url,
                //crossDomain: true,
                async: true,
                type: 'PUT',
                //data: JSON.stringify(data),
                data: data,
                contentType:"Application/json; charset=utf-8",
                //dataType:"text|html|json|jsonp|script|xml"
                dataType: (typeof type !== 'undefinde' ? type:'json')
            });
            return this._rpomise;
        }
    }
}