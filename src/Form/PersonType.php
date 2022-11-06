<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;

use App\Lib\ExternalEntityConfig;
use App\Form\AddressType;
use App\Entity\Person;

class PersonType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('username')
            ->add('email')
            ->add('first_name')
            ->add('last_name')
        ;
        // Annoying, but working. 
        if (in_array('date_of_birth', $options['personfields']))
            $builder->add('date_of_birth', BirthdayType::class, array('required' => false));

        if (in_array('nationality', $options['personfields']))
            $builder->add('nationality', CountryType::class, array('required' => false));

        if (in_array('emergency_contact', $options['personfields']))
            $builder->add('emergency_contact', TextareaType::class, array('required' => false));

        if (in_array('workload_percentage', $options['personfields']))
            $builder->add('workload_percentage', NumberType::class, array('required' => false));

        if (in_array('diets', $options['personfields']))
            $builder->add('diets', ChoiceType::class,array(
                'choices' => ExternalEntityConfig::getTypesAsChoicesFor('Person', 'Diet'),
                'expanded'  => true,
                'multiple'  => true,));

        if (in_array('', $options['personfields']))
            $builder->add('mobile_phone_number');

        if (in_array('home_phone_number', $options['personfields']))
            $builder->add('home_phone_number');

        $builder->add('state', ChoiceType::class, array('label' => 'Status', 'choices' => ExternalEntityConfig::getStatesAsChoicesFor('Person')));

        $builder->add('system_roles', ChoiceType::class,
            array(
                'label' =>  'User type',
                'multiple' =>  true,
                'choices' => ExternalEntityConfig::getSystemRolesAsChoices('with_description'),
            )
        );
        if (in_array('address', $options['personfields']))
            $builder->add('address', AddressType::class, ['address_elements' => $options['address_elements']]);

        // Butter and lard.
        if (in_array('postal_address', $options['personfields']) && $options['addressing_config']['use_postal_address']) {
            $builder->add('postal_address', AddressType::class, ['address_elements' => $options['address_elements']])
            ;
        }
        $builder->remove('plainPassword');
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Person::class,
            'address_elements' => [],
            'addressing_config' => [],
            'personfields' => []
        ));
    }
}
