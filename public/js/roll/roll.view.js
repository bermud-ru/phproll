/**
 * @module roll.view.js
 * Модуль работы с представлениями данных SPA
 * @author Андрей Новиков <bermud@nm.ru>
 * @data 08/10/2015
 */

/**
 * @class RollView
 * Класс управления эелементами GUI SPA
 * @property { Application } parent владелец объкта класса Application
 * @property { View } instance указатель на экземпляр класса
 * @event { }
 */
var RollView = function(parent) {
    this.parent = parent;
    this._pattern = [];
    return this.instance = this;
};

RollView.prototype = {
    set pattern(obj){
        if (this._pattern[obj.id] !== 'undefined') $('#' + obj.id).remove();
        this._pattern[obj.id] = new RollPattern(obj.model, obj.elementID);
    },
    get pattern(){
        var self = this;
        return function(id) {
            if (typeof id !== 'undefined') {
                return self._pattern[id];
            } else {
                return self._pattern;
            }
        }
    },
    load: function(id, route, elementID, data, method) {
        this.pattern = {id:id, model:data, elementID: elementID };
        return this.pattern(id).load(id, route, method);
    },
    destroy: function(id){
        var map = [];
        switch (typeof id) {
            case 'undefined':
                for (var key in this.pattern()) this.remove(key);
                break;
            case 'string': this.remove(id);

        };
        return false;
    },
    remove: function(id){
        if (item = this.pattern(id)) {
            $(item.script).remove();
            $('#' + item.id).remove();
            delete this._pattern[id];
            return true;
        }
        return false;
    }
};
/**
 * @class RollPattern
 * Внедряем данные в документ с ипользованием библиотеки Handlebars
 * @property { RollView } parent владелец объкта класса RollPattern
 * @property { String } id Определяет id контейнера - обёртку кода шаблона с данными
 * @property { jQuery } _domElement ссылка на DOM-элемент куда будет вставляться код шаблона
 * @property { JSON } _data Данные для работы шаблонизатора, ссылка на экземпляр класса Model
 * @property { Object } _script результат работы document.createElement('script');
 * @property { String } _template <script id="{this._scriptID} ... для повторного использования шаблона или реалзиации очистки
 * @property { Promise } _ajax
 * @property { Boolean } pending индикатор асинхронного запроса
 * @property { RollPattern } instance указатель на экземпляр класса
 * @event { script.render | script.uppend | script.load.fail | script.load.done }
 */
var RollPattern = function(data, container){
    this._domElement = container;
    this._data = data;
    this._script = null;
    this._template = null;
    this._ajax = null;
    this.pending = false;
    return this.instance = this;
};

RollPattern.prototype = {
    get id(){
        return 'pattern-'+this.template;
    },
    set domElement(params){
        this._domElement = params;
    },
    get domElement(){
        return this._domElement;
    },
    set data(data){
        this._data = data;
    },
    get data(){
        return this._data;
    },
    set script(params){
        this._script = params;
    },
    get script(){
        return this._script;
    },
    set template(name){
        this._template = name;
    },
    get template(){
        return this._template.replace(/\./g,'-');
    },
    set ajax(object) {
        if (!this.pending) {
            this.pending = true;
            this._ajax = $.ajax(object);
            return this._ajax.fail(this.onFail.bind(this)).done(this.onDone.bind(this));
        }
    },
    get ajax() {
        return this._ajax;
    },
    /**
     * Pattern.render()
     * Внедряем данные в документ с ипользованием библиотеки Handlebars
     * @param { JSON } data
     * @return { void }
     * @event script.render при успешной внеднрении данных
     */
    render: function(data){
        if (this.domElement) {
            var template = Handlebars.compile($('#' + this.template).html());
            this.domElement.html(template((data !== 'undefined') ? data : this.data));
            document.application.event('script.render', {pattern:this});
        }

        return this.instance;
    },
    /**
     * Pattern.append()
     * Внедряем объект тиапа SCRIPT в HEDER документа
     * @param { JSON } data
     * @return { void }
     * @event script.uppend при успешной внеднрении скрипта в HEADER
     */
    append: function(data){
        if (!$('#'+this.template).length) {
            var head = document.getElementsByTagName("head");
            if (head.length > 0) {
                head = head[0];
                this.script = document.createElement('script');
                this.script.id = this.template;
                this.script.type = 'text/x-handlebars-template';
                //this.script.src = src; // для генерации сслыки на удалнный ресурс
                this.script.text = (data !== 'undefined') ? data : '<!-- empty //-->'; // код скрипта
                this.script.async = false; // дождаться заргрузки
                head.appendChild(this.script); // записываем в <head></head>
            }
        }
        this.render({TemplateContainerID: this.id, data: this.data}); // Рисуем в теле приложения
        document.application.event('script.uppend', {pattern: this});
        return this.instance;
    },
    /**
     * Pattern.load()
     * Внедряем объект тиапа SCRIPT в HEDER документа
     * @param { String } template_name идентификатор шаблона
     * @param { String } route URI куда будет делаться асинхронный запрос
     * @return { void }
     */
    load: function(template_name, route, method) {
        this.template = template_name;
        if (tmpl = document.application.storage.getItem(this.template)) {
            this.append(tmpl);
            document.application.event('pattern.done', {data: tmpl, textStatus: 200});
        } else {
            this.ajax = {
                url: (typeof route !== 'undefined') ? route : window.location,
                type: (typeof method !== 'undefined') ? method : 'POST',
                //async: true,
                // crossDomain: true,
                //contentType: 'Application/xml; charset=utf-8',
                //dataType: 'html',
                headers: {'X-Pattern': this.id, 'X-Template': template_name}
            };
        }
        return this.instance;
    },
    /**
     * RollPattern.onFail()
     * Promise на ошибку работы асинхронного запроса загрузки шаблона
     * @param { Object } jqXHR
     * @param { String } textStatus
     * @return { void }
     * @event script.load.fail
     */
    onFail: function( jqXHR, textStatus ) {
        document.tools.debug.info('Произошла ошибка передачи данных!','RollPattern::onFail');
        document.application.event('pattern.fail', {jqXHR:jqXHR, textStatus: textStatus});
        this.pending = false;
    },
    /**
     * RollPattern.onDone()
     * Promise на успешное выполнение асинхронного запроса загрузки шаблона
     * @param { JSON } data
     * @param { String } textStatus
     * @param { Object } jqXHR
     * @return { void }
     * @event script.load.done
     */
    onDone: function( data, textStatus, jqXHR) {
        if (data.length) {
            this.append(data);
            document.application.storage.setItem(this.template, data);
            document.application.event('pattern.done', {data:data, jqXHR:jqXHR, textStatus: textStatus});
        } else {
            // что то пошло не так ((
            document.tools.debug.info('Пустой конетент!','RollPattern::onDone');
        }
        this.pending = false;
    }
};

