<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

use App\Lib\ExternalEntityConfig;
use App\Entity\Event;
use App\Entity\Organization;
use App\Entity\Location;

class EventType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('description', TextType::class, array('required' => false))
            ->add('start', DateTimeType::class, array(
                'label' => "Start",
                'date_widget' => "single_text",
                'time_widget' => "single_text"))
            ->add('end', DateTimeType::class, array('label' => "End",
                'date_widget' => "single_text",
                'time_widget' => "single_text"))
            ->add('state', ChoiceType::class, array(
                'label' => 'Status',
                'choices' => ExternalEntityConfig::getStatesAsChoicesFor('Event')))
            ->add('location', EntityType::class,
                array('class' => Location::class,
                    'query_builder' => function(EntityRepository $er) {
                    return $er->createQueryBuilder('l')
                     ->where("l.state = 'OPEN'")
                     ->orderBy('l.name', 'ASC');
                    },
                ))
            ->add('organization', EntityType::class,
                array('class' => Organization::class,
                    'query_builder' => function(EntityRepository $er) {
                    return $er->createQueryBuilder('o')
                     ->where("o.state = 'ACTIVE'")
                     ->orderBy('o.name', 'ASC');
                    },
                ))
            ->add('parent', EntityType::class,
                array(
                    'required' => false,
                    'placeholder' => "",
                    'class' => Event::class,
                    'query_builder' => function(EntityRepository $er) {
                        $today = new \DateTime();
                        return $er->createQueryBuilder('e')
                         ->where("e.parent is null")
                         ->andWhere("e.end > :today")
                         ->setParameter('today', $today);
                    },
                ))
           ;
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Event::class
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'event';
    }
}
