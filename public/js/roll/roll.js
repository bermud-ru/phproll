/**
 * @module roll.js
 * @category Application
 *
 * SPA приложение
 * @author Андрей Новиков <bermud@nm.ru>
 * @data 05/10/2015
 */


/**
 * @class Application
 * @property { Object } settings Объект хранения параметров приложения (key => value)
 * @property { Application } instancee ссылка на экземпляр Application
 * @event {}
 */

var SPARoll = function (params)
{
    /**
     * Конфигурация по-уммолчанию
     * @type {{title: string, workspace: (*|jQuery|HTMLElement), defaultRoute: string, storage: Storage, user: {}, route: string, model: Model, view: View}}
     */
    this.settings = {
        title: 'BI.local []',
        workspace: $("[role='SPARoll']"),
        defaultRoute:  '#/report',
        storage: new Storage(window.localStorage),
        user: {},
        route: '',
        model: new RollModel(this),
        view: new RollView(this)
    };

    this.init(params);
    return this.instance = this;
}

SPARoll.prototype = {
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
    set title(str) {
        document.title = this.settings.title.replace(/\[(.)*\]/, '['+str+']');
    },
    get title() {
        return document.title;
    },
    set storage(params) {
        this.settings.storage = params;
    },
    get storage() {
        return this.settings.storage;
    },
    set route(params) {
        //TODO: убрать костыль
        if (window.location.hash.match(/^#\/search\/.*$/i)){
            $('[role="search"] input[type="text"]').val(decodeURI(window.location.hash.split('/').pop()));
        } else {
            $('[role="search"] input[type="text"]').val('');
        }

        window.location.hash = this.settings.route = ((typeof (params) !== 'undefined' && params !== null )? params : this.settings.defaultRoute);
    },
    get route() {
        return this.settings.route;
    },
    set user(params) {
        if ('object' === typeof params ) {
            this.storage.setItem('user', JSON.stringify(params));
            this.settings.user = params;
        } else {
            if (typeof params !== 'undefined') this.settings.user = JSON.parse(params);
        }
    },
    get user() {
        return this.settings.user;
    },

    init: function(params){
        for (var item in this.settings.storage) {
            if (pt = item.match(/^((templates-ejs).*)?$/i)) {
                delete this.storage[item];
            }
        };

        this.settings = $.extend(this.settings, params);
        this.user = this.storage.getItem('user') ? this.storage.getItem('user') : '{}';
        //Если у нас пустой url, то проверяем url в локальном хранилице
        (this.settings.hash !== '') && this.storage.setItem('route',this.settings.hash);
        this.route = this.storage.getItem('route');
        //Навешиваем события
        this.bind();
        return this.instance;
    },
    search: function(query) {
        this.route = encodeURI('/search/'+query);
        this.model.getData();
    },
    run: function(params){
        if (this.user === null || !this.user.hasOwnProperty('id') ) {
            this.view.destroy();
            this.view.load('templates.auth.login-form', 'auth', this.settings.workspace,{},'GET');
        } else {
            this.view.load('navbar.tmpl', 'roll/navbar.tmpl', this.settings.navbar, this.user, 'GET');
            this.render(this.route);
        }
    },
    render: function(route){
        this.route = window.location.hash;
        this.model.getData();
    },
    event: function(eventID, data){
        window.dispatchEvent(new CustomEvent(eventID, {detail:data}));
    },
    bind: function(){
        window.addEventListener('model.done', this.onDataDone.bind(this), false);
        window.addEventListener('model.fail', this.onDataFail.bind(this), false);
        window.addEventListener('pattern.fail', this.onPatternFail.bind(this), false);
        window.addEventListener('application.logout', this.onLogout.bind(this), false);

        window.addEventListener('hashchange', this.run.bind(this), false);
        window.addEventListener('beforeunload', this.onBeforeUnload.bind(this), false);
        document.addEventListener('click',this.onClick.bind(this), true );
    },
    onClick: function(e) {
        if ($(e.target).attr('roll-link')) {
            //    // Kill the event
            console.log(e.target.attributes['roll-link']);
            e.preventDefault();
            e.stopPropagation();
        }

        // Doing nothing in this method lets the event proceed as normal
    },
    onBeforeUnload:function(e){
        // e.preventDefault();
        //e.stopPropagation();
        //window.location.pathname = '/';
        //window.history.pushState(null,null, '/index.php');
        return false;
    },
    onDataDone: function(e){
        var data = e.detail.data;
        if (data != null) {
            var view = (data.view && data.view.length) ? data.view : 'table';
            this.view.load('templates.ejs.' + view, (new Route(this.route.substring(2))).view(view, 'ejs'), this.settings.workspace, data, 'GET');
            this.title = data.title || data.name;
        } else {
            this.view.load('templates.roll.info.404', 'error/404.tmpl', this.settings.workspace, e.detail.error, 'GET');
        }
    },
    onDataFail: function(e){
        document.tools.debug.info('Произошла ошибка передачи данных!','SPARoll::onDataFail');
    },
    onPatternFail: function(e) {
        this.title = 'Упс! ошибка - 404';
        this.view.load('templates.roll.info.404', 'error/404.tmpl', this.settings.workspace, {}, 'GET');
    },
    onLogout: function(e) {
        this.user = {};
        this.view.load('templates.auth.login-form', 'auth', this.settings.workspace, {},'GET');
    },

}
