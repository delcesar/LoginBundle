
$(document).ready(function () {
    
    // vaciar el local storage
    localStorage.clear();

    // incluir los hashed request
    if (location.href.indexOf('#!') > 0 && location.href.indexOf('?return=') > 0) {
        var hash = location.href.substring(location.href.indexOf('#!'));
        $('#return').val($('#return').val() + hash);
        $('#rutavisiblereturn').append(hash);
    }

    //cargar timezone
    $('#timezone').val(zonaHoraria());

    // submit
    $('#login-check').click(function () {
        $('#login-form').submit();
    });

    // capturar el evento submit
    $('#login-form').submit(function () {
        data = $("#login-form").serialize();
        url = $("#login-form").attr('action');
        $('#login-submit').disable();
        $('#alerta-login').html('');

        $.post(url, data, function (datos) {
            if (datos.valido === true) {
                Materialize.toast('Login correcto. Redireccionado...', 5000);
                //
                location.href = datos.redireccion;
            } else {
                if (datos.bloqueado) {
                    $('#login-form').html('');
                    $('#login-alert').html('<div class="alert alert-warning alert-block"><div class="alertWrapper">Login bloqueado. Espera <strong><span id="restante"></span></strong><br/>El sistema ha bloqueado tu usuario, por demasiados intentos fallidos de login. Se desbloqueará automáticamente tu usuario pasado el tiempo necesario.</div></div>');
                    RunLoginBlock(datos.tiemporestante, datos.urllogin);
                } else {
                    Materialize.toast(datos.error, 5000);
                    $('#usuario').focus();
                }
            }
        }, "json");

        $('#login-submit').enable();
        return false;
    });

});

/*==== CONTADOR ATRAS TIEMPO LOGIN BLOQUEADO ====*/
function RunLoginBlock(sessionTimeout, urlactualizacion) {
    var sessionCurrentTimeout = null;

    jQuery(function () {
        sessionCurrentTimeout = sessionTimeout;
        var inter = setInterval(LoginBlockHandler, 1000);
        clearInterval(inter);
        inter = setInterval(LoginBlockHandler, 1000);
    });

    function LoginBlockHandler() {
        if (sessionCurrentTimeout === null) {
            return;
        }

        // contador
        sessionCurrentTimeout = sessionCurrentTimeout - 1;

        // actualizar el contador en el form
        if (sessionCurrentTimeout <= 0) {
            $('#login-alert').html('<div class="alert alert-info alert-block"><div class="alertWrapper">Espera un momento... Actualizando estado de login.<br/> Recuerda que sólo tienes 3 intentos y luego tu usuario pasará a estar bloqueado por 5 minutos.</div></div>');
        }

        // refresh si contador llego a cero
        if (sessionCurrentTimeout < -3) {
            window.location = urlactualizacion;
        }

        var minutes = Math.floor(sessionCurrentTimeout / 60);
        var seconds = sessionCurrentTimeout % 60;

        if (seconds < 10) {
            seconds = '0' + seconds;
        }
        var time = minutes + ":" + seconds;

        // resaltar en rojo cuando el contador este llegando a cero
        if (sessionCurrentTimeout > 0) {
            $('#restante').html(time);
            if (minutes <= 1) {
                $('#clock').css({color: '#fdbabb', fontWeight: 'bold'});
            }
        }
    }
}

/*=========== ZONA HORARIA DEL CLIENTE ===========*/
function zonaHoraria() {
    var offset = (new Date()).getTimezoneOffset();
    var timezones = {
        '-12': 'Pacific/Kwajalein',
        '-11': 'Pacific/Samoa',
        '-10': 'Pacific/Honolulu',
        '-9': 'America/Juneau',
        '-8': 'America/Los_Angeles',
        '-7': 'America/Denver',
        '-6': 'America/Mexico_City',
        '-5': 'America/Lima',
        '-4': 'America/Caracas',
        '-3.5': 'America/St_Johns',
        '-3': 'America/Argentina/Buenos_Aires',
        '-2': 'Atlantic/Azores',
        '-1': 'Atlantic/Azores',
        '0': 'Europe/London',
        '1': 'Europe/Paris',
        '2': 'Europe/Helsinki',
        '3': 'Europe/Moscow',
        '3.5': 'Asia/Tehran',
        '4': 'Asia/Baku',
        '4.5': 'Asia/Kabul',
        '5': 'Asia/Karachi',
        '5.5': 'Asia/Calcutta',
        '6': 'Asia/Colombo',
        '7': 'Asia/Bangkok',
        '8': 'Asia/Singapore',
        '9': 'Asia/Tokyo',
        '9.5': 'Australia/Darwin',
        '10': 'Pacific/Guam',
        '11': 'Asia/Magadan',
        '12': 'Asia/Kamchatka'
    };
    return timezones[-offset / 60];
}