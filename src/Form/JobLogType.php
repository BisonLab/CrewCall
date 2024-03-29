<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

use App\Lib\ExternalEntityConfig;
use App\Entity\JobLog;
use App\Entity\Job;

class JobLogType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
           ->add('in', DateTimeType::class, array(
                'label' => "In:",
                'date_widget' => "single_text",
                'time_widget' => "single_text",
                'attr' => array('autofocus' => true, 'tabindex' => 0)))
           ->add('out', DateTimeType::class, array(
                'label' => "Out:",
                'date_widget' => "single_text",
                'time_widget' => "single_text",
                'attr' => array('tabindex' => 1)))
           ->add('break_minutes', IntegerType::class, array(
                'label' => "Break in mins: ",
                'attr' => array('size'=> 3, 'tabindex' => 2)))
           ;
        // Anyone at all?
        $noshow_states = ExternalEntityConfig::getNoShowStatesFor('Job');
        if (!empty($noshow_states)) {
            // Yep, ugly
            $job_states = ExternalEntityConfig::getStatesFor('Job');
            $choices = [];
            foreach ($job_states as $state => $arr) {
                if (in_array($state, $noshow_states))
                    $choices[$arr['label']] = $state;
            }
            $builder->add('noshow_state', ChoiceType::class,array(
                'label' => 'No show?',
                'placeholder' => 'Did show up',
                'choices' => $choices,
                'mapped'  => false,
                'multiple'  => false));
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => JobLog::class,
            'allow_extra_fields' => true
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'joblog';
    }
}
