<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use App\Lib\ExternalEntityConfig;
use App\Form\AddressType;
use App\Entity\Location;

class LocationType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('description')
            ->add('state', ChoiceType::class, array(
                'label' => 'Status',
                'choices' => ExternalEntityConfig::getStatesAsChoicesFor('Location')))
            ->add('address', AddressType::class, ['address_elements' => $options['address_elements']])
            ->add('map_link', TextType::class)
        ;
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Location::class,
            'address_elements' => []
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'location';
    }


}
