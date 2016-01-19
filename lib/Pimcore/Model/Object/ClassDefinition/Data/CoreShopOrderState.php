<?php
/**
 * CoreShop
 *
 * LICENSE
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2015 Dominik Pfaffenbauer (http://dominik.pfaffenbauer.at)
 * @license    http://www.coreshop.org/license     GNU General Public License version 3 (GPLv3)
 */

namespace Pimcore\Model\Object\ClassDefinition\Data;

use CoreShop\Model\OrderState;
use Pimcore\Model;

class CoreShopOrderState extends CoreShopSelect {

    /**
     * Static type of this element
     *
     * @var string
     */
    public $fieldtype = "coreShopOrderState";

    /**
     * Type for the column to query
     *
     * @var string
     */
    public $queryColumnType = "int(11)";

    /**
     * Type for the column
     *
     * @var string
     */
    public $columnType = "int(11)";


    /**
     * Type for the generated phpdoc
     *
     * @var string
     */
    public $phpdocType = "CoreShop\\Model\\OrderState";


    public function __construct() {
        $this->buildOptions();
    }

    public function __wakeup() {
        $this->buildOptions();
    }

    private function buildOptions() {
        $orderStates = OrderState::getOrderStates();

        $options = array();

        foreach ($orderStates as $state) {
            $options[] = array(
                "key" => $state->getName(),
                "value" => $state->getId()
            );
        }

        $this->setOptions($options);
    }

    /** True if change is allowed in edit mode.
     * @return bool
     */
    public function isDiffChangeAllowed() {
        return true;
    }

    /**
     * @see Object\ClassDefinition\Data::getDataForResource
     * @param OrderState $data
     * @param null|Model\Object\AbstractObject $object
     * @return integer|null
     */
    public function getDataForResource($data, $object = null) {
        if ($data instanceof OrderState) {
            return $data->getId();
        }
        return null;
    }

    /**
     * @see Object\ClassDefinition\Data::getDataFromResource
     * @param integer $data
     * @return OrderState
     */
    public function getDataFromResource($data) {
        if (intval($data) > 0) {
            return OrderState::getById($data);
        }
        return null;
    }

    /**
     * @see Object\ClassDefinition\Data::getDataForQueryResource
     * @param OrderState $data
     * @param null|Model\Object\AbstractObject $object
     * @return integer|null
     */
    public function getDataForQueryResource($data, $object = null) {
        if ($data instanceof OrderState) {
            return $data->getId();
        }
        return null;
    }

    /**
     * @see Object\ClassDefinition\Data::getDataForEditmode
     * @param OrderState $data
     * @param null|Model\Object\AbstractObject $object
     * @return integer
     */
    public function getDataForEditmode($data, $object = null, $objectFromVersion = NULL) {
        return $this->getDataForResource($data, $object);
    }

    /**
     * @see Model\Object\ClassDefinition\Data::getDataFromEditmode
     * @param integer $data
     * @param null|Model\Object\AbstractObject $object
     * @return OrderState
     */
    public function getDataFromEditmode($data, $object = null) {
        return $this->getDataFromResource($data);
    }
}
