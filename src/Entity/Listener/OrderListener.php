<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Vendor;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;

class OrderListener
{
    public function preFlush(Order $order, PreFlushEventArgs $args)
    {
        $entityManager = $args->getEntityManager();
        $vendor = $order->getVendor();

        if (null !== $vendor) {
            if (!$entityManager->contains($vendor)) {
                $existingVendor = $entityManager->getRepository(Vendor::class)
                    ->findOneBy(['restaurant' => $vendor->getRestaurant()]);
                if (null !== $existingVendor) {
                    $order->setVendor($existingVendor);
                } else {
                    $entityManager->persist($vendor);
                    $entityManager->flush();
                }
            }
        }
    }
}
