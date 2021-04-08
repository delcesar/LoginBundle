<?php

namespace LoginBundle\Listener;

use DatosBundle\Entity\Loginobservado;
use Symfony\Component\HttpFoundation\Session\Session;
use Doctrine\ORM\Event\LifecycleEventArgs;

class LoginObservadoEntityListener {

    public function __construct(Session $session) {
        $this->session = $session;
    }

    /**
     * Si el login fue observado, guardar en log y mostrar mensaje
     */
    public function prePersist(LifecycleEventArgs $args) {
        $entity = $args->getEntity();

        if ($entity instanceof Loginobservado) {
            $this->session->getFlashBag()->add(
                    'error-sistema', 'Tu usuario no tiene permisos activados para acceder a la sección'
            );
        }
    }

}
