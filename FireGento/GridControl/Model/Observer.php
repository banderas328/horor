<?php
/**
 * GridControl observer
 *
 * adminhtml_block_html_before:
 * checks if grid block id is found in gridcontrol config and, if found, pass a reference to the block to the gridcontrol processor
 *
 * eav_collection_abstract_load_before:
 * checks if current blockid is set to add joints and attributes to grid collection
 */
class FireGento_GridControl_Model_Observer
{
    /**
     * observe adminhtml_block_html_before
     *
     * @param Varien_Event_Observer $event
     * @return void
     */
    public function adminhtmlBlockHtmlBefore(Varien_Event_Observer $event)
    {
        $block = $event->getBlock();
        if (in_array($block->getId(), Mage::getSingleton('firegento_gridcontrol/config')->getGridList())) {
            Mage::getModel('firegento_gridcontrol/processor')->processBlock($block);
        }
    }

    /**
     * observes eav_collection_abstract_load_before to add attributes and joins to grid collection
     *
     * @param Varien_Event_Observer $event
     * @return void
     */

   public function accessProtected($obj, $prop) {
        $reflection = new ReflectionClass($obj);
        $property = $reflection->getProperty($prop);
        $property->setAccessible(true);
        return $property->getValue($obj);
    }
    public function eavCollectionAbstractLoadBefore(Varien_Event_Observer $event)
    {

        $columnJoinField = array();
        if (Mage::registry('firegento_gridcontrol_current_block')) {
            $blockId = Mage::registry('firegento_gridcontrol_current_block')->getId();
            $config = Mage::getSingleton('firegento_gridcontrol/config');
            $adapter = &$event->getCollection()->getSelect();
            $adapter = $this->accessProtected($adapter, "_parts");
            $where_flag = false;
            if ($event->getCollection() instanceof Mage_Eav_Model_Entity_Collection_Abstract){
                foreach ($config->getCollectionUpdates(FireGento_GridControl_Model_Config::TYPE_ADD_ATTRIBUTE, $blockId) as $entry) {
                    //if we use our custom filter fro sku list
                    if($entry == "sku") {
                        $whereExist = false;
                        foreach ($adapter["where"] as $where_key => $where_value) {

                            preg_match_all("|\%(.)+\%|", $adapter["where"][$where_key], $out, PREG_PATTERN_ORDER);
                            //cut the % symblol first
                            $out[0][0] = substr($out[0][0], 1);
                            $lenght = strlen($out[0][0]);
                            //cut the % symbol last
                            $out[0][0] = substr($out[0][0], 0, $lenght - 1);
                            //mage array for in query for collection
                            if (strlen($out[0][0])) {
                                $pieces_test = explode(",", $out[0][0]);
                                $pieces =$out[0][0];
                                if (!empty($pieces_test[1])) {
                                    unset($adapter["where"][$where_key]);
                                    $event->getCollection()->removeAttributeToSelect($entry) ;
                                    $where_flag = array("name" => "sku_in", "pieces" => $pieces);
                                    $whereExist = true;
                                    continue;
                                } else {
                                    preg_match_all("|*(\-)+|", $adapter["where"][$where_key], $out, PREG_PATTERN_ORDER);
                                    //cut the % symblol first
                                    $out[0][0] = substr($out[0][0], 1);
                                    //cut the % symbol last
                                    preg_match_all("|\%(.)+\%|", $adapter["where"][$where_key], $out, PREG_PATTERN_ORDER);
                                    $out[0][0] = substr($out[0][0], 1);
                                    $lenght = strlen($out[0][0]);
                                    $out[0][0] = substr($out[0][0], 0, $lenght - 1);
                                    $pieces = explode("-", $out[0][0]);
                                    if (!empty($pieces[1])) {
                                        $from = $pieces[0];
                                        $to = $pieces[1];
                                        unset($adapter["where"][$where_key]);
                                        $event->getCollection()->removeAttributeToSelect($entry) ;
                                        $where_flag = array("name" => "sku_between", "from" => (int)$from, "to" => (int)$to);
                                        $whereExist = true;
                                        continue;
                                    }
                                }
                            }
                            if (!$whereExist) {
                                $event->getCollection()->addAttributeToSelect($entry);

                            }
                        }
                    }
                   elseif($entry == "condition") {
                        if($where_flag) {
                            if($where_flag["name"] == "sku_in") {
                                $event->getCollection()->addAttributeToSelect('sku', array('in' => $where_flag["pieces"]));
                                $eventFilter = clone $event;
                                $eventFilter->getCollection()->getSelect()->orWhere(new Zend_Db_Expr("`e`.`sku` in  (".$pieces.")"));
                                foreach($adapter['where'] as $key => $value) {
                                    $andString = substr($value,0,4);
                                    if($andString == 'AND ') {
                                        $whereStringClear = substr($value,5);
                                        $whereStringClear = substr($whereStringClear, 0, -1);
                                        if(strlen($whereStringClear) > 1){
                                            $eventFilter->getCollection()->getSelect()->where(new Zend_Db_Expr($whereStringClear));
                                        }
                                    }
                                    else {

                                        $eventFilter->getCollection()->getSelect()->where(new Zend_Db_Expr($value));
                                    }

                                }
                            }
                            else {
                                if($where_flag["name"] == "sku_between") {
                                    $eventFilter = clone $event;
                                    $eventFilter->getCollection()->getSelect()->orWhere(new Zend_Db_Expr("`e`.`sku` >=  ".$where_flag["from"]." and `e`.`sku` <= ".$where_flag["to"]));
                                    foreach($adapter['where'] as $key => $value) {
                                        $andString = substr($value,0,4);
                                        if($andString == 'AND ') {
                                            $whereStringClear = substr($value,5);
                                            $whereStringClear = substr($whereStringClear, 0, -1);
                                            if(strlen($whereStringClear) > 1){
                                                $eventFilter->getCollection()->getSelect()->where(new Zend_Db_Expr($whereStringClear));
                                            }
                                        }
                                        else {

                                                $eventFilter->getCollection()->getSelect()->where(new Zend_Db_Expr($value));
                                        }

                                    }
                                }
                            }
                        }

                             if($whereExist) {
                                 foreach($adapter["where"] as $where_key => $where_value){
                                     preg_match_all("|[.condition.]+\'(.)+\'|", $adapter["where"][$where_key], $out, PREG_PATTERN_ORDER);
                                     $out[0][0] = substr($out[0][0], 1);
                                     $lenght = strlen($out[0][0]);
                                     $out[0][0] = substr($out[0][0], 0, $lenght - 1);
                                     if((int)$out[0][0]) {
                                         $event->getCollection()->addFieldToFilter('condition', array('eq' => (int)$out[0][0]));
                                     }

                                 }
                             }
                        else {
                            $event->getCollection()->addAttributeToSelect($entry);
                        }
                    }
                }
            }

            $adapter = &$event->getCollection()->getSelect();
            $adapter = $this->accessProtected($adapter, "_parts");
            $backupWhere = $adapter["where"];
            $i = 0;
            $dropshiping_flag = false;
            foreach ($config->getCollectionUpdates(FireGento_GridControl_Model_Config::TYPE_JOIN_FIELD, $blockId) as $field) {
                if (!$i) {
                    $i++;


                    $event->getCollection()
                        ->getSelect()->join(array('trs_sales_flat_order_item' => 'trs_sales_flat_order_item'),
                            'entity_id = order_id', array('order_id'));
                    $event->getCollection()
                        ->getSelect()
                        ->columns('SUM(trs_catalog_product_entity_decimal.value) as price')
                        ->joinLeft(array('trs_catalog_product_entity_decimal' => 'trs_catalog_product_entity_decimal'),
                            'product_id = trs_catalog_product_entity_decimal.entity_id', array())
                        ->where("trs_catalog_product_entity_decimal.attribute_id=75");
                    $event->getCollection()
                        ->getSelect()->columns('trs_catalog_product_entity_varchar.value as trs_catalog_product_entity_varchar.value')->
                        joinLeft(array('trs_catalog_product_entity_varchar' => 'trs_catalog_product_entity_varchar'),
                            'product_id = trs_catalog_product_entity_varchar.entity_id', array())
                        ->where("trs_catalog_product_entity_varchar.attribute_id=206");


                    $event->getCollection()
                        ->getSelect()->group('order_id');
                    $adapter = &$event->getCollection()->getSelect();
                    $adapter = $this->accessProtected($adapter, "_parts");
                    $backupWhere = $adapter["where"];
                    $event->getCollection()->getSelect()->reset(Zend_Db_Select::WHERE);
                }
                if ($field == 'dropshiping') {
                    foreach ($backupWhere as $where_key => $where_value) {
                        preg_match_all("|value+(.)*|", $where_value, $out, PREG_PATTERN_ORDER);
                        if (isset($out[0][0]) and !$dropshiping_flag) {
                            $dropshiping_flag = true;
                            $dropshiping_value = '(' . $out[0][0];
                            $dropshiping_value = str_replace('`', "", $dropshiping_value);
                            $dropshiping_value = str_replace('value', "trs_catalog_product_entity_varchar.value", $dropshiping_value);
                            $event->getCollection()->getSelect()->where($dropshiping_value);
                            continue;
                        }
                    }
                }
                if ($field == 'price') {
                    foreach ($backupWhere as $where_key => $where_value) {
                        preg_match_all("|value+(.)*|", $adapter["where"][$where_key], $out, PREG_PATTERN_ORDER);
                        if (!isset($out[0][0])) {
                            $and_check = substr($where_value, 0, 3);
                            if ($and_check == 'AND') {
                                $where_value = substr($where_value, 0, -1);
                                $where_value = substr($where_value, 5, strlen($where_value) - 3);
                                $event->getCollection()->getSelect()->where($where_value);
                                continue;
                            } else {
                                $event->getCollection()->getSelect()->where($where_value);

                            }


                        }

                    }
                }

            }
            foreach ($config->getCollectionUpdates(FireGento_GridControl_Model_Config::TYPE_JOIN, $blockId) as $field) {
                try {
                    $event->getCollection()->join(
                        $field['table'],
                        str_replace('{{table}}', '`' . $field['table'] . '`', $field['condition']),
                        $field['field']
                    );
                    $columnJoinField[$field['column']] = $field['field'];
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }

            // update index from join_index (needed for joins)
            foreach (Mage::registry('firegento_gridcontrol_current_block')->getColumns() as $column) {
                if (isset($columnJoinField[$column->getId()])) {
                    $column->setIndex($columnJoinField[$column->getId()]);
                }
            }
//            echo "<br>";
//           var_dump($event->getCollection()->getSelect()->__toString());
//       //    die();
        }

}}
