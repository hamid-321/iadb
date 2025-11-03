<?php

namespace App\Form;

use App\Entity\Album;
use App\Entity\Review;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rating', ChoiceType::class, [
                'label' => 'Rating',
                'choices' => [
                    '5 Stars' => 5,
                    '4 Stars' => 4,
                    '3 Stars' => 3,
                    '2 Stars' => 2,
                    '1 Star' => 1,
                ],
                'expanded' => true, //use buttons
                'multiple' => false,
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500',
                ],
            ])
            ->add('reviewText', TextareaType::class, [
                'label' => 'Your Review',
                'attr' => [
                    'rows' => 8,
                    'class' => 'w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500',
                    'placeholder' => 'What did you think of this album?'
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Review::class,
        ]);
    }
}
