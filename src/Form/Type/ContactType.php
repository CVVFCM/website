<?php

declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ContactType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', options: ['label' => 'Prénom'])
            ->add('lastName', options: ['label' => 'Nom'])
            ->add('company', options: ['required' => false, 'label' => 'Entreprise / Association'])
            ->add('email', EmailType::class, ['label' => 'Adresse e-mail'])
            ->add(
                'subject',
                ChoiceType::class,
                [
                    'label' => 'Sujet',
                    'choices' => [
                        'Demande d\'informations' => 'Demande d\'informations',
                        'Demande de devis' => 'Demande de devis',
                        'Location / Baptême / Cours particulier' => 'Location / Baptême / Cours particulier',
                        'Régates / Compétitions' => 'Régates / Compétitions',
                        'Autre' => 'Autre',
                    ],
                ],
            )
            ->add('message', TextareaType::class, ['label' => 'Message'])
            ->add('submit', SubmitType::class, ['label' => 'Envoyer']);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'method' => 'POST',
            ]);
    }
}
