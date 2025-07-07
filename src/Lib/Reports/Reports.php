<?php

namespace App\Lib\Reports;

use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;

use BisonLab\ReportsBundle\Lib\Reports\ReportsInterface;
use BisonLab\ReportsBundle\Lib\Reports\CommonReportFunctions;
use App\Entity\Event;
use App\Entity\Person;

/*
 * I know, this is old school and everything should be listeners and event
 * and services.
 */
class Reports
{
    public function addCriteriasToForm(&$form)
    {
        $em = $this->getManager();

        $form
            ->add('active_crew_only', CheckboxType::class,
                array('label' => 'Active crew only',
                    'required' => false,
                ))
            ->add('event', EntityType::class,
                array('class' => Event::class,
                    'required' => false,
                    'placeholder' => 'Choose an event if you need one',
                    'query_builder' => function(EntityRepository $er) {
                    return $er->createQueryBuilder('e')
                        ->where('e.parent is null')
                        ->orderBy('e.name', 'ASC');
                    },
                ))
            ->add('from_date', DateType::class,
                array(
                    'required' => false,
                    'label' => 'Time period start',
                    'format' => 'yyyy-MM-dd',
                    'widget' => "single_text"
                ))
            ->add('to_date', DateType::class,
                array(
                    'required' => false,
                    'label' => 'Time period end',
                    'format' => 'yyyy-MM-dd',
                    'widget' => "single_text"
                ))
            ->add('people', EntityType::class,
                array(
                    'label' => 'Crew',
                    'required' => false,
                    'class' => Person::class,
                    'query_builder' => function(EntityRepository $er) {
                    return $er->createQueryBuilder('p')
                        ->orderBy('p.first_name', 'ASC');
                    },
                    'multiple' => true,
                    'mapped' => false,
                    'attr' => [
                        'class' => 'selectpicker',
                        'data-live-search' => 'true',
                        'data-width' => '100%',
                        'data-style' => 'btn-dropdown',
                    ],
            ));
    }
}
