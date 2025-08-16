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
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $personfields = $options['personfields'];
        $builder
            ->add('username')
            ->add('email')
            ->add('first_name')
            ->add('last_name')
        ;
        if ($personfields['date_of_birth']['enabled'])
            $builder->add('date_of_birth', BirthdayType::class, array('required' => false));

        if ($personfields['nationality']['enabled'])
            $builder->add('nationality', CountryType::class, array(
                'alpha3' => true, 'required' => false));

        if ($personfields['emergency_contact']['enabled'])
            $builder->add('emergency_contact', TextareaType::class, array('required' => false));

        if ($personfields['workload_percentage']['enabled'])
            $builder->add('workload_percentage', NumberType::class, array('required' => false));

        if ($personfields['diets']['enabled'])
            $builder->add('diets', ChoiceType::class,array(
                'choices' => ExternalEntityConfig::getTypesAsChoicesFor('Person', 'Diet'),
                'expanded'  => true,
                'multiple'  => true,));

        if ($personfields['mobile_phone_number']['enabled'])
            $builder->add('mobile_phone_number');

        if ($personfields['home_phone_number']['enabled'])
            $builder->add('home_phone_number');

        $builder->add('state', ChoiceType::class, [
            'label' => 'Status',
            'choices' => ExternalEntityConfig::getStatesAsChoicesFor('Person')
            ]);

        $builder->add('system_roles', ChoiceType::class,
            array(
                'label' =>  'User type',
                'multiple' =>  true,
                'choices' => ExternalEntityConfig::getSystemRolesAsChoices('with_description'),
            )
        );
        if ($personfields['address']['enabled'])
            $builder->add('address', AddressType::class, [
                'address_elements' => $options['address_elements']]);

        // Butter and lard.
        if ($personfields['postal_address']['enabled'] && $options['addressing_config']['use_postal_address']) {
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
