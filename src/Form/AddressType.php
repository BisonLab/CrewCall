<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use App\Entity\EmbeddableAddress;

class AddressType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        foreach($options['address_elements'] as $elem) {
            // If it guesses, addresslines wil be texareas and that I will not
            // Seems like embeddable addreess and this does not work with
            // Assert. which means I have to hack here.
            if ($elem == "countryCode")
                $builder->add($elem, TextType::class, ['required'=>false, 'attr' => ['size' => 3, 'maxlength' => 3]]);
            else
                $builder->add($elem, TextType::class, ['required'=>false]);
        }
/*
        $builder
            ->add('countryCode')
            ->add('addressLine1')
            ->add('addressLine2')
            ->add('postalCode')
            ->add('postalName')
            ->add('locality')
            ->add('dependentLocality')
            ->add('sortingCode')
            ->add('administrativeArea')
            ->add('locale')
            ;
*/
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => EmbeddableAddress::class,
            'address_elements' => []
        ));
    }
}
