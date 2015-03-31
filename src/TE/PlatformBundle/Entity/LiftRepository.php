<?php

namespace TE\PlatformBundle\Entity;

use Doctrine\ORM\EntityRepository;

/**
 * LiftRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class LiftRepository extends EntityRepository
{
  public function findAvailableLiftByDate()
  {
    $qb = $this->createQueryBuilder('lift');

    $qb->Where('lift.dateLift > :date')
         ->setParameter('date', new \DateTime())
       ->orderBy('lift.dateLift', 'ASC')
    ;

    return $qb
      ->getQuery()
      ->getResult()
    ;
  }
}
