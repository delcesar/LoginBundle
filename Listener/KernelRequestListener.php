<?php

namespace LoginBundle\Listener;

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManager;

class KernelRequestListener {

    protected $em;
    protected $router;

    public function __construct(EntityManager $em, Session $session, Router $router) { // this is @service_container
        $this->em = $em;
        $this->session = $session;
        $this->router = $router;
    }


    /**
     * validar el ID de sesion del usuario
     * @return {bool}
     */
    private function idSesionValida() {
        $valido = false;
        $idusuario = $this->session->get('idusuario');
        $idsesion = $this->session->get('idsesion');
        if (is_numeric($idusuario) && $idusuario > 0) {
            $usuario = $this->em->getRepository('DatosBundle:Usuario')->find($idusuario);
            if ($idsesion == $usuario->getIdsession()) {
                $valido = true;
            }
        }
        return $valido;
    }


    /**
     * comprobar que tiene permisos de acceso para este hotel
     * @return {bool}
     */
    private function comprobacionPermisoHotel() {
        $permitido = false;
        $permisos = $this->session->get('permisosusuario');
        $permisosexpiran = $this->session->get('permisosexpiran');

        // si el idhotel está entre los permisos
        if (in_array($this->session->get('idhotelactivo'), $permisos)) {

            // ninguno de sus permisos expiran
            if (empty($permisosexpiran)) {
                $permitido = true;

            } else {
                // el idhotel no está entre los permisos que expiran
                if (!array_key_exists($this->session->get('idhotelactivo'), $permisosexpiran)) {
                    $permitido = true;

                } else {
                    // guardar los permisos que expiraran pronto
                    $hoy = new \DateTime();
                    $vencimiento = \DateTime::createFromFormat('Y-m-d H:i:s', $permisosexpiran[$this->session->get('idhotelactivo')]);

                    // todavía está vigente el permiso
                    if ($hoy < $vencimiento) {
                        $permitido = true;
                    }
                }
            }
        }
        return $permitido;
    }


    /**
     * Interceptar TODAS las llamadas al Kernel para validar la sesion y permisos del usuario
     */
    public function onKernelRequest(GetResponseEvent $event) {
        $request = $event->getRequest();
        $bloquearRequest = false;

        // se esta tratando  de acceder a un módulo protegido?

        //== LOGIN
        if (stripos($request->getRequestUri(), '/perfil/') === 0) {
            // debe tener la sesión iniciada y con ID sesión válida
            /* if (!$this->idSesionValida()) {
              $bloquearRequest = true;
              } */
        }

        //== VENTAS
        elseif (stripos($request->getRequestUri(), '/ventas/') === 0) {

            // id sesión válida y nivel de ventas o recepcion
            if ($this->idSesionValida()) {
                $permitido = false;

                if ($this->session->get('nivelusuario') <= 1) {
                    $permitido = $this->comprobacionPermisoHotel();
                }
                if (!$permitido) {
                    $bloquearRequest = true;
                }

            } else {
                $bloquearRequest = true;
            }
        }
        //== RECEPCION
        elseif (stripos($request->getRequestUri(), '/recepcion/') === 0) {
            // id sesion valida y nivel de recepcion
            if ($this->idSesionValida()) {
                $permitido = false;

                if ($this->session->get('nivelusuario') === 1) {
                    $permitido = $this->comprobacionPermisoHotel();
                }
                if (!$permitido) {
                    $bloquearRequest = true;
                }

            } else {
                $bloquearRequest = true;
            }
        }
        //== ADMINISTRACIÓN
        elseif (stripos($request->getRequestUri(), '/administracion/') === 0) {
            // id sesion valida y nivel de administracion
            if ($this->idSesionValida()) {
                $permitido = false;

                if ($this->session->get('nivelusuario') === 2) {
                    $permitido = $this->comprobacionPermisoHotel();
                }
                if (!$permitido) {
                    $bloquearRequest = true;
                }

            } else {
                $bloquearRequest = true;
            }
        }
        //== RESERVAS
        elseif (stripos($request->getRequestUri(), '/reservaciones/') === 0) {
            if (!$this->idSesionValida() || $this->session->get('nivelusuario') !== 3) {
                $bloquearRequest = true;
            }
        }
        //== GERENCIA
        elseif (stripos($request->getRequestUri(), '/gerencia/') === 0) {
            if (!$this->idSesionValida() && $this->session->get('nivelusuario') !== 4) {
                $bloquearRequest = true;
            }
        }
        //== RECURSOS SISTEMA
        elseif (stripos($request->getRequestUri(), '/recursos/') === 0) {
            if (!$this->idSesionValida()) {
                $bloquearRequest = true;
            }
        }

        // bloquear Request?
        if ($bloquearRequest) {

            // es una petición por AJAX
            if ($request->isXmlHttpRequest()) {
                $path = str_replace("#", "%23", $request->headers->get('referer'));
                $idhotel = $this->session->get('idhotelactivo');
                $url = $this->router->generate('login_logout', array('blocked' => 1, 'return' => $path, 'idhotel' => $idhotel, "uri" => $request->getRequestUri()), true);
                $ret["redirect"] = $url;
                $ret["blocked"] = true;
                $response = new JsonResponse($ret);
                $event->setResponse($response);

            } else {
                $path = str_replace("#", "%23", "http://" . $request->getHttpHost() . $request->getRequestUri());
                $idhotel = $this->session->get('idhotelactivo');
                $url = $this->router->generate('login_logout', array('blocked' => 1, 'return' => $path, 'idhotel' => $idhotel, "uri" => $request->getRequestUri()), true);
                $response = new RedirectResponse($url);
                $event->setResponse($response);
            }
        }
    }

}
