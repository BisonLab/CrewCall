<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

use App\Lib\ExternalEntityConfig;
use App\Entity\Shift;
use App\Entity\FunctionEntity;

class ShiftType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
           ->add('start', DateTimeType::class, array(
                'required' => true,
                'label' => "Start",
                'date_widget' => "single_text",
                'time_widget' => "single_text"))
           ->add('end', DateTimeType::class, array(
                'required' => true,
                'label' => "End",
                'date_widget' => "single_text",
                'time_widget' => "single_text"))
           ->add('state', ChoiceType::class, array(
                'label' => 'Status',
                'choices' => ExternalEntityConfig::getStatesAsChoicesFor('Shift')))
           ->add('amount', TextType::class, array('required' => true, 'attr' => array('size' => 3, 'pattern' => '[0-9]{1,3}')))
           ->add('function', EntityType::class,
               array('class' => FunctionEntity::class,
                   'query_builder' => function(EntityRepository $er) use ($options) {
                        return $er->createQueryBuilder('fe')
                             ->where("fe.state in (:active_states)")
                             ->orderBy('fe.name', 'ASC')
                             ->setParameter('active_states',  ExternalEntityConfig::getActiveStatesFor('FunctionEntity'));
                        },
               ))
            ->add('event')
           ;
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Shift::class
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'shift';
    }


}
