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

/*
 * I know, this is old school and everything should be listeners and event
 * and services.
 */
class Reports extends CommonReportFunctions implements ReportsInterface
{
    protected $container;

    public $reports = [
        'LocationsStats' => array(
            'system_role' => "ROLE_ADMIN",
            'required_options' => array('event'),
            'class' => 'App\Lib\Reports\LocationsStats',
            'description' => "Summary of jobs, events and people per Location."
            ),
        'OrganizationsStats' => array(
            'system_role' => "ROLE_ADMIN",
            'required_options' => array('event'),
            'class' => 'App\Lib\Reports\OrganizationsStats',
            'description' => "Summary of jobs, events and people per organization."
            ),
        'WorkLog' => array(
            'system_role' => "ROLE_ADMIN",
            'required_options' => array('event'),
            'class' => 'App\Lib\Reports\WorkLog',
            'description' => "Jobs done in an event."
            ),
    ];

    public $picker_functions = array(
    );

    public $filter_functions = array(
    );

    public function __construct($container, $options = array())
    {
        $this->container = $container;
    }

    public function runFixedReport($config = null) {

    }

    public function getReports() {
        return $this->reports;
    }

    public function getPickerFunctions() {
        return $this->picker_functions;
    }

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
        ;
    }
}
