<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;

use App\Lib\ExternalEntityConfig;
use App\Form\AddressType;
use App\Entity\Person;

class EditMyselfType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $personfields = $options['personfields'];
        if ($personfields['email']['user_editable'])
            $builder->add('email', EmailType::class, array('required' => false));

        if ($personfields['first_name']['user_editable'])
            $builder->add('first_name', TextType::class, array('required' => false));

        if ($personfields['last_name']['user_editable'])
            $builder->add('last_name', TextType::class, array('required' => false));

        if ($personfields['date_of_birth']['user_editable'])
            $builder->add('date_of_birth', BirthdayType::class, array('required' => false));

        if ($personfields['nationality']['user_editable'])
            $builder->add('nationality', CountryType::class, array(
                'alpha3' => true, 'required' => false));

        if ($personfields['emergency_contact']['user_editable'])
            $builder->add('emergency_contact', TextareaType::class, array('required' => false));

        if ($personfields['workload_percentage']['user_editable'])
            $builder->add('workload_percentage', NumberType::class, array('required' => false));

        if ($personfields['diets']['user_editable'])
            $builder->add('diets', ChoiceType::class,array(
                'choices' => ExternalEntityConfig::getTypesAsChoicesFor('Person', 'Diet'),
                'expanded'  => true,
                'multiple'  => true,));

        if ($personfields['mobile_phone_number']['user_editable'])
            $builder->add('mobile_phone_number');

        if ($personfields['home_phone_number']['user_editable'])
            $builder->add('home_phone_number');

        if ($personfields['address']['user_editable'])
            $builder->add('address', AddressType::class, [
                'address_elements' => $options['address_elements']]);

        // Butter and lard.
        if ($personfields['postal_address']['user_editable'] && $options['addressing_config']['use_postal_address']) {
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
