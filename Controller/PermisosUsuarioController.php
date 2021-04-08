<?php

namespace LoginBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class PermisosUsuarioController extends Controller {

    /**
     * Recuperar permisos de usuario
     * este metodo comprueba si un usuario tiene permiso de acceso a un hotel
     * pero también actualiza la lista de permisos de un usuario
     * por lo que se le invoca solo para actualizar los permisos (con un ID hotel que no existe)
     */
    public function permisosUsuarioAction(Request $request) {
        
        $this->get('guardian')->tienePermisoAccesoHotel(0);
        $session = $request->getSession();
        $permisos = $session->get('permisosusuario');

        if (count($permisos) > 0) {

            //seleccionar el primer hotel por default
            $session->set('idhotelactivo', $permisos[0]);

            $nivel = $session->get('nivelusuario');

            switch ($nivel) {
                // 0 : puesto de venta
                case 0: {
                        return $this->redirect($this->generateUrl('venta_homepage'));
                        break;
                    }
                // 1 : recepcion de hotel
                case 1: {
                        return $this->redirect($this->generateUrl('recepcion_homepage'));
                        break;
                    }
                // 2 : administracion de hotel
                case 2: {
                        return $this->redirect($this->generateUrl('administracion_homepage'));
                        break;
                    }
                // 3 : central de reservas
                case 3: {
                        return $this->redirect($this->generateUrl('reservaciones_homepage'));
                        break;
                    }
                // 4 : gerencia general
                case 4: {
                        return $this->redirect($this->generateUrl('gerencia_homepage'));
                        break;
                    }
                // valor corrupto
                default: {
                        return $this->redirect($this->generateUrl('login_permisos_denegados'));
                        break;
                    }
            }
        }

        // permisos denegados
        return $this->redirect($this->generateUrl('login_permisos_denegados'));
    }

    
    /**
     * Redirigir a la pagina de inicio del nivel correspondiente
     * dependiendo del nivel de usuario y los permisos
     * 
     */
    public function redirigirHomepageNivelAction(Request $request) {
        $session = $request->getSession();

        // comprobar si el usuario tiene permiso de acceso al hotel
        $idhotel = $session->get('idhotelactivo');
        $tienepermiso = $this->get('guardian')->tienePermisoAccesoHotel($idhotel);

        if ($tienepermiso) {

            $nivel = $session->get('nivelusuario');
            switch ($nivel) {
                // 0 : puesto de venta
                case 0: {
                        return $this->redirect($this->generateUrl('venta_hotel'));
                        break;
                    }
                // 1 : recepcion de hotel
                case 1: {
                        return $this->redirect($this->generateUrl('recepcion_hotel'));
                        break;
                    }
                // 2 : administracion de hotel
                case 2: {
                        return $this->redirect($this->generateUrl('administracion_hotel'));
                        break;
                    }
                // 3 : central de reservas
                case 3: {
                        return $this->redirect($this->generateUrl('reservaciones_homepage'));
                        break;
                    }
                // 4 : gerencia general
                case 4: {
                        return $this->redirect($this->generateUrl('gerencia_homepage'));
                        break;
                    }
                // valor corrupto
                default: {
                        return $this->redirect($this->generateUrl('login_permisos_denegados'));
                        break;
                    }
            }
        } 

        // mostrar permisos de este usuario que podrian estar caducados
        return $this->redirect($this->generateUrl('login_permisos_usuario'));
    }



    // usuario no tiene permisos activos - mostrar login
    public function loginDenegadoAction(Request $request) {
        $session = $request->getSession();
        $session->getFlashBag()->add('alerta-sistema', 'Tu usuario no tiene permisos activos');
        $this->getRequest()->getSession()->clear();
        
        return $this->redirect($this->generateUrl('login_homepage'));
    }


    // mostrar permisos denegados 
    public function permisosDenegadosAction(Request $request) {
        $request->getSession()->clear();
        return $this->render('LoginBundle:Permisos:permisosDenegados.html.twig');
    }

}
