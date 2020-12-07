<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.github.io/license/
 */

namespace craft\test\fixtures\elements;

use Craft;
use craft\base\ElementInterface;
use craft\db\Table;
use craft\errors\InvalidElementException;
use craft\helpers\Db;
use yii\test\FileFixtureTrait;
use yii\test\Fixture;

/**
 * Class BaseElementFixture is a base class for setting up fixtures for element types.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Robuust digital | Bob Olde Hampsink <bob@robuust.digital>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since  3.6.0
 */
abstract class BaseElementFixture extends Fixture
{
    use FileFixtureTrait;

    /**
     * @var array
     */
    protected $siteIds = [];

    /**
     * @var ElementInterface[] The loaded elements
     */
    private $_elements = [];

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $this->siteIds[$site->handle] = $site->id;
        }
    }

    /**
     * @inheritdoc
     */
    public function load()
    {
        foreach ($this->loadData($this->dataFile) as $key => $data) {
            $element = $this->createElement();

            // If they want to add a date deleted. Store it but dont set that as an element property
            $dateDeleted = null;

            if (isset($data['dateDeleted'])) {
                $dateDeleted = $data['dateDeleted'];
                unset($data['dateDeleted']);
            }

            // Set the field layout
            if (isset($data['fieldLayoutType'])) {
                $fieldLayoutType = $data['fieldLayoutType'];
                unset($data['fieldLayoutType']);

                $fieldLayout = Craft::$app->getFields()->getLayoutByType($fieldLayoutType);
                if ($fieldLayout) {
                    $element->fieldLayoutId = $fieldLayout->id;
                } else {
                    codecept_debug("Field layout with type: $fieldLayoutType could not be found");
                }
            }

            foreach ($data as $handle => $value) {
                $element->$handle = $value;
            }

            if (!$this->saveElement($element)) {
                throw new InvalidElementException($element, implode(' ', $element->getErrorSummary(true)));
            }

            if ($dateDeleted) {
                // Now that the element exists, update its dateDeleted value
                Db::update(Table::ELEMENTS, [
                    'dateDeleted' => Db::prepareDateForDb($dateDeleted),
                ], ['id' => $element->id], [], false);
            } else {
                // Only need to index the search keywords if it's not deleted
                Craft::$app->getSearch()->indexElementAttributes($element);
            }

            $this->_elements[$key] = $element;
        }
    }

    /**
     * @inheritdoc
     */
    public function unload()
    {
        $elementsService = Craft::$app->getElements();

        foreach ($this->_elements as $element) {
            $elementsService->deleteElement($element, true);
        }

        $this->_elements = [];
    }

    /**
     * Get element model.
     *
     * @param string $key The key of the element in the [[$dataFile|data file]].
     * @return ElementInterface|null
     */
    public function getElement(string $key): ?ElementInterface
    {
        return $this->_elements[$key] ?? null;
    }

    /**
     * Creates an element.
     */
    abstract protected function createElement(): ElementInterface;

    /**
     * Saves an element.
     *
     * @param ElementInterface $element The element to be saved
     * @return bool Whether the save was successful
     */
    protected function saveElement(ElementInterface $element): bool
    {
        return Craft::$app->getElements()->saveElement($element, true, true, false);
    }
}
