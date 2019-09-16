<?php

namespace AppBundle\Form;

use AppBundle\Service\RoutingInterface;
use AppBundle\Service\SettingsManager;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatorInterface;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber as AssertPhoneNumber;

class DeliveryEmbedType extends DeliveryType
{
    private $settingsManager;

    public function __construct(
        RoutingInterface $routing,
        TranslatorInterface $translator,
        string $country,
        string $locale,
        SettingsManager $settingsManager)
    {
        parent::__construct($routing, $translator, $country, $locale);

        $this->settingsManager = $settingsManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $options = array_merge($options, [
            'with_vehicle' => $this->settingsManager->getBoolean('embed.delivery.withVehicle'),
        ]);

        parent::buildForm($builder, $options);

        $builder
            ->add('name', TextType::class, [
                'mapped' => false,
                'label' => 'form.delivery_embed.name.label',
                'help' => 'form.delivery_embed.name.help'
            ])
            ->add('email', EmailType::class, [
                'mapped' => false,
                'label' => 'form.email',
                'translation_domain' => 'FOSUserBundle'
            ])
            ->add('telephone', PhoneNumberType::class, [
                'mapped' => false,
                'format' => PhoneNumberFormat::NATIONAL,
                'default_region' => strtoupper($this->country),
                'label' => 'form.delivery_embed.telephone.label',
                'constraints' => [
                    new AssertPhoneNumber()
                ],

            ])
            ->add('billingAddress', AddressType::class, [
                'mapped' => false,
                'extended' => true,
            ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options) {

            $form = $event->getForm();

            // This is here to avoid a BC break since AddressBookType was introduced
            // FIXME Use AddressBookType everywhere

            $form->get('pickup')->remove('address');
            $form->get('dropoff')->remove('address');

            $form->get('pickup')->add('address', AddressType::class);
            $form->get('dropoff')->add('address', AddressType::class);
        });
    }
}
