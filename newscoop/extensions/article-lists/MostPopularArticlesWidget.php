<?php
/**
 * @package Campsite
 *
 * @author Petr Jasek <petr.jasek@sourcefabric.org>
 * @copyright 2010 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl.txt
 * @link http://www.sourcefabric.org
 */

require_once dirname(__FILE__) . '/ArticlesWidget.php';

/**
 * @title Most Popular Articles
 */
class MostPopularArticlesWidget extends ArticlesWidget
{
    public function __construct()
    {
       $this->title = getGS('Most Popular Articles');
    }

    public function beforeRender()
    {
        $this->items = Article::GetList(array(
            new ComparisonOperation('published', new Operator('is'), 'true'),
            new ComparisonOperation('reads', new Operator('greater'), '0'),
            ), array(
                array(
                    'field' => 'bypopularity',
                    'dir' => 'desc',
                )
            ), 0, self::LIMIT, $count = 0);
    }

    public function render()
    {
        $articlelist = new ArticleList();
        $articlelist->setItems($this->items);
        $articlelist->setOrderBy('Reads', 'desc');
        if (!$this->isFullscreen()) {
            $articlelist->setHidden('Status');
            $articlelist->setHidden('Comments');
            $articlelist->setHidden('UseMap');
            $articlelist->setHidden('Locations');
            $articlelist->setHidden('PublishDate');
        }
        $articlelist->render();
    }
}
