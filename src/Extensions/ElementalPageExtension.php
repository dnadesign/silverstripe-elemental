<?php

namespace DNADesign\Elemental\Extensions;

use DNADesign\Elemental\Models\ElementalArea;
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
     * Exclude blocks, by classname, from being indexed.
     * @var array
     */
    private static $elements_excluded_from_search = [];

    /**
     * Returns the contents of each ElementalArea has_one's markup for use in Solr or Elastic search indexing
     *
     * @return string
     */
    public function getElementsForSearch()
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
                    // Only render the elemental blocks which are not excluded from search and don't pull from a page.
                    foreach ($area->Elements() as $element) {
                        if (!in_array($element->ClassName, Config::inst()->get(static::class, 'elements_excluded_from_search'))) {
                            $forIndexing = $element->forTemplate();
                            $element->extend('updateElementForSearch', $forIndexing);
                            if ($forIndexing) {
                                $output[] = $forIndexing;
                            }
                        }
                    }
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
