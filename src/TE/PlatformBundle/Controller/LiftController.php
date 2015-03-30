<?php

namespace TE\PlatformBundle\Controller;

use TE\PlatformBundle\Entity\Lift;
use TE\PlatformBundle\Entity\Booked;
use TE\PlatformBundle\Entity\User;
use TE\PlatformBundle\Entity\BookedPassenger;
use TE\PlatformBundle\Form\AddLiftType;
use TE\PlatformBundle\Form\AddPassengerType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

class LiftController extends Controller
{
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();
        $liftRepository= $em->getRepository('TEPlatformBundle:Lift');
        $lifts = $liftRepository->findBy(array('isAvailable' => 1), array('dateLift' => 'asc'), null, null);

        return $this->render('TEPlatformBundle:Lift:index.html.twig',array('lifts' => $lifts ));

    }

    /**
     * @Security("has_role('ROLE_USER')")
     */
    public function addAction(Request $request)
    {
      $user = $this->get('security.context')->getToken()->getUser();
      $lift = new Lift();

      $form = $this->createForm(new AddLiftType(), $lift);
      // On fait le lien Requête <-> Formulaire
      // À partir de maintenant, la variable $advert contient les valeurs entrées dans le formulaire par le visiteur

      // On vérifie que les valeurs entrées sont correctes
      // (Nous verrons la validation des objets en détail dans le prochain chapitre)
      if ($form->handleRequest($request)->isValid()) {
        // On l'enregistre notre objet $advert dans la base de données, par exemple
        $em = $this->getDoctrine()->getManager();
        $lift->setDriver($user);
        $em->persist($lift);

        $booked = new Booked();
        $booked->setDriver($user);
        $booked->setLift($lift);
        $em->persist($booked);

        $em->flush();

        $request->getSession()->getFlashBag()->add('notice', 'Trajet bien enregistrée');

        // On redirige vers la page de visualisation de l'annonce nouvellement créée
        return $this->redirect($this->generateUrl('te_platform_homepage'));
      }

      // À ce stade, le formulaire n'est pas valide car :
      // - Soit la requête est de type GET, donc le visiteur vient d'arriver sur la page et veut voir le formulaire
      // - Soit la requête est de type POST, mais le formulaire contient des valeurs invalides, donc on l'affiche de nouveau
      return $this->render('TEPlatformBundle:Lift:addLift.html.twig', array(
        'form' => $form->createView(),
      ));
    }

    public function viewAction(Request $request, $id)
    {
        $user = $this->get('security.context')->getToken()->getUser();
        $em = $this->getDoctrine()->getManager();

        $liftRepository = $em->getRepository('TEPlatformBundle:Lift');
        $bookedRepository = $em->getRepository('TEPlatformBundle:Booked');
        $bookedPassengerRepository = $em->getRepository('TEPlatformBundle:BookedPassenger');

        $lift = $liftRepository->find($id);
        $booked = $bookedRepository->findOneByLift($lift);

        $isDriver = false;
        $isSubscribed = false;
        $listBookedPassenger = $bookedPassengerRepository->findByBooked($booked);
        $passengers = array();
        if ($booked->getDriver() == $user) {
            $isDriver = true;
            foreach ($listBookedPassenger as $bookedPassenger) {
              $passenger['user'] = $bookedPassenger->getPassenger();
              $passenger['seats'] = $bookedPassenger->getSeats();
              $passengers[] = $passenger;
            }
        } else {
            foreach ($listBookedPassenger as $bookedPassenger) {
              if ($bookedPassenger->getPassenger() == $user) {
                $isSubscribed = true;
                break;
              }
            }
        }

        return $this->render('TEPlatformBundle:Lift:viewLift.html.twig', array('lift' => $lift, 'booked' => $booked, 'isSubscribed' => $isSubscribed, 'isDriver' => $isDriver, 'passengers' => $passengers));
    }

    /**
     * @Security("has_role('ROLE_USER')")
     */
    public function subscribeAction(Request $request, $id)
    {
        $request = $this->get('request');
        $user = $this->get('security.context')->getToken()->getUser();
        $em = $this->getDoctrine()->getManager();
        $bookedRepository = $em->getRepository('TEPlatformBundle:Booked');
        $booked = $em->getRepository('TEPlatformBundle:Booked')->find($id);

        if( $request->getMethod() == 'POST' ) {
          $seats = $request->get('seats');
          $bookedPassenger = new BookedPassenger();
          $bookedPassenger->setPassenger($user);
          $bookedPassenger->setBooked($booked);
          $bookedPassenger->setSeats($seats);
          $em->persist($bookedPassenger);
          $em->flush();
        }

        $idLift = $booked->getLift()->getId();
        $response = $this->forward('TEPlatformBundle:Lift:view', array('id' => $idLift));
        return $response;
    }

    /**
     * @Security("has_role('ROLE_USER')")
     */
    public function unsubscribeAction($id)
    {
        $user = $this->get('security.context')->getToken()->getUser();
        $em = $this->getDoctrine()->getManager();
        $bookedRepository = $em->getRepository('TEPlatformBundle:Booked');
        $booked = $em->getRepository('TEPlatformBundle:Booked')->find($id);

        $bookedPassengerRepository = $em->getRepository('TEPlatformBundle:BookedPassenger');
        $bookedPassenger = $bookedPassengerRepository->findOneBy(array("booked" => $booked, "passenger" => $user));

        $em->remove($bookedPassenger);
        $em->flush();

        $idLift = $booked->getLift()->getId();
        $response = $this->forward('TEPlatformBundle:Lift:view', array('id' => $idLift));
        return $response;
    }
  }
