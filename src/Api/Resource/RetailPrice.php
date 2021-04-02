<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Core\Action\NotFoundAction;
use AppBundle\Action\Delivery\CalculateRetailPrice as CalculateController;
use AppBundle\Api\Dto\DeliveryInput;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Taxation\Model\TaxRateInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

/**
 * @ApiResource(
 *   attributes={
 *     "normalization_context"={"groups"={"pricing_deliveries"}}
 *   },
 *   collectionOperations={
 *     "calc_price"={
 *       "method"="POST",
 *       "path"="/retail_prices/calculate",
 *       "input"=DeliveryInput::class,
 *       "controller"=CalculateController::class,
 *       "status"=200,
 *       "write"=false,
 *       "denormalization_context"={"groups"={"delivery_create", "pricing_deliveries"}},
 *       "access_control"="is_granted('ROLE_ADMIN') or is_granted('ROLE_STORE') or is_granted('ROLE_OAUTH2_DELIVERIES')",
 *       "swagger_context"={
 *         "summary"="Calculates price of a Delivery",
 *       }
 *     },
 *   },
 *   itemOperations={
 *     "get": {
 *       "method"="GET",
 *       "controller"=NotFoundAction::class,
 *       "read"=false,
 *       "output"=false
 *     }
 *   }
 * )
 */
final class RetailPrice
{
    /**
     * @var string
     *
     * @ApiProperty(identifier=true)
     */
    public $id;

    /**
     * @var int
     *
     * @Groups({"pricing_deliveries"})
     */
    public $amount;

    /**
     * @var string
     *
     * @Groups({"pricing_deliveries"})
     */
    public $currency;

    /**
     * @var int
     */
    public $taxAmount;

    /**
     * @var TaxRateInterface
     */
    public $taxRate;

    public function __construct(int $amount, string $currency, int $taxAmount)
    {
        $this->id = Uuid::uuid4()->toString();
        $this->amount = $amount;
        $this->currency = $currency;
        $this->taxAmount = $taxAmount;
    }

    /**
     * @Groups({"pricing_deliveries"})
     * @SerializedName("tax")
     */
    public function getTax(): array
    {
        return [
            // 'rate'   => $this->taxRate->getAmount(),
            'amount' => $this->taxAmount,
        ];
    }
}