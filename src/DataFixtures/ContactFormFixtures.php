<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Sulu\Bundle\FormBundle\Entity\Form;
use Sulu\Bundle\FormBundle\Entity\FormField;

final class ContactFormFixtures extends Fixture
{
    public const string CONTACT_FORM_REFERENCE = 'contact-form';

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $form = new Form();
        $form->setDefaultLocale('fr');

        $translation = $form->getTranslation('fr', true);
        $translation->setTitle('Formulaire de contact');
        $translation->setSubject('[WEB] Contact CVVFCM');
        $translation->setFromEmail('contact@cvvfcm.fr');
        $translation->setFromName('CVVFCM');
        $translation->setToEmail('contact@cvvfcm.fr');
        $translation->setToName('CVVFCM');
        $translation->setReplyTo(true);
        $translation->setSendAttachments(true);
        $translation->setSubmitLabel('Envoyer');
        $translation->setSuccessText('<p>Votre message a bien été envoyé. Nous vous répondrons dans les meilleurs délais.</p>');
        $translation->setMailText(<<<'HTML'
            <p>Bonjour,</p>
            <p>Nous avons bien reçu votre message et nous vous en remercions.</p>
            <p>Le club est animé par des bénévoles : nous vous répondrons dès que possible, merci de votre patience.</p>
            <p>L'équipe du CVVFCM</p>
            HTML);

        $this->addField($form, 'salutation', 'salutation', 1, required: true, title: 'Civilité');
        $this->addField($form, 'lastName', 'lastName', 2, required: true, title: 'Nom', placeholder: 'Votre nom');
        $this->addField($form, 'dropdown', 'audience', 3, title: 'Vous êtes', options: [
            'choices' => "Particulier\nAdhérent\nCompétiteurs\nGroupe\nEntreprise\nAutre",
        ]);
        $this->addField($form, 'company', 'company', 4, title: 'Société', placeholder: 'Nom de votre société / entreprise');
        $this->addField($form, 'email', 'email', 5, required: true, title: 'Courriel', placeholder: 'exemple@cvvfcm.fr');
        $this->addField($form, 'phone', 'phone', 6, title: 'Téléphone', placeholder: '01 02 03 04 05');
        $this->addField($form, 'dropdown', 'subject', 7, required: true, title: 'Sujet', options: [
            'choices' => "Adhésion\nÉcole de Voile\nLocation\nRégate\nRenseignement\nStages de Voile\nPrestations et devis\nAutre",
        ]);
        $this->addField($form, 'textarea', 'message', 8, required: true, title: 'Votre message');
        $this->addField($form, 'attachment', 'attachment', 9, title: 'Pièce jointe', options: ['max' => 3]);

        $manager->persist($form);
        $manager->flush();

        $this->addReference(self::CONTACT_FORM_REFERENCE, $form);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function addField(
        Form $form,
        string $type,
        string $key,
        int $order,
        bool $required = false,
        ?string $title = null,
        ?string $placeholder = null,
        array $options = [],
    ): void {
        $field = new FormField();
        $field->setForm($form);
        $field->setDefaultLocale('fr');
        $field->setRequired($required);
        $field->setType($type);
        $field->setWidth('full');
        $field->setOrder($order);
        $field->setKey($key);

        $fieldTranslation = $field->getTranslation('fr', true);
        $fieldTranslation->setTitle($title);
        $fieldTranslation->setPlaceholder($placeholder);
        $fieldTranslation->setOptions($options);

        $form->addField($field);
    }
}
