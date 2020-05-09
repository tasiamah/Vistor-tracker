<?php
    namespace App\Controller;

    use App\Entity\Article;
    //use http\Env\Request;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\Routing\Annotation\Route;

   // use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
    use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

    use Symfony\Component\Form\Extension\Core\Type\TextType;
    use Symfony\Component\Form\Extension\Core\Type\TextareaType;
    use Symfony\Component\Form\Extension\Core\Type\SubmitType;

    class ArticleController extends AbstractController {
        /**
         * @Route("/articles", name="article_list")

         */

        public function index() {
//            return new Response('<html><body>Hello</body></html>');
            $articles = $this->getDoctrine()->getRepository(Article ::class)->findAll();

            return $this->render('articles/index.html.twig', array('articles' => $articles));
        }


        /**
         * @Route("/article/new", name="new_article", methods={"GET", "POST"})
         */

        public function new(Request $request) {
            $article = new Article();

            $form = $this->createFormBuilder($article)
                ->add("title", TextType::class, array('attr' =>
                    array('class' => 'form-control')))
                ->add('body', TextareaType::class, array(
                'required' => false,
                'attr' => array('class' => 'form-control')
            ))->add('save',SubmitType::class, array(
                'label' => 'Create',
                "attr" => array('class' => 'btn btn-primary mt-3')
            ))->getForm();

            return $this->render('articles/new.html.twig', array('form' => $form->createView()));

        }

        /**
         * @Route("/article/save")
         */
        public function save() {
            $entityManager = $this->getDoctrine()->getManager();

            $article = new Article();
            $article->setTitle('Article 1');
            $article->setBody('This is body for article one');

            $entityManager->persist($article);

            $entityManager->flush();

            return new Response('Saves article with the id of'.$article->getID());
        }

        /**
         * @Route("/article/{id}", name="article_show")
         */
        public function show($id) {
            $article = $this->getDoctrine()->getRepository(Article::class)->find($id);

            return $this->render("articles/show.html.twig", array('article' => $article));
        }

    }