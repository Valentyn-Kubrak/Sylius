<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sylius\Bundle\CoreBundle\EventListener;

use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Sylius\Bundle\CoreBundle\Model\OrderItemInterface;

/**
 * Order item inventory processing listener.
 *
 * @author Alexandre Bacco <alexandre.bacco@gmail.com>
 */
class OrderItemInventoryListener
{
    /**
     * Event Dispatcher
     *
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * Constructor.
     *
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function prePersist(LifecycleEventArgs $args)
    {
        $item = $args->getEntity();

        if (!$this->supports($item)) {
            return;
        }

        $this->eventDispatcher->dispatch('sylius.order_item.pre_create', new GenericEvent($item));
    }

    public function onFlush(OnFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($this->supports($entity)) {
                $this->eventDispatcher->dispatch('sylius.order_item.pre_update', new GenericEvent($entity));

                foreach ($entity->getInventoryUnits() as $unit) {
                    $em->persist($unit);
                    $uow->computeChangeSet($em->getClassMetadata(get_class($unit)), $unit);
                }

                $uow->recomputeSingleEntityChangeSet($em->getClassMetadata(get_class($entity)), $entity);
            }
        }
    }

    protected function supports($entity)
    {
        return $entity instanceof OrderItemInterface;
    }
}
