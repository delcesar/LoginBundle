<?php

namespace LoginBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class PerfilUsuarioController extends Controller {

    // perfil del usuario
    public function indexAction(Request $request) {

        $session = $request->getSession();
        $em = $this->getDoctrine()->getManager();
        $usuario = $em->getRepository("DatosBundle:Usuario")->find($session->get('idusuario'));
        $nivel = $session->get('nivelusuario');
        $permisosusuario = array();

        // los usuario de nivel superior tienen acceso a todos los hoteles
        if ($nivel <= 2) {

            // recuperar el último permiso concedido a este usuario para uno de los hoteles
            foreach ($session->get("permisosusuario") as $idhotel) {
                $auxpermisos = $em->createQuery('SELECT p FROM DatosBundle:Permisousuariohotel p LEFT JOIN p.idhotel h WHERE p.idusuario = :user AND p.desde <= CURRENT_DATE() AND (p.hasta IS NULL OR p.hasta >= CURRENT_DATE()) AND p.vigente = 1 AND h.idhotel = :idhotel ORDER BY p.idpermisousuariohotel DESC')
                        ->setParameter('user', $session->get('idusuario'))
                        ->setParameter('idhotel', $idhotel)
                        ->setMaxresults(1)
                        ->getResult();
                $permisosusuario[$idhotel] = $auxpermisos[0];
            }
        }

        $hoteles = $em->createQuery('SELECT h FROM DatosBundle:Hotel h INDEX BY h.idhotel')->getResult();

        return $this->render('LoginBundle:Perfil:perfilUsuario.html.twig', array(
            "usuario" => $usuario,
            "hoteles" => $hoteles,
            "permisos" => $permisosusuario
        ));
    }

}
