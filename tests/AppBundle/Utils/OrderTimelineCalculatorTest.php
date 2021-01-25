<?php

namespace Tests\AppBundle\Utils;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderTimeline;
use AppBundle\Entity\Task;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\DateUtils;
use AppBundle\Utils\OrderTimelineCalculator;
use AppBundle\Utils\PreparationTimeCalculator;
use AppBundle\Utils\PreparationTimeResolver;
use AppBundle\Utils\PickupTimeResolver;
use AppBundle\Utils\ShippingTimeCalculator;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class OrderTimelineCalculatorTest extends TestCase
{
    use ProphecyTrait;

    private $preparationTimeResolver;
    private $pickupTimeResolver;
    private $preparationTimeCalculator;
    private $shippingTimeCalculator;

    public function setUp(): void
    {
        $this->preparationTimeResolver = $this->prophesize(PreparationTimeResolver::class);
        $this->pickupTimeResolver = $this->prophesize(PickupTimeResolver::class);
        $this->preparationTimeCalculator = $this->prophesize(PreparationTimeCalculator::class);
        $this->shippingTimeCalculator = $this->prophesize(ShippingTimeCalculator::class);
    }

    private function createOrder(TsRange $shippingTimeRange)
    {
        $order = $this->prophesize(OrderInterface::class);
        $order
            ->isTakeaway()
            ->willReturn(false);
        $order
            ->getFulfillmentMethod()
            ->willReturn('delivery');
        $order
            ->getShippingTimeRange()
            ->willReturn($shippingTimeRange);

        return $order->reveal();
    }

    public function calculateProvider()
    {
        return [
            [
                TsRange::create(
                    new \DateTime('2018-08-25 13:25:00'),
                    new \DateTime('2018-08-25 13:35:00')
                ),
                $preparationTime = '10 minutes',
                $shippingTime = '20 minutes',
                new \DateTime('2018-08-25 13:15:00'),
                new \DateTime('2018-08-25 13:05:00'),
            ],
            [
                TsRange::create(
                    new \DateTime('2018-08-25 13:25:00'),
                    new \DateTime('2018-08-25 13:35:00')
                ),
                $preparationTime = '15 minutes',
                $shippingTime = '20 minutes',
                new \DateTime('2018-08-25 13:15:00'),
                new \DateTime('2018-08-25 13:00:00'),
            ],
            [
                TsRange::create(
                    new \DateTime('2018-08-25 13:25:00'),
                    new \DateTime('2018-08-25 13:35:00')
                ),
                $preparationTime = '30 minutes',
                $shippingTime = '20 minutes',
                new \DateTime('2018-08-25 13:15:00'),
                new \DateTime('2018-08-25 12:45:00'),
            ],
        ];
    }

    /**
     * @dataProvider calculateProvider
     */
    public function testCalculate(
        TsRange $shippingTimeRange,
        string $preparationTime,
        string $shippingTime,
        \DateTime $pickup,
        \DateTime $preparation)
    {
        $order = $this->createOrder($shippingTimeRange);

        $this->preparationTimeResolver
            ->resolve($order, $shippingTimeRange->getUpper())
            ->willReturn($preparation);

        $this->pickupTimeResolver
            ->resolve($order, $shippingTimeRange->getUpper())
            ->willReturn($pickup);

        $this->preparationTimeCalculator
            ->calculate($order)
            ->willReturn($preparationTime);

        $this->shippingTimeCalculator
            ->calculate($order)
            ->willReturn($shippingTime);

        $calculator = new OrderTimelineCalculator(
            $this->preparationTimeResolver->reveal(),
            $this->pickupTimeResolver->reveal(),
            $this->preparationTimeCalculator->reveal(),
            $this->shippingTimeCalculator->reveal()
        );

        $timeline = $calculator->calculate($order);

        $this->assertEquals($shippingTimeRange->getUpper(), $timeline->getDropoffExpectedAt());
        $this->assertEquals($pickup, $timeline->getPickupExpectedAt());
        $this->assertEquals($preparation, $timeline->getPreparationExpectedAt());
        $this->assertEquals($preparationTime, $timeline->getPreparationTime());
        $this->assertEquals($shippingTime, $timeline->getShippingTime());
    }

    public function testCalculateForTakeaway()
    {
        $shippingTimeRange = DateUtils::dateTimeToTsRange(new \DateTime('2018-08-25 13:30:00'), 5);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->isTakeaway()
            ->willReturn(true);
        $order
            ->getFulfillmentMethod()
            ->willReturn('collection');
        $order
            ->getShippingTimeRange()
            ->willReturn($shippingTimeRange);

        $pickup = new \DateTime('2018-08-25 13:30:00');
        $preparation = new \DateTime('2018-08-25 13:10:00');

        $this->preparationTimeResolver
            ->resolve($order->reveal(), $shippingTimeRange->getUpper())
            ->willReturn($preparation);

        $this->pickupTimeResolver
            ->resolve($order->reveal(), $shippingTimeRange->getUpper())
            ->willReturn($pickup);

        $this->preparationTimeCalculator
            ->calculate($order->reveal())
            ->willReturn('20 minutes');

        $this->shippingTimeCalculator
            ->calculate($order->reveal())
            ->shouldNotBeCalled();

        $calculator = new OrderTimelineCalculator(
            $this->preparationTimeResolver->reveal(),
            $this->pickupTimeResolver->reveal(),
            $this->preparationTimeCalculator->reveal(),
            $this->shippingTimeCalculator->reveal()
        );

        $timeline = $calculator->calculate($order->reveal());

        $this->assertNull($timeline->getDropoffExpectedAt());
        $this->assertEquals($pickup, $timeline->getPickupExpectedAt());
        $this->assertEquals($preparation, $timeline->getPreparationExpectedAt());
        $this->assertEquals('20 minutes', $timeline->getPreparationTime());
        $this->assertNull($timeline->getShippingTime());
    }

    public function testDelay()
    {
        $timeline = new OrderTimeline();
        $timeline->setPreparationExpectedAt(new \DateTime('2020-04-09 19:30:00'));
        $timeline->setPickupExpectedAt(new \DateTime('2020-04-09 19:45:00'));
        $timeline->setDropoffExpectedAt(new \DateTime('2020-04-09 20:00:00'));

        $pickup = new Task();
        $pickup->setAfter(new \DateTime('2020-04-09 19:40:00'));
        $pickup->setBefore(new \DateTime('2020-04-09 19:50:00'));

        $dropoff = new Task();
        $dropoff->setAfter(new \DateTime('2020-04-09 19:55:00'));
        $dropoff->setBefore(new \DateTime('2020-04-09 20:05:00'));

        $delivery = $this->prophesize(Delivery::class);
        $delivery
            ->getTasks()
            ->willReturn([ $pickup, $dropoff ]);

        $order = $this->prophesize(Order::class);
        $order
            ->getTimeline()
            ->willReturn($timeline);
        $order
            ->getShippingTimeRange()
            ->willReturn(TsRange::create(
                $dropoff->getAfter(),
                $dropoff->getBefore()
            ));
        $order
            ->getDelivery()
            ->willReturn($delivery->reveal());

        $order
            ->setShippingTimeRange(Argument::type(TsRange::class))
            ->shouldBeCalled();

        $calculator = new OrderTimelineCalculator(
            $this->preparationTimeResolver->reveal(),
            $this->pickupTimeResolver->reveal(),
            $this->preparationTimeCalculator->reveal(),
            $this->shippingTimeCalculator->reveal()
        );

        $calculator->delay($order->reveal(), 10);

        $this->assertEquals(new \DateTime('2020-04-09 19:40:00'), $timeline->getPreparationExpectedAt());
        $this->assertEquals(new \DateTime('2020-04-09 19:55:00'), $timeline->getPickupExpectedAt());
        $this->assertEquals(new \DateTime('2020-04-09 20:10:00'), $timeline->getDropoffExpectedAt());

        $this->assertEquals(new \DateTime('2020-04-09 19:50:00'), $pickup->getAfter());
        $this->assertEquals(new \DateTime('2020-04-09 20:00:00'), $pickup->getBefore());

        $this->assertEquals(new \DateTime('2020-04-09 20:05:00'), $dropoff->getAfter());
        $this->assertEquals(new \DateTime('2020-04-09 20:15:00'), $dropoff->getBefore());
    }

    public function testDelayWithTakeaway()
    {
        $timeline = new OrderTimeline();
        $timeline->setPreparationExpectedAt(new \DateTime('2020-04-09 19:30:00'));
        $timeline->setPickupExpectedAt(new \DateTime('2020-04-09 19:45:00'));
        $timeline->setDropoffExpectedAt(null);

        $order = $this->prophesize(Order::class);
        $order
            ->getTimeline()
            ->willReturn($timeline);
        $order
            ->getShippingTimeRange()
            ->willReturn(TsRange::create(
                new \DateTime('2020-04-09 19:35:00'),
                new \DateTime('2020-04-09 19:45:00')
            ));
        $order
            ->getDelivery()
            ->willReturn(null);

        $order
            ->setShippingTimeRange(Argument::type(TsRange::class))
            ->shouldBeCalled();

        $calculator = new OrderTimelineCalculator(
            $this->preparationTimeResolver->reveal(),
            $this->pickupTimeResolver->reveal(),
            $this->preparationTimeCalculator->reveal(),
            $this->shippingTimeCalculator->reveal()
        );

        $calculator->delay($order->reveal(), 10);

        $this->assertEquals(new \DateTime('2020-04-09 19:40:00'), $timeline->getPreparationExpectedAt());
        $this->assertEquals(new \DateTime('2020-04-09 19:55:00'), $timeline->getPickupExpectedAt());
        $this->assertNull($timeline->getDropoffExpectedAt());
    }
}
