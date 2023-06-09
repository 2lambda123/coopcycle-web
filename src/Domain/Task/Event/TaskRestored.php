<?php

namespace AppBundle\Domain\Task\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\HasIconInterface;
use AppBundle\Domain\Task\Event;

class TaskRestored extends Event implements DomainEvent, HasIconInterface
{
    public static function messageName(): string
    {
        return 'task:restored';
    }

    public static function iconName()
    {
        return 'plus';
    }
}

