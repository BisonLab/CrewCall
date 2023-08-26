<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

use App\Lib\ExternalEntityConfig;
use App\Form\AddressType;
use App\Entity\Person;
use App\Entity\Role;
use App\Entity\Organization;
use App\Entity\FunctionEntity;

class NewPersonType extends PersonType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);
        // Must have an initial function.
        $builder->add('function', EntityType::class,
               array('class' => FunctionEntity::class,
                    'required' => true,
                    'mapped' => false,
                    'placeholder' => "Required",
                    'label' => "First function.",
                    'query_builder' => function(EntityRepository $er) use ($options) {
                        return $er->createQueryBuilder('fe')
                             ->where("fe.state in (:active_states)")
                             ->orderBy('fe.name', 'ASC')
                             ->setParameter('active_states',  ExternalEntityConfig::getActiveStatesFor('FunctionEntity'));
                        },
               ));
        // Dotenv does not handle booleans well.
        if ($options['internal_organization_config']['allow_external_crew'] == "true") {
            $builder->add('role', EntityType::class,
               array('class' => Role::class,
                     'required' => true,
                     'mapped' => false,
                     'preferred_choices' => [$options['internal_organization_config']['role']],
                     'label' => "Role.",
                ))
                ->add('organization', EntityType::class,
                    array('class' => Organization::class,
                     'required' => true,
                     'mapped' => false,
                     'preferred_choices' => [$options['internal_organization_config']['organization']],
                     'label' => "Organization.",
                     ))
                ;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Person::class,
            'internal_organization_config' => [],
            'addressing_config' => [],
            'address_elements' => [],
            'role' => null,
            'organization' => null,
            'personfields' => []
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'person';
    }
}
