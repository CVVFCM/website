<?php

declare(strict_types=1);

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
use Sulu\Bundle\TagBundle\Entity\Tag;

final class ContactsFixtures extends Fixture implements DependentFixtureInterface
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

        // Label with a complement and mixed case, as typed in the back office.
        $vicePresident = new Position();
        $vicePresident->setPosition('Vice-Présidente déléguée');
        $manager->persist($vicePresident);

        $secretary = new Position();
        $secretary->setPosition('Secrétaire général');
        $manager->persist($secretary);

        $treasurer = new Position();
        $treasurer->setPosition('Trésorier');
        $manager->persist($treasurer);

        // Contacts carrying this tag are exposed by Forgie's board_members tool.
        $forgieTag = new Tag();
        $forgieTag->setName('Forgie');
        $manager->persist($forgieTag);

        // [firstName, lastName, email, phone, gender, position, main account link, exposed to Forgie]
        $data = [
            ['Yohan', 'Giarelli', 'yohan@cvvfcm.fr', '+33630741240', 'M', $president, true, true],
            ['Thomas', 'Van Den Schrieck', 'thomas@cvvfcm.fr', '+33671275659', 'M', $secretary, true, true],
            ['Baptiste', 'Gilles-Carret', 'baptiste@cvvfcm.fr', '+33682007221', 'M', $treasurer, true, true],
            // Position carried by an account link that is not flagged "main".
            ['Claire', 'Lefèvre', 'claire@cvvfcm.fr', '+33600000004', 'F', $vicePresident, false, true],
            // Board member without any position: listed last.
            ['Alice', 'Moreau', 'alice@cvvfcm.fr', '+33600000005', 'F', null, false, true],
            // Not tagged: never exposed by the board_members tool.
            ['Paul', 'Durand', 'paul@cvvfcm.fr', '+33600000006', 'M', null, false, false],
        ];

        foreach ($data as $contactData) {
            [$firstName, $lastName, $email, $phone, $gender, $position, $main, $exposed] = $contactData;

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

            if (null !== $position) {
                $accountContact = new AccountContact();
                $accountContact->setAccount($cvvfcm);
                $accountContact->setPosition($position);
                $accountContact->setContact($contact);
                $accountContact->setMain($main);

                $contact->addAccountContact($accountContact);
            }

            if ($exposed) {
                $contact->addTag($forgieTag);
            }

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
