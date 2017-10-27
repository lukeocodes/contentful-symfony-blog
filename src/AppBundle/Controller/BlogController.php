<?php

namespace AppBundle\Controller;

use Contentful\Delivery\DynamicEntry;
use Contentful\Delivery\Query;
use Contentful\ResourceArray;
use GuzzleHttp\Exception\ClientException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/blog")
 */
class BlogController extends Controller
{
    /** @var string */
    const CATEGORIES_CATEGORY = '5KMiN6YPvi42icqAUQMCQe';

    /** @var string */
    const AUTHOR_CATEGORY = '1kUEViTN4EmGiEaaeC6ouY';

    /** @var string */
    const ENTRY_CATEGORY = '2wKn6yEnZewu2SCCkus4as';

    /** @var int */
    const ENTRY_LIMIT = 2;

    /**
     * @Route("/", name="blog_index")
     * @Route("/{year}", name="blog_archive", requirements={"year": "\d+"})
     * @Route("/{year}/{month}", name="blog_archive", requirements={"year": "\d+", "month": "\d+"})
     *
     * @param null $year
     * @param null $month
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction($year = null, $month = null, Request $request)
    {
        $request->query->set('year', $year);
        $request->query->set('month', $month);

        $page = $request->get('page') < 1 ? 1 : $request->get('page');

        $client = $this->get('contentful.delivery');
        $query = new Query();
        $query->setContentType(self::ENTRY_CATEGORY)
            ->setLimit(self::ENTRY_LIMIT)
            ->setSkip($page > 0 ? $page - 1 : 0 * self::ENTRY_LIMIT);

        $authorName = $request->get('author');

        if (!empty($authorName)) {
            $query->where('fields.author.sys.id', $this->getAuthor($authorName)->getId());
        }

        $categoryName = $request->get('category');

        if (!empty($categoryName)) {
            $query->where('fields.category.sys.id', $this->getCategory($categoryName)->getId());
        }

        if (is_numeric($year) && is_numeric($month)) {
            $date = new \DateTime($year . '-' . $month . '-01');
            $query->where('sys.createdAt', $date->format('Y-m-d\T00:00:00'), 'gte')
                ->where('sys.createdAt', $date->format('Y-m-t\T23:59:59'), 'lte');
        } elseif (!is_null($year)) {
            $date = new \DateTime($year . '-01-01');
            $query->where('sys.createdAt', $date->format('Y-m-d\T00:00:00'), 'gte')
                ->where('sys.createdAt', $date->format('Y-12-31\T23:59:59'), 'lte');
        }

        /** @var ResourceArray $entries */
        $entries = $client->getEntries($query);

        return $this->render('blog/index.html.twig', [
            'entries' => $entries,
            'pages' => ceil($entries->getTotal()/self::ENTRY_LIMIT),
            'page' => $page,
            'route' => $request->get('_route')
        ]);
    }

    /**
     * @Route("/{year}/{month}/{slug}", name="blog_entry", requirements={"year": "\d+", "month": "\d+"})
     *
     * @param $slug
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function entryAction($year, $month, $slug)
    {
        /** @var DynamicEntry $entry */
        $entry = $this->getEntry($slug);

        return $this->render('blog/entry.html.twig', [
            'entry' => $entry,
            'author' => $entry->getAuthor()[0]
        ]);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function authorPartialAction($authorName = null, DynamicEntry $author = null)
    {
        if (is_null($author) && is_null($authorName)) {
            throw new \Twig_Error('Cannot render author partial without atleast name or object');
        }

        if (is_null($author)) {
            $author = $this->getAuthor($authorName);
        }

        return $this->render('blog/partials/author.html.twig', [
            'author' => $author
        ]);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function archivesPartialAction()
    {
        $entries = $this->getEntries();
        $archives = [];

        foreach ($entries as $entry) {
            $date = $entry->getCreatedAt();
            $year = $date->format('Y');
            $month = $date->format('m');
            $archives[$year][$month] = sprintf(
                '%s %s',
                $date->format('F'),
                $year
            );
        }

        return $this->render('blog/partials/archives.html.twig', [
            'archives' => $archives
        ]);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function categoriesPartialAction()
    {
        return $this->render('blog/partials/categories.html.twig', [
            'categories' => $this->getCategories()
        ]);
    }

    /**
     * @param string $categoryName
     *
     * @return DynamicEntry
     */
    private function getCategory($categoryName)
    {
        $client = $this->get('contentful.delivery');
        $query = new Query();
        $query->setContentType(self::CATEGORIES_CATEGORY)
            ->where('fields.title', $categoryName);

        /** @var ResourceArray $entries */
        $categories = $client->getEntries($query);

        return $categories->getItems()[0];
    }

    /**
     * @return ResourceArray
     */
    private function getCategories()
    {
        $client = $this->get('contentful.delivery');
        $query = new Query();
        $query->setContentType(self::CATEGORIES_CATEGORY);

        /** @var ResourceArray $entries */
        return $client->getEntries($query);
    }

    /**
     * @return ResourceArray
     */
    private function getEntries()
    {
        $client = $this->get('contentful.delivery');
        $query = new Query();
        $query->setContentType(self::ENTRY_CATEGORY)
            ->setLimit(1000);

        /** @var ResourceArray $entries */
        return $client->getEntries($query);
    }

    /**
     * @param string $authorName
     *
     * @return DynamicEntry
     */
    private function getAuthor($authorName)
    {
        $client = $this->get('contentful.delivery');
        $query = new Query();
        $query->setContentType(self::AUTHOR_CATEGORY)
            ->where('fields.name', $authorName);

        /** @var ResourceArray $entries */
        $authors = $client->getEntries($query);

        return $authors->getItems()[0];
    }

    /**
     * @param string $slug
     *
     * @return DynamicEntry
     */
    private function getEntry($slug)
    {
        $client = $this->get('contentful.delivery');
        $query = new Query();
        $query->setContentType(self::ENTRY_CATEGORY)
            ->where('fields.slug', $slug);

        /** @var ResourceArray $entries */
        $entries = $client->getEntries($query);

        return $entries->getItems()[0];
    }
}
