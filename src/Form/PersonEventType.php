<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

use App\Lib\ExternalEntityConfig;
use App\Entity\PersonRoleEvent;

class PersonEventType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
           ->add('person', EntityType::class, array(
                    'class' => 'App:Person',
                    'label' => "Person",
                    'choices' => $options['people'],
                    'required' => true
                ))
           ->add('role', EntityType::class,
               array('class' => 'App:Role'))
        ;
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'people' => [],
            'data_class' => PersonRoleEvent::class
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'pre';
    }
}
