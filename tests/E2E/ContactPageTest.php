<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ContactPageTest extends WebTestCase
{
    public function testContactPageRendersDynamicForm(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/contact');

        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $crawler->filter('.DynamicForm form')->count());
        $this->assertSame('Envoyer', $crawler->filter('.DynamicForm button[type="submit"]')->text());
        $this->assertStringContainsString(
            'École de Voile',
            $crawler->filter('.DynamicForm select')->last()->html(),
        );
    }

    public function testEveryVisibleControlHasALabel(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/contact');

        $this->assertResponseIsSuccessful();

        $controls = $crawler->filter('.DynamicForm form')->filter(
            'input:not([type="hidden"]), select, textarea',
        );
        $this->assertGreaterThan(0, $controls->count());

        foreach ($controls as $control) {
            $id = $control->getAttribute('id');
            $this->assertNotSame('', $id);
            $this->assertSame(
                1,
                $crawler->filter(\sprintf('label[for="%s"]', $id))->count(),
                \sprintf('Control "%s" has no label.', $control->getAttribute('name')),
            );
        }
    }

    public function testSubmissionRedirectsToSuccessMessage(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/contact');

        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Envoyer')->form();
        $name = $form->getName();
        $form[$name.'[salutation]'] = 'mr';
        $form[$name.'[lastName]'] = 'Testeur';
        $form[$name.'[email]'] = 'testeur@example.com';
        $form[$name.'[subject]'] = 'Adhésion';
        $form[$name.'[message]'] = 'Bonjour, ceci est un message de test.';

        $client->submit($form);

        $this->assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        $this->assertStringContainsString('send=true', $location);

        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $crawler->filter('.DynamicForm__success')->count());
        $this->assertSame(0, $crawler->filter('.DynamicForm form')->count());
    }
}
