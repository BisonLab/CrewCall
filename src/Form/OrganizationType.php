<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

use App\Form\AddressType;
use App\Lib\ExternalEntityConfig;

class OrganizationType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('organization_number')
            ->add('office_phone_number')
            ->add('office_email')
            ->add('state', ChoiceType::class, array(
              'label' => 'Status',
              'choices' => ExternalEntityConfig::getStatesAsChoicesFor('Organization')))
            // ->add('attributes')
            ->add('visit_address', AddressType::class, ['address_elements' => $options['address_elements']])
            ;

        if ($options['addressing_config']['use_postal_address']) {
            $builder
                ->add('postal_address', AddressType::class, ['address_elements' => $options['address_elements']])
            ;
        }
           ;
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'App\Entity\Organization',
            'address_elements' => [],
            'addressing_config' => []
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'organization';
    }


}
