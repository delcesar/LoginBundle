<?php

namespace LoginBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use DatosBundle\Entity\Loginobservado;

class LoginController extends Controller {

    public function indexAction(Request $request) {
        $return = $request->query->get('return');
        return $this->render('LoginBundle:Login:index.html.twig', array("return" => $return));
    }

    /**
     * Validar credenciales de usuario
     * @return {JSON}
     *  */ 
    public function validarCredencialesAction(Request $request) {
        $intentospermitidos = 3;
        $tiempobloqueado = 180; //segundos
        $ip = $this->getIP(); // IP del usuario
        $username = $request->request->get('usuario');
        $clave = $request->request->get('clave');
        $timezone = $request->request->get('timezone');
        $redirigir = $request->request->get('redirigir');
        $return = $request->request->get('return');
        $idhotel = $request->request->get('idhotel');

        if (strlen($username) > 2 && strlen($clave) > 0) {
            if ($this->intentosLogin($ip, $username) < $intentospermitidos) {
                $res = $this->validarLogin($request, $username, $clave, $ip, $timezone, $redirigir, $return, $idhotel, $intentospermitidos);
                
            } else {
                // ya pasó el tiempo de bloqueo?
                $tiempo = $this->tiempoUltimoIntento($ip, $username);
                $res["tiempo"] = $tiempo;

                if ($tiempo > $tiempobloqueado) {
                    $this->borrarIntentos($ip, $username);
                    $res = $this->validarLogin($request, $username, $clave, $ip, $timezone, $redirigir, $return, $idhotel, $intentospermitidos);
                } else {
                    $res["valido"] = false;
                    $res["bloqueado"] = true;
                    $res["tiemporestante"] = $tiempobloqueado - $tiempo;
                }
            }
        } else {
            $res["valido"] = false;
            $res["error"] = "Por favor, completa los campos de usuario y clave cuidadosamente.";
        }

        $res["ip"] = $ip;
        $res["urllogin"] = $this->generateUrl('login_homepage', array('return' => $return));
        return new JsonResponse($res);
    }



    /**
    * validar el intento de login y accesos 
    * @return {array} - {valido, temporal, redireccion}
    */
    private function validarLogin(Request $request, $username, $clave, $ip, $timezone, $redirigir, $return, $idhotel, $intentospermitidos = 3) {
        $res["valido"] = false;
        $session = $request->getSession();
        $em = $this->getDoctrine()->getManager();
        $usuarios = $em->createQuery('SELECT u FROM DatosBundle:Usuario u WHERE u.username = :user AND u.enabled = 1')
                        ->setParameter('user', $username)->getResult();
        if ($usuarios) {
            $usuario = $usuarios[0]; // primer elemento

            if (crypt($clave, $usuario->getPassword()) == $usuario->getPassword()) {
                $session->set('idusuario', $usuario->getIdusuario());
                $session->set('nombreusuario', $usuario->getNombre());
                $session->set('emailusuario', $usuario->getEmail());
                $session->set('nivelusuario', $usuario->getNivel());
                $idsesion = $this->idSesion();
                $session->set('idsesion', $idsesion);

                // actualizar idsession del usuario
                $usuario->setIdsession($idsesion);
                $hoy = new \DateTime();
                $usuario->setUltimologin($hoy);
                $em->flush();

                // borrar intentos de login
                $this->borrarIntentos($ip, $username);
                $this->borrarClaveTemporal($usuario->getIdusuario());
                $res["valido"] = true;

                if ($redirigir == "on") {
                    if ($this->get('utilidades')->validar_url($return) == true) {

                        // seleccionar el hotel activo para esta url
                        // tiene permiso para acceder a este hotel?
                        if ($this->get('guardian')->tienePermisoAccesoHotel($idhotel)) {
                            $session->set('idhotelactivo', $idhotel);
                            $res["redireccion"] = $return;

                        } else {
                            $permisos = $session->get('permisosusuario');

                            // seleccionar el primer hotel al que tiene permiso el usuario
                            if (count($permisos) > 0) {
                                $session->set('idhotelactivo', $permisos[0]); //guardar en sesión
                                $res["redireccion"] = $return;

                            } else {
                                $res["redireccion"] = $this->generateUrl('login_permisos_usuario');
                            }
                        }
                    } else {
                        $res["redireccion"] = $this->generateUrl('login_permisos_usuario');
                    }

                } else {
                    $res["redireccion"] = $this->generateUrl('login_permisos_usuario');
                }

            } else {
                // comprobar si está usando una clave temporal
                if (crypt($clave, $usuario->getTemppassword()) == $usuario->getTemppassword()) {
                    $session->set('idusuario', $usuario->getIdusuario());
                    $session->set('nombreusuario', $usuario->getNombre());
                    $idsesion = $this->idSesion();
                    $session->set('idsesion', $idsesion);
                    $this->borrarIntentos($ip, $username);

                    $res["valido"] = true;
                    $res["temporal"] = true;
                    $res["redireccion"] = $this->generateUrl('login_crear_clave_usuario');
                }
            }
        }

        // login denegado
        if (!$res["valido"]) {
            $intentos = $intentospermitidos - $this->intentosLogin($ip, $username) - 1; // menos un intento
            $res["error"] = "La combinación de usuario y clave es incorrecta. " . $intentos . " intentos restantes";
            $this->actualizarIntentos($ip, $username);

        } else {
            // establecer timezone del usuario
            if ($timezone != "") {
                $session->set('timezone', $timezone);

            } else {
                $session->set('timezone', "America/Lima");
            }
        }

        return $res;
    }



    /**
     * validar usuario y contraseña 
     * Este metodo puede ser llamado directamente antes de cualquier evento critico que necesite
     * comprobacion de credenciales del usuario
     * 
     * @return {JSON} 
     *  */ 
    public function validarUsuarioPasswordAction($username, $clave) {
        $intentospermitidos = 3;
        $tiempobloqueado = 180; //segundos
        $ip = $this->getIP(); // IP del usuario
        $ret["valido"] = false; // default
        $ret["bloqueado"] = false;

        if (strlen($username) > 2 && strlen($clave) > 0) {
            $em = $this->getDoctrine()->getManager();
            $usuarios = $em->createQuery('SELECT u FROM DatosBundle:Usuario u WHERE u.username = :user AND u.enabled = 1')
                            ->setParameter('user', $username)->getResult();

            if ($usuarios) {
                $usuario = $usuarios[0]; // primer elemento

                // comprobar que el usuario no esté bloqueado aun
                if ($this->intentosLogin($ip, $username) < $intentospermitidos) {
                    if (crypt($clave, $usuario->getPassword()) == $usuario->getPassword()) {
                        $ret["valido"] = true;
                        $ret["usuario"]["idusuario"] = $usuario->getIdusuario();
                        $ret["usuario"]["nombreusuario"] = $usuario->getNombre();
                        $ret["usuario"]["nivel"] = $usuario->getNivel();
                    }

                } else {
                    // está bloqueado
                    // ya pasó el tiempo de bloqueo?
                    $tiempo = $this->tiempoUltimoIntento($ip, $username);
                    $ret["tiempo"] = $tiempo;

                    if ($tiempo > $tiempobloqueado) {
                        $this->borrarIntentos($ip, $username);

                        if (crypt($clave, $usuario->getPassword()) == $usuario->getPassword()) {
                            $ret["valido"] = true;
                            $ret["usuario"]["idusuario"] = $usuario->getIdusuario();
                            $ret["usuario"]["nombreusuario"] = $usuario->getNombre();
                            $ret["usuario"]["nivel"] = $usuario->getNivel();
                        }

                    } else {
                        // retornar el tiempo de espera antes de intentar nuevamente
                        $ret["bloqueado"] = true;
                        $ret["tiemporestante"] = $tiempobloqueado - $tiempo;
                    }
                }
            }
        }

        // login no fue exitoso - actualizar intentos
        if (!$ret["valido"]) {
            $intentos = $intentospermitidos - $this->intentosLogin($ip, $username) - 1; // menos un intento

            // bloquear usuario
            if ($intentos <= 0) {
                $ret["bloqueado"] = true;
                $tiempo = $this->tiempoUltimoIntento($ip, $username);
                $ret["tiemporestante"] = $tiempobloqueado - $tiempo;
                $ret["error"] = "Demasiados intentos. Cerrando sesión...";

            } else {
                // reducir numero de intentos permitidos
                $ret["error"] = "La combinación de usuario y clave es incorrecta. " . $intentos . " intentos restantes";
                $this->actualizarIntentos($ip, $username);
            }
        }

        // IP del usuario
        $ret["ip"] = $ip;

        return new JsonResponse($ret);
    }



    /**
     * formulario para crear nueva clave de usuario, luego de haber iniciado con una clave temporal
     *  */ 
    public function crearClaveUsuarioAction() {
        return $this->render('LoginBundle:Login:crearClaveUsuario.html.twig');
    }



    /**
     * registrar la nueva clave del usuario
     */
    public function registrarNuevaClaveUsuarioAction(Request $request) {
        $password = $request->request->get('password');
        $confirmacion = $request->request->get('confirmacion');

        if ($password != $confirmacion) {
            $ret["valido"] = false;
            $ret["invalido"][] = "confirmacion";
            $ret["error"] = "Valores diferentes. Debes escribir cuidadosamente tu nueva clave en los dos campos";

        } elseif (strlen($password) < 8) {
            $ret["valido"] = false;
            $ret["error"] = "Tu nueva clave debe, al menos, tener 8 caracteres entre letras, números y símbolos";

        } else {
            $session = $request->getSession();
            $em = $this->getDoctrine()->getManager();
            $usuario = $em->getRepository('DatosBundle:Usuario')->find($session->get("idusuario"));
            $nuevaclave = $this->crypt_blowfish($password);
            $usuario->setPassword($nuevaclave);
            $em->flush();
            $ret["valido"] = true;
            $ret["redireccion"] = $this->generateUrl('login_logout');
            $request->getSession()->getFlashBag()->add(
                    'alerta-sistema', 'Se ha registrado tu nueva clave. Inicia sesión'
            );
        }

        return new JsonResponse($ret);
    }



    /**
     * formulario para crear nueva clave de usuario, luego de haber iniciado con una clave temporal
     *  */ 
    public function cambiarClaveUsuarioAction() {
        return $this->render('LoginBundle:Perfil:cambiarClaveUsuario.html.twig');
    }



    /**
     * registrar la nueva clave del usuario
     */
    public function registrarCambioClaveUsuarioAction(Request $request) {
        $intentospermitidos = 3;
        $ip = $this->getIP();
        $actual = $request->request->get('actual');
        $nueva = $request->request->get('password');
        $confirmacion = $request->request->get('confirmacion');
        $em = $this->getDoctrine()->getManager();
        $usuario = $em->getRepository("DatosBundle:Usuario")->find($request->getSession()->get('idusuario'));

        if ($usuario) {

            // si los intentos de login no superar los máximos permitidos
            if ($this->intentosLogin($ip, $usuario->getUsername()) < $intentospermitidos) {

                // comprobar que la clave actual es correcta
                if (crypt($actual, $usuario->getPassword()) == $usuario->getPassword()) {

                    if ($nueva == $confirmacion) {
                        $nuevaclave = $this->crypt_blowfish($nueva);
                        $usuario->setPassword($nuevaclave);
                        $em->flush();
                        $res["valido"] = true;
                        $res["redireccion"] = $this->generateUrl('login_perfil_usuario');
                        $request->getSession()->getFlashBag()->add(
                                'alerta-sistema', 'Se ha registrado tu nueva clave'
                        );

                    } else {
                        $res["valido"] = false;
                        $res["invalido"][] = 'confirmacion';
                        $res["error"] = "Valores diferentes. Debes escribir cuidadosamente tu nueva clave en los dos campos";
                    }

                } else {
                    $res["valido"] = false;
                    $res["invalido"][] = 'actual';
                    $intentos = $intentospermitidos - $this->intentosLogin($ip, $usuario->getUsername()) - 1; // menos un intento
                    $res["error"] = "Clave es incorrecta. " . $intentos . " intentos restantes";
                    $this->actualizarIntentos($ip, $usuario->getUsername());
                }

            } else {
                $res["blocked"] = true;
                $res["redirect"] = $this->generateUrl('login_logout');
                $request->getSession()->getFlashBag()->add(
                        'error-sistema', 'Se ha bloqueado este usuario. Espera 5 minutos e inicia sesión en el sistema'
                );
            }

        } else {
            $res["blocked"] = true;
            $res["redirect"] = $this->generateUrl('login_logout');
            $request->getSession()->getFlashBag()->add(
                    'error-sistema', 'Sesión no reconocida. Inicia sesión'
            );
        }

        return new JsonResponse($res);

    }



    // formulario para recuperar la clave de usuario
    public function olvidoClaveUsuarioAction() {
        return $this->render('LoginBundle:Login:olvidoClaveUsuario.html.twig');
    }



    // registrar una clave temporal y enviarla por email
    public function enviarClaveTemporalAction(Request $request) {
        $username = $request->request->get('usuario');
        $email = $request->request->get('email');
        $em = $this->getDoctrine()->getManager();
        $usuario = $em->getRepository("DatosBundle:Usuario")->findOneBy(array("username" => $username));

        if ($usuario) {

            if ($usuario->getEmail() == $email) {
                $cadena = '*/1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
                $temporal = "";

                for ($i = 0; $i < 8; $i++) {
                    $temporal .= $cadena[mt_rand(0, 63)];
                }

                $clavetemporal = $this->crypt_blowfish($temporal);
                $usuario->setTemppassword($clavetemporal);
                $em->flush();

                // enviar la clave temporal por Email
                $message = \Swift_Message::newInstance()
                        ->setSubject('PMS: Clave Temporal')
                        ->setFrom(array('pms@pirwahostels.com' => 'Pirwa PMS'))
                        ->setTo($usuario->getEmail())
                        ->setBody(
                        $this->renderView(
                                'LoginBundle:Login:emailClaveTemporal.html.twig', array("usuario" => $usuario, "temporal" => $temporal)
                        ), 'text/html'
                );
                $this->get('mailer')->send($message);

                //
                $ret["valido"] = true;
                $ret["redireccion"] = $this->generateUrl('login_homepage');
                $request->getSession()->getFlashBag()->add(
                        'alerta-sistema', 'Te enviamos un email con tu clave temporal. Úsala para iniciar sesión'
                );

            } else {
                // email invalido - mostrar pista
                $ret["valido"] = false;
                $ret["invalido"][] = "email";
                $aux = explode("@", $usuario->getEmail(), 2);
                $mascara = substr($aux[0], 0, 3);

                for ($i = 0; $i < strlen($aux[0]) - 1; $i++) {
                    $mascara .= "*";
                }

                $mascara .= "@" . $aux[1];
                $ret["error"] = "Email incorrecto. Pista --> " . $mascara;
            }

        } else {
            $ret["valido"] = false;
            $ret["invalido"][] = "usuario";
            $ret["error"] = "No existe el usuario: " . $username;
        }
        return new JsonResponse($ret);
    }



    // recuperar IP del cliente
    private function getIP() {
        if (isset($_SERVER)) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                return $_SERVER['HTTP_X_FORWARDED_FOR'];
            } else {
                return $_SERVER['REMOTE_ADDR'];
            }
        } else {
            if (isset($GLOBALS['HTTP_SERVER_VARS']['HTTP_X_FORWARDER_FOR'])) {
                return $GLOBALS['HTTP_SERVER_VARS']['HTTP_X_FORWARDED_FOR'];
            } else {
                return $GLOBALS['HTTP_SERVER_VARS']['REMOTE_ADDR'];
            }
        }
    }



    // Recupera el tiempo transcurrido desde el último intento de login
    function tiempoUltimoIntento($ip, $username) {
        $segundosDiferencia = 1000;
        $em = $this->getDoctrine()->getManager();
        $lo = $em->getRepository('DatosBundle:Loginobservado')->findBy(array("username" => $username, "ip" => $ip), array("timestamp" => "DESC"));
        if ($lo) {
            $hoymismo = $this->get('utilidades')->hoyUTC();
            $horaintento = $lo[0]->getTimestamp();
            $segundosDiferencia = $hoymismo->getTimestamp() - $horaintento->getTimestamp();
        }
        return $segundosDiferencia;
    }



    // Recupera los intentos de login de un usuario desde una IP
    private function intentosLogin($ip, $username) {
        $em = $this->getDoctrine()->getManager();
        $lo = $em->getRepository('DatosBundle:Loginobservado')->findBy(array("username" => $username, "ip" => $ip), array("timestamp" => "DESC"));
        if ($lo) {
            return $lo[0]->getIntentos();
        } else {
            return 0;
        }
    }


    // actualiza el número de intentos de login en BD
    private function actualizarIntentos($ip, $username) {
        $hoy = $this->get('utilidades')->hoyUTC();
        $em = $this->getDoctrine()->getManager();
        $login = $em->getRepository('DatosBundle:Loginobservado')->findBy(array("username" => $username, "ip" => $ip), array("timestamp" => "DESC"));
        
        // ya hay un intento previo
        if ($login) {
            $intentos = $login[0]->getIntentos();
            $login[0]->setIntentos($intentos + 1);
            $login[0]->setTimestamp($hoy);

        } else {
            $log = new Loginobservado();
            $log->setIp($ip);
            $log->setUsername($username);
            $log->setIntentos(1);
            $log->setTimestamp($hoy);
            $em->persist($log);
        }
        $em->flush();
    }



    // borrar los intentos previos de login
    private function borrarIntentos($ip, $username) {
        $em = $this->getDoctrine()->getManager();
        $login = $em->getRepository('DatosBundle:Loginobservado')->findBy(array("username" => $username, "ip" => $ip));
        if ($login) {
            foreach ($login as $log) {
                $em->remove($log);
            }
            $em->flush();
        }
    }



    // borrar la clave temporal de un usuario
    private function borrarClaveTemporal($idusuario) {
        $em = $this->getDoctrine()->getManager();
        $usuario = $em->getRepository('DatosBundle:Usuario')->find($idusuario);
        if ($usuario) {
            $usuario->setTemppassword(NULL);
            $em->flush();
        }
    }

    // generar id de session
    private function idSesion() {
        $hoy = new \DateTime();
        $cadenahoy = $hoy->format('dmYHisu');
        $idsesion = $this->crypt_blowfish($cadenahoy);
        return $idsesion;
    }

    // encriptar cadena password
    function crypt_blowfish($password, $digito = 7) {
        $set_salt = './1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $salt = sprintf('$2a$%02d$', $digito);
        for ($i = 0; $i < 22; $i++) {
            $salt .= $set_salt[mt_rand(0, 63)];
        }
        return crypt($password, $salt);
    }

    /**
     * Logout the sistema
     * Redirigir a URL de return 
     */
    public function logoutAction(Request $request) {
        // limpiar la sesión
        $request->getSession()->clear();

        // el usuario ha llegado por una redirección de bloqueo?
        if ($request->query->get('blocked') == 1) {
            $return = $request->query->get('return');
            $idhotelactivo = $request->query->get('idhotel');
            $request->getSession()->getFlashBag()->add(
                    'error-sistema', 'Tu usuario no tiene permisos activados para acceder este módulo <br><span class="grey-text text-darken-3 semibold">' . $request->query->get('uri') . '</span>'
            );

            if ($this->get('utilidades')->validar_url($return) == true) {
                return $this->redirect($this->generateUrl('login_homepage', array("return" => $return, "idhotel" => $idhotelactivo)));
            } 

            return $this->redirect($this->generateUrl('login_homepage'));

        } 
        
        return $this->redirect($this->generateUrl('login_homepage'));
    }

}
