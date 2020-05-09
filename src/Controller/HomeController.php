<?php


namespace App\Controller;

use App\Entity\Contact;
use App\Entity\Counter;
use App\Form\ContactType;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Date;

class HomeController extends AbstractController
{
    /**
     * @Route("/", name="home")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function index(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        if ($request->isMethod('POST')) {

            $latlong = $request->request->get('location');
            $geoCodeData = json_decode(file_get_contents('https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=' . $latlong[0] . '&longitude=' . $latlong[1] . '&localityLanguage=en'), true);

            $counter = new Counter();
            $counter->setCountry($geoCodeData['countryName'])
                ->setCameFrom($request->request->get('source') == 'true' ? 'Home' : 'Direct')
                ->setLocation(implode(',', $request->request->get('location')))
                ->setPageName($request->request->get('page_name'))
                ->setScreenSize($request->request->get('screen_size'))
                ->setUserAgent($this->getBrowser($request->server->get('HTTP_USER_AGENT'))['name']);

            $em->persist($counter);

            $em->flush();

            $this->getStats($request, $newVisitors, $newSameBrowsers);
            $childStats = $this->getChildStats();
            $referralHits = $childStats[0];
            $directHits = $childStats[1];

            return $this->json([
                'status' => 'OK',
                'message' => '',
                'data' => [
                    'greetings' => sprintf('Hello you are from %s', $geoCodeData['countryName']),
                    'screenSize' => sprintf('Your screen size is %s', $request->request->get('screen_size')),
                    'visitors' => $newVisitors->getSingleScalarResult(),
                    'same_browsers' => $newSameBrowsers->getSingleScalarResult(),
                    'child_direct_hits' => $directHits->getSingleScalarResult(),
                    'child_indirect_hits' => $referralHits->getSingleScalarResult(),
                    'my_browser' => $this->getBrowser($request->server->get('HTTP_USER_AGENT'))['name']
                ]
            ]);
        }

        $this->getStats($request, $visitors, $sameBrowsers);

        return $this->render('home/index.html.twig', [
            'visitors' => $visitors->getSingleScalarResult(),
            'same_browsers' => $sameBrowsers->getSingleScalarResult(),
            'user_browser' => $this->getBrowser($request->server->get('HTTP_USER_AGENT'))['name']
        ]);
    }

    private function getStats($request, &$visitors, &$sameBrowsers, $page = 'Home')
    {
        $em = $this->getDoctrine()->getManager();
        /**
         * @var $q QueryBuilder
         */
        $q = $em->createQueryBuilder();
        $visitors = $q->from('App:Counter', 'c')
            ->select('COUNT(c) as visitors')
            ->andWhere('c.pageName = :page')->setParameter('page', $page)
            ->getQuery();

        /**
         * @var $s QueryBuilder
         */
        $s = $em->createQueryBuilder();
        $sameBrowsers = $s->from('App:Counter', 'c')
            ->select('COUNT(c)')
            ->andWhere('c.userAgent = :browser')
            ->setParameter('browser', $this->getBrowser($request->server->get('HTTP_USER_AGENT'))['name'])
            ->andWhere('c.pageName = :page')->setParameter('page', $page)
            ->getQuery();
    }

    /**
     * @Route("/child-page", name="child_page")
     */
    public function child(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $contact = new Contact();

        $form = $this->createForm(ContactType::class, $contact);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $contact->setCreatedAt(new \DateTime());

            $em->persist($contact);

            $em->flush();
        }

        $childStats = $this->getChildStats();
        $referralVisitors = $childStats[0];
        $directVisitors = $childStats[1];

        return $this->render('home/child.html.twig', [
            'form' => $form->createView(),
            'indirect' => $referralVisitors->getSingleScalarResult(),
            'direct' => $directVisitors->getSingleScalarResult()
        ]);
    }

    private function getChildStats(){
        $em = $this->getDoctrine()->getManager();

        /**
         * @var $q QueryBuilder
         */

        $q = $em->createQueryBuilder();
        $referralVisitors = $q->from('App:Counter', 'c')
            ->select('COUNT(c) as visitors')
            ->andWhere('c.cameFrom = :direct')
            ->setParameter('direct', 'Home')
            ->andWhere('c.pageName = :page')
            ->setParameter('page', 'Child')
            ->getQuery();


        $h = $em->createQueryBuilder();
        $directVisitors = $h->from('App:Counter', 'c')
            ->select('COUNT(c) as visitors')
            ->andWhere('c.cameFrom = :direct')
            ->setParameter('direct', 'Direct')
            ->andWhere('c.pageName = :page')
            ->setParameter('page', 'Child')
            ->getQuery();

        return [
            $referralVisitors,
            $directVisitors
        ];
    }

    // from github get browser from HTTP_USER_AGENT header
    public function getBrowser($user_agent = null)
    {
        if (is_null($user_agent)) {
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
        }
        $bname = 'Unknown';
        $platform = 'Unknown';
        $version = "";
        $ub = '';

        if (preg_match('/linux/i', $user_agent)) {
            $platform = 'linux';
        } elseif (preg_match('/macintosh|mac os x/i', $user_agent)) {
            $platform = 'mac';
        } elseif (preg_match('/windows|win32/i', $user_agent)) {
            $platform = 'windows';
        }

        if (preg_match('/MSIE/i', $user_agent) && !preg_match('/Opera/i', $user_agent)) {
            $bname = 'Internet Explorer';
            $ub = "MSIE";
        } elseif (preg_match('/Firefox/i', $user_agent)) {
            $bname = 'Mozilla Firefox';
            $ub = "Firefox";
        } elseif (preg_match('/Chrome/i', $user_agent)) {
            $bname = 'Google Chrome';
            $ub = "Chrome";
        } elseif (preg_match('/Safari/i', $user_agent)) {
            $bname = 'Apple Safari';
            $ub = "Safari";
        } elseif (preg_match('/Opera/i', $user_agent)) {
            $bname = 'Opera';
            $ub = "Opera";
        } elseif (preg_match('/Netscape/i', $user_agent)) {
            $bname = 'Netscape';
            $ub = "Netscape";
        }

        $known = array('Version', $ub, 'other');
        $pattern = '#(?<browser>' . join('|', $known) . ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
        if (!preg_match_all($pattern, $user_agent, $matches)) {
        }

        $i = count($matches['browser']);
        if ($i != 1) {
            if (strripos($user_agent, "Version") < strripos($user_agent, $ub)) {
                $version = $matches['version'][0];
            } else {
                $version = $matches['version'][1];
            }
        } else {
            $version = $matches['version'][0];
        }
        if ($version == null || $version == "") {
            $version = "?";
        }
        return array(
            'userAgent' => $user_agent,
            'name' => $bname,
            'version' => $version,
            'platform' => $platform,
            'pattern' => $pattern,
            'ub' => $ub,
        );
    }
}
