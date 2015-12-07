/**
 * @module roll.view.js
 * Модуль работы с представлениями данных SPA
 * @author Андрей Новиков <bermud@nm.ru>
 * @data 08/10/2015
 */

/**
 * @class RollRoute
 * Класс управления маршрутом GUI SPA
 * @property { Application } parent владелец объкта класса Application
 * @property { View } instance указатель на экземпляр класса
 * @event { }
 */
var RollRoute = function(path) {
    this._path = path;
    this._collection = [];
    return this.instance = this;
};

RollRoute.prototype = {
    set path(params) {
        this._path = params;
    },
    get path() {
        return this._path;
    },
    set view(params) {
        //this._path = params;
    },
    get view() {
        var self = this;
        return function(id, ext) {
            var file = self._path.split('/')[0];
            file += '/view/' + ((typeof id === 'undefined') ? 'table' : id);
            if ((typeof (file.split('.')[1]) === 'undefined')) file += '.'+ext;
            return file;
        }
    }
}