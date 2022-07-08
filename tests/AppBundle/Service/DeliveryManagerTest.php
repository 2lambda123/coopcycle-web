<?php

namespace Tests\AppBundle\Service;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderTimeline;
use AppBundle\Entity\Task;
use AppBundle\Entity\Zone;
use AppBundle\ExpressionLanguage\ZoneExpressionLanguageProvider;
use AppBundle\Exception\ShippingAddressMissingException;
use AppBundle\Security\TokenStoreExtractor;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\RoutingInterface;
use AppBundle\Utils\OrderTimeHelper;
use AppBundle\Utils\OrderTimelineCalculator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class DeliveryManagerTest extends KernelTestCase
{
    use ProphecyTrait;

    private $expressionLanguage;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->expressionLanguage = static::$kernel->getContainer()->get('coopcycle.expression_language');

        $this->orderTimeHelper = $this->prophesize(OrderTimeHelper::class);
        $this->routing = $this->prophesize(RoutingInterface::class);
        $this->orderTimelineCalculator = $this->prophesize(OrderTimelineCalculator::class);
        $this->storeExtractor = $this->prophesize(TokenStoreExtractor::class);
    }

    public function testGetPrice()
    {
        $rule1 = new PricingRule();
        $rule1->setExpression('distance in 0..3000');
        $rule1->setPrice(5.99);

        $rule2 = new PricingRule();
        $rule2->setExpression('distance in 3000..5000');
        $rule2->setPrice(6.99);

        $rule3 = new PricingRule();
        $rule3->setExpression('distance in 5000..7500');
        $rule3->setPrice(8.99);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
            $rule3,
        ]));

        $deliveryManager = new DeliveryManager(
            $this->expressionLanguage,
            $this->routing->reveal(),
            $this->orderTimeHelper->reveal(),
            $this->orderTimelineCalculator->reveal(),
            $this->storeExtractor->reveal()
        );

        $delivery = new Delivery();
        $delivery->setDistance(1500);

        $this->assertEquals(5.99, $deliveryManager->getPrice($delivery, $ruleSet));
    }

    public function testGetPriceWithMapStrategy()
    {
        $rule1 = new PricingRule();
        $rule1->setExpression('true');
        $rule1->setPrice(599);

        $rule2 = new PricingRule();
        $rule2->setExpression('distance in 0..3000');
        $rule2->setPrice(100);

        $rule3 = new PricingRule();
        $rule3->setExpression('distance in 3000..5000');
        $rule3->setPrice(200);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
            $rule3,
        ]));

        $deliveryManager = new DeliveryManager(
            $this->expressionLanguage,
            $this->routing->reveal(),
            $this->orderTimeHelper->reveal(),
            $this->orderTimelineCalculator->reveal(),
            $this->storeExtractor->reveal()
        );

        $delivery = new Delivery();
        $delivery->setDistance(1500);

        $this->assertEquals(699, $deliveryManager->getPrice($delivery, $ruleSet));
    }

    public function testCreateFromOrder()
    {
        $restaurantAddress = new Address();
        $restaurantAddressCoords = new GeoCoordinates();
        $restaurantAddress->setGeo($restaurantAddressCoords);

        $shippingAddress = new Address();
        $shippingAddressCoords = new GeoCoordinates();
        $shippingAddress->setGeo($shippingAddressCoords);

        $restaurant = new Restaurant();
        $restaurant->setAddress($restaurantAddress);

        $order = new Order();
        $order->setRestaurant($restaurant);
        // $order->addItem($this->createOrderItem(1000));
        $order->setShippingAddress($shippingAddress);

        $shippingTimeRange = new TsRange();
        $shippingTimeRange->setLower(new \DateTime('2020-04-09 19:55:00'));
        $shippingTimeRange->setUpper(new \DateTime('2020-04-09 20:05:00'));

        $this->orderTimeHelper
            ->getShippingTimeRange($order)
            ->willReturn($shippingTimeRange);

        $expectedPickupAfter = new \DateTime('2020-04-09 19:40:00');
        $expectedPickupBefore = new \DateTime('2020-04-09 19:50:00');

        $this->routing
            ->getDistance($restaurantAddressCoords, $shippingAddressCoords)
            ->willReturn(1200);

        $this->routing
            ->getDuration($restaurantAddressCoords, $shippingAddressCoords)
            ->willReturn(900);

        $timeline = new OrderTimeline();
        $timeline->setPickupExpectedAt(new \DateTime('2020-04-09 19:45:00'));

        $this->orderTimelineCalculator
            ->calculate($order, $shippingTimeRange)
            ->willReturn($timeline);

        $deliveryManager = new DeliveryManager(
            $this->expressionLanguage,
            $this->routing->reveal(),
            $this->orderTimeHelper->reveal(),
            $this->orderTimelineCalculator->reveal(),
            $this->storeExtractor->reveal()
        );

        $delivery = $deliveryManager->createFromOrder($order);

        $pickup = $delivery->getPickup();
        $dropoff = $delivery->getDropoff();

        $this->assertEquals(1200, $delivery->getDistance());
        $this->assertEquals($expectedPickupAfter, $pickup->getAfter());
        $this->assertEquals($expectedPickupBefore, $pickup->getBefore());
        $this->assertEquals($restaurantAddress, $pickup->getAddress());
        $this->assertEquals($shippingAddress, $dropoff->getAddress());
    }

    public function testCreateFromOrderThrowsException()
    {
        $this->expectException(ShippingAddressMissingException::class);

        $restaurantAddress = new Address();
        $restaurantAddressCoords = new GeoCoordinates();
        $restaurantAddress->setGeo($restaurantAddressCoords);

        $shippingAddress = new Address();
        $shippingAddressCoords = new GeoCoordinates();
        $shippingAddress->setGeo($shippingAddressCoords);

        $restaurant = new Restaurant();
        $restaurant->setAddress($restaurantAddress);

        $order = new Order();
        $order->setRestaurant($restaurant);
        // The shipping address is missing
        // $order->setShippingAddress(null);

        $deliveryManager = new DeliveryManager(
            $this->expressionLanguage,
            $this->routing->reveal(),
            $this->orderTimeHelper->reveal(),
            $this->orderTimelineCalculator->reveal(),
            $this->storeExtractor->reveal()
        );

        $delivery = $deliveryManager->createFromOrder($order);
    }

    public function testGetPriceWithMapStrategyWithMultiplePickups()
    {
        $pickup1Address = new Address();
        $pickup1Address->setStreetAddress('Pickup 1');

        $pickup2Address = new Address();
        $pickup2Address->setStreetAddress('Pickup 2');

        $rule2 = new PricingRule();
        $rule2->setExpression('in_zone(pickup.address, "Zone A")');
        $rule2->setPrice(100);

        $rule3 = new PricingRule();
        $rule3->setExpression('in_zone(pickup.address, "Zone B")');
        $rule3->setPrice(200);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule2,
            $rule3,
        ]));

        $zoneA = $this->prophesize(Zone::class);
        $zoneA
            ->containsAddress(Argument::type(Address::class))
            ->will(function ($args) use ($pickup1Address) {

                if ($args[0] === $pickup1Address) {

                    return true;
                }

                return false;
            });

        $zoneB = $this->prophesize(Zone::class);
        $zoneB
            ->containsAddress(Argument::type(Address::class))
            ->will(function ($args) use ($pickup2Address) {

                if ($args[0] === $pickup2Address) {

                    return true;
                }

                return false;
            });

        $zoneRepository = $this->prophesize(EntityRepository::class);
        $zoneRepository
            ->findOneBy(['name' => 'Zone A'])
            ->willReturn($zoneA->reveal());
        $zoneRepository
            ->findOneBy(['name' => 'Zone B'])
            ->willReturn($zoneB->reveal());

        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->registerProvider(
            new ZoneExpressionLanguageProvider($zoneRepository->reveal())
        );

        $deliveryManager = new DeliveryManager(
            $expressionLanguage,
            $this->routing->reveal(),
            $this->orderTimeHelper->reveal(),
            $this->orderTimelineCalculator->reveal(),
            $this->storeExtractor->reveal()
        );

        $pickup1 = new Task();
        $pickup1->setType(Task::TYPE_PICKUP);
        $pickup1->setAddress($pickup1Address);

        $pickup2 = new Task();
        $pickup2->setType(Task::TYPE_PICKUP);
        $pickup2->setAddress($pickup2Address);

        $dropoff = new Task();
        $dropoff->setType(Task::TYPE_DROPOFF);

        $delivery = Delivery::createWithTasks(...[ $pickup1, $pickup2, $dropoff ]);

        $this->assertEquals(300, $deliveryManager->getPrice($delivery, $ruleSet));
    }
}
