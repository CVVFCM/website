<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\Type\ContactType;
use Sulu\Component\Webspace\Analyzer\Attributes\RequestAttributes;
use Sulu\Component\Webspace\Webspace;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\UserInterface\Controller\Website\ContentController;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 *
 * @extends ContentController<DimensionContentInterface>
 */
final class ContactController extends ContentController
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%env(SULU_ADMIN_EMAIL)%')]
        private readonly string $suluAdminEmail,
    ) {
    }

    #[\Override]
    public function indexAction(Request $request, DimensionContentInterface $object, string $view, bool $preview = false, bool $partial = false): Response
    {
        $suluAttribute = $request->attributes->get('_sulu');
        \assert($suluAttribute instanceof RequestAttributes, 'The "_sulu" request attribute must be of type '.RequestAttributes::class.', but got: '.\get_debug_type($suluAttribute));
        $attributes = $suluAttribute->getAttributes();
        $webspace = $attributes['webspace'];
        \assert($webspace instanceof Webspace, 'The "webspace" request attribute must be of type '.Webspace::class.', but got: '.\get_debug_type($webspace));

        $parameters = $this->resolveSuluParameters($object, $webspace->getKey(), false);

        $form = $this->createForm(ContactType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /**
             * @var array{
             *     firstName: string,
             *     lastName: string,
             *     company: string|null,
             *     email: string,
             *     subject: string,
             *     message: string,
             * } $data
             */
            $data = $form->getData();

            $contactMessage = new TemplatedEmail()
                ->from($this->suluAdminEmail)
                ->to($this->suluAdminEmail)
                ->replyTo($data['email'])
                ->subject('[WEB] '.$data['subject'])
                ->htmlTemplate('emails/contact.html.twig')
                ->context(['data' => $data])
            ;

            $confirmMessage = new TemplatedEmail()
                ->from($this->suluAdminEmail)
                ->to($data['email'])
                ->subject('Confirmation de message : '.$data['subject'])
                ->htmlTemplate('emails/confirm_contact.html.twig')
                ->context(['data' => $data])
            ;

            $this->mailer->send($contactMessage);
            $this->mailer->send($confirmMessage);

            $this->addFlash('success', 'Votre message a bien été envoyé. Nous vous répondrons dès que possible.');

            return $this->redirect($request->getUri());
        }

        $response = new Response(
            $this->renderSuluView(
                $view,
                'html',
                [...$parameters, 'form' => $form->createView()],
                $preview,
                $partial,
            ),
        );

        $this->enhanceSuluCacheLifeTime($response);

        return $response;
    }
}
