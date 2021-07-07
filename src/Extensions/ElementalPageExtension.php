<?php

namespace DNADesign\Elemental\Extensions;

use DNADesign\Elemental\Models\ElementalArea;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\View\Parsers\HTML4Value;
use SilverStripe\View\SSViewer;

/**
 * @method ElementalArea ElementalArea()
 * @property int ElementalAreaID
 */
class ElementalPageExtension extends ElementalAreasExtension
{
    private static $has_one = [
        'ElementalArea' => ElementalArea::class,
    ];

    private static $owns = [
        'ElementalArea',
    ];

    private static $cascade_duplicates = [
        'ElementalArea',
    ];

    /**
     * Returns the contents of each ElementalArea has_one's markup for use in Solr or Elastic search indexing
     *
     * @return string
     */
    public function getElementsForSearch()
    {
        return strip_tags($this->createHtml());
    }

    /**
     * @param array $anchors
     *
     * @see SiteTree::getAnchorsOnPage()
     */
    public function updateAnchorsOnPage(array &$anchors): void
    {
        if (!($this->owner instanceof SiteTree)) {
            return;
        }
        $content = $this->createHtml();
        $anchors = array_merge($anchors, $this->owner->getAnchorsInContent($content));
    }

    private function createHtml()
    {
        $oldThemes = SSViewer::get_themes();
        SSViewer::set_themes(SSViewer::config()->get('themes'));
        try {
            $output = [];
            foreach ($this->owner->hasOne() as $key => $class) {
                if ($class !== ElementalArea::class) {
                    continue;
                }
                /** @var ElementalArea $area */
                $area = $this->owner->$key();
                if ($area) {
                    $output[] = $area->forTemplate();
                }
            }
        } finally {
            // Reset theme if an exception occurs, if you don't have a
            // try / finally around code that might throw an Exception,
            // CMS layout can break on the response. (SilverStripe 4.1.1)
            SSViewer::set_themes($oldThemes);
        }
        return implode($output);
    }

    public function MetaTags(&$tags)
    {
        if (!Controller::has_curr()) {
            return;
        }
        $controller = Controller::curr();
        $request = $controller->getRequest();
        if ($request->getVar('ElementalPreview') !== null) {
            $html = HTML4Value::create($tags);
            $xpath = "//meta[@name='x-page-id' or @name='x-cms-edit-link']";
            $removeTags = $html->query($xpath);
            $body = $html->getBody();
            foreach ($removeTags as $tag) {
                $body->removeChild($tag);
            }
            $tags = $html->getContent();
        }
    }
}
