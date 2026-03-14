<?php

namespace App\Form;

use App\Entity\Album;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Vich\UploaderBundle\Form\Type\VichImageType;

class AlbumType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title')
            ->add('artist')
            ->add('selectedMbid', ChoiceType::class, [
                'choices' => $options['mbid_choices'],
                'required' => false,
                'label' => 'Select album',
                'mapped' => false,
            ])
            ->add('useSelected', SubmitType::class, [
                'label' => 'Use selected album',
                'attr' => [
                    'formnovalidate' => 'formnovalidate',
                ],
                'validation_groups' => false,
            ])
            ->add('genre')
            ->add('trackList')
            ->add('coverFile', VichImageType::class, [
                'required' => false,
                'allow_delete' => true,
                'delete_label' => 'Delete cover',
                'download_uri' => false,
                'image_uri' => false,
            ])
            ->add('autofill', SubmitType::class, [
                'label' => 'Search MusicBrainz',
                'attr' => [
                    'formnovalidate' => 'formnovalidate',
                ],
                'validation_groups' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Album::class,
            'mbid_choices' => [],
        ]);

        $resolver->setAllowedTypes('mbid_choices', 'array');
    }
}
