<?php

namespace DNADesign\Elemental\Tests;

use DNADesign\Elemental\Extensions\ElementalPageExtension;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementContent;
use DNADesign\Elemental\Tests\Src\TestElement;
use DNADesign\Elemental\Tests\Src\TestPage;
use SilverStripe\CMS\Model\RedirectorPage;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;

class ElementalPageExtensionTest extends FunctionalTest
{
    protected static $fixture_file = 'ElementalPageExtensionTest.yml';

    protected static $required_extensions = [
        TestPage::class => [
            ElementalPageExtension::class,
        ],
    ];

    protected static $extra_dataobjects = [
        TestElement::class,
        TestPage::class,
    ];

    protected function setUp()
    {
        parent::setUp();

        $this->logInWithPermission('ADMIN');
    }

    public function testUpdateCmsFields()
    {
        $page = $this->objFromFixture(TestPage::class, 'elementaldemo');

        $elementalArea = $page->getCMSFields()->dataFieldByName('ElementalArea');
        $this->assertNotNull($elementalArea);

        $content = $page->getCMSFields()->dataFieldByName('Content');
        $this->assertNull($content);

        $redirect = $this->objFromFixture(RedirectorPage::class, 'elementredirectpage');
        $elementalArea = $redirect->getCMSFields()->dataFieldByName('ElementalArea');

        $this->assertNull($elementalArea);
    }

    public function testGetElementalTypes()
    {
        $page = $this->objFromFixture(TestPage::class, 'elementaldemo');
        $types = $page->getElementalTypes();

        $this->assertArrayHasKey(ElementContent::class, $types);
        $this->assertArrayNotHasKey(BaseElement::class, $types, 'Base class should not appear');

        // if we disallow a type then it should remove it
        Config::modify()->set(TestPage::class, 'disallowed_elements', [
            ElementContent::class
        ]);

        $types = $page->getElementalTypes();
        $this->assertArrayNotHasKey(ElementContent::class, $types, 'Disallowed items should not appear');

        // conversely, if we set allowed items to a number of classes then they
        // should be the only ones to appear.
        Config::modify()->set(TestPage::class, 'allowed_elements', [
            TestElement::class
        ]);

        Config::modify()->remove(TestPage::class, 'disallowed_elements');
        $types = $page->getElementalTypes();

        $this->assertArrayNotHasKey(ElementContent::class, $types, 'Disallowed items should not appear');
        $this->assertArrayHasKey(TestElement::class, $types);

        $this->assertEquals('A test element', $types[TestElement::class], 'Types should use their "type"');
    }

    public function testDuplicatingPageDuplicatesElements()
    {
        /** @var TestPage $page */
        $page = $this->objFromFixture(TestPage::class, 'page_with_elements');
        $this->assertCount(2, $page->ElementalArea()->Elements());

        /** @var TestPage $newPage */
        $newPage = $page->duplicate();
        $this->assertNotEquals($page->ElementalArea->ID, $newPage->ElementalArea->ID, 'Area is duplicated');
        $this->assertCount(2, $newPage->ElementalArea()->Elements());

        $this->assertNotEquals(
            $page->ElementalArea()->Elements()->column('ID'),
            $newPage->ElementalArea()->Elements()->column('ID'),
            'Duplicated page has duplicated area and duplicated elements, i.e. not shared'
        );
    }

    public function testGetElementsForSearch()
    {
        /** @var TestPage $page */
        $page = $this->objFromFixture(TestPage::class, 'page_with_html_elements');
        $output = $page->getElementsForSearch();
        $this->assertNotEmpty($output);

        // Confirm tags have been stripped
        $this->assertNotContains('<p>', $output);
        $this->assertNotContains('</p>', $output);

        // Confirm paragraphs don't get smushed together, also across elements
        $this->assertNotContains('paragraphAnd', $output);
        $this->assertNotContains('oneMore', $output);
        $this->assertNotContains('paragraphsAnd', $output);
    }
}
