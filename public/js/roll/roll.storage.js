/**
 * @module roll.storage.js
 * @category Application
 *
 * Класс контейнер хранилища SPA
 * @author Андрей Новиков <bermud@nm.ru>
 * @data 05/10/2015
 */

/**
 * @class RollStorage
 *  Класс контейнер хранилища SPA
 *  Fix for "QUOTA_EXCEEDED_ERR: DOM Exception 22: An attempt was made to add something to storage that exceeded the quota."
 * @property { Boolean } isNativeStorage исользуется нативное хранилище
 * @property { Array } _properties Ключи
 * @property { window.Storage | Storage } instance объект хранилище
 * @event { }
 */
var RollStorage = function(instance) {
    this.isNativeStorage = false;
    this._properties = [];
    try {
        instance.setItem('test', '1');
        instance.removeItem('test');
        this.isNativeStorage = true;
        this.instance = instance;
    } catch (e) {
        this.instance = this;
    }
    return this.instance;
};

/**
 * @type {{setItem: Function, getItem: Function, removeItem: Function, clear: Function}}
 */
RollStorage.prototype = {
    setItem:function(key, value){
        this._properties.push(key);
        this[key] = value;
    },
    getItem:function(key){
        if (this.hasOwnProperty(key)){
            return this[key];
        }
        return null;
    },
    removeItem: function(key){
        if (this.instance.hasOwnProperty(key)){
            delete this._properties[key];
            delete this[key];
        }
    },
    clear:function(){
        this._properties.map(function(item){delete this[item];});
        this._properties = [];
    }
}