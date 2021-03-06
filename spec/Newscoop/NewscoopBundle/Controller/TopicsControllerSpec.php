<?php

namespace spec\Newscoop\NewscoopBundle\Controller;

use PhpSpec\ObjectBehavior;
use Symfony\Component\DependencyInjection\Container;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;
use Prophecy\Argument;
use Newscoop\NewscoopBundle\Entity\Repository\TopicRepository;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormView;
use Newscoop\NewscoopBundle\Entity\Topic;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfTokenManagerAdapter;
use Doctrine\ORM\AbstractQuery;
use Doctrine\Common\Collections\ArrayCollection;
use Newscoop\NewscoopBundle\Entity\TopicTranslation;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\Form\FormError;

class TopicsControllerSpec extends ObjectBehavior
{
    private $token;

    public function it_is_initializable()
    {
        $this->shouldHaveType('Newscoop\NewscoopBundle\Controller\TopicsController');
    }

    public function it_is_of_type_container_aware()
    {
        $this->shouldBeAnInstanceOf('Symfony\Component\DependencyInjection\ContainerAware');
    }

    public function let(
        Container $container,
        Translator $translator,
        Session $session,
        TopicRepository $topicRepository,
        EntityRepository $repository,
        EntityManager $entityManager,
        Request $request,
        FormFactory $formFactory,
        FormBuilder $formBuilder,
        Form $form,
        FormView $formView,
        Topic $topic,
        CsrfTokenManagerAdapter $csrfTokenManagerAdapter,
        AbstractQuery $query,
        ParameterBag $parameterBag
    )
    {
        $container->get('em')->willReturn($entityManager);
        $container->get('session')->willReturn($session);
        $container->get('request')->willReturn($request);
        $container->get('translator')->willReturn($translator);
        $container->get('form.factory')->willReturn($formFactory);
        $container->get('form.csrf_provider')->willReturn($csrfTokenManagerAdapter);

        $formBuilder->getForm(Argument::cetera())->willReturn($form);
        $formFactory->create(Argument::cetera())->willReturn($form);
        $form->createView()->willReturn($formView);
        $form->handleRequest(Argument::cetera())->willReturn(true);
        $form->isValid()->willReturn(true);

        $entityManager->persist(Argument::any())->willReturn(true);
        $entityManager->flush(Argument::any())->willReturn(true);
        $entityManager->remove(Argument::any())->willReturn(true);

        $entityManager->getRepository('Newscoop\NewscoopBundle\Entity\Topic')->willReturn($repository);
        $this->setContainer($container);

        $this->token = 'uTxRiEkont4XxRpTcSADPCowge7TgNONE7Y5HWd4pmY';
        $request->get('_csrf_token')->willReturn($this->token);
        $request->get('_code')->willReturn('en');
        $request->getLocale()->willReturn('en');
        $request->get('_code', 'en')->willReturn('en');
        $request->request = $parameterBag;
    }

    public function its_treeAction_should_render_the_tree_of_topics($topicRepository, $request, $entityManager, $query)
    {
        $entityManager->getRepository('Newscoop\NewscoopBundle\Entity\Topic')->willReturn($topicRepository);
        $topicRepository->getTranslatableTopicsQuery('en')->willReturn($query);
        $topics = array(array(
            'id' => 1,
            'level' => 0,
            'lft' => 1,
            'rgt' => 6,
            'root' => null,
            'title' => "new root polish",
            'topicOrder' => 1,
        ), array(
            'id' => 2,
            'level' => 0,
            'lft' => 2,
            'rgt' => 8,
            'root' => null,
            'title' => "new root2 polish",
            'topicOrder' => 2
        ));
        $query->getArrayResult()->willReturn($topics);
        $topicRepository->buildTreeArray($topics)->willReturn($topics);
        $response = $this->treeAction($request);
        $response->getStatusCode()->shouldReturn(200);
        $response->shouldBeAnInstanceOf('Symfony\Component\HttpFoundation\JsonResponse');
    }

    public function its_addAction_should_add_a_new_topic_when_form_is_valid($request, $formFactory, $form, $entityManager, $topicRepository, $repository, $topic, $csrfTokenManagerAdapter)
    {
        $entityManager->getRepository('Newscoop\NewscoopBundle\Entity\Topic')->willReturn($topicRepository);
        $csrfTokenManagerAdapter->isCsrfTokenValid('default', $this->token)->willReturn(true);

        $classTopic = Argument::exact('Newscoop\NewscoopBundle\Entity\Topic')->getValue();
        $topic = new $classTopic;
        $classTopicType = Argument::exact('Newscoop\NewscoopBundle\Form\Type\TopicType')->getValue();
        $topicType = new $classTopicType;

        $formFactory->create($topicType, $topic)->willReturn($form);
        $form->handleRequest($request)->willReturn($form);
        $form->isValid()->willReturn(true);

        $topicRepository->saveNewTopic($topic, 'en')->willReturn(true);
        $response = $this->addAction($request);
        $response->getStatusCode()->shouldReturn(200);
        $response->shouldBeAnInstanceOf('Symfony\Component\HttpFoundation\JsonResponse');
    }

    public function its_addAction_should_add_a_new_topic_when_form_is_invalid(FormErrorIterator $formIterator, FormError $formError, $request, $formFactory, $form, $entityManager, $topicRepository, $repository, $topic, $csrfTokenManagerAdapter)
    {
        $entityManager->getRepository('Newscoop\NewscoopBundle\Entity\Topic')->willReturn($topicRepository);
        $csrfTokenManagerAdapter->isCsrfTokenValid('default', $this->token)->willReturn(true);

        $classTopic = Argument::exact('Newscoop\NewscoopBundle\Entity\Topic')->getValue();
        $topic = new $classTopic;
        $classTopicType = Argument::exact('Newscoop\NewscoopBundle\Form\Type\TopicType')->getValue();
        $topicType = new $classTopicType;

        $formFactory->create($topicType, $topic)->willReturn($form);
        $form->handleRequest($request)->willReturn($form);
        $form->isValid()->willReturn(false);
        $form->getErrors()->willReturn($formIterator);
        $formIterator->getChildren()->willReturn($formError);
        $formError->getMessage()->willReturn('Invalid form');
        $responseArray = '{"status":false,"message":"Invalid form"}';
        $response = $this->addAction($request);
        $response->getContent()->shouldReturn($responseArray);
        $response->getStatusCode()->shouldReturn(200);
        $response->shouldBeAnInstanceOf('Symfony\Component\HttpFoundation\JsonResponse');
    }

    public function its_addAction_should_return_403_when_invalid_csrf_token($request, $form, $repository, $topic, $csrfTokenManagerAdapter)
    {
        $csrfTokenManagerAdapter->isCsrfTokenValid('default', $this->token)->willReturn(false);
        $response = $this->addAction($request);
        $response->getStatusCode()->shouldReturn(403);
        $response->shouldBeAnInstanceOf('Symfony\Component\HttpFoundation\JsonResponse');
    }

    public function its_deleteAction_should_delete_single_topic($request, $topic, $repository)
    {
        $repository->findOneBy(array(
            'id' => 1,
        ))->willReturn($topic);
        $response = $this->deleteAction($request, 1);
        $response->getStatusCode()->shouldReturn(200);
        $response->shouldBeAnInstanceOf('Symfony\Component\HttpFoundation\JsonResponse');
    }

    public function its_deleteAction_should_return_404_when_topic_not_found($request, $repository)
    {
        $repository->findOneBy(array(
            'id' => 3,
        ))->willReturn(null);
        $response = $this->deleteAction($request, 3);
        $response->getStatusCode()->shouldReturn(404);
        $response->shouldBeAnInstanceOf('Symfony\Component\HttpFoundation\JsonResponse');
    }

    public function its_editAction_should_edit_topic($request, $repository, $topic, $csrfTokenManagerAdapter, $form)
    {
        $repository->findOneBy(array(
            'id' => 1,
        ))->willReturn($topic);
        $data = array('id' => 1, 'title' => 'test');
        $form->getData()->willReturn($data);

        $csrfTokenManagerAdapter->isCsrfTokenValid('default', $this->token)->willReturn(true);
        $topicTranslation = new TopicTranslation('en', 'title', $data['title']);
        $topic->getTranslations()->willReturn(new ArrayCollection(array($topicTranslation)));
        $response = $this->editAction($request, 1);
        $response->getStatusCode()->shouldReturn(200);
        $response->shouldBeAnInstanceOf('Symfony\Component\HttpFoundation\JsonResponse');
    }

    public function its_editAction_should_return_404_when_topic_not_found($request, $repository, $csrfTokenManagerAdapter)
    {
        $repository->findOneBy(array(
            'id' => 1,
        ))->willReturn(null);

        $csrfTokenManagerAdapter->isCsrfTokenValid('default', $this->token)->willReturn(true);
        $response = $this->editAction($request, 1);
        $response->getStatusCode()->shouldReturn(404);
        $response->shouldBeAnInstanceOf('Symfony\Component\HttpFoundation\JsonResponse');
    }

    public function its_editAction_should_return_403_when_invalid_csrf_token($request, $csrfTokenManagerAdapter)
    {
        $csrfTokenManagerAdapter->isCsrfTokenValid('default', $this->token)->willReturn(false);
        $response = $this->editAction($request, 1);
        $response->getStatusCode()->shouldReturn(403);
        $response->shouldBeAnInstanceOf('Symfony\Component\HttpFoundation\JsonResponse');
    }

    public function its_moveAction_should_move_child_topic_to_first_position_in_current_subtree($request, $entityManager, $topicRepository, $parameterBag, $repository, $topic)
    {
        $entityManager->getRepository('Newscoop\NewscoopBundle\Entity\Topic')->willReturn($topicRepository);
        $parameterBag->all()->willReturn(array(
            'first' => true,
            'parent' => 2,
        ));

        $request->get('last')->willReturn(null);
        $request->get('first')->willReturn(true);
        $request->get('middle')->willReturn(null);
        $request->request = $parameterBag;

        $topicRepository->findOneBy(array(
            'id' => 1
        ))->willReturn($topic);

        $topicRepository->saveTopicPosition($topic, $request->request->all())->willReturn(true);
        $response = $this->moveAction($request, 1);
        $response->getStatusCode()->shouldReturn(200);
        $response->shouldBeAnInstanceOf('Symfony\Component\HttpFoundation\JsonResponse');
    }

    public function its_moveAction_should_move_child_topic_to_last_position_in_current_subtree($request, $entityManager, $topicRepository, $parameterBag, $repository, $topic)
    {
        $entityManager->getRepository('Newscoop\NewscoopBundle\Entity\Topic')->willReturn($topicRepository);
        $parameterBag->all()->willReturn(array(
            'last' => true,
            'parent' => 2,
        ));

        $request->get('last')->willReturn(true);
        $request->get('first')->willReturn(null);
        $request->get('middle')->willReturn(null);
        $request->request = $parameterBag;

        $topicRepository->findOneBy(array(
            'id' => 1
        ))->willReturn($topic);

        $topicRepository->saveTopicPosition($topic, $request->request->all())->willReturn(true);
        $response = $this->moveAction($request, 1);
        $response->getStatusCode()->shouldReturn(200);
        $response->shouldBeAnInstanceOf('Symfony\Component\HttpFoundation\JsonResponse');
    }

    public function its_moveAction_should_move_child_topic_to_middle_position_in_current_subtree($request, $entityManager, $topicRepository, $parameterBag, $repository, $topic)
    {
        $entityManager->getRepository('Newscoop\NewscoopBundle\Entity\Topic')->willReturn($topicRepository);
        $parameterBag->all()->willReturn(array(
            'middle' => true,
            'parent' => 3,
        ));

        $request->get('last')->willReturn(null);
        $request->get('first')->willReturn(null);
        $request->get('middle')->willReturn(true);
        $request->request = $parameterBag;

        $topicRepository->findOneBy(array(
            'id' => 1
        ))->willReturn($topic);

        $topicRepository->saveTopicPosition($topic, $request->request->all())->willReturn(true);
        $response = $this->moveAction($request, 1);
        $response->getStatusCode()->shouldReturn(200);
        $response->shouldBeAnInstanceOf('Symfony\Component\HttpFoundation\JsonResponse');
    }

    public function its_moveAction_should_return_404_status_code_when_topic_to_be_moved_not_found($request)
    {
        $response = $this->moveAction($request, 1);
        $response->getStatusCode()->shouldReturn(404);
        $response->shouldBeAnInstanceOf('Symfony\Component\HttpFoundation\JsonResponse');
    }
}
