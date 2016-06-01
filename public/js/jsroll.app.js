/**
 * @app jsroll.app.js
 * @category RIA (Rich Internet Application) / SPA (Single-page Application)
 *
 * Классы RIA / SPA application
 * @author Андрей Новиков <andrey@novikov.be>
 * @status beta
 * @version 0.1.0
 * @revision $Id: jsroll.app.js 0004 2016-06-01 9:00:01Z $
 */

(function ( g, undefined ) {
'use strict';

// demo: popup && template
tmpl('/tmpl/login.tmpl', null, function (content) {
    popup.show({
        content: content,
        event: [
            function(){
                spa.el('[role="login-submit"]').spa.on('click',function(e){
                    spinner.run = true;
                    JSON.form(spa.el('#login')).release({fn:function(responce){
                        if (responce.result == 'ok') {
                            popup.hide();
                            msg.show({error: 'Добро пожаловать!', message: 'в SPA приложение [' +JSON.stringify(responce.data)+']'}, true);
                        }
                        spinner.run = false;
                    }});
                })
            }
        ]
    });
});

}( window ));