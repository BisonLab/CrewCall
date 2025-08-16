<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use App\Lib\ExternalEntityConfig;
use App\Entity\Job;

class JobType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('pname', TextType::class, array(
                'label' => "Person",
                'mapped' => false,
                'required' => true, 'attr' => ['class' => 'ui-front']))
            ->add('state', ChoiceType::class, array(
                'label' => 'Status',
                'choices' => ExternalEntityConfig::getStatesAsChoicesFor('Job')))
            ->add('shift')
        ;
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Job::class
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'job';
    }
}
