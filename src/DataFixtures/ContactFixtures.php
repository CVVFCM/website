<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Sulu\Bundle\ContactBundle\DataFixtures\ORM\LoadDefaultTypes;
use Sulu\Bundle\ContactBundle\Entity\Account;
use Sulu\Bundle\ContactBundle\Entity\AccountContact;
use Sulu\Bundle\ContactBundle\Entity\Contact;
use Sulu\Bundle\ContactBundle\Entity\Email;
use Sulu\Bundle\ContactBundle\Entity\EmailType;
use Sulu\Bundle\ContactBundle\Entity\Phone;
use Sulu\Bundle\ContactBundle\Entity\PhoneType;
use Sulu\Bundle\ContactBundle\Entity\Position;

final class ContactFixtures extends Fixture implements DependentFixtureInterface
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $cvvfcm = new Account();
        $cvvfcm->setName('CVVFCM');
        $cvvfcm->setMainEmail('contact@cvvfcm.fr');
        $emailEntity = new Email();
        $emailEntity->setEmail($cvvfcm->getMainEmail());
        $emailEntity->setEmailType($this->getReference('email.type.work', EmailType::class));
        $cvvfcm->addEmail($emailEntity);
        $manager->persist($cvvfcm);

        $president = new Position();
        $president->setPosition('Président');
        $manager->persist($president);

        $secretary = new Position();
        $secretary->setPosition('Secrétaire général');
        $manager->persist($secretary);

        $treasurer = new Position();
        $treasurer->setPosition('Trésorier');
        $manager->persist($treasurer);

        $data = [
            ['Yohan', 'Giarelli', 'yohan@cvvfcm.fr', '+33630741240', 'M', $president],
            ['Thomas', 'Van Den Schrieck', 'thomas@cvvfcm.fr', '+33671275659', 'M', $secretary],
            ['Baptiste', 'Gilles-Carret', 'baptiste@cvvfcm.fr', '+33682007221', 'M', $treasurer],
        ];

        foreach ($data as $contactData) {
            [$firstName, $lastName, $email, $phone, $gender, $position] = $contactData;

            $contact = new Contact();
            $contact->setGender($gender);
            $contact->setFirstName($firstName);
            $contact->setLastName($lastName);
            $contact->setMainEmail($email);
            $contact->setMainPhone($phone);

            $emailEntity = new Email();
            $emailEntity->setEmail($contact->getMainEmail());
            $emailEntity->setEmailType($this->getReference('email.type.work', EmailType::class));
            $contact->addEmail($emailEntity);

            $yohanPhone = new Phone();
            $yohanPhone->setPhone($contact->getMainPhone());
            $yohanPhone->setPhoneType($manager->find(PhoneType::class, 2));
            $contact->addPhone($yohanPhone);

            $accountContact = new AccountContact();
            $accountContact->setAccount($cvvfcm);
            $accountContact->setPosition($position);
            $accountContact->setContact($contact);
            $accountContact->setMain(true);

            $contact->addAccountContact($accountContact);

            $manager->persist($contact);
        }

        $manager->flush();
    }

    #[\Override]
    public function getDependencies(): array
    {
        return [LoadDefaultTypes::class];
    }
}
