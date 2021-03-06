<?php
/**
 * @package Newscoop\NewscoopBundle
 * @author Rafał Muszyński <rafal.muszynski@sourcefabric.org>
 * @copyright 2014 Sourcefabric z.ú.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\NewscoopBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Newscoop\NewscoopBundle\Entity\Topic;
use Newscoop\NewscoopBundle\Form\Type\TopicType;
use Newscoop\NewscoopBundle\Form\Type\TopicTranslationType;
use Newscoop\NewscoopBundle\Entity\TopicTranslation;
use Doctrine\ORM\Query;

/**
 * Topic controller.
 */
class TopicsController extends Controller
{
    /**
     * @Route("/admin/topics/get", options={"expose"=true})
     */
    public function getTopicsAction(Request $request)
    {

        $term = $request->query->get('term', '');
        $limit = $request->query->get('limit', null);

        if (trim($term) === '') {
            return new JsonResponse(array());
        }

        $topics = $this->container->get('em')
            ->getRepository('Newscoop\Entity\Topic')
            ->getTopicsByName($term, $limit)
            ->getArrayResult();

        return new JsonResponse($topics);
    }

    /**
     * @Route("/admin/new-topics/", options={"expose"=true})
     */
    public function indexAction()
    {
        return $this->render('NewscoopNewscoopBundle:Topics:index.html.twig');
    }

    /**
     * @Route("/admin/topics/get-languages", options={"expose"=true})
     */
    public function getLanguages(Request $request)
    {
        $languages = $this->get('em')
            ->getRepository('Newscoop\Entity\Language')
            ->getAllLanguagesQuery()
            ->getArrayResult();

        return new JsonResponse(array(
            'languages' => $languages
        ));
    }

    /**
     * @Route("/admin/topics/tree/", options={"expose"=true})
     * @Method("GET")
     */
    public function treeAction(Request $request)
    {
        $em = $this->get('em');
        $locale = $request->get('_code');
        $repository = $em->getRepository('Newscoop\NewscoopBundle\Entity\Topic');
        $topicsQuery = $repository->getTranslatableTopicsQuery($locale);
        $nodes = $topicsQuery->getArrayResult();
        $tree = $repository->buildTreeArray($nodes);

        usort($tree, function ($node1, $node2) {
            return $node1['topicOrder'] - $node2['topicOrder'];
        });

        return new JsonResponse(array('tree' => $tree));
    }

    /**
     * @Route("/admin/topics/move/{id}", options={"expose"=true})
     */
    public function moveAction(Request $request, $id)
    {
        $em = $this->get('em');
        $repository = $em->getRepository('Newscoop\NewscoopBundle\Entity\Topic');
        $translator = $this->get('translator');
        $node = $this->findOr404($id);
        if (is_array($node)) {
            return new JsonResponse($node, 404);
        }

        $params = $request->request->all();
        $parent = isset($params['parent']) && $params['parent'] ? $params['parent'] : null;
        $result = $repository->saveTopicPosition($node, $params);

        if (!$result) {
            return new JsonResponse(array(
                'status' => false,
                'message' => $translator->trans('topics.failedfind', array('%id%' => $id), 'topics'),
            ));
        }

        if (($request->get('last') || $request->get('first') || $request->get('middle')) && !$parent) {
            $rootNodes = $repository->getRootNodes();
            $order = explode(',', $request->get('order'));
            $repository->reorderRootNodes($rootNodes, $order);
        }

        return new JsonResponse(array(
            'status' => true,
            'message' => $translator->trans('topics.alerts.ordersaved', array(), 'topics'),
        ), 200);
    }

    /**
     * @Route("/admin/topics/add/", options={"expose"=true})
     * @Method("POST")
     */
    public function addAction(Request $request)
    {
        $node = new Topic();
        $translator = $this->get('translator');
        $form = $this->createForm(new TopicType(), $node);
        $form->handleRequest($request);
        $response = array(
            'status' => false,
            'message' => $translator->trans('topics.error', array(), 'topics'),
        );

        if (!$this->get('form.csrf_provider')->isCsrfTokenValid('default', $request->get('_csrf_token'))) {
            return new JsonResponse(array(
                'status' => false,
                'message' => $translator->trans('topics.csrfinvalid', array(), 'topics'),
            ), 403);
        }

        if ($form->isValid()) {
            $em = $this->get('em');
            $locale = $request->get('_code', $request->getLocale());
            $em->getRepository('Newscoop\NewscoopBundle\Entity\Topic')->saveNewTopic($node, $locale);

            $response = array(
                'status' => true,
                'message' => $translator->trans('topics.added', array(), 'topics'),
                'topicId' => $node->getId(),
                'topicTitle' => $node->getTitle(),
                'locale' => $request->getLocale()
            );
        } else {
            $response = array(
                'status' => false,
                'message' => $form->getErrors()->getChildren()->getMessage(),
            );
        }

        return new JsonResponse($response);
    }

    /**
     * @Route("/admin/new-topics/add/translation/{id}", requirements={"id" = "\d+"}, options={"expose"=true})
     * @Method("POST")
     */
    public function addTranslation(Request $request, $id)
    {
        $em = $this->get('em');
        $translator = $this->get('translator');
        $form = $this->createForm(new TopicTranslationType());
        $form->handleRequest($request);
        $response = array(
            'status' => false,
            'message' => $translator->trans('topics.error', array(), 'topics'),
        );

        if (!$this->get('form.csrf_provider')->isCsrfTokenValid('default', $request->get('_csrf_token'))) {
            return new JsonResponse(array(
                'status' => false,
                'message' => $translator->trans('topics.csrfinvalid', array(), 'topics'),
            ), 403);
        }

        $node = $this->findOr404($id);
        if (is_array($node)) {
            return new JsonResponse($node, 404);
        }

        if ($form->isValid()) {
            $data = $form->getData();
            $locale = $data['locale'];
            $language = $em
                ->getRepository('Newscoop\Entity\Language')
                ->findOneByCode($locale);

            if (!$language) {
                return new JsonResponse(array(
                    'status' => false,
                    'message' => $translator->trans('topics.alerts.languagenotfound', array('%locale%' => $locale), 'topics'),
                ), 404);
            }

            try {
                $topicTranslation = new TopicTranslation($language->getCode(), 'title', $data['title']);
                $node->addTranslation($topicTranslation);
                $em->flush();
            } catch (\Exception $e) {
                return new JsonResponse(array(
                    'status' => false,
                    'message' => $translator->trans('topics.alerts.translationexists', array('%locale%' => $locale), 'topics'),
                ), 403);
            }

            $response = array(
                'status' => true,
                'message' => $translator->trans('topics.alerts.translationadded', array(), 'topics'),
                'topicTranslationId' => $topicTranslation->getId(),
                'topicTranslationTitle' => $topicTranslation->getContent(),
                'topicTranslationLocale' => $topicTranslation->getLocale()
            );
        }

        return new JsonResponse($response);
    }

    /**
     * @Route("/admin/topics/delete/{id}", options={"expose"=true})
     * @Method("POST")
     */
    public function deleteAction(Request $request, $id)
    {
        $translator = $this->get('translator');
        $em = $this->get('em');
        $node = $this->findOr404($id);
        if (is_array($node)) {
            return new JsonResponse($node, 404);
        }

        $em->remove($node);
        $em->flush();

        return new JsonResponse(array(
            'status' => true,
            'message' => $translator->trans('topics.removed', array('%title%' => $node->getTitle()), 'topics')
        ));
    }

    /**
     * @Route("/admin/topics/translations/delete/{id}", options={"expose"=true})
     * @Method("POST")
     */
    public function deleteTranslationAction(Request $request, $id)
    {
        $translator = $this->get('translator');
        $em = $this->get('em');
        $topicTranslation = $em->getRepository('Newscoop\NewscoopBundle\Entity\TopicTranslation')->findOneBy(array(
            'id' => $id,
        ));

        if (!$topicTranslation) {
            return new JsonResponse(array(
                'status' => false,
                'message' => $translator->trans('topics.failedfindTranslation', array('%id%' => $id), 'topics')
            ), 404);
        }

        if ($topicTranslation->getObject()->getTitle() === $topicTranslation->getContent()) {
            return new JsonResponse(array(
                'status' => false,
                'message' => $translator->trans('topics.failedremoveTranslation', array(), 'topics')
            ), 403);
        }

        $em->remove($topicTranslation);
        $em->flush();

        return new JsonResponse(array(
            'status' => true,
            'message' => $translator->trans('topics.removedTranslation', array(), 'topics')
        ));
    }

    /**
     * @Route("/admin/topics/edit/{id}", options={"expose"=true})
     * @Method("POST")
     */
    public function editAction(Request $request, $id)
    {
        $em = $this->get('em');
        $translator = $this->get('translator');

        if (!$this->get('form.csrf_provider')->isCsrfTokenValid('default', $request->get('_csrf_token'))) {
            return new JsonResponse(array(
                'status' => false,
                'message' => $translator->trans('topics.csrfinvalid', array(), 'topics'),
            ), 403);
        }

        $node = $this->findOr404($id);
        if (is_array($node)) {
            return new JsonResponse($node, 404);
        }

        $locale = $request->get('_code', $request->getLocale());
        $form = $this->createForm(new TopicType());
        $form->handleRequest($request);
        if ($form->isValid()) {
            $data = $form->getData();

            $exists = false;
            foreach ($node->getTranslations() as $translation) {
                if ($translation->getLocale() == $locale && $translation->getField() == 'title') {
                    $translation->setContent($data['title']);
                    $exists = true;
                }
            }

            if (!$exists) {
                $topicTranslation = new TopicTranslation($locale, 'title', $data['title']);
                $node->addTranslation($topicTranslation);
                $em->persist($node);
            }

            $em->flush();

            return new JsonResponse(array(
                'status' => true,
                'message' => $translator->trans('topics.updated', array(), 'topics')
            ));
        }

        return new JsonResponse(array(
            'status' => false,
            'message' => $translator->trans('topics.error', array(), 'topics')
        ));
    }

    public function findOr404($id)
    {
        $em = $this->get('em');
        $translator = $this->get('translator');
        $node = $em->getRepository('Newscoop\NewscoopBundle\Entity\Topic')->findOneBy(array(
            'id' => $id,
        ));

        if (!$node) {
            return array(
                'status' => false,
                'message' => $translator->trans('topics.failedfind', array('%id%' => $id), 'topics'),
            );
        }

        return $node;
    }
}
